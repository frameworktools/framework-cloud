<?php

use App\Http\Controllers\Admin\SetupController;
use App\Http\Controllers\App\AgentsController;
use App\Http\Controllers\App\BlueprintsController;
use App\Http\Controllers\App\CollectionsController;
use App\Http\Controllers\App\DeviceController;
use App\Http\Controllers\App\DocumentsController;
use App\Http\Controllers\App\FacetsController;
use App\Http\Controllers\App\GitHubLibraryController;
use App\Http\Controllers\App\ProjectsController;
use App\Http\Controllers\App\ReportsController;
use App\Http\Controllers\App\SkillsController;
use App\Http\Controllers\App\SkillTagsController;
use App\Http\Controllers\App\TokensController;
use App\Http\Controllers\App\TrashController;
use App\Http\Controllers\App\WelcomeController;
use App\Http\Controllers\Auth\PasskeyPromptController;
use App\Http\Controllers\CollectionViewController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\DocumentViewController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\ReportViewController;
use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Settings\AuthorController;
use App\Http\Controllers\Settings\DefaultsController;
use App\Http\Controllers\Settings\GitHubController;
use App\Http\Controllers\Settings\PackageSearchController;
use App\Http\Controllers\Settings\PasskeysController;
use App\Http\Middleware\EnsureAgentsEnabled;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// r., py., cli., and stata.framework.pub: the docs sites, domain-routed on the same app.
// Domain groups must precede host-less routes: a host-less route matches any host.
foreach (array_unique(array_filter(config('framework.docs_hosts', []))) as $docsProduct => $docsHost) {
    Route::domain($docsHost)->name("docs.{$docsProduct}.")->group(function () use ($docsProduct) {
        Route::get('/', [DocsController::class, 'home'])->defaults('product', $docsProduct)->name('home');
        Route::get('/guides/{id}', [DocsController::class, 'guide'])->defaults('product', $docsProduct)->name('guide');
        Route::get('/reference', [DocsController::class, 'reference'])->defaults('product', $docsProduct)->name('reference');
        Route::get('/reference/{id}', [DocsController::class, 'entry'])->defaults('product', $docsProduct)->name('entry');
        Route::get('/search', [DocsController::class, 'search'])->defaults('product', $docsProduct)->name('search');
        Route::get('/search.json', [DocsController::class, 'searchIndex'])->defaults('product', $docsProduct)->name('search-index');
        Route::get('/images/{path}', [DocsController::class, 'image'])->defaults('product', $docsProduct)
            ->where('path', '[A-Za-z0-9_./-]+')->name('image');
    });
}

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('self-hosting', 'self-hosting')->name('self-hosting');

// The approval page for a computer's sign-in request. Signed-out visitors see
// the request and a way to sign in or create an account; approving needs an
// account (routes in the auth group).
Route::get('device', [DeviceController::class, 'show'])->middleware('throttle:30,1')->name('device');

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

    // Public work only: public artifacts without a password, newest first
    $artifacts = Document::whereHas('project', fn ($q) => $q->where('user_id', $user->id))
        ->where('visibility', 'public')->whereNull('password_hash')->whereNull('merged_into_id')
        ->where(fn ($q) => $q->whereNull('driver')->orWhere('driver', 'cloud'))
        ->with('project.user')->latest('updated_at')->limit(50)->get();

    return view('profile', ['user' => $user, 'affiliation' => $affiliation, 'artifacts' => $artifacts]);
})->where('handle', '[a-z0-9][a-z0-9-]*')->name('profile');

Route::view('terms', 'legal.terms')->name('terms');
Route::view('privacy', 'legal.privacy')->name('privacy');

