<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Persistence;

use App\Assignment\Application\Consolidated\ConsolidatedListingQuery;
use App\Assignment\Application\Consolidated\ConsolidatedListingReader;
use App\Assignment\Application\Consolidated\ConsolidatedListingResult;
use App\Assignment\Infrastructure\Cache\ConsolidatedListingCache;

/**
 * Caching decorator over the consolidated listing reader: serves the result from the
 * version-keyed cache and only hits the real (Query Builder) reader on a miss. The inner
 * reader is untouched — this is wired in the service provider.
 */
final readonly class CachingConsolidatedListingReader implements ConsolidatedListingReader
{
    public function __construct(
        private ConsolidatedListingReader $inner,
        private ConsolidatedListingCache $cache,
    ) {}

    public function read(ConsolidatedListingQuery $query): ConsolidatedListingResult
    {
        return $this->cache->remember($query, fn (): ConsolidatedListingResult => $this->inner->read($query));
    }
}
