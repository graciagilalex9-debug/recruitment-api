<?php

declare(strict_types=1);

namespace App\Report\Infrastructure\Providers;

use App\Report\Application\Generate\ConsolidatedReportWriter;
use App\Report\Application\Generate\ReportNotifier;
use App\Report\Application\ReportDispatcher;
use App\Report\Domain\ReportRepository;
use App\Report\Infrastructure\Mail\MailReportNotifier;
use App\Report\Infrastructure\Persistence\EloquentReportRepository;
use App\Report\Infrastructure\Queue\LaravelReportDispatcher;
use App\Report\Infrastructure\Report\PhpSpreadsheetConsolidatedReportWriter;
use Illuminate\Support\ServiceProvider;

final class ReportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReportRepository::class, EloquentReportRepository::class);
        $this->app->bind(ReportDispatcher::class, LaravelReportDispatcher::class);
        $this->app->bind(ConsolidatedReportWriter::class, PhpSpreadsheetConsolidatedReportWriter::class);
        $this->app->bind(ReportNotifier::class, MailReportNotifier::class);
    }
}
