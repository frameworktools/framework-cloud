<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Framework Cloud</title>
    <meta name="description" content="Start, share, and publish research projects in R. Blueprints, GitHub integration, and notebook publishing for the Framework R package. Open source, hosted or self-hosted.">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🔬</text></svg>">
    <x-theme-script />
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100">

    @php
        $features = [
            [
                'heading' => 'Get projects started quickly with blueprints',
                'body' => 'A blueprint sets up a whole project at once: folders, R packages, Quarto templates, and settings. Pick one for an analysis, a course, or a talk, and start working in minutes.',
            ],
            [
                'heading' => 'Borrow and remix other people\'s blueprints',
                'body' => 'Copy a colleague\'s blueprint, change what you need, and share your version with your lab or the community.',
            ],
            [
                'heading' => 'Create projects directly on GitHub',
                'body' => 'With GitHub integration, a new project becomes a repository with its structure already committed. Clone it on any computer and run one command to finish setup.',
            ],
            [
                'heading' => 'Publish notebooks with one command',
                'body' => 'Run publish() in R to render a Quarto notebook and post it at a stable link you can send to collaborators.',
            ],
            [
                'heading' => 'Bring your settings to every computer',
                'body' => 'Your author details, ORCID, affiliations, and project defaults are saved once and used in every new notebook, report, and slide deck.',
            ],
            [
                'heading' => 'Give AI assistants the right context',
                'body' => 'Projects come with instructions and skills for Claude, Copilot, and other assistants, so they follow your project\'s conventions.',
            ],
        ];
    @endphp

    <header class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-6 py-6">
        <div class="font-semibold tracking-tight">Framework <span class="text-orange-500">Cloud</span></div>
        <nav class="flex items-center gap-4 text-sm">
            <x-theme-toggle class="max-sm:hidden" />
            <a href="{{ route('guide') }}" class="text-neutral-600 hover:text-neutral-900 max-sm:hidden dark:text-neutral-400 dark:hover:text-neutral-100">Docs</a>
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-md bg-orange-500 px-4 py-2 font-medium text-white hover:bg-orange-600">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100">Sign in</a>
                <a href="{{ route('register') }}" class="rounded-md bg-orange-500 px-4 py-2 font-medium text-white hover:bg-orange-600">Sign up</a>
            @endauth
        </nav>
    </header>

    <main class="mx-auto max-w-5xl px-6">

        <section class="grid items-center gap-12 py-16 sm:py-24 lg:grid-cols-[1.1fr_1fr]">
            <div>
                <h1 class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                    Start, share, and publish research projects in R.
                </h1>
                <p class="mt-6 text-lg text-neutral-600 dark:text-neutral-400">
                    Framework Cloud works with the
                    <a href="https://framework.table1.org" class="text-orange-600 hover:underline dark:text-orange-400">Framework</a>
                    R package. Set up every project from a blueprint, create it on GitHub,
                    and publish your notebooks to a link you can share.
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a href="{{ route('register') }}" class="rounded-md bg-orange-500 px-5 py-2.5 font-medium text-white hover:bg-orange-600">Create an account</a>
                    <a href="#self-host" class="rounded-md border border-neutral-300 px-5 py-2.5 font-medium text-neutral-800 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-100 dark:hover:bg-neutral-900">Host it yourself</a>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl bg-neutral-900 p-6 font-mono text-sm leading-relaxed text-neutral-100 dark:ring-1 dark:ring-neutral-800">
                <div class="text-neutral-500"># Connect R to your account</div>
                <div>framework::cloud_login(<span class="text-orange-400">"&lt;your token&gt;"</span>)</div>
                <div class="mt-4 text-neutral-500"># Set up a project you created on the site</div>
                <div>framework::setup(<span class="text-orange-400">"&lt;project token&gt;"</span>)</div>
                <div class="mt-4 text-neutral-500"># Share your results</div>
                <div>framework::publish(<span class="text-orange-400">"analysis.qmd"</span>)</div>
            </div>
        </section>

        <section class="border-t border-neutral-200 py-16 sm:py-20 dark:border-neutral-800">
            <h2 class="max-w-xl text-2xl font-semibold tracking-tight">Everything a research project needs on day one</h2>
            <div class="mt-10 grid gap-x-10 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($features as $feature)
                    <div class="border-l-2 border-orange-500 pl-5">
                        <h3 class="font-semibold">{{ $feature['heading'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-neutral-600 dark:text-neutral-400">{{ $feature['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section id="self-host" class="border-t border-neutral-200 py-16 sm:py-20 dark:border-neutral-800">
            <h2 class="max-w-xl text-2xl font-semibold tracking-tight">Open source. Use ours or run your own.</h2>
            <p class="mt-3 max-w-2xl text-neutral-600 dark:text-neutral-400">
                Framework Cloud and the Framework R package are open source. Sign up on
                framework.pub, or run the same server for your lab, department, or institution.
            </p>

            <div class="mt-10 grid gap-6 md:grid-cols-2">
                <div class="flex flex-col rounded-xl border border-neutral-200 p-6 dark:border-neutral-800">
                    <h3 class="font-semibold">Use framework.pub</h3>
                    <p class="mt-2 flex-1 text-sm leading-relaxed text-neutral-600 dark:text-neutral-400">
                        Create a free account and start a project right away. We run the
                        server and keep it updated. Hosting your published notebooks with us
                        is an optional, low-cost add-on.
                    </p>
                    <a href="{{ route('register') }}" class="mt-6 self-start rounded-md bg-orange-500 px-4 py-2 text-sm font-medium text-white hover:bg-orange-600">Create a free account</a>
                </div>
                <div class="flex flex-col rounded-xl border border-neutral-200 p-6 dark:border-neutral-800">
                    <h3 class="font-semibold">Host it yourself</h3>
                    <p class="mt-2 flex-1 text-sm leading-relaxed text-neutral-600 dark:text-neutral-400">
                        Run Framework Cloud on your own server with Docker. Every feature is
                        included, and published notebooks go to your server or your own bucket.
                    </p>
                    <a href="{{ route('guide', 'self-hosting') }}" class="mt-6 self-start rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-800 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-100 dark:hover:bg-neutral-900">Self-hosting guide</a>
                </div>
            </div>
        </section>

    </main>

    <footer class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 border-t border-neutral-200 px-6 py-8 text-sm text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
        <div>Framework Cloud</div>
        <nav class="flex flex-wrap items-center gap-5">
            <a href="{{ route('terms') }}" class="hover:text-neutral-900 dark:hover:text-neutral-100">Terms</a>
            <a href="{{ route('privacy') }}" class="hover:text-neutral-900 dark:hover:text-neutral-100">Privacy</a>
            <a href="{{ route('guide') }}" class="hover:text-neutral-900 dark:hover:text-neutral-100">Documentation</a>
            <a href="https://framework.table1.org" class="hover:text-neutral-900 dark:hover:text-neutral-100">R package</a>
            <a href="https://github.com/frameworktools/framework-cloud" class="hover:text-neutral-900 dark:hover:text-neutral-100">Source code</a>
            <x-theme-toggle class="sm:hidden" />
        </nav>
    </footer>

</body>
</html>
