<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// r.framework.pub — the R package docs site, domain-routed on the same app.
// Domain groups must precede host-less routes: a host-less route matches any host.
Route::domain(config('framework.docs_host'))->name('docs.')->group(function () {
    Route::get('/', [\App\Http\Controllers\DocsController::class, 'home'])->name('home');
    Route::get('/guides/{slug}', [\App\Http\Controllers\DocsController::class, 'guide'])->name('guide');
    Route::get('/reference', [\App\Http\Controllers\DocsController::class, 'reference'])->name('reference');
    Route::get('/reference/{name}', [\App\Http\Controllers\DocsController::class, 'fn'])->name('function');
    Route::get('/search', [\App\Http\Controllers\DocsController::class, 'search'])->name('search');
});

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Public profiles: /@{handle} (opt-in; 404 unless profile_public with a handle)
Route::get('/@{handle}', function (string $handle) {
    $user = \App\Models\User::where('handle', $handle)->where('profile_public', true)->firstOrFail();

    $affiliation = \Illuminate\Support\Arr::get(
        $user->globalSettings()->document, 'author.affiliation', '',
    );

    return view('profile', ['user' => $user, 'affiliation' => $affiliation]);
})->where('handle', '[a-z0-9][a-z0-9-]*')->name('profile');

Route::view('terms', 'legal.terms')->name('terms');
Route::view('privacy', 'legal.privacy')->name('privacy');

// Published documents (public capability URLs)
Route::get('d/{key}', [\App\Http\Controllers\DocumentViewController::class, 'show'])->name('documents.show');
Route::get('d/{key}/raw', [\App\Http\Controllers\DocumentViewController::class, 'raw'])->name('documents.raw');
Route::get('d/{key}/{path}', [\App\Http\Controllers\DocumentViewController::class, 'asset'])
    ->where('path', '.*')->name('documents.asset');

// The app (Inertia + Vue)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = request()->user();

        return Inertia::render('Dashboard', [
            'counts' => [
                'projects' => $user->projects()->count(),
                'tokens' => $user->tokens()->count(),
                'settings_revision' => $user->globalSettings()->revision,
            ],
        ]);
    })->name('dashboard');

    Route::get('projects', [\App\Http\Controllers\App\ProjectsController::class, 'index'])->name('projects');
    Route::post('projects', [\App\Http\Controllers\App\ProjectsController::class, 'store']);
    Route::post('projects/{project}/token', [\App\Http\Controllers\App\ProjectsController::class, 'issueToken']);
    Route::delete('projects/{project}', [\App\Http\Controllers\App\ProjectsController::class, 'destroy']);

    Route::get('tokens', [\App\Http\Controllers\App\TokensController::class, 'index'])->name('tokens');
    Route::post('tokens', [\App\Http\Controllers\App\TokensController::class, 'store']);
    Route::delete('tokens/{tokenId}', [\App\Http\Controllers\App\TokensController::class, 'destroy']);

    Route::redirect('settings', 'settings/profile');
    Route::get('settings/profile', [\App\Http\Controllers\Settings\ProfileController::class, 'edit'])->name('settings.profile');
    Route::patch('settings/profile', [\App\Http\Controllers\Settings\ProfileController::class, 'update']);
    Route::delete('settings/profile', [\App\Http\Controllers\Settings\ProfileController::class, 'destroy']);

    Route::get('settings/defaults', [\App\Http\Controllers\Settings\DefaultsController::class, 'edit'])->name('settings.defaults');
    Route::patch('settings/defaults', [\App\Http\Controllers\Settings\DefaultsController::class, 'update']);

    Route::get('settings/passkeys', [\App\Http\Controllers\Settings\PasskeysController::class, 'index'])->name('settings.passkeys');
    Route::post('settings/passkeys/options', [\App\Http\Controllers\Settings\PasskeysController::class, 'options']);
    Route::post('settings/passkeys', [\App\Http\Controllers\Settings\PasskeysController::class, 'store']);
    Route::delete('settings/passkeys/{passkeyId}', [\App\Http\Controllers\Settings\PasskeysController::class, 'destroy']);
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/blueprints');
    Route::get('blueprints', [\App\Http\Controllers\Admin\BlueprintsController::class, 'index'])->name('blueprints');
    Route::patch('blueprints/{blueprint}', [\App\Http\Controllers\Admin\BlueprintsController::class, 'update']);
});

require __DIR__.'/auth.php';
