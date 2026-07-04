<?php

declare(strict_types=1);

namespace App\Evaluator\Infrastructure\Providers;

use App\Evaluator\Domain\EvaluatorRepository;
use App\Evaluator\Infrastructure\Persistence\EloquentEvaluatorRepository;
use Illuminate\Support\ServiceProvider;

final class EvaluatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EvaluatorRepository::class, EloquentEvaluatorRepository::class);
    }
}
