<?php

declare(strict_types=1);

namespace App\Assignment\Application\Consolidated;

/**
 * A page of the consolidated listing plus pagination metadata.
 */
final readonly class ConsolidatedListingResult
{
    /**
     * @param  list<ConsolidatedRow>  $rows
     */
    public function __construct(
        public array $rows,
        public int $total,
        public int $perPage,
        public int $currentPage,
        public int $lastPage,
    ) {}
}
