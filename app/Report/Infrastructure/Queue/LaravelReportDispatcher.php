<?php

declare(strict_types=1);

namespace App\Report\Infrastructure\Queue;

use App\Report\Application\ReportDispatcher;
use App\Report\Domain\ValueObject\ReportId;

/**
 * Laravel implementation of the ReportDispatcher port: enqueue a GenerateReportJob on the
 * configured queue (Redis in dev/prod, sync in tests). The application never sees the queue.
 */
final readonly class LaravelReportDispatcher implements ReportDispatcher
{
    public function dispatch(ReportId $id): void
    {
        GenerateReportJob::dispatch($id->value())->afterCommit();
    }
}
