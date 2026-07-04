<?php

declare(strict_types=1);

namespace App\Report\Application;

use App\Report\Domain\ValueObject\ReportId;

/**
 * Port for scheduling the background generation of a report. The application asks for a
 * report to be generated "later"; how (a queued job on Redis, a synchronous run in tests,
 * anything else) is an infrastructure decision. This keeps the queue out of the use case.
 */
interface ReportDispatcher
{
    public function dispatch(ReportId $id): void;
}
