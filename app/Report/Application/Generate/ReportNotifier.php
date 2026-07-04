<?php

declare(strict_types=1);

namespace App\Report\Application\Generate;

use App\Report\Domain\Report;

/**
 * Port for notifying that a report is ready. The concrete notifier (an email via Mailpit
 * with a download link) lives in Infrastructure; the use case only knows "tell the
 * requester this report is ready".
 */
interface ReportNotifier
{
    public function notifyReady(Report $report): void;
}
