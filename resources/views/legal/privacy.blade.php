<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy — framework.pub</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100">
    <main class="mx-auto max-w-2xl px-6 py-16">
        <a href="/" class="text-sm text-orange-600 hover:underline dark:text-orange-400">&larr; framework.pub</a>
        <h1 class="mt-6 text-3xl font-semibold tracking-tight">Privacy</h1>
        <p class="mt-2 text-sm text-neutral-500">Draft — last updated {{ date('F j, Y') }}. Plain-language summary; formal review before launch.</p>

        <div class="prose prose-neutral mt-8 dark:prose-invert">
            <h2>What we store</h2>
            <p>Your name, email, settings document (with revision history), project
            definitions, hashed authentication tokens and passkey public keys, and the
            documents you publish. Settings pushes record which device sent them.</p>

            <h2>What we never store</h2>
            <p>Passwords — there are none. Plain-text tokens — only hashes. The
            server-side deny-list strips secret-shaped keys from synced settings.</p>

            <h2>Analytics</h2>
            <p>Published documents count views. No third-party trackers, no ad tech,
            anywhere.</p>

            <h2>Sharing</h2>
            <p>Nothing is public unless you make it so: published document URLs are
            unguessable capability links you choose to share. We do not sell or share
            your data with anyone.</p>

            <h2>Deletion</h2>
            <p>Deleting your account deletes your settings, projects, tokens, and
            published documents.</p>
        </div>
    </main>
</body>
</html>
