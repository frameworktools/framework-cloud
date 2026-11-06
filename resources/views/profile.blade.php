<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ '@'.$user->handle }} — Framework Cloud</title>
    <x-theme-script />
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <header class="mx-auto flex max-w-3xl items-center justify-between px-6 py-6">
        <a href="/" class="font-semibold tracking-tight"><span class="inline-flex items-center gap-2"><img src="/images/logo.png" alt="" class="h-8 w-auto"><span>Framework <span class="text-sky-600 dark:text-sky-500">Cloud</span></span></span></a>
        <x-theme-toggle />
    </header>

    <main class="mx-auto max-w-3xl px-6 py-16">
        <div class="flex items-center gap-5">
            <div class="flex size-16 items-center justify-center rounded-full bg-sky-600 text-2xl font-semibold text-white">
                {{ $user->initials() }}
            </div>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ $user->name }}</h1>
                <div class="text-slate-500 dark:text-slate-400">{{ '@'.$user->handle }}</div>
            </div>
        </div>

        @if ($affiliation)
            <p class="mt-6 text-slate-600 dark:text-slate-400">{{ $affiliation }}</p>
        @endif

        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
            Using Framework since {{ $user->created_at->format('F Y') }}
        </p>

        @if ($artifacts->isNotEmpty())
            <section class="mt-12" aria-labelledby="public-work">
                <h2 id="public-work" class="text-base font-medium">Public artifacts</h2>
                <ul class="mt-3 divide-y divide-slate-800/10 border-t border-slate-800/10 dark:divide-white/15 dark:border-white/15">
                    @foreach ($artifacts as $artifact)
                        <li class="flex items-baseline justify-between gap-4 py-3">
                            <a href="{{ $artifact->shareUrl() }}" class="font-medium text-slate-800 underline decoration-slate-300 underline-offset-2 hover:decoration-slate-500 dark:text-slate-200">{{ $artifact->title ?? $artifact->slug }}</a>
                            <span class="shrink-0 text-sm text-slate-500 dark:text-slate-400">{{ $artifact->project->name }} · {{ $artifact->updated_at->format('M j, Y') }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </main>

    <footer class="mx-auto max-w-3xl border-t border-slate-200 px-6 py-8 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
        <a href="/" class="hover:underline">Framework Cloud</a> · Structured data science workflows in R, Python, and Stata
    </footer>
</body>
</html>
