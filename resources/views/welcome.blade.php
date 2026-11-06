<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Framework: structured data science workflows</title>
    <meta name="description" content="Reproducible research with R, Python, and the Framework CLI. Create projects, connect databases, track data integrity, and publish versioned figures, tables, models, and reports.">
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <x-share-meta />
    <x-theme-script />
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">



    <div class="relative overflow-hidden border-b border-sky-900/10 bg-gradient-to-b from-sky-100 via-sky-50 to-white text-slate-900 dark:border-white/10 dark:from-slate-900 dark:via-slate-950 dark:to-slate-950 dark:text-slate-50">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(40%_60%_at_85%_20%,rgba(255,255,255,0.9),transparent_70%),radial-gradient(30%_45%_at_65%_75%,rgba(255,255,255,0.7),transparent_70%)] dark:bg-[radial-gradient(60%_80%_at_100%_0%,rgba(14,165,233,0.18),transparent_70%)]"></div>
    <x-site-header overlay />

    {{-- Hero: a light sky gradient, or slate in dark mode --}}
    <section class="relative">
        <div class="relative mx-auto grid max-w-7xl items-start gap-12 px-6 py-14 sm:py-16 lg:grid-cols-[1fr_1.05fr] lg:gap-14">
            <div class="min-w-0 lg:pt-10">
                <h1 class="text-4xl font-semibold leading-[1.06] tracking-tight text-balance sm:text-5xl xl:text-[3.5rem]">Structured data science workflows</h1>
                <p class="mt-6 max-w-xl text-lg leading-relaxed text-slate-700 dark:text-slate-200">Framework helps you set up a data science project, keep track of the data and settings you use, and share your results. Start a project in Framework Cloud, or with our R, Python, {{ isset(\App\Support\Docs\Products::listed()['stata']) ? 'Stata, ' : '' }}or command-line tools.</p>
                @php
                    // The tools a visitor can use without Cloud, each linking to its docs site
                    $tools = collect([
                        'r' => ['R package', 'icon-lang-r'],
                        'py' => ['Python package', 'icon-lang-python'],
                        'stata' => ['Stata package', 'heroicon-o-chart-bar-square'],
                        'cli' => ['CLI tool', 'heroicon-o-command-line'],
                    ])->only(array_keys(\App\Support\Docs\Products::listed()));
                @endphp
                <div class="mt-12 flex flex-wrap items-center gap-3">
                    <a href="{{ auth()->check() ? route('dashboard') : route('register') }}" class="rounded-md bg-sky-600 px-5 py-2.5 font-medium text-white hover:bg-sky-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500">{{ auth()->check() ? 'Open dashboard' : 'Create a Framework Cloud account' }}</a>
                    <a href="{{ route('guide') }}" class="rounded-md px-4 py-2.5 font-medium text-slate-700 hover:bg-white/50 dark:text-slate-200 dark:hover:bg-white/5">Read the docs <span aria-hidden="true">&rarr;</span></a>
                </div>
                <nav aria-label="Framework tools" class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                    @foreach ($tools as $product => [$name, $icon])
                        <a href="{{ \App\Support\Docs\Products::publicUrl($product) }}" class="group inline-flex items-center gap-1.5 font-medium text-slate-600 hover:text-slate-950 dark:text-slate-400 dark:hover:text-white"><x-dynamic-component :component="$icon" class="size-4 shrink-0 text-slate-500 group-hover:text-slate-800 dark:group-hover:text-slate-200" />{{ $name }}</a>
                    @endforeach
                </nav>
            </div>
            <div class="min-w-0 rounded-xl shadow-panel-deep dark:shadow-panel-deep-dark"><x-landing-demo /></div>
        </div>
    </section>
    </div>

    <main class="mx-auto max-w-6xl px-6">
        <x-landing-cloud />
        <span id="features"></span><span id="structure"></span>
        <x-landing-setup />
        <x-landing-workflow />
        <x-landing-platform />
        <span id="copy"></span><span id="environments"></span>
        <x-landing-safeguards />
        <x-landing-ai />
        <x-landing-tools />
        <x-landing-start />
    </main>

    <x-site-footer />
    <script type="application/json" id="landing-project-sources">@json(\App\Support\LandingProject::sources())</script>

</body>
</html>
