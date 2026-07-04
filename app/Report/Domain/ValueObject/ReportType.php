<?php

declare(strict_types=1);

namespace App\Report\Domain\ValueObject;

/**
 * The kind of report. A closed set, so a native backed enum models it exactly (and stays
 * pure PHP — no Laravel — so it belongs in the domain). Today only the consolidated
 * listing is exported; new report types are new cases here.
 */
enum ReportType: string
{
    case ConsolidatedListing = 'consolidated_listing';
}
