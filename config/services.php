<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // The Framework GitHub App. Its OAuth client signs users in and acts on
    // their behalf (user tokens), limited to the accounts where it is installed.
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', '/auth/github/callback'),
        'app_id' => env('GITHUB_APP_ID'),
        'app_slug' => env('GITHUB_APP_SLUG'),
        'private_key' => env('GITHUB_APP_PRIVATE_KEY'),
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
        // The framework package commit the scaffold workflow installs. A SHA,
        // never a branch, so a repo's scaffold is reproducible. Bump it here
        // deliberately; the env var overrides it, and an empty one turns
        // GitHub scaffolding off.
        'framework_sha' => env('GITHUB_FRAMEWORK_SHA', 'c74051f2f75a5b20f41a2281bb548ff57310cf58'),
        // The fw release that scaffolds Stata projects. Empty turns Stata
        // scaffolding on GitHub off. The workflow checks the download against
        // fw_sha256 when it is set, and otherwise against the release's
        // checksums file. The default checksum applies only to the default
        // release.
        'fw_version' => env('GITHUB_FW_VERSION', '0.2.0'),
        'fw_sha256' => env('GITHUB_FW_SHA256', env('GITHUB_FW_VERSION', '0.2.0') === '0.2.0'
            ? '43a30b0658d025a6ff2ebfdd2b69da1bd5a6a0aa38328cce3cd4697c9dd806d4'
            : null),
    ],

];
