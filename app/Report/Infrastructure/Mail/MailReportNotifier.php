<?php

declare(strict_types=1);

namespace App\Report\Infrastructure\Mail;

use App\Report\Application\Generate\ReportNotifier;
use App\Report\Domain\Report;
use Illuminate\Support\Facades\Mail;

/**
 * Mail implementation of the ReportNotifier port: send the "report ready" email with a link
 * to the download endpoint (caught by Mailpit in dev).
 *
 * The recipient is a fixed operations mailbox: this exercise has no authentication, so there
 * is no requester identity to address. Notifying the actual requester (and on failure too)
 * is deferred to #7 — see docs/scalability-backlog.md.
 */
final readonly class MailReportNotifier implements ReportNotifier
{
    private const RECIPIENT = 'reports@example.com';

    public function notifyReady(Report $report): void
    {
        $downloadUrl = route('reports.download', ['id' => $report->id()->value()]);

        Mail::to(self::RECIPIENT)->send(
            new ReportReadyMail($report->id()->value(), $downloadUrl),
        );
    }
}
