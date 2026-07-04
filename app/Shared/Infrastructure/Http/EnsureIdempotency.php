<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a write endpoint safe to retry via an optional `Idempotency-Key` header.
 *
 * - No header → passes through unchanged (opt-in; nothing breaks).
 * - First request with a key → processed, and its response (status + body + a fingerprint of the
 *   request) is stored in the cache with a TTL.
 * - A retry with the same key and the same body → the stored response is replayed (no reprocessing,
 *   no duplicate), flagged with `Idempotency-Replayed: true`.
 * - Same key but a different body → `422` (a client bug: the key was reused for another request).
 * - A concurrent request whose key is still being processed → `409` (a lock guards the critical
 *   section so the operation runs once).
 *
 * Generic and reusable: attach it to any write route via the `idempotent` alias. State lives in the
 * cache (Redis), so it works across app instances.
 */
final readonly class EnsureIdempotency
{
    private const HEADER = 'Idempotency-Key';

    public function __construct(
        private Repository $cache,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);

        if (! is_string($key) || $key === '') {
            return $next($request);
        }

        $fingerprint = $this->fingerprint($request);
        $resultKey = 'idempotency:result:'.$key;

        // Fast path: a result is already stored for this key.
        $stored = $this->cache->get($resultKey);
        if (is_array($stored)) {
            return $this->fromStored($stored, $fingerprint);
        }

        // Be the sole processor for this key; a concurrent holder means "in progress".
        // Locks come from the cache store (LockProvider), exposed via the Cache facade.
        $lock = Cache::lock('idempotency:lock:'.$key, 30);

        if (! $lock->get()) {
            return $this->conflict();
        }

        try {
            // Another request may have completed between the fast-path check and acquiring the lock.
            $stored = $this->cache->get($resultKey);
            if (is_array($stored)) {
                return $this->fromStored($stored, $fingerprint);
            }

            $response = $next($request);

            $this->cache->put($resultKey, [
                'status' => $response->getStatusCode(),
                'body' => (string) $response->getContent(),
                'fingerprint' => $fingerprint,
            ], $this->ttl());

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function fromStored(array $stored, string $fingerprint): Response
    {
        if (($stored['fingerprint'] ?? null) !== $fingerprint) {
            return response()->json(
                ['message' => 'Idempotency-Key reused with a different request payload.'],
                422,
            );
        }

        return response((string) $stored['body'], (int) $stored['status'])
            ->header('Content-Type', 'application/json')
            ->header('Idempotency-Replayed', 'true');
    }

    private function conflict(): Response
    {
        return response()->json(
            ['message' => 'A request with this Idempotency-Key is already being processed.'],
            409,
        );
    }

    private function fingerprint(Request $request): string
    {
        return hash('sha256', $request->getMethod().'|'.$request->getPathInfo().'|'.$request->getContent());
    }

    private function ttl(): int
    {
        return (int) config('performance.idempotency_ttl', 86400);
    }
}
