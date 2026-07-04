<?php

declare(strict_types=1);

use App\Assignment\Infrastructure\Http\PutCandidatureEvaluatorController;
use App\Candidature\Infrastructure\Http\GetCandidatureValidationController;
use App\Candidature\Infrastructure\Http\PostCandidatureController;
use App\Evaluator\Infrastructure\Http\PostEvaluatorController;
use Illuminate\Support\Facades\Route;

Route::post('/candidatures', PostCandidatureController::class);
Route::get('/candidatures/{id}/validation', GetCandidatureValidationController::class)->whereUlid('id');

Route::post('/evaluators', PostEvaluatorController::class);
Route::put('/candidatures/{id}/evaluator', PutCandidatureEvaluatorController::class)->whereUlid('id');
