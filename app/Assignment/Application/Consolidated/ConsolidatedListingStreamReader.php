<?php

declare(strict_types=1);

namespace App\Assignment\Application\Consolidated;

/**
 * Read port that streams the WHOLE consolidated listing (not a page) matching a query's
 * sort and filters. Used by the Excel export, which must walk every matching row without
 * loading them all into memory. The paginated read (ConsolidatedListingReader) is for the
 * HTTP listing; this one is for bulk export. The query's page/perPage are ignored here.
 *
 * The Query Builder implementation lives in Infrastructure (iterating with a cursor), so
 * the Application layer stays framework-agnostic.
 */
interface ConsolidatedListingStreamReader
{
    /**
     * @return iterable<ConsolidatedRow>
     */
    public function stream(ConsolidatedListingQuery $query): iterable;
}
