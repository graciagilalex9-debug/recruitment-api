<?php

declare(strict_types=1);

namespace App\Report\Application\Request;

use App\Report\Application\ReportDispatcher;
use App\Report\Domain\Report;
use App\Report\Domain\ReportRepository;
use App\Report\Domain\ValueObject\ReportCriteria;
use App\Report\Domain\ValueObject\ReportType;
use DateTimeImmutable;

/**
 * Use case: request an export. It records a pending report and schedules its background
 * generation, then returns immediately — it never builds the file inline. The heavy work
 * happens later in GenerateReport, run by the dispatcher's worker.
 */
final readonly class RequestReport
{
    public function __construct(
        private ReportRepository $reports,
        private ReportDispatcher $dispatcher,
    ) {}

    public function __invoke(ReportCriteria $criteria): RequestReportResponse
    {
        $report = Report::request(
            id: $this->reports->nextIdentity(),
            type: ReportType::ConsolidatedListing,
            criteria: $criteria,
            requestedAt: new DateTimeImmutable,
        );

        $this->reports->save($report);

        $this->dispatcher->dispatch($report->id());

        return new RequestReportResponse(
            id: $report->id()->value(),
            status: $report->status()->value,
        );
    }
}
