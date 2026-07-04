<?php

declare(strict_types=1);

namespace App\Report\Infrastructure\Persistence;

use App\Report\Domain\Report;
use App\Report\Domain\ReportRepository;
use App\Report\Domain\ValueObject\ReportId;
use Illuminate\Support\Str;

/**
 * Eloquent implementation of the ReportRepository port.
 */
final readonly class EloquentReportRepository implements ReportRepository
{
    public function __construct(
        private ReportMapper $mapper,
    ) {}

    public function nextIdentity(): ReportId
    {
        return new ReportId((string) Str::ulid());
    }

    /**
     * Upsert by id: the first save inserts the pending report, later saves update it as it
     * moves through its lifecycle (processing → completed/failed).
     */
    public function save(Report $report): void
    {
        $row = $this->mapper->toRow($report);

        ReportModel::query()->updateOrCreate(['id' => $row['id']], $row);
    }

    public function find(ReportId $id): ?Report
    {
        $model = ReportModel::find($id->value());

        return $model === null ? null : $this->mapper->toDomain($model);
    }
}
