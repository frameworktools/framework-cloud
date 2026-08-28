<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Framework docs')</title>
    <meta name="description" content="Documentation for the Framework R package.">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📖</text></svg>">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100">

    <header class="border-b border-neutral-200 dark:border-neutral-800">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
            <a href="{{ route('docs.home') }}" class="font-semibold tracking-tight">framework <span class="text-sm font-normal text-neutral-500">docs</span></a>
            <form action="{{ route('docs.search') }}" method="get" class="max-w-xs flex-1">
                <input
                    type="search" name="q" value="{{ request('q') }}" placeholder="Search functions and guides…"
                    class="w-full rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-sm outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 dark:border-neutral-700 dark:bg-neutral-900"
                >
            </form>
            <nav class="flex items-center gap-4 text-sm">
                <a href="{{ route('docs.reference') }}" class="text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100">Reference</a>
                <a href="https://framework.pub" class="text-orange-600 hover:underline dark:text-orange-400">framework.pub</a>
                <a href="https://github.com/table1/framework" class="text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100">GitHub</a>
            </nav>
        </div>
    </header>

    <div class="mx-auto flex max-w-6xl gap-10 px-6 py-10">
        <aside class="hidden w-56 shrink-0 md:block">
            <nav class="flex flex-col gap-6 text-sm">
                @foreach (\App\Support\Docs::SECTIONS as $key => $label)
                    <div>
                        <div class="mb-2 font-medium uppercase tracking-wide text-neutral-500 text-xs">{{ $label }}</div>
                        <ul class="flex flex-col gap-1 border-l border-neutral-200 dark:border-neutral-800">
                            @foreach ($guides->where('section', $key) as $g)
                                <li>
                                    <a href="{{ route('docs.guide', $g['slug']) }}"
                                       class="-ml-px block border-l py-0.5 pl-3 {{ request()->route('slug') === $g['slug'] ? 'border-orange-500 text-orange-600 dark:text-orange-400' : 'border-transparent text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100' }}">
                                        {{ $g['title'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
                <div>
                    <div class="mb-2 font-medium uppercase tracking-wide text-neutral-500 text-xs">Reference</div>
                    <ul class="flex flex-col gap-1 border-l border-neutral-200 dark:border-neutral-800">
                        @foreach ($categories as $c)
                            <li><a href="{{ route('docs.reference') }}#{{ Str::slug($c->name) }}" class="-ml-px block border-l border-transparent py-0.5 pl-3 text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100">{{ $c->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            @yield('content')
        </main>
    </div>

    <footer class="border-t border-neutral-200 dark:border-neutral-800">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6 text-sm text-neutral-500 dark:text-neutral-400">
            <div>Framework — structured data science in R</div>
            <div>Docs generated from the package's roxygen source</div>
        </div>
    </footer>

</body>
</html>
