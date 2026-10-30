<?php

use App\Http\Controllers\Admin\SetupController;
use App\Http\Controllers\App\BlueprintsController;
use App\Http\Controllers\App\DeviceController;
use App\Http\Controllers\App\DocumentsController;
use App\Http\Controllers\App\FacetsController;
use App\Http\Controllers\App\GitHubLibraryController;
use App\Http\Controllers\App\ProjectsController;
use App\Http\Controllers\App\SkillsController;
use App\Http\Controllers\App\TokensController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\DocumentViewController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Settings\AuthorController;
use App\Http\Controllers\Settings\DefaultsController;
use App\Http\Controllers\Settings\GitHubController;
use App\Http\Controllers\Settings\PasskeysController;
use App\Models\User;
use App\Services\GitHub\Scaffolder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// r.framework.pub — the R package docs site, domain-routed on the same app.
// Domain groups must precede host-less routes: a host-less route matches any host.
Route::domain(config('framework.docs_host'))->name('docs.')->group(function () {
    Route::get('/', [DocsController::class, 'home'])->name('home');
    Route::get('/guides/{slug}', [DocsController::class, 'guide'])->name('guide');
    Route::get('/reference', [DocsController::class, 'reference'])->name('reference');
    Route::get('/reference/{name}', [DocsController::class, 'fn'])->name('function');
    Route::get('/search', [DocsController::class, 'search'])->name('search');
});

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('self-hosting', 'self-hosting')->name('self-hosting');

// Framework Cloud guide, rendered from docs/guide
Route::get('docs/{page?}', [GuideController::class, 'show'])
    ->where('page', '[a-z0-9-]+')
    ->name('guide');

// Public profiles: /@{handle} (opt-in; 404 unless profile_public with a handle)
Route::get('/@{handle}', function (string $handle) {
    $user = User::where('handle', $handle)->where('profile_public', true)->firstOrFail();

    $affiliation = Arr::get(
        $user->globalSettings()->document, 'author.affiliation', '',
    );

    return view('profile', ['user' => $user, 'affiliation' => $affiliation]);
})->where('handle', '[a-z0-9][a-z0-9-]*')->name('profile');

Route::view('terms', 'legal.terms')->name('terms');
Route::view('privacy', 'legal.privacy')->name('privacy');

// Published documents. Private links use the key; versions are kept until
// their owner deletes them.
Route::get('d/{key}', [DocumentViewController::class, 'show'])->name('documents.show');
Route::get('d/{key}/raw', [DocumentViewController::class, 'raw'])->name('documents.raw');
Route::get('d/{key}/versions/{number}', [DocumentViewController::class, 'showVersion'])
    ->whereNumber('number')->name('documents.version');
Route::get('d/{key}/versions/{number}/raw', [DocumentViewController::class, 'versionRaw'])
    ->whereNumber('number')->name('documents.version.raw');
Route::get('d/{key}/versions/{number}/{path}', [DocumentViewController::class, 'versionAsset'])
    ->whereNumber('number')->where('path', '.*')->name('documents.version.asset');
Route::get('d/{key}/{path}', [DocumentViewController::class, 'asset'])
    ->where('path', '.*')->name('documents.asset');

// Readable links for public documents: /@{handle}/{project}/{slug}
Route::get('/@{handle}/{project}/{slug}', [DocumentViewController::class, 'readable'])
    ->where(['handle' => '[a-z0-9][a-z0-9-]*', 'project' => '[A-Za-z0-9_-]+', 'slug' => '[A-Za-z0-9_-]+'])
    ->name('documents.readable');
Route::get('/@{handle}/{project}/{slug}/versions/{number}', [DocumentViewController::class, 'readableVersion'])
    ->where(['handle' => '[a-z0-9][a-z0-9-]*', 'project' => '[A-Za-z0-9_-]+', 'slug' => '[A-Za-z0-9_-]+'])
    ->whereNumber('number')->name('documents.readable.version');

