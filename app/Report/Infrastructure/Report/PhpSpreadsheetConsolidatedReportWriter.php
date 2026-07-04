<?php

declare(strict_types=1);

namespace App\Report\Infrastructure\Report;

use App\Assignment\Application\Consolidated\ConsolidatedRow;
use App\Report\Application\Generate\ConsolidatedReportWriter;
use App\Report\Domain\ValueObject\ReportId;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * PhpSpreadsheet implementation of the report writer. It walks the streamed consolidated
 * rows, starting a new worksheet every 50 candidates (each sheet repeats the header), writes
 * the .xlsx to the `local` disk under reports/{id}.xlsx and returns the stored path.
 *
 * Scalability note: PhpSpreadsheet builds the whole workbook in memory. A streaming writer
 * (constant memory) for very large exports is deferred to #7 — see docs/scalability-backlog.md.
 */
final readonly class PhpSpreadsheetConsolidatedReportWriter implements ConsolidatedReportWriter
{
    private const ROWS_PER_SHEET = 50;

    private const DISK = 'local';

    /** @var list<string> */
    private const HEADER = [
        'Full name',
        'Email',
        'Years of experience',
        'Evaluator',
        'Assigned at',
        'Evaluator total',
        'Evaluator candidate emails',
    ];

    /**
     * @param  iterable<ConsolidatedRow>  $rows
     */
    public function write(ReportId $id, iterable $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0); // drop the default sheet; we add our own

        $sheet = null;
        $rowsInSheet = 0;
        $sheetNumber = 0;

        foreach ($rows as $row) {
            if ($sheet === null || $rowsInSheet === self::ROWS_PER_SHEET) {
                $sheetNumber++;
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle('Candidates '.$sheetNumber);
                $sheet->fromArray(self::HEADER, null, 'A1');
                $rowsInSheet = 0;
            }

            $sheet->fromArray(
                [
                    $row->fullName,
                    $row->email,
                    $row->yearsOfExperience,
                    $row->evaluatorName,
                    $row->assignedAt,
                    $row->evaluatorTotal,
                    $row->evaluatorCandidateEmails,
                ],
                null,
                'A'.($rowsInSheet + 2), // +2: row 1 is the header
            );

            $rowsInSheet++;
        }

        if ($sheet === null) {
            // No matching candidatures: still produce a valid, header-only workbook.
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Candidates 1');
            $sheet->fromArray(self::HEADER, null, 'A1');
        }

        $spreadsheet->setActiveSheetIndex(0);

        $path = sprintf('reports/%s.xlsx', $id->value());
        $temporaryFile = tempnam(sys_get_temp_dir(), 'report_');

        (new Xlsx($spreadsheet))->save($temporaryFile);
        Storage::disk(self::DISK)->put($path, (string) file_get_contents($temporaryFile));

        unlink($temporaryFile);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
