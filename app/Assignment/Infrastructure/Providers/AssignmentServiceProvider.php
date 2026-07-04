<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Providers;

use App\Assignment\Domain\AssignmentRepository;
use App\Assignment\Infrastructure\Persistence\EloquentAssignmentRepository;
use Illuminate\Support\ServiceProvider;

final class AssignmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AssignmentRepository::class, EloquentAssignmentRepository::class);
    }
}
