<?php

declare(strict_types=1);

use App\Assignment\Infrastructure\Http\GetConsolidatedListingController;
use App\Assignment\Infrastructure\Http\PostCandidatureAutoAssignController;
use App\Assignment\Infrastructure\Http\PutCandidatureEvaluatorController;
use App\Candidature\Infrastructure\Http\GetCandidatureSummaryController;
use App\Candidature\Infrastructure\Http\GetCandidatureValidationController;
use App\Candidature\Infrastructure\Http\PostCandidatureController;
use App\Evaluator\Infrastructure\Http\PostEvaluatorController;
use App\Report\Infrastructure\Http\DownloadReportController;
use App\Report\Infrastructure\Http\GetReportController;
use App\Report\Infrastructure\Http\PostConsolidatedReportController;
use Illuminate\Support\Facades\Route;

Route::post('/candidatures', PostCandidatureController::class);
Route::post('/candidatures/auto-assign', PostCandidatureAutoAssignController::class);
Route::get('/candidatures/consolidated', GetConsolidatedListingController::class);
Route::post('/candidatures/consolidated/export', PostConsolidatedReportController::class)->middleware('idempotent');
Route::get('/candidatures/{id}/validation', GetCandidatureValidationController::class)->whereUlid('id');
Route::get('/candidatures/{id}/summary', GetCandidatureSummaryController::class)->whereUlid('id');

Route::post('/evaluators', PostEvaluatorController::class);
Route::put('/candidatures/{id}/evaluator', PutCandidatureEvaluatorController::class)->whereUlid('id');

Route::get('/reports/{id}', GetReportController::class)->whereUlid('id');
Route::get('/reports/{id}/download', DownloadReportController::class)->whereUlid('id')->name('reports.download');
