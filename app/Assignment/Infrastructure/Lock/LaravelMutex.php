<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Lock;

use App\Assignment\Application\Lock\LockNotAcquired;
use App\Assignment\Application\Lock\Mutex;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Redis-backed Mutex using the cache store's atomic lock. The lock is acquired without
 * blocking (get() returns false immediately if held → LockNotAcquired), and always released
 * in a finally. A TTL from config auto-releases it if the process crashes mid-run.
 */
final readonly class LaravelMutex implements Mutex
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function withLock(string $name, Closure $callback): mixed
    {
        $lock = Cache::lock($name, $this->ttl());

        if (! $lock->get()) {
            throw new LockNotAcquired($name);
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function ttl(): int
    {
        return (int) config('performance.auto_assign_lock_ttl', 120);
    }
}
