<?php

use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ProjectPullController;
use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

// Prometheus scrape (bearer token) — outside /v1: it is
// infrastructure, not client API
Route::get('/metrics', \App\Http\Controllers\MetricsController::class)
    ->middleware(\App\Http\Middleware\VerifyMetricsToken::class);

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/me', MeController::class);

    // User-token endpoints
    Route::get('/settings', [SettingsController::class, 'show']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::get('/blueprints', \App\Http\Controllers\Api\V1\BlueprintsController::class);
    Route::get('/projects', [\App\Http\Controllers\Api\V1\ProjectsController::class, 'index']);
    Route::post('/projects', [\App\Http\Controllers\Api\V1\ProjectsController::class, 'store']);

    // Project-token endpoints: framework::setup("{project-token}") / publish()
    Route::get('/project', ProjectPullController::class);
    Route::post('/documents', [\App\Http\Controllers\Api\V1\DocumentsController::class, 'store']);
    Route::post('/ledger', [\App\Http\Controllers\Api\V1\LedgerController::class, 'store']);
    Route::get('/ledger', [\App\Http\Controllers\Api\V1\LedgerController::class, 'index']);
});
