<?php

use App\Http\Controllers\Api\GitHubWebhookController;
use App\Http\Controllers\Api\V1\ArtifactsController;
use App\Http\Controllers\Api\V1\BlueprintDesignController;
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
use App\Http\Controllers\Api\V1\SkillsController;
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

// An upload link's raw file. The one-time token in the address is the permission;
// see ArtifactsController::createUpload.
Route::match(['put', 'post'], 'v1/uploads/{token}', [ArtifactsController::class, 'receiveUpload'])
    ->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:30,1')->name('api.uploads.store');

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/me', MeController::class);
    Route::delete('/token', [TokenController::class, 'destroy']);

    // Account settings and the catalog: fw blueprints, structures, skills, defaults
    Route::get('/settings', [SettingsController::class, 'show']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::get('/blueprints', BlueprintsController::class);
    // Designing blueprints, as the web builder does (keys may be framework/x or @owner/x)
    Route::get('/blueprints/options', [BlueprintDesignController::class, 'options']);
    Route::post('/blueprints/preview', [BlueprintDesignController::class, 'preview'])->middleware('throttle:60,1');
    Route::post('/blueprints', [BlueprintDesignController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/blueprints/{blueprint}', [BlueprintDesignController::class, 'show'])->where('blueprint', '.+');
    Route::put('/blueprints/{blueprint}', [BlueprintDesignController::class, 'update'])->where('blueprint', '.+')->middleware('throttle:30,1');
    foreach (['structures' => 'structure', 'tools' => 'tool', 'package-sets' => 'package_set', 'skill-sets' => 'skill_set'] as $path => $kind) {
        Route::get("/{$path}", [PiecesController::class, 'index'])->defaults('kind', $kind);
    }
    Route::post('/structures', [StructuresController::class, 'store']);
    Route::get('/skills', [PiecesController::class, 'skills']);
    Route::post('/skills/sync', [SkillSyncController::class, 'store'])->middleware('throttle:60,1');
    Route::get('/skills/sync/{id}/history', [SkillSyncController::class, 'history']);
    Route::get('/skills/sync/{id}', [SkillSyncController::class, 'show']);
    // One skill as SKILL.md text: read, create, and save a new version (keys may be framework/x or @owner/x)
    Route::post('/skills', [SkillsController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/skills/{skill}', [SkillsController::class, 'show'])->where('skill', '.+');
    Route::put('/skills/{skill}', [SkillsController::class, 'update'])->where('skill', '.+')->middleware('throttle:60,1');
    Route::delete('/skills/{skill}', [SkillsController::class, 'destroy'])->where('skill', '.+')->middleware('throttle:30,1');
    Route::get('/defaults', [PiecesController::class, 'defaults']);
    Route::get('/destinations', [PiecesController::class, 'destinations']);
    Route::post('/resolve', [PiecesController::class, 'resolve'])->middleware('throttle:60,1');

    // Projects. POST /projects takes resolved choices, or the older name-and-type registration.
    Route::get('/projects', [ProjectsController::class, 'index']);
    Route::post('/projects', [ProjectsController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/projects/pull', [ProjectLayersController::class, 'pull']);
    Route::get('/projects/{slug}', [ProjectLayersController::class, 'show']);
    Route::patch('/projects/{slug}', [ProjectsController::class, 'update'])->middleware('throttle:60,1');
    Route::delete('/projects/{slug}', [ProjectsController::class, 'destroy'])->middleware('throttle:30,1');
    Route::get('/projects/{slug}/download', [ProjectLayersController::class, 'download'])->middleware('throttle:10,1');
    Route::get('/projects/{slug}/layers', [ProjectLayersController::class, 'layers']);
    Route::post('/projects/{slug}/pull-tokens', [ProjectLayersController::class, 'issuePullToken'])->middleware('throttle:30,1');

    // Project token: the project's own setup, publishing, and data ledger
    Route::get('/project', ProjectPullController::class);
    Route::put('/project/spec', [ProjectLayersController::class, 'updateSpec']);
    Route::post('/documents', [DocumentsController::class, 'store']);
    Route::post('/ledger', [LedgerController::class, 'store']);
    Route::get('/ledger', [LedgerController::class, 'index']);
    // Artifacts across the account; "project" is optional, and those without one live in the account's home
    Route::get('/artifacts', [ArtifactsController::class, 'index']);
    Route::post('/artifacts', [ArtifactsController::class, 'store'])->middleware('throttle:30,1');
    Route::post('/artifacts/uploads', [ArtifactsController::class, 'createUpload'])->middleware('throttle:30,1');
    Route::get('/artifacts/{artifact}', [ArtifactsController::class, 'show']);
    Route::delete('/artifacts/{artifact}', [ArtifactsController::class, 'destroy'])->middleware('throttle:30,1');
    Route::post('/projects/{slug}/linked-artifacts', [ArtifactsController::class, 'link'])->middleware('throttle:60,1');
    Route::delete('/projects/{slug}/linked-artifacts/{artifact}', [ArtifactsController::class, 'unlink']);
    Route::get('/projects/{slug}/artifacts', [ArtifactsController::class, 'index']);
    Route::post('/projects/{slug}/artifacts', [ArtifactsController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/projects/{slug}/artifacts/{artifact}', [ArtifactsController::class, 'show']);
    Route::post('/projects/{slug}/artifacts/external', [ExternalArtifactsController::class, 'store'])->middleware('throttle:60,1');

    // Reports: Markdown or HTML text with published artifacts, built from a script or an agent
    Route::get('/reports', [ReportsController::class, 'indexAll']);
    Route::post('/reports', [ReportsController::class, 'storeAnywhere'])->middleware('throttle:30,1');
    Route::get('/projects/{slug}/reports', [ReportsController::class, 'index']);
    Route::post('/projects/{slug}/reports', [ReportsController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/projects/{slug}/reports/{report}', [ReportsController::class, 'show']);
    Route::patch('/projects/{slug}/reports/{report}', [ReportsController::class, 'update'])->middleware('throttle:60,1');
    Route::post('/projects/{slug}/reports/{report}/publish', [ReportsController::class, 'publish'])->middleware('throttle:30,1');
    Route::delete('/projects/{slug}/reports/{report}', [ReportsController::class, 'destroy'])->middleware('throttle:30,1');

    // Collections
    Route::get('/collections', [CollectionsController::class, 'index']);
    Route::post('/collections', [CollectionsController::class, 'store'])->middleware('throttle:20,1');
    Route::post('/collections/{collection}/items', [CollectionsController::class, 'storeItem'])->middleware('throttle:60,1');
});
