<?php

declare(strict_types=1);

namespace App\Report\Application\Fail;

use App\Report\Domain\ReportRepository;
use App\Report\Domain\ValueObject\ReportId;

/**
 * Use case: record that a report's generation failed. Called from the job's failed() hook
 * after the queue exhausts its retries, so the report never stays stuck in "processing".
 * It is defensive (no-op if the report is gone or already terminal) because a failed() hook
 * must not itself throw.
 */
final readonly class MarkReportFailed
{
    public function __construct(
        private ReportRepository $reports,
    ) {}

    public function __invoke(ReportId $id, string $reason): void
    {
        $report = $this->reports->find($id);

        if ($report === null || $report->status()->isTerminal()) {
            return;
        }

        $report->markFailed($reason);
        $this->reports->save($report);
    }
}
