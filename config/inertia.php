<?php

// Only what differs from inertia-laravel's defaults; the package fills in the rest.
return [

    // The page components live in resources/js/Pages. The package looks in
    // js/pages, which matches only on a case-insensitive disk such as macOS,
    // so tests that check a page exists failed on Linux.
    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [
            resource_path('js/Pages'),
        ],
        'extensions' => ['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue'],
    ],

];
