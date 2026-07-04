<?php

declare(strict_types=1);

namespace App\Report\Infrastructure\Http;

use App\Report\Domain\Exception\ReportNotFound;
use App\Report\Domain\ReportRepository;
use App\Report\Domain\ValueObject\ReportId;
use Illuminate\Http\JsonResponse;

/**
 * GET /reports/{id} — the current status of a report (and a download link once completed).
 */
final readonly class GetReportController
{
    public function __construct(
        private ReportRepository $reports,
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        $reportId = new ReportId($id);

        $report = $this->reports->find($reportId) ?? throw new ReportNotFound($reportId);

        return response()->json(ReportResource::status($report));
    }
}
