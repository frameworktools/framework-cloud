<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Framework docs')</title>
    <meta name="description" content="Documentation for the Framework R package.">
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <x-theme-script />
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">

    <header class="border-b border-slate-200 dark:border-slate-800">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
            <a href="{{ route('docs.home') }}" class="font-semibold tracking-tight">framework <span class="text-sm font-normal text-slate-500">docs</span></a>
            <form action="{{ route('docs.search') }}" method="get" class="max-w-xs flex-1">
                <input
                    type="search" name="q" value="{{ request('q') }}" placeholder="Search functions and guides…"
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-900"
                >
            </form>
            <nav class="flex items-center gap-4 text-sm">
                <a href="{{ route('docs.reference') }}" class="text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">Reference</a>
                <a href="https://framework.pub" class="text-sky-600 hover:underline dark:text-sky-400">Framework Cloud</a>
                <a href="https://github.com/frameworktools/framework" class="text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">GitHub</a>
                <x-theme-toggle />
            </nav>
        </div>
    </header>

    <div class="mx-auto flex max-w-6xl gap-10 px-6 py-10">
        <aside class="hidden w-56 shrink-0 md:block">
            <nav class="flex flex-col gap-6 text-sm">
                @foreach (\App\Support\Docs::SECTIONS as $key => $label)
                    <div>
                        <div class="mb-2 font-medium uppercase tracking-wide text-slate-500 text-xs">{{ $label }}</div>
                        <ul class="flex flex-col gap-1 border-l border-slate-200 dark:border-slate-800">
                            @foreach ($guides->where('section', $key) as $g)
                                <li>
                                    <a href="{{ route('docs.guide', $g['slug']) }}"
                                       class="-ml-px block border-l py-0.5 pl-3 {{ request()->route('slug') === $g['slug'] ? 'border-sky-500 text-sky-600 dark:text-sky-400' : 'border-transparent text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100' }}">
                                        {{ $g['title'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
                <div>
                    <div class="mb-2 font-medium uppercase tracking-wide text-slate-500 text-xs">Reference</div>
                    <ul class="flex flex-col gap-1 border-l border-slate-200 dark:border-slate-800">
                        @foreach ($categories as $c)
                            <li><a href="{{ route('docs.reference') }}#{{ Str::slug($c->name) }}" class="-ml-px block border-l border-transparent py-0.5 pl-3 text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">{{ $c->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            @yield('content')
        </main>
    </div>

    <footer class="border-t border-slate-200 dark:border-slate-800">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6 text-sm text-slate-500 dark:text-slate-400">
            <div>Framework — structured data science in R</div>
            <div>Docs generated from the package's roxygen source</div>
        </div>
    </footer>

</body>
</html>
