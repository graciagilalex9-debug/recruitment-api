<?php

declare(strict_types=1);

namespace App\Report\Infrastructure\Http;

use App\Report\Application\Request\RequestReportResponse;
use App\Report\Domain\Report;
use App\Report\Domain\ValueObject\ReportStatus;
use DateTimeInterface;

/**
 * Shapes the report JSON. A plain presenter (not a JsonResource): `accepted()` for the 202
 * echo of a freshly requested report, `status()` for the full status resource (adds a
 * download link once completed, and the reason once failed).
 */
final class ReportResource
{
    /**
     * @return array{data: array{id: string, status: string}}
     */
    public static function accepted(RequestReportResponse $response): array
    {
        return [
            'data' => [
                'id' => $response->id,
                'status' => $response->status,
            ],
        ];
    }

    /**
     * @return array{data: array<string, mixed>}
     */
    public static function status(Report $report): array
    {
        return [
            'data' => [
                'id' => $report->id()->value(),
                'type' => $report->type()->value,
                'status' => $report->status()->value,
                'requested_at' => $report->requestedAt()->format(DateTimeInterface::ATOM),
                'completed_at' => $report->completedAt()?->format(DateTimeInterface::ATOM),
                'download_url' => $report->isCompleted()
                    ? route('reports.download', ['id' => $report->id()->value()])
                    : null,
                'failure_reason' => $report->status() === ReportStatus::Failed
                    ? $report->failureReason()
                    : null,
            ],
        ];
    }
}
