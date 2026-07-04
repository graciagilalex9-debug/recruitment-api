<?php

declare(strict_types=1);

use App\Assignment\Domain\Exception\AutoAssignInProgress;
use App\Assignment\Domain\Exception\CandidatureNotEligible;
use App\Assignment\Domain\Exception\NoEvaluatorsAvailable;
use App\Candidature\Domain\Exception\CandidatureAlreadyExists;
use App\Candidature\Domain\Exception\CandidatureNotFound;
use App\Evaluator\Domain\Exception\EvaluatorNotFound;
use App\Report\Domain\Exception\ReportNotFound;
use App\Shared\Infrastructure\Http\EnsureIdempotency;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',                       // API-only app: routes live at the root (POST /candidatures)
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Opt-in idempotency for write endpoints via the `Idempotency-Key` header.
        $middleware->alias([
            'idempotent' => EnsureIdempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API-only service: always answer errors as JSON.
        $exceptions->shouldRenderJsonWhen(fn () => true);

        // A duplicate email is a business conflict, not a server error.
        $exceptions->render(
            fn (CandidatureAlreadyExists $e) => response()->json(['message' => $e->getMessage()], 409),
        );

        // Addressing a missing candidature or evaluator is a 404.
        $exceptions->render(
            fn (CandidatureNotFound $e) => response()->json(['message' => $e->getMessage()], 404),
        );
        $exceptions->render(
            fn (EvaluatorNotFound $e) => response()->json(['message' => $e->getMessage()], 404),
        );
        $exceptions->render(
            fn (ReportNotFound $e) => response()->json(['message' => $e->getMessage()], 404),
        );

        // Assigning an ineligible candidature is a business conflict.
        $exceptions->render(
            fn (CandidatureNotEligible $e) => response()->json(['message' => $e->getMessage()], 409),
        );

        // Bulk auto-assign with no evaluators to distribute to is a business conflict.
        $exceptions->render(
            fn (NoEvaluatorsAvailable $e) => response()->json(['message' => $e->getMessage()], 409),
        );

        // A second bulk auto-assign while one is already running is a business conflict.
        $exceptions->render(
            fn (AutoAssignInProgress $e) => response()->json(['message' => $e->getMessage()], 409),
        );
    })->create();
