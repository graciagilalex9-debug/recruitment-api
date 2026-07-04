<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Providers;

use App\Assignment\Application\Consolidated\ConsolidatedListingReader;
use App\Assignment\Application\Consolidated\ConsolidatedListingStreamReader;
use App\Assignment\Domain\AssignmentRepository;
use App\Assignment\Domain\PendingAssignmentReader;
use App\Assignment\Infrastructure\Persistence\EloquentAssignmentRepository;
use App\Assignment\Infrastructure\Persistence\QueryBuilderConsolidatedListingReader;
use App\Assignment\Infrastructure\Persistence\QueryBuilderConsolidatedListingStreamReader;
use App\Assignment\Infrastructure\Persistence\QueryBuilderPendingAssignmentReader;
use Illuminate\Support\ServiceProvider;

final class AssignmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AssignmentRepository::class, EloquentAssignmentRepository::class);
        $this->app->bind(PendingAssignmentReader::class, QueryBuilderPendingAssignmentReader::class);
        $this->app->bind(ConsolidatedListingReader::class, QueryBuilderConsolidatedListingReader::class);
        $this->app->bind(ConsolidatedListingStreamReader::class, QueryBuilderConsolidatedListingStreamReader::class);
    }
}
