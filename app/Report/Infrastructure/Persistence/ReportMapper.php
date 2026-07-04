<?php

declare(strict_types=1);

namespace App\Report\Infrastructure\Persistence;

use App\Report\Domain\Report;
use App\Report\Domain\ValueObject\ReportCriteria;
use App\Report\Domain\ValueObject\ReportId;
use App\Report\Domain\ValueObject\ReportStatus;
use App\Report\Domain\ValueObject\ReportType;
use DateTimeImmutable;

/**
 * Translates between the Report aggregate and its Eloquent model.
 */
final class ReportMapper
{
    public function toDomain(ReportModel $model): Report
    {
        return Report::reconstitute(
            id: new ReportId($model->id),
            type: ReportType::from($model->type),
            criteria: new ReportCriteria(
                sort: $model->sort,
                direction: $model->direction,
                filters: array_map(static fn (mixed $value): string => (string) $value, $model->filters),
            ),
            status: ReportStatus::from($model->status),
            requestedAt: DateTimeImmutable::createFromInterface($model->requested_at),
            filePath: $model->file_path,
            completedAt: $model->completed_at === null
                ? null
                : DateTimeImmutable::createFromInterface($model->completed_at),
            failureReason: $model->failure_reason,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toRow(Report $report): array
    {
        $criteria = $report->criteria();

        return [
            'id' => $report->id()->value(),
            'type' => $report->type()->value,
            'status' => $report->status()->value,
            'sort' => $criteria->sort(),
            'direction' => $criteria->direction(),
            'filters' => $criteria->filters(),
            'file_path' => $report->filePath(),
            'failure_reason' => $report->failureReason(),
            'requested_at' => $report->requestedAt()->format('Y-m-d H:i:s'),
            'completed_at' => $report->completedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
