<?php

use App\Support\Credentials;

return [

    /*
    |--------------------------------------------------------------------------
    | Documents storage
    |--------------------------------------------------------------------------
    | Which filesystem disk holds published documents. Local in dev/tests;
    | "r2" in production (bucket framework-pub). Clients never see the
    | difference — the /d/{key} routes are the interface.
    */
    /*
    | open: anyone may register. closed: no new accounts (single-user
    | self-host; `php artisan fw:login-link --create` still works).
    */
    'registration' => env('FW_REGISTRATION', 'open'),

    'documents_disk' => env('FW_DOCUMENTS_DISK', 'local'),

    /*
    | Public base URL for document content (the R2 custom domain, e.g.
    | https://files.framework.pub). When set, the /d/{key} wrapper iframes
    | content from this separate origin (sandboxing uploaded HTML/JS away
    | from app cookies) and the app's raw/asset routes 302 there. When null
    | (dev), content streams through the app from the local disk.
    */
    'documents_base_url' => env('FW_DOCUMENTS_BASE_URL'),

    /*
    | Hostnames of the three docs sites (Route::domain groups), by product:
    | the R package, framework-py, and the fw command line tool. Herd
    | resolves any subdomain of a linked site, so *.framework.test works
    | locally; production is r., py., and cli.framework.pub. FW_DOCS_HOST
    | is the older name for the R host and still works.
    */
    'docs_hosts' => [
        'r' => env('FW_DOCS_HOST_R', env('FW_DOCS_HOST', 'r.framework.test')),
        'py' => env('FW_DOCS_HOST_PY', 'py.framework.test'),
        'cli' => env('FW_DOCS_HOST_CLI', 'cli.framework.test'),
    ],

    /*
    | GitHub repositories whose releases carry each product's docs-site.tar.gz
    | (`php artisan fw:docs-import {product} --release`). prerelease picks
    | which release: "fallback" (the newest stable release with the asset,
    | or the newest pre-release with it when there is no such stable
    | release), "always" (the newest release of either kind), or "never".
    */
    'docs_repositories' => [
        'r' => [
            'repository' => env('FW_DOCS_REPO_R', 'frameworktools/framework'),
            'prerelease' => env('FW_DOCS_PRERELEASE_R', 'fallback'),
        ],
        'py' => [
            'repository' => env('FW_DOCS_REPO_PY', 'frameworktools/framework-py'),
            'prerelease' => env('FW_DOCS_PRERELEASE_PY', 'fallback'),
        ],
        'cli' => [
            'repository' => env('FW_DOCS_REPO_CLI', 'frameworktools/framework-cli'),
            'prerelease' => env('FW_DOCS_PRERELEASE_CLI', 'fallback'),
        ],
    ],

    /*
    | Where imported docs sites live: {docs_path}/{product}/releases/{name}
    | with a "current" pointer file. See App\Support\Docs\Store.
    */
    'docs_path' => env('FW_DOCS_PATH', storage_path('app/docs')),

    /*
    | Handles that can never be claimed: route words, brand words, and
    | anything that would be confusing at /@{handle}.
    */
    'reserved_handles' => [
        'admin', 'api', 'app', 'auth', 'billing', 'blog', 'cloud', 'd', 'dashboard',
        'docs', 'files', 'framework', 'help', 'install', 'l', 'login', 'logout',
        'mail', 'me', 'metrics', 'official', 'privacy', 'projects', 'r', 'register',
        'root', 's', 'search', 'settings', 'staff', 'support', 'system', 'terms',
        'tokens', 'up', 'www',
    ],

    /*
    | Storage hygiene, not a plan limit: how many settings revisions to keep
    | per document (sync needs history; unbounded history is just bloat).
    */
    'setting_revisions_kept' => (int) env('FW_REVISIONS_KEPT', 200),

    /*
    |--------------------------------------------------------------------------
    | Mode
    |--------------------------------------------------------------------------
    |
    | "self" for a self-hosted server: everything is on, and the admin setup
    | page manages this server's credentials. "hosted" for framework.pub,
    | where publishing documents is a paid feature.
    |
    */

    'mode' => env('FW_MODE', 'self') === 'hosted' ? 'hosted' : 'self',

    /*
    | Password sign-in, alongside passkeys and email links. On by default
    | for a self-hosted server, where email may not be set up.
    */

    'passwords' => (bool) env('FW_PASSWORDS', env('FW_MODE', 'self') !== 'hosted'),

    /*
    | Single-user mode signs every request from this computer in as the
    | admin, with no sign-in screen. It applies only to requests that come
    | straight from one of these networks (loopback by default) to a
    | localhost address, and only while APP_URL is a localhost address.
    | See App\Http\Middleware\SingleUserMode.
    */

    'single_user' => (bool) env('FW_SINGLE_USER', false),

    'single_user_networks' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FW_SINGLE_USER_NETWORKS', '127.0.0.1/32,::1/128')),
    ))),

    'admin_email' => env('FW_ADMIN_EMAIL'),

    /*
    | Which credential variables are set, recorded here because env() returns
    | null at runtime once config is cached. See App\Support\Credentials.
    */

    'env_set' => array_values(array_filter(
        Credentials::envVars(),
        fn (string $var) => ! in_array(env($var), [null, ''], true),
    )),
];
