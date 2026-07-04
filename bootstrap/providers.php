<?php

declare(strict_types=1);

use App\Assignment\Infrastructure\Providers\AssignmentServiceProvider;
use App\Candidature\Infrastructure\Providers\CandidatureServiceProvider;
use App\Evaluator\Infrastructure\Providers\EvaluatorServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CandidatureServiceProvider::class,
    EvaluatorServiceProvider::class,
    AssignmentServiceProvider::class,
];