// Published documents. Private links use the key; versions are kept until
// their owner deletes them.
Route::get('d/{key}', [DocumentViewController::class, 'show'])->name('documents.show');
Route::post('d/{key}/unlock', [DocumentViewController::class, 'unlock'])->middleware('throttle:10,1')->name('documents.unlock');
Route::get('d/{key}/download', [DocumentViewController::class, 'download'])->name('documents.download');
Route::get('d/{key}/raw', [DocumentViewController::class, 'raw'])->name('documents.raw');
Route::get('d/{key}/versions/{number}', [DocumentViewController::class, 'showVersion'])
    ->whereNumber('number')->name('documents.version');
Route::get('d/{key}/versions/{number}/raw', [DocumentViewController::class, 'versionRaw'])
    ->whereNumber('number')->name('documents.version.raw');
Route::get('d/{key}/versions/{number}/download', [DocumentViewController::class, 'versionDownload'])
    ->whereNumber('number')->name('documents.version.download');
Route::get('d/{key}/content/{number}/{access}/{path?}', [DocumentViewController::class, 'content'])
    ->whereNumber('number')->where('path', '.*')->name('documents.content');
Route::get('d/{key}/chart/{number}/{access}', [DocumentViewController::class, 'chart'])
    ->whereNumber('number')->name('documents.chart');
Route::get('d/{key}/versions/{number}/{path}', [DocumentViewController::class, 'versionAsset'])
    ->whereNumber('number')->where('path', '.*')->name('documents.version.asset');
Route::get('d/{key}/{path}', [DocumentViewController::class, 'asset'])
    ->where('path', '.*')->name('documents.asset');

Route::get('c/{key}', [CollectionViewController::class, 'show'])->name('collections.show');
Route::post('c/{key}/unlock', [CollectionViewController::class, 'unlock'])->middleware('throttle:10,1')->name('collections.unlock');
Route::get('r/{key}', [ReportViewController::class, 'show'])->name('reports.show');
Route::get('r/{key}/versions/{number}', [ReportViewController::class, 'showVersion'])->whereNumber('number')->name('reports.version');
Route::post('r/{key}/unlock', [ReportViewController::class, 'unlock'])->middleware('throttle:10,1')->name('reports.unlock');

// Readable links for public documents: /@{handle}/{project}/{slug}
Route::get('/@{handle}/{project}/{slug}', [DocumentViewController::class, 'readable'])
    ->where(['handle' => '[a-z0-9][a-z0-9-]*', 'project' => '[A-Za-z0-9_-]+', 'slug' => '[A-Za-z0-9_-]+'])
    ->name('documents.readable');
Route::get('/@{handle}/{project}/{slug}/versions/{number}', [DocumentViewController::class, 'readableVersion'])
    ->where(['handle' => '[a-z0-9][a-z0-9-]*', 'project' => '[A-Za-z0-9_-]+', 'slug' => '[A-Za-z0-9_-]+'])
    ->whereNumber('number')->name('documents.readable.version');