// The app (Inertia + Vue)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = request()->user();

        $github = Scaffolder::enabled();

        return Inertia::render('Dashboard', [
            'counts' => [
                'projects' => $user->projects()->count(),
                'tokens' => $user->tokens()->count(),
                'settings_revision' => $user->globalSettings()->revision,
            ],
            // Getting started; the dashboard hides it once everything is done
            'steps' => array_values(array_filter([
                ['key' => 'profile', 'done' => filled(Arr::get($user->globalSettings()->document, 'author.affiliations'))
                    || filled(Arr::get($user->globalSettings()->document, 'author.affiliation'))],
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

    Route::get('projects', [ProjectsController::class, 'index'])->name('projects');
    Route::get('published', [DocumentsController::class, 'index'])->name('published');
    Route::delete('published/{document}', [DocumentsController::class, 'destroy']);
    Route::patch('published/{document}', [DocumentsController::class, 'update']);
    Route::delete('published/{document}/versions/{number}', [DocumentsController::class, 'destroyVersion'])->whereNumber('number');
    Route::get('projects/create', [ProjectsController::class, 'create'])->name('projects.create');
    Route::post('projects', [ProjectsController::class, 'store']);
    Route::post('projects/{project}/token', [ProjectsController::class, 'issueToken']);
    Route::delete('projects/{project}/tokens/{token}', [ProjectsController::class, 'revokeToken']);
    Route::delete('projects/{project}', [ProjectsController::class, 'destroy']);
    Route::post('projects/{project}/scaffold/refresh', [ProjectsController::class, 'refreshScaffold']);
    Route::post('projects/{project}/scaffold/retry', [ProjectsController::class, 'retryScaffold']);

    // The shared library: blueprints compose facets, facets carry skills
    foreach ([
        'blueprints' => BlueprintsController::class,
        'facets' => FacetsController::class,
        'skills' => SkillsController::class,
    ] as $name => $controller) {
        Route::post("{$name}/{".str($name)->singular().'}/fork', [$controller, 'fork'])->name("{$name}.fork");
    }
    Route::post('skills/import', [GitHubLibraryController::class, 'importSkill'])->middleware('throttle:20,1');
    Route::post('facets/import', [GitHubLibraryController::class, 'importFacet'])->middleware('throttle:20,1');
    Route::post('skills/{skill}/publish', [GitHubLibraryController::class, 'publishSkill'])->middleware('throttle:20,1');
    Route::post('facets/{facet}/publish', [GitHubLibraryController::class, 'publishFacet'])->middleware('throttle:20,1');
    Route::resource('blueprints', BlueprintsController::class);
    Route::resource('facets', FacetsController::class)->except('show');
    Route::resource('skills', SkillsController::class)->except('show');

    Route::get('tokens', [TokensController::class, 'index'])->name('tokens');
    Route::post('tokens', [TokensController::class, 'store']);
    Route::delete('tokens/{tokenId}', [TokensController::class, 'destroy']);

    // fw login approves here; see routes/api.php for the CLI half
    Route::get('device', [DeviceController::class, 'show'])->middleware('throttle:30,1')->name('device');
    Route::post('device/approve', [DeviceController::class, 'approve'])->middleware('throttle:30,1');
    Route::post('device/deny', [DeviceController::class, 'deny'])->middleware('throttle:30,1');

    Route::redirect('settings', 'settings/profile');
    Route::get('settings/profile', [AuthorController::class, 'edit'])->name('settings.profile');
    Route::patch('settings/profile', [AuthorController::class, 'update']);
    Route::get('settings/account', [AccountController::class, 'edit'])->name('settings.account');
    Route::patch('settings/account', [AccountController::class, 'update']);
    Route::delete('settings/account', [AccountController::class, 'destroy']);

    Route::get('settings/defaults', [DefaultsController::class, 'edit'])->name('settings.defaults');
    Route::patch('settings/defaults', [DefaultsController::class, 'update']);

    Route::get('settings/github', [GitHubController::class, 'show'])->name('settings.github');
    Route::delete('settings/github', [App\Http\Controllers\Auth\GitHubController::class, 'destroy']);

    Route::get('settings/passkeys', [PasskeysController::class, 'index'])->name('settings.passkeys');
    Route::post('settings/passkeys/options', [PasskeysController::class, 'options']);
    Route::post('settings/passkeys', [PasskeysController::class, 'store']);
    Route::delete('settings/passkeys/{passkeyId}', [PasskeysController::class, 'destroy']);

    // This server's own credentials (GitHub App, storage, mail)
    Route::middleware('admin')->group(function () {
        Route::get('admin/setup', [SetupController::class, 'show'])->name('admin.setup');
        Route::get('admin/setup/github/callback', [SetupController::class, 'githubCallback'])->name('admin.setup.github.callback');
        Route::post('admin/setup/storage/test', [SetupController::class, 'testStorage'])->middleware('throttle:10,1');
        Route::post('admin/setup/mail/test', [SetupController::class, 'testMail'])->middleware('throttle:5,1');
        Route::put('admin/setup/{group}', [SetupController::class, 'update']);
        Route::delete('admin/setup/{group}', [SetupController::class, 'destroy']);
    });
});

require __DIR__.'/auth.php';
