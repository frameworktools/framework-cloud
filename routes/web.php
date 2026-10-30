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

        $github = \App\Services\GitHub\Scaffolder::enabled();

        return Inertia::render('Dashboard', [
            'counts' => [
                'projects' => $user->projects()->count(),
                'tokens' => $user->tokens()->count(),
                'settings_revision' => $user->globalSettings()->revision,
            ],
            // Getting started; the dashboard hides it once everything is done
            'steps' => array_values(array_filter([
                ['key' => 'profile', 'done' => filled(\Illuminate\Support\Arr::get($user->globalSettings()->document, 'author.affiliations'))
                    || filled(\Illuminate\Support\Arr::get($user->globalSettings()->document, 'author.affiliation'))],
                ['key' => 'machine', 'done' => $user->tokens()->exists()],
                $github ? ['key' => 'github', 'done' => $user->githubAccount()->exists()] : null,
                ['key' => 'project', 'done' => $user->projects()->exists()],
            ])),
            // What this server still needs, for its admins
            'setup' => $user->is_admin ? array_values(array_filter([
                in_array(config('mail.default'), ['log', 'array'], true) ? 'mail' : null,
                $github ? null : 'github',
            ])) : [],
        ]);
    })->name('dashboard');

    Route::get('projects', [\App\Http\Controllers\App\ProjectsController::class, 'index'])->name('projects');
    Route::get('published', [\App\Http\Controllers\App\DocumentsController::class, 'index'])->name('published');
    Route::delete('published/{document}', [\App\Http\Controllers\App\DocumentsController::class, 'destroy']);
    Route::get('projects/create', [\App\Http\Controllers\App\ProjectsController::class, 'create'])->name('projects.create');
    Route::post('projects', [\App\Http\Controllers\App\ProjectsController::class, 'store']);
    Route::post('projects/{project}/token', [\App\Http\Controllers\App\ProjectsController::class, 'issueToken']);
    Route::delete('projects/{project}', [\App\Http\Controllers\App\ProjectsController::class, 'destroy']);
    Route::post('projects/{project}/scaffold/refresh', [\App\Http\Controllers\App\ProjectsController::class, 'refreshScaffold']);
    Route::post('projects/{project}/scaffold/retry', [\App\Http\Controllers\App\ProjectsController::class, 'retryScaffold']);

    // The shared library: blueprints compose facets, facets carry skills
    foreach ([
        'blueprints' => \App\Http\Controllers\App\BlueprintsController::class,
        'facets' => \App\Http\Controllers\App\FacetsController::class,
        'skills' => \App\Http\Controllers\App\SkillsController::class,
    ] as $name => $controller) {
        Route::post("{$name}/{".str($name)->singular().'}/fork', [$controller, 'fork'])->name("{$name}.fork");
    }
    Route::post('skills/import', [\App\Http\Controllers\App\GitHubLibraryController::class, 'importSkill'])->middleware('throttle:20,1');
    Route::post('facets/import', [\App\Http\Controllers\App\GitHubLibraryController::class, 'importFacet'])->middleware('throttle:20,1');
    Route::post('skills/{skill}/publish', [\App\Http\Controllers\App\GitHubLibraryController::class, 'publishSkill'])->middleware('throttle:20,1');
    Route::post('facets/{facet}/publish', [\App\Http\Controllers\App\GitHubLibraryController::class, 'publishFacet'])->middleware('throttle:20,1');
    Route::resource('blueprints', \App\Http\Controllers\App\BlueprintsController::class);
    Route::resource('facets', \App\Http\Controllers\App\FacetsController::class)->except('show');
    Route::resource('skills', \App\Http\Controllers\App\SkillsController::class)->except('show');

    Route::get('tokens', [\App\Http\Controllers\App\TokensController::class, 'index'])->name('tokens');
    Route::post('tokens', [\App\Http\Controllers\App\TokensController::class, 'store']);
    Route::delete('tokens/{tokenId}', [\App\Http\Controllers\App\TokensController::class, 'destroy']);

    Route::redirect('settings', 'settings/profile');
    Route::get('settings/profile', [\App\Http\Controllers\Settings\AuthorController::class, 'edit'])->name('settings.profile');
    Route::patch('settings/profile', [\App\Http\Controllers\Settings\AuthorController::class, 'update']);
    Route::get('settings/account', [\App\Http\Controllers\Settings\AccountController::class, 'edit'])->name('settings.account');
    Route::patch('settings/account', [\App\Http\Controllers\Settings\AccountController::class, 'update']);
    Route::delete('settings/account', [\App\Http\Controllers\Settings\AccountController::class, 'destroy']);

    Route::get('settings/defaults', [\App\Http\Controllers\Settings\DefaultsController::class, 'edit'])->name('settings.defaults');
    Route::patch('settings/defaults', [\App\Http\Controllers\Settings\DefaultsController::class, 'update']);

    Route::get('settings/github', [\App\Http\Controllers\Settings\GitHubController::class, 'show'])->name('settings.github');
    Route::delete('settings/github', [\App\Http\Controllers\Auth\GitHubController::class, 'destroy']);

    Route::get('settings/passkeys', [\App\Http\Controllers\Settings\PasskeysController::class, 'index'])->name('settings.passkeys');
    Route::post('settings/passkeys/options', [\App\Http\Controllers\Settings\PasskeysController::class, 'options']);
    Route::post('settings/passkeys', [\App\Http\Controllers\Settings\PasskeysController::class, 'store']);
    Route::delete('settings/passkeys/{passkeyId}', [\App\Http\Controllers\Settings\PasskeysController::class, 'destroy']);

    // This server's own credentials (GitHub App, storage, mail)
    Route::middleware('admin')->group(function () {
        Route::get('admin/setup', [\App\Http\Controllers\Admin\SetupController::class, 'show'])->name('admin.setup');
        Route::get('admin/setup/github/callback', [\App\Http\Controllers\Admin\SetupController::class, 'githubCallback'])->name('admin.setup.github.callback');
        Route::post('admin/setup/storage/test', [\App\Http\Controllers\Admin\SetupController::class, 'testStorage'])->middleware('throttle:10,1');
        Route::post('admin/setup/mail/test', [\App\Http\Controllers\Admin\SetupController::class, 'testMail'])->middleware('throttle:5,1');
        Route::put('admin/setup/{group}', [\App\Http\Controllers\Admin\SetupController::class, 'update']);
        Route::delete('admin/setup/{group}', [\App\Http\Controllers\Admin\SetupController::class, 'destroy']);
    });
});

require __DIR__.'/auth.php';
