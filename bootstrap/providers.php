<?php

declare(strict_types=1);

use App\Candidature\Infrastructure\Providers\CandidatureServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CandidatureServiceProvider::class,
];
