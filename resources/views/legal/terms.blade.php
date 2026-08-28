<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms — framework.pub</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100">
    <main class="mx-auto max-w-2xl px-6 py-16">
        <a href="/" class="text-sm text-orange-600 hover:underline dark:text-orange-400">&larr; framework.pub</a>
        <h1 class="mt-6 text-3xl font-semibold tracking-tight">Terms of Service</h1>
        <p class="mt-2 text-sm text-neutral-500">Draft — last updated {{ date('F j, Y') }}. Plain-language summary; formal review before launch.</p>

        <div class="prose prose-neutral mt-8 dark:prose-invert">
            <h2>The service</h2>
            <p>framework.pub stores your Framework settings, project definitions, and
            published documents, and serves them back to you and to links you share.
            Your local projects are yours: nothing here claims ownership of your code,
            data, or results, and the framework R package works fully without an account.</p>

            <h2>Acceptable use</h2>
            <p><strong>Do not upload protected health information (PHI), personally
            identifiable data, or other regulated data.</strong> framework.pub is not a
            HIPAA business associate and offers no BAA. Publish rendered results that are
            cleared for sharing, the way you would put them on any website.</p>
            <p>No malware, no impersonation, no scraping other users' content, no using
            published documents as a general-purpose file host.</p>

            <h2>Your content</h2>
            <p>You keep all rights to what you publish. You grant us only the license
            needed to store it and serve it at the URLs you create. Delete a project or
            document and its tokens and links stop working.</p>

            <h2>Accounts and tokens</h2>
            <p>You are responsible for the machines that hold your tokens. Revoke tokens
            for lost machines at Settings → API tokens.</p>

            <h2>The free tier</h2>
            <p>Settings, blueprints, and project definitions are free. Fair-use ceilings
            exist to prevent abuse; a human doing research will not hit them.</p>

            <h2>Warranty</h2>
            <p>Provided as-is, without warranty. We work to keep published URLs stable
            and will give notice before any breaking change to them.</p>
        </div>
    </main>
</body>
</html>
