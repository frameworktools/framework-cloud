<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>framework.pub</title>
    <meta name="description" content="The cloud companion to the Framework R package: settings sync, project definitions, and publishing for rendered notebooks.">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🔬</text></svg>">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100">

    <header class="mx-auto flex max-w-4xl items-center justify-between px-6 py-6">
        <div class="font-semibold tracking-tight">framework<span class="text-orange-500">.pub</span></div>
        <nav class="flex items-center gap-4 text-sm">
            <a href="https://framework.table1.org" class="text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100">Docs</a>
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-md bg-orange-500 px-4 py-2 font-medium text-white hover:bg-orange-600">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100">Sign in</a>
                <a href="{{ route('register') }}" class="rounded-md bg-orange-500 px-4 py-2 font-medium text-white hover:bg-orange-600">Sign up</a>
            @endauth
        </nav>
    </header>

    <main class="mx-auto max-w-4xl px-6">

        <section class="py-16 sm:py-24">
            <h1 class="max-w-2xl text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                The cloud companion to the
                <a href="https://framework.table1.org" class="text-orange-500 hover:underline">Framework</a>
                R package.
            </h1>
            <p class="mt-5 max-w-2xl text-lg text-neutral-600 dark:text-neutral-400">
                Framework structures data-science projects in R. framework.pub keeps your
                settings with you across machines, lets you hand a project to any computer
                with one token, and gives rendered notebooks a clean URL. Free for settings
                and project definitions; storage tiers later.
            </p>

            <div class="mt-10 max-w-2xl overflow-x-auto rounded-lg bg-neutral-900 p-5 font-mono text-sm text-neutral-100 dark:ring-1 dark:ring-neutral-700">
                <div><span class="text-neutral-500"># connect this machine (token from your account page)</span></div>
                <div>framework::cloud_login(<span class="text-orange-400">"fw_..."</span>)</div>
                <div class="mt-3"><span class="text-neutral-500"># and go</span></div>
                <div>framework::new(<span class="text-orange-400">"my-study"</span>)</div>
            </div>

            <div class="mt-8 flex items-center gap-4">
                <a href="{{ route('register') }}" class="rounded-md bg-orange-500 px-5 py-2.5 font-medium text-white hover:bg-orange-600">Create an account</a>
                <span class="text-sm text-neutral-500 dark:text-neutral-400">Passkeys and email sign-in — no password to manage.</span>
            </div>
        </section>

        <section class="grid gap-x-12 gap-y-10 border-t border-neutral-200 py-16 sm:grid-cols-2 dark:border-neutral-800">
            <div>
                <h2 class="font-semibold">Settings that follow you</h2>
                <p class="mt-2 text-neutral-600 dark:text-neutral-400">
                    Author info, project defaults, and AI-context preferences live in your
                    account. <code class="font-mono text-sm">cloud_sync()</code> keeps every
                    machine current, and <code class="font-mono text-sm">new()</code> uses
                    them automatically — so you set things up once and stop thinking about it.
                </p>
            </div>
            <div>
                <h2 class="font-semibold">Projects by token</h2>
                <p class="mt-2 text-neutral-600 dark:text-neutral-400">
                    Define a project here, or let <code class="font-mono text-sm">new()</code>
                    register it for you. <code class="font-mono text-sm">setup("&lt;token&gt;")</code>
                    then recreates it on any machine — yours, a collaborator's, a lab
                    workstation.
                </p>
            </div>
            <div>
                <h2 class="font-semibold">Structure when you want it</h2>
                <p class="mt-2 text-neutral-600 dark:text-neutral-400">
                    Blueprints are optional, editable project structures — full analysis
                    projects, courses, presentations. The default is bare: your own
                    conventions, with Framework staying out of the way.
                </p>
            </div>
            <div>
                <h2 class="font-semibold">Publish rendered work</h2>
                <p class="mt-2 text-neutral-600 dark:text-neutral-400">
                    <code class="font-mono text-sm">publish("analysis.qmd")</code> renders and
                    uploads to a stable URL you can share or embed — with the raw HTML one
                    click away. Data integrity records, so collaborators can verify files
                    match what you analyzed, are on the way.
                </p>
            </div>
        </section>

    </main>

    <footer class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-4 border-t border-neutral-200 px-6 py-8 text-sm text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
        <div>framework.pub</div>
        <nav class="flex gap-5">
            <a href="{{ route('terms') }}" class="hover:text-neutral-900 dark:hover:text-neutral-100">Terms</a>
            <a href="{{ route('privacy') }}" class="hover:text-neutral-900 dark:hover:text-neutral-100">Privacy</a>
            <a href="https://framework.table1.org" class="hover:text-neutral-900 dark:hover:text-neutral-100">Documentation</a>
            <a href="https://github.com/table1/framework" class="hover:text-neutral-900 dark:hover:text-neutral-100">GitHub</a>
        </nav>
    </footer>

</body>
</html>
