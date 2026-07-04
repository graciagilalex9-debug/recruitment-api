<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Providers;

use App\Assignment\Application\Consolidated\ConsolidatedListingReader;
use App\Assignment\Application\Consolidated\ConsolidatedListingStreamReader;
use App\Assignment\Domain\AssignmentRepository;
use App\Assignment\Domain\PendingAssignmentReader;
use App\Assignment\Infrastructure\Cache\ConsolidatedListingCache;
use App\Assignment\Infrastructure\Persistence\CachingAssignmentRepository;
use App\Assignment\Infrastructure\Persistence\CachingConsolidatedListingReader;
use App\Assignment\Infrastructure\Persistence\EloquentAssignmentRepository;
use App\Assignment\Infrastructure\Persistence\QueryBuilderConsolidatedListingReader;
use App\Assignment\Infrastructure\Persistence\QueryBuilderConsolidatedListingStreamReader;
use App\Assignment\Infrastructure\Persistence\QueryBuilderPendingAssignmentReader;
use Illuminate\Support\ServiceProvider;

final class AssignmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PendingAssignmentReader::class, QueryBuilderPendingAssignmentReader::class);
        $this->app->bind(ConsolidatedListingStreamReader::class, QueryBuilderConsolidatedListingStreamReader::class);

        // Writes go through a caching decorator that invalidates the listing cache on save().
        $this->app->bind(AssignmentRepository::class, fn ($app): AssignmentRepository => new CachingAssignmentRepository(
            $app->make(EloquentAssignmentRepository::class),
            $app->make(ConsolidatedListingCache::class),
        ));

        // The listing is read through a caching decorator over the Query Builder reader.
        $this->app->bind(ConsolidatedListingReader::class, fn ($app): ConsolidatedListingReader => new CachingConsolidatedListingReader(
            $app->make(QueryBuilderConsolidatedListingReader::class),
            $app->make(ConsolidatedListingCache::class),
        ));
    }
}
