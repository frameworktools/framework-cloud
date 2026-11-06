<?php

use App\Http\Controllers\Api\GitHubWebhookController;
use App\Http\Controllers\Api\V1\BlueprintsController;
use App\Http\Controllers\Api\V1\CliDeviceController;
use App\Http\Controllers\Api\V1\CollectionsController;
use App\Http\Controllers\Api\V1\DocumentsController;
use App\Http\Controllers\Api\V1\ExternalArtifactsController;
use App\Http\Controllers\Api\V1\LedgerController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PiecesController;
use App\Http\Controllers\Api\V1\ProjectLayersController;
use App\Http\Controllers\Api\V1\ProjectPullController;
use App\Http\Controllers\Api\V1\ProjectsController;
use App\Http\Controllers\Api\V1\ReportsController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SkillSyncController;
use App\Http\Controllers\Api\V1\StructuresController;
use App\Http\Controllers\Api\V1\TokenController;
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

    // Account settings and the catalog: fw blueprints, structures, skills, defaults
    Route::get('/settings', [SettingsController::class, 'show']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::get('/blueprints', BlueprintsController::class);
    foreach (['structures' => 'structure', 'tools' => 'tool', 'package-sets' => 'package_set', 'skill-sets' => 'skill_set'] as $path => $kind) {
        Route::get("/{$path}", [PiecesController::class, 'index'])->defaults('kind', $kind);
    }
    Route::post('/structures', [StructuresController::class, 'store']);
    Route::get('/skills', [PiecesController::class, 'skills']);
    Route::post('/skills/sync', [SkillSyncController::class, 'store'])->middleware('throttle:60,1');
    Route::get('/skills/sync/{id}/history', [SkillSyncController::class, 'history']);
    Route::get('/skills/sync/{id}', [SkillSyncController::class, 'show']);
    Route::get('/defaults', [PiecesController::class, 'defaults']);
    Route::get('/destinations', [PiecesController::class, 'destinations']);
    Route::post('/resolve', [PiecesController::class, 'resolve'])->middleware('throttle:60,1');

    // Projects. POST /projects takes resolved choices, or the older name-and-type registration.
    Route::get('/projects', [ProjectsController::class, 'index']);
    Route::post('/projects', [ProjectsController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/projects/pull', [ProjectLayersController::class, 'pull']);
    Route::get('/projects/{slug}', [ProjectLayersController::class, 'show']);
    Route::get('/projects/{slug}/download', [ProjectLayersController::class, 'download'])->middleware('throttle:10,1');
    Route::get('/projects/{slug}/layers', [ProjectLayersController::class, 'layers']);
    Route::post('/projects/{slug}/pull-tokens', [ProjectLayersController::class, 'issuePullToken'])->middleware('throttle:30,1');

    // Project token: the project's own setup, publishing, and data ledger
    Route::get('/project', ProjectPullController::class);
    Route::put('/project/spec', [ProjectLayersController::class, 'updateSpec']);
    Route::post('/documents', [DocumentsController::class, 'store']);
    Route::post('/ledger', [LedgerController::class, 'store']);
    Route::get('/ledger', [LedgerController::class, 'index']);
    Route::post('/projects/{slug}/artifacts/external', [ExternalArtifactsController::class, 'store'])->middleware('throttle:60,1');

    // Reports: Markdown or HTML text with published artifacts, built from a script or an agent
    Route::get('/projects/{slug}/reports', [ReportsController::class, 'index']);
    Route::post('/projects/{slug}/reports', [ReportsController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/projects/{slug}/reports/{report}', [ReportsController::class, 'show']);
    Route::patch('/projects/{slug}/reports/{report}', [ReportsController::class, 'update'])->middleware('throttle:60,1');
    Route::post('/projects/{slug}/reports/{report}/publish', [ReportsController::class, 'publish'])->middleware('throttle:30,1');

    // Collections
    Route::get('/collections', [CollectionsController::class, 'index']);
    Route::post('/collections', [CollectionsController::class, 'store'])->middleware('throttle:20,1');
    Route::post('/collections/{collection}/items', [CollectionsController::class, 'storeItem'])->middleware('throttle:60,1');
});
