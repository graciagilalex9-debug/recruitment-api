<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API documentation: Swagger UI at /docs, backed by the spec-first contract at /openapi.yaml.
Route::view('/docs', 'docs');

Route::get('/openapi.yaml', function () {
    return response()->file(base_path('docs/openapi.yaml'), [
        'Content-Type' => 'application/yaml',
    ]);
});
