<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Cache;

use App\Assignment\Application\Consolidated\ConsolidatedListingQuery;
use App\Assignment\Application\Consolidated\ConsolidatedListingResult;
use App\Assignment\Application\Consolidated\ConsolidatedRow;
use Closure;
use Illuminate\Contracts\Cache\Repository;

/**
 * Version-keyed cache for the consolidated listing.
 *
 * The listing has many entries (one per filter+sort+page), so instead of enumerating keys on a
 * write we namespace every entry by a version counter. Creating an assignment bumps the version
 * (invalidate()), which orphans all previous entries at once — O(1), no key scan — and they expire
 * via their safety TTL. The version counter itself is permanent (no TTL): if it reset to a lower
 * value we could read a stale entry that still exists. See docs/performance-notes.md.
 *
 * We cache a plain-array form of the result, not the DTO objects: on a pure cache hit the DTO
 * classes may not be autoloaded yet when the store unserializes, which would yield
 * __PHP_Incomplete_Class. Arrays have no such dependency.
 */
final readonly class ConsolidatedListingCache
{
    private const VERSION_KEY = 'consolidated-listing:version';

    public function __construct(
        private Repository $cache,
    ) {}

    /**
     * @param  Closure(): ConsolidatedListingResult  $callback
     */
    public function remember(ConsolidatedListingQuery $query, Closure $callback): ConsolidatedListingResult
    {
        /** @var array<string, mixed> $data */
        $data = $this->cache->remember(
            $this->keyFor($query),
            $this->ttl(),
            static fn (): array => self::encode($callback()),
        );

        return self::decode($data);
    }

    /**
     * @return array<string, mixed>
     */
    private static function encode(ConsolidatedListingResult $result): array
    {
        return [
            'rows' => array_map(static fn (ConsolidatedRow $row): array => [
                'fullName' => $row->fullName,
                'email' => $row->email,
                'yearsOfExperience' => $row->yearsOfExperience,
                'evaluatorName' => $row->evaluatorName,
                'assignedAt' => $row->assignedAt,
                'evaluatorTotal' => $row->evaluatorTotal,
                'evaluatorCandidateEmails' => $row->evaluatorCandidateEmails,
            ], $result->rows),
            'total' => $result->total,
            'perPage' => $result->perPage,
            'currentPage' => $result->currentPage,
            'lastPage' => $result->lastPage,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function decode(array $data): ConsolidatedListingResult
    {
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = $data['rows'] ?? [];

        $rows = array_map(static fn (array $row): ConsolidatedRow => new ConsolidatedRow(
            fullName: (string) $row['fullName'],
            email: (string) $row['email'],
            yearsOfExperience: (int) $row['yearsOfExperience'],
            evaluatorName: (string) $row['evaluatorName'],
            assignedAt: (string) $row['assignedAt'],
            evaluatorTotal: (int) $row['evaluatorTotal'],
            evaluatorCandidateEmails: (string) $row['evaluatorCandidateEmails'],
        ), $rawRows);

        return new ConsolidatedListingResult(
            rows: $rows,
            total: (int) $data['total'],
            perPage: (int) $data['perPage'],
            currentPage: (int) $data['currentPage'],
            lastPage: (int) $data['lastPage'],
        );
    }

    /** Bump the version so every existing listing entry becomes unreachable. */
    public function invalidate(): void
    {
        $this->ensureVersion();
        $this->cache->increment(self::VERSION_KEY);
    }

    private function keyFor(ConsolidatedListingQuery $query): string
    {
        $fingerprint = md5(serialize([
            $query->sort,
            $query->direction,
            $query->filters,
            $query->page,
            $query->perPage,
        ]));

        return sprintf('consolidated-listing:v%d:%s', $this->version(), $fingerprint);
    }

    private function version(): int
    {
        $this->ensureVersion();

        return (int) $this->cache->get(self::VERSION_KEY, 1);
    }

    /** Create the permanent counter on first use (no TTL); no-op if it already exists. */
    private function ensureVersion(): void
    {
        $this->cache->add(self::VERSION_KEY, 1);
    }

    private function ttl(): int
    {
        return (int) config('performance.listing_cache_ttl', 600);
    }
}
