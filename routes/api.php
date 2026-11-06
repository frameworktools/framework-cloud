<?php

use App\Http\Controllers\Api\GitHubWebhookController;
use App\Http\Controllers\Api\V1\BlueprintsController;
use App\Http\Controllers\Api\V1\CliDeviceController;
use App\Http\Controllers\Api\V1\DocumentsController;
use App\Http\Controllers\Api\V1\LedgerController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\ProjectPullController;
use App\Http\Controllers\Api\V1\ProjectsController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V2\CollectionsController;
use App\Http\Controllers\Api\V2\ExternalArtifactsController;
use App\Http\Controllers\Api\V2\PiecesController;
use App\Http\Controllers\Api\V2\SkillSyncController;
use App\Http\Controllers\Api\V2\StructuresController;
use App\Http\Controllers\MetricsController;
use App\Http\Middleware\VerifyMetricsToken;
use Illuminate\Support\Facades\Route;

// Prometheus scrape (bearer token) — outside /v1: it is
// infrastructure, not client API
Route::get('/metrics', MetricsController::class)
    ->middleware(VerifyMetricsToken::class);

// Framework GitHub App events (signed with the webhook secret)
Route::post('/github/webhook', GitHubWebhookController::class)->middleware('throttle:120,1');

// fw login's device flow: the CLI has no token yet, so these sit outside
// auth:sanctum. The browser half is /device in routes/web.php.
Route::prefix('v1/cli')->group(function () {
    Route::post('/device', [CliDeviceController::class, 'store'])->middleware('throttle:10,1');
    Route::post('/device/token', [CliDeviceController::class, 'token'])->middleware('throttle:30,1');
});

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/me', MeController::class);
    Route::delete('/token', [TokenController::class, 'destroy']);

    // User-token endpoints
    Route::get('/settings', [SettingsController::class, 'show']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::get('/blueprints', BlueprintsController::class);
    Route::get('/projects', [ProjectsController::class, 'index']);
    Route::post('/projects', [ProjectsController::class, 'store']);

    // Project-token endpoints: framework::setup("{project-token}") / publish()
    Route::get('/project', ProjectPullController::class);
    Route::post('/documents', [DocumentsController::class, 'store']);
    Route::post('/ledger', [LedgerController::class, 'store']);
    Route::get('/ledger', [LedgerController::class, 'index']);
});

// Pull v2: layers envelopes for the R package and fw
Route::prefix('v2')->middleware('auth:sanctum')->group(function () {
    Route::post('/projects/{slug}/artifacts/external', [ExternalArtifactsController::class, 'store'])->middleware('throttle:60,1');
    Route::get('/collections', [CollectionsController::class, 'index']);
    Route::post('/collections', [CollectionsController::class, 'store'])->middleware('throttle:20,1');
    Route::post('/collections/{collection}/items', [CollectionsController::class, 'storeItem'])->middleware('throttle:60,1');
    // Project token
    Route::get('/projects/pull', [App\Http\Controllers\Api\V2\ProjectsController::class, 'pull']);
    Route::put('/project/spec', [App\Http\Controllers\Api\V2\ProjectsController::class, 'updateSpec']);

    // User token: fw projects, fw project show, fw blueprints, fw new SLUG
    Route::get('/blueprints', App\Http\Controllers\Api\V2\BlueprintsController::class);
    foreach (['structures' => 'structure', 'tools' => 'tool', 'package-sets' => 'package_set', 'skill-sets' => 'skill_set'] as $path => $kind) {
        Route::get("/{$path}", [PiecesController::class, 'index'])->defaults('kind', $kind);
    }
    Route::post('/structures', [StructuresController::class, 'store']);
    Route::get('/skills', [PiecesController::class, 'skills']);
    Route::post('/skills/sync', [SkillSyncController::class, 'store'])->middleware('throttle:60,1');
    Route::get('/skills/sync/{id}/history', [SkillSyncController::class, 'history']);
    Route::get('/skills/sync/{id}', [SkillSyncController::class, 'show']);
    Route::get('/defaults', [PiecesController::class, 'defaults']);
    Route::post('/resolve', [PiecesController::class, 'resolve'])->middleware('throttle:60,1');
    Route::post('/projects', [PiecesController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/projects/{slug}', [App\Http\Controllers\Api\V2\ProjectsController::class, 'show']);
    Route::get('/destinations', [PiecesController::class, 'destinations']);
    Route::get('/projects/{slug}/download', [App\Http\Controllers\Api\V2\ProjectsController::class, 'download'])->middleware('throttle:10,1');
    Route::get('/projects/{slug}/layers', [App\Http\Controllers\Api\V2\ProjectsController::class, 'layers']);
    Route::post('/projects/{slug}/pull-tokens', [App\Http\Controllers\Api\V2\ProjectsController::class, 'issuePullToken'])
        ->middleware('throttle:30,1');
});
