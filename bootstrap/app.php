<?php

declare(strict_types=1);

use App\Candidature\Domain\Exception\CandidatureAlreadyExists;
use App\Candidature\Domain\Exception\CandidatureNotFound;
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
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API-only service: always answer errors as JSON.
        $exceptions->shouldRenderJsonWhen(fn () => true);

        // A duplicate email is a business conflict, not a server error.
        $exceptions->render(
            fn (CandidatureAlreadyExists $e) => response()->json(['message' => $e->getMessage()], 409),
        );

        // Validating (or otherwise addressing) a missing candidature is a 404.
        $exceptions->render(
            fn (CandidatureNotFound $e) => response()->json(['message' => $e->getMessage()], 404),
        );
    })->create();
