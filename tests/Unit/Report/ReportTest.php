<?php

declare(strict_types=1);

namespace Tests\Unit\Report;

use App\Report\Domain\Exception\InvalidReportTransition;
use App\Report\Domain\Report;
use App\Report\Domain\ValueObject\ReportCriteria;
use App\Report\Domain\ValueObject\ReportId;
use App\Report\Domain\ValueObject\ReportStatus;
use App\Report\Domain\ValueObject\ReportType;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReportTest extends TestCase
{
    private function report(): Report
    {
        return Report::request(
            id: new ReportId('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            type: ReportType::ConsolidatedListing,
            criteria: new ReportCriteria('years_of_experience', 'desc', []),
            requestedAt: new DateTimeImmutable('2026-07-04T10:00:00+00:00'),
        );
    }

    public function test_a_requested_report_starts_pending_with_nothing_produced(): void
    {
        $report = $this->report();

        $this->assertSame(ReportStatus::Pending, $report->status());
        $this->assertNull($report->filePath());
        $this->assertNull($report->completedAt());
        $this->assertNull($report->failureReason());
        $this->assertFalse($report->isCompleted());
    }

    public function test_it_moves_from_processing_to_completed(): void
    {
        $report = $this->report();
        $completedAt = new DateTimeImmutable('2026-07-04T10:00:07+00:00');

        $report->markProcessing();
        $this->assertSame(ReportStatus::Processing, $report->status());

        $report->markCompleted('reports/report.xlsx', $completedAt);

        $this->assertSame(ReportStatus::Completed, $report->status());
        $this->assertTrue($report->isCompleted());
        $this->assertSame('reports/report.xlsx', $report->filePath());
        $this->assertSame($completedAt, $report->completedAt());
    }

    public function test_it_can_fail_while_processing(): void
    {
        $report = $this->report();
        $report->markProcessing();

        $report->markFailed('the spreadsheet could not be written');

        $this->assertSame(ReportStatus::Failed, $report->status());
        $this->assertSame('the spreadsheet could not be written', $report->failureReason());
    }

    public function test_completing_a_report_that_is_not_processing_is_rejected(): void
    {
        $report = $this->report(); // still pending

        $this->expectException(InvalidReportTransition::class);

        $report->markCompleted('reports/report.xlsx', new DateTimeImmutable);
    }

    public function test_processing_a_report_twice_is_rejected(): void
    {
        $report = $this->report();
        $report->markProcessing();

        $this->expectException(InvalidReportTransition::class);

        $report->markProcessing();
    }

    public function test_a_completed_report_cannot_then_fail(): void
    {
        $report = $this->report();
        $report->markProcessing();
        $report->markCompleted('reports/report.xlsx', new DateTimeImmutable);

        $this->expectException(InvalidReportTransition::class);

        $report->markFailed('too late');
    }
}
