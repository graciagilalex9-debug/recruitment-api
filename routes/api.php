<?php

declare(strict_types=1);

use App\Candidature\Infrastructure\Http\PostCandidatureController;
use Illuminate\Support\Facades\Route;

Route::post('/candidatures', PostCandidatureController::class);
