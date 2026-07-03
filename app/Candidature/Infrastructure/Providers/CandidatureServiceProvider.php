<?php

declare(strict_types=1);

namespace App\Candidature\Infrastructure\Providers;

use App\Candidature\Domain\CandidatureRepository;
use App\Candidature\Infrastructure\Persistence\EloquentCandidatureRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Candidature context into Laravel's service container.
 *
 * This is the single place where the domain port is bound to its concrete Eloquent
 * implementation. Everything else depends on the CandidatureRepository interface, so the
 * data layer can be swapped by changing only this line.
 */
final class CandidatureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CandidatureRepository::class, EloquentCandidatureRepository::class);
    }
}
