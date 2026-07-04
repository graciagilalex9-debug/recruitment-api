<?php

declare(strict_types=1);

namespace App\Assignment\Application\Consolidated;

/**
 * Input for the consolidated listing: sort column + direction, per-column filters, and
 * pagination. Plain primitives; the reader validates them against its whitelist.
 */
final readonly class ConsolidatedListingQuery
{
    /**
     * @param  array<string, string>  $filters  column => value
     */
    public function __construct(
        public string $sort,
        public string $direction,
        public array $filters,
        public int $page,
        public int $perPage,
    ) {}
}
