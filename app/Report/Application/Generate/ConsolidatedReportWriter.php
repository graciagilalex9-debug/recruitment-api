<?php

declare(strict_types=1);

namespace App\Report\Application\Generate;

use App\Assignment\Application\Consolidated\ConsolidatedRow;
use App\Report\Domain\ValueObject\ReportId;

/**
 * Port that turns the consolidated rows into a stored spreadsheet file and returns its
 * path. The concrete writer (PhpSpreadsheet, sheets of 50, on a storage disk) lives in
 * Infrastructure; the use case only knows "write these rows, get a path back".
 */
interface ConsolidatedReportWriter
{
    /**
     * @param  iterable<ConsolidatedRow>  $rows
     * @return string the stored file path (on the configured disk)
     */
    public function write(ReportId $id, iterable $rows): string;
}
