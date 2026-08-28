<?php

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
    | Hostname the R package docs site answers on (Route::domain group).
    | Herd resolves any subdomain of a linked site, so r.framework.test
    | works locally with zero setup; production is r.framework.pub
    | (CNAME r -> framework.pub + cert coverage).
    */
    'docs_host' => env('FW_DOCS_HOST', 'r.framework.test'),

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
];
