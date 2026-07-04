<?php

declare(strict_types=1);

namespace App\Report\Infrastructure\Http;

use App\Report\Application\Request\RequestReport;
use App\Report\Domain\ValueObject\ReportCriteria;
use Illuminate\Http\JsonResponse;

/**
 * POST /candidatures/consolidated/export — request an Excel export of the consolidated
 * listing. Records a pending report, schedules its background generation and returns 202
 * with the report id. It never builds the file inline.
 */
final readonly class PostConsolidatedReportController
{
    public function __construct(
        private RequestReport $requestReport,
    ) {}

    public function __invoke(ExportConsolidatedReportRequest $request): JsonResponse
    {
        $criteria = new ReportCriteria(
            sort: $request->string('sort', 'years_of_experience')->toString(),
            direction: $request->string('direction', 'desc')->toString(),
            filters: $this->filters($request),
        );

        $response = ($this->requestReport)($criteria);

        return response()->json(ReportResource::accepted($response), 202);
    }

    /**
     * @return array<string, string>
     */
    private function filters(ExportConsolidatedReportRequest $request): array
    {
        $filters = [];

        foreach ($request->array('filter') as $key => $value) {
            if (is_string($key) && (is_string($value) || is_int($value) || is_float($value))) {
                $filters[$key] = (string) $value;
            }
        }

        return $filters;
    }
}
