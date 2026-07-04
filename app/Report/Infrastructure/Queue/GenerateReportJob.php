<?php

declare(strict_types=1);

namespace App\Report\Infrastructure\Queue;

use App\Report\Application\Fail\MarkReportFailed;
use App\Report\Application\Generate\GenerateReport;
use App\Report\Domain\ValueObject\ReportId;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued job that builds a report in the background. It carries only the report id (jobs are
 * serialized — never pass aggregates or data) and delegates all work to the GenerateReport
 * use case; this class is a thin infrastructure adapter (an "entry point" like a controller).
 *
 * It is enqueued with ->afterCommit() (see LaravelReportDispatcher) so the job only runs once
 * the DB transaction that created the report row has committed, and the worker can find it.
 */
final class GenerateReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $reportId,
    ) {}

    public function handle(GenerateReport $generate): void
    {
        $generate(new ReportId($this->reportId));
    }

    /**
     * Called by the queue after retries are exhausted: record the failure so the report does
     * not stay stuck in "processing". A failed() hook must not throw, and the use case is
     * defensive, so no try/catch is needed here.
     */
    public function failed(Throwable $exception): void
    {
        app(MarkReportFailed::class)(new ReportId($this->reportId), $exception->getMessage());
    }
}