// The app (Inertia + Vue)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [ProjectsController::class, 'dashboard'])->name('dashboard');

    Route::get('welcome', [WelcomeController::class, 'show'])->name('welcome');
    Route::post('welcome', [WelcomeController::class, 'store']);
    Route::post('welcome/skip', [WelcomeController::class, 'skip'])->name('welcome.skip');
    Route::get('projects', [ProjectsController::class, 'index'])->name('projects');
    Route::get('published', [DocumentsController::class, 'index'])->name('published');
    Route::get('artifacts/{document}', [DocumentsController::class, 'show'])->whereNumber('document')->name('artifacts.show');
    Route::post('artifacts/{document}/projects', [DocumentsController::class, 'link'])->whereNumber('document');
    Route::delete('artifacts/{document}/projects/{project}', [DocumentsController::class, 'unlink'])->whereNumber(['document', 'project']);
    Route::delete('published/{document}', [DocumentsController::class, 'destroy']);
    Route::patch('published/{document}', [DocumentsController::class, 'update']);
    Route::post('published/{document}/merge', [DocumentsController::class, 'merge']);
    Route::delete('published/{document}/versions/{number}', [DocumentsController::class, 'destroyVersion'])->whereNumber('number');
    Route::post('projects/{project}/artifacts', [DocumentsController::class, 'store'])->whereNumber('project')->middleware('throttle:20,1');
    Route::get('collections', [CollectionsController::class, 'index'])->name('collections.all');
    Route::get('reports', [ReportsController::class, 'index'])->name('reports.all');
    Route::get('projects/{project}/collections', [CollectionsController::class, 'index'])->whereNumber('project')->name('collections.index');
    // The same lists at the project's slug, as its other tabs use
    Route::get('projects/{slug}/collections', [CollectionsController::class, 'indexForSlug'])->where('slug', '[A-Za-z0-9_-]*[A-Za-z_-][A-Za-z0-9_-]*');
    Route::get('projects/{slug}/reports', [ReportsController::class, 'indexForSlug'])->where('slug', '[A-Za-z0-9_-]*[A-Za-z_-][A-Za-z0-9_-]*');
    Route::post('projects/{project}/collections', [CollectionsController::class, 'store'])->whereNumber('project')->name('collections.store');
    Route::get('collections/{collection}/edit', [CollectionsController::class, 'edit'])->name('collections.edit');
    Route::patch('collections/{collection}', [CollectionsController::class, 'update'])->name('collections.update');
    Route::delete('collections/{collection}', [CollectionsController::class, 'destroy'])->name('collections.destroy');
    Route::post('collections/{collection}/items', [CollectionsController::class, 'storeItem'])->name('collections.items.store');
    Route::patch('collections/{collection}/items/{item}', [CollectionsController::class, 'updateItem'])->name('collections.items.update');
    Route::delete('collections/{collection}/items/{item}', [CollectionsController::class, 'destroyItem'])->name('collections.items.destroy');
    Route::put('collections/{collection}/order', [CollectionsController::class, 'reorder'])->name('collections.order');
    Route::get('projects/{project}/reports', [ReportsController::class, 'index'])->whereNumber('project')->name('reports.index');
    Route::post('projects/{project}/reports', [ReportsController::class, 'store'])->whereNumber('project')->name('reports.store');
    Route::get('reports/{report}/edit', [ReportsController::class, 'edit'])->name('reports.edit');
    Route::get('reports/{report}/preview', [ReportViewController::class, 'preview'])->name('reports.preview');
    Route::patch('reports/{report}', [ReportsController::class, 'update'])->name('reports.update');
    Route::post('reports/{report}/publish', [ReportsController::class, 'publish'])->name('reports.publish');
    Route::delete('reports/{report}', [ReportsController::class, 'destroy'])->name('reports.destroy');
    Route::get('projects/create', [ProjectsController::class, 'create'])->name('projects.create');
    Route::post('projects', [ProjectsController::class, 'store']);
    Route::post('projects/preview', [ProjectsController::class, 'preview'])->name('projects.preview');
    Route::get('projects/{slug}', [ProjectsController::class, 'show'])->where('slug', '[A-Za-z0-9_-]+')->name('projects.show');
    Route::get('projects/{slug}/{section}', [ProjectsController::class, 'show'])->where('slug', '[A-Za-z0-9_-]+')
        ->whereIn('section', array_slice(ProjectsController::SECTIONS, 1))->name('projects.section');
    Route::get('projects/{project}/download', [ProjectsController::class, 'download'])->whereNumber('project')->middleware('throttle:10,1')->name('projects.download');
    Route::post('projects/{project}/token', [ProjectsController::class, 'issueToken']);
    Route::delete('projects/{project}/tokens/{token}', [ProjectsController::class, 'revokeToken']);
    Route::delete('projects/{project}', [ProjectsController::class, 'destroy']);
    Route::post('projects/{project}/scaffold/refresh', [ProjectsController::class, 'refreshScaffold']);
    Route::post('projects/{project}/scaffold/retry', [ProjectsController::class, 'retryScaffold']);
    Route::post('projects/{project}/pages', [ProjectsController::class, 'enablePages'])->middleware('throttle:10,1');
    Route::delete('projects/{project}/pages', [ProjectsController::class, 'disablePages'])->middleware('throttle:10,1');

    // Blueprints stack a project structure with tools, package sets, and skill sets
    foreach (FacetsController::PATHS as $kind => $path) {
        Route::get($path, [FacetsController::class, 'index'])->defaults('kind', $kind)->name("{$path}.index");
        Route::get("{$path}/create", [FacetsController::class, 'create'])->defaults('kind', $kind)->name("{$path}.create");
        Route::post($path, [FacetsController::class, 'store'])->defaults('kind', $kind)->name("{$path}.store");
        Route::get("{$path}/{facet}/edit", [FacetsController::class, 'edit'])->defaults('kind', $kind)->name("{$path}.edit");
        Route::put("{$path}/{facet}", [FacetsController::class, 'update'])->defaults('kind', $kind)->name("{$path}.update");
        Route::delete("{$path}/{facet}", [FacetsController::class, 'destroy'])->defaults('kind', $kind)->name("{$path}.destroy");
        Route::post("{$path}/{facet}/fork", [FacetsController::class, 'fork'])->defaults('kind', $kind)->name("{$path}.fork");
    }
    Route::redirect('facets', 'structures');
    Route::post('package-sets/{facet}/defaults', [FacetsController::class, 'addToDefaults'])->name('package-sets.defaults');

    foreach ([
        'blueprints' => BlueprintsController::class,
        'skills' => SkillsController::class,
    ] as $name => $controller) {
        Route::post("{$name}/{".str($name)->singular().'}/fork', [$controller, 'fork'])->name("{$name}.fork");
    }
    Route::post('skills/import', [GitHubLibraryController::class, 'importSkill'])->middleware('throttle:20,1');
    Route::post('facets/import', [GitHubLibraryController::class, 'importFacet'])->middleware('throttle:20,1');
    Route::post('skills/{skill}/publish', [GitHubLibraryController::class, 'publishSkill'])->middleware('throttle:20,1');
    Route::post('facets/{facet}/publish', [GitHubLibraryController::class, 'publishFacet'])->middleware('throttle:20,1');
    Route::post('blueprints/preview', [BlueprintsController::class, 'preview'])->name('blueprints.preview');
    Route::post('blueprints/{blueprint}/preview', [BlueprintsController::class, 'previewBlueprint'])->middleware('throttle:120,1')->name('blueprints.preview-one');
    Route::post('blueprints/{blueprint}/default', [BlueprintsController::class, 'makeDefault'])->name('blueprints.default');
    Route::resource('blueprints', BlueprintsController::class);
    Route::post('skills/{skill}/restore', [SkillsController::class, 'restore']);
    Route::resource('skills', SkillsController::class);

    // Deleted projects, reports, and skills, until restored
    Route::get('trash', [TrashController::class, 'index'])->name('trash');
    Route::post('trash/{type}/{id}/restore', [TrashController::class, 'restore'])->whereIn('type', ['project', 'artifact', 'version', 'report', 'skill'])->whereNumber('id');
    Route::delete('trash/{type}/{id}', [TrashController::class, 'destroy'])->whereIn('type', ['artifact', 'version'])->whereNumber('id');

    Route::get('tokens', [TokensController::class, 'index'])->name('tokens');
    Route::post('tokens', [TokensController::class, 'store']);
    Route::delete('tokens/{tokenId}', [TokensController::class, 'destroy']);

    // AI agents connected over MCP; the server and its OAuth sign-in are in routes/ai.php
    Route::middleware(EnsureAgentsEnabled::class)->group(function () {
        Route::get('agents', [AgentsController::class, 'index'])->name('agents');
        Route::get('agents/history', [AgentsController::class, 'history'])->name('agents.history');
        Route::delete('agents/{connection}', [AgentsController::class, 'destroy'])->whereNumber('connection');
        Route::patch('agents/apps/{app}', [AgentsController::class, 'updateApp'])->whereNumber('app');
        Route::delete('agents/apps/{app}', [AgentsController::class, 'forgetApp'])->whereNumber('app');
    });

    // fw login, fw_login, and cloud_login() approve here; see routes/api.php
    // for the computer's half. The page itself is public (below).
    Route::post('device/approve', [DeviceController::class, 'approve'])->middleware('throttle:30,1');
    Route::post('device/deny', [DeviceController::class, 'deny'])->middleware('throttle:30,1');

    // Your account: who you are, how you sign in, and what's connected
    Route::redirect('account', 'account/profile');
    Route::get('account/profile', [AuthorController::class, 'edit'])->name('account.profile');
    Route::patch('account/profile', [AuthorController::class, 'update']);
    Route::get('account/account', [AccountController::class, 'edit'])->name('account.account');
    Route::patch('account/account', [AccountController::class, 'update']);
    Route::delete('account/account', [AccountController::class, 'destroy']);

    Route::get('account/github', [GitHubController::class, 'show'])->name('account.github');
    Route::delete('account/github', [App\Http\Controllers\Auth\GitHubController::class, 'destroy']);

    Route::get('account/security', [PasskeysController::class, 'index'])->name('account.security');
    Route::redirect('account/passkeys', '/account/security');
    Route::post('account/passkeys/options', [PasskeysController::class, 'options']);
    Route::post('account/passkeys', [PasskeysController::class, 'store']);
    Route::delete('account/passkeys/{passkeyId}', [PasskeysController::class, 'destroy']);

    // Offered once after an email link or password sign-in
    Route::get('welcome/passkey', [PasskeyPromptController::class, 'show'])->name('passkey.prompt');
    Route::post('welcome/passkey/skip', [PasskeyPromptController::class, 'skip']);

    // Your defaults for new projects: one form, shown a section at a time.
    // The account pages used to live here.
    Route::redirect('settings', '/settings/language');
    Route::redirect('settings/defaults', '/settings/language');
    // Blueprint & editor were one page
    Route::redirect('settings/basics', '/settings/blueprint');
    // Blueprints decide whether a project is sensitive
    Route::redirect('settings/data', '/settings/hooks');
    foreach (['profile', 'account', 'github', 'passkeys'] as $moved) {
        Route::redirect("settings/{$moved}", $moved === 'passkeys' ? '/account/security' : "/account/{$moved}");
    }
    Route::get('settings/{section}', [DefaultsController::class, 'edit'])
        ->whereIn('section', DefaultsController::SECTIONS)->name('settings');
    Route::patch('settings', [DefaultsController::class, 'update']);
    // Package names for the package lists
    Route::get('packages/search', PackageSearchController::class)->middleware('throttle:120,1');
    // What a blueprint would create as the default, with the form's unsaved values
    Route::post('settings/blueprint/preview', [DefaultsController::class, 'preview'])->middleware('throttle:120,1');

    // This server's own credentials (GitHub App, storage, mail)
    Route::middleware('admin')->group(function () {
        Route::get('admin/skill-tags', [SkillTagsController::class, 'index']);
        Route::post('admin/skill-tags', [SkillTagsController::class, 'store']);
        Route::put('admin/skill-tags/{tag}', [SkillTagsController::class, 'update']);
        Route::get('admin/setup', [SetupController::class, 'show'])->name('admin.setup');
        Route::get('admin/setup/github/callback', [SetupController::class, 'githubCallback'])->name('admin.setup.github.callback');
        Route::post('admin/setup/storage/test', [SetupController::class, 'testStorage'])->middleware('throttle:10,1');
        Route::post('admin/setup/mail/test', [SetupController::class, 'testMail'])->middleware('throttle:5,1');
        Route::put('admin/setup/{group}', [SetupController::class, 'update']);
        Route::delete('admin/setup/{group}', [SetupController::class, 'destroy']);
    });
});

require __DIR__.'/auth.php';
