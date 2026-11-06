<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy — Framework Cloud</title>
    <x-share-meta title="Privacy — Framework Cloud" />
    <x-theme-script />
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <main class="mx-auto max-w-2xl px-6 py-16">
        <div class="flex items-center justify-between">
            <a href="/" class="text-sm text-sky-600 hover:underline dark:text-sky-400">&larr; Framework Cloud</a>
            <x-theme-toggle />
        </div>
        <h1 class="mt-6 text-3xl font-semibold tracking-tight">Privacy</h1>
        <p class="mt-2 text-sm text-slate-500">Draft — last updated {{ date('F j, Y') }}. Plain-language summary; formal review before launch.</p>

        <div class="prose prose-neutral mt-8 dark:prose-invert">
            <h2>Stored data</h2>
            <p>Your name, email, settings document (with revision history), project
            definitions, hashed authentication tokens and passkey public keys, and the
            documents you publish. Settings pushes record which device sent them.</p>

            <h2>Credential protection</h2>
            <p>Passwords and tokens are stored as hashes. The server-side deny-list
            removes secret-shaped keys from synced settings.</p>

            <h2>Analytics</h2>
            <p>Published artifacts count views. We use no third-party trackers or advertising technology.</p>

            <h2>Sharing</h2>
            <p>You choose each artifact’s visibility. Unlisted artifacts use
            unguessable links. Public artifacts can also use readable links. We do not sell or share
            your data with anyone.</p>

            <h2>Deletion</h2>
            <p>Deleting your account deletes your settings, projects, tokens, and
            published documents.</p>
        </div>
    </main>
</body>
</html>
