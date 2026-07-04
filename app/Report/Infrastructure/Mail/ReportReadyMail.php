<?php

declare(strict_types=1);

namespace App\Report\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The "your report is ready" email. A notification with a download link (not an attachment),
 * so large workbooks never hit SMTP size limits. The body is an inline HTML string to avoid
 * a Blade view for a one-line message.
 */
final class ReportReadyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $reportId,
        public readonly string $downloadUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your consolidated report is ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: sprintf(
                '<p>Your consolidated report (<code>%s</code>) is ready.</p>'
                .'<p><a href="%s">Download it here</a></p>',
                e($this->reportId),
                e($this->downloadUrl),
            ),
        );
    }
}
