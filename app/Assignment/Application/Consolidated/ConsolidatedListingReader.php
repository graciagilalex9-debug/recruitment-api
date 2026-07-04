<?php

declare(strict_types=1);

namespace App\Assignment\Application\Consolidated;

/**
 * Read port for the consolidated listing. The Query Builder implementation lives in
 * Infrastructure; this keeps the Application layer framework-agnostic.
 */
interface ConsolidatedListingReader
{
    public function read(ConsolidatedListingQuery $query): ConsolidatedListingResult;
}
