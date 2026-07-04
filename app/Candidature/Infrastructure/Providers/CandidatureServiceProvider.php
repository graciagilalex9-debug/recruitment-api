<?php

declare(strict_types=1);

namespace App\Candidature\Infrastructure\Providers;

use App\Candidature\Application\Validate\CandidatureValidationFinder;
use App\Candidature\Application\Validate\CandidatureValidationReader;
use App\Candidature\Domain\CandidatureRepository;
use App\Candidature\Domain\Validation\CandidatureValidator;
use App\Candidature\Domain\Validation\Rule\MustHaveCv;
use App\Candidature\Domain\Validation\Rule\MustHaveMinimumExperience;
use App\Candidature\Domain\Validation\Rule\MustHaveValidEmail;
use App\Candidature\Infrastructure\Cache\CachingCandidatureValidationReader;
use App\Candidature\Infrastructure\Persistence\EloquentCandidatureRepository;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Candidature context into Laravel's service container.
 *
 * The single place where domain ports are bound to their implementations, and where the
 * ordered validation rule set is assembled — adding a rule is one line here, nothing else.
 */
final class CandidatureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CandidatureRepository::class, EloquentCandidatureRepository::class);

        // The validation report is read through a caching decorator over the finder.
        $this->app->bind(CandidatureValidationReader::class, fn ($app): CandidatureValidationReader => new CachingCandidatureValidationReader(
            $app->make(CandidatureValidationFinder::class),
            $app->make(Repository::class),
        ));

        // Stateless validator reused across requests; the rule order is defined here.
        $this->app->singleton(CandidatureValidator::class, static fn (): CandidatureValidator => new CandidatureValidator([
            new MustHaveCv,
            new MustHaveValidEmail,
            new MustHaveMinimumExperience,
        ]));
    }
}
