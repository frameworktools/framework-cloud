<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page === 'index' ? 'Documentation' : $title }} — Framework Cloud</title>
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <x-theme-script />
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">

    <header class="border-b border-slate-200 dark:border-slate-800">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
            <a href="/" class="font-semibold tracking-tight">
                <span class="inline-flex items-center gap-2"><img src="/images/logo.png" alt="" class="h-8 w-auto"><span>Framework <span class="text-sky-600 dark:text-sky-500">Cloud</span></span></span>
                <span class="ml-1 text-sm font-normal text-slate-500">docs</span>
            </a>
            <nav class="flex items-center gap-4 text-sm">
                <x-theme-toggle class="max-sm:hidden" />
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-md bg-sky-600 px-4 py-2 font-medium text-white hover:bg-sky-700">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">Sign in</a>
                @endauth
            </nav>
        </div>
    </header>

    <div class="mx-auto flex max-w-6xl gap-12 px-6 py-10 max-md:flex-col max-md:gap-6">
        <nav class="w-56 shrink-0 text-sm max-md:w-full">
            @foreach ($pages as $section => $items)
                <div class="mb-6">
                    <div class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">{{ $section }}</div>
                    <ul class="flex flex-col gap-0.5">
                        @foreach ($items as $slug => $label)
                            <li>
                                <a href="{{ $slug === 'index' ? url('/docs') : url('/docs/'.$slug) }}"
                                   @class([
                                       'block rounded-md px-2 py-1.5',
                                       'bg-sky-100 font-medium text-sky-700 dark:bg-sky-950/50 dark:text-sky-300' => $slug === $page,
                                       'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-900' => $slug !== $page,
                                   ])>{{ $label }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <main class="prose prose-neutral min-w-0 max-w-3xl flex-1 dark:prose-invert prose-a:text-sky-600 dark:prose-a:text-sky-400 prose-code:before:content-none prose-code:after:content-none prose-table:text-sm">
            {!! $html !!}

            <p class="mt-12 text-sm text-slate-500">
                <a href="https://github.com/frameworktools/framework-cloud/blob/main/docs/guide/{{ $page }}.md">Edit this page on GitHub</a>
            </p>
        </main>
    </div>

</body>
</html>
