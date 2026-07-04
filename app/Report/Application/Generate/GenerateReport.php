<?php

declare(strict_types=1);

namespace App\Report\Application\Generate;

use App\Assignment\Application\Consolidated\ConsolidatedListingQuery;
use App\Assignment\Application\Consolidated\ConsolidatedListingStreamReader;
use App\Report\Domain\Exception\ReportNotFound;
use App\Report\Domain\ReportRepository;
use App\Report\Domain\ValueObject\ReportId;
use DateTimeImmutable;

/**
 * Use case: build a requested report's file. Run in the background (by the queue worker),
 * it drives the report through its lifecycle: processing → write the workbook from the
 * consolidated listing → completed → notify the requester.
 *
 * The result (completed report + stored file) is persisted BEFORE notifying, so a failing
 * email never discards the expensive work already done — see the notify step.
 */
final readonly class GenerateReport
{
    public function __construct(
        private ReportRepository $reports,
        private ConsolidatedListingStreamReader $listing,
        private ConsolidatedReportWriter $writer,
        private ReportNotifier $notifier,
    ) {}

    public function __invoke(ReportId $id): void
    {
        $report = $this->reports->find($id) ?? throw new ReportNotFound($id);

        $report->markProcessing();
        $this->reports->save($report);

        $criteria = $report->criteria();
        $query = new ConsolidatedListingQuery(
            sort: $criteria->sort(),
            direction: $criteria->direction(),
            filters: $criteria->filters(),
            page: 1,
            perPage: 1, // ignored by the stream reader (it returns every matching row)
        );

        $path = $this->writer->write($report->id(), $this->listing->stream($query));

        $report->markCompleted($path, new DateTimeImmutable);
        $this->reports->save($report);

        // Persist first, notify second: if the mail transport fails, the report stays
        // completed and downloadable — we don't want to regenerate the whole file on retry.
        $this->notifier->notifyReady($report);
    }
}
