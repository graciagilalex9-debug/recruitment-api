<?php

declare(strict_types=1);

namespace App\Report\Infrastructure\Http;

use App\Report\Domain\Exception\ReportNotFound;
use App\Report\Domain\ReportRepository;
use App\Report\Domain\ValueObject\ReportId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /reports/{id}/download — stream the generated .xlsx when the report is completed.
 * 404 when the report does not exist; 409 when it exists but is not yet completed.
 */
final readonly class DownloadReportController
{
    private const DISK = 'local';

    private const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(
        private ReportRepository $reports,
    ) {}

    public function __invoke(string $id): Response
    {
        $reportId = new ReportId($id);

        $report = $this->reports->find($reportId) ?? throw new ReportNotFound($reportId);

        if (! $report->isCompleted() || $report->filePath() === null) {
            return new JsonResponse(
                ['message' => 'The report is not ready for download.'],
                409,
            );
        }

        return Storage::disk(self::DISK)->download(
            $report->filePath(),
            sprintf('consolidated-report-%s.xlsx', $report->id()->value()),
            ['Content-Type' => self::CONTENT_TYPE],
        );
    }
}
