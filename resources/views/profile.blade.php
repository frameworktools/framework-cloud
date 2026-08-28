<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ '@'.$user->handle }} — framework.pub</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100">
    <header class="mx-auto flex max-w-3xl items-center justify-between px-6 py-6">
        <a href="/" class="font-semibold tracking-tight">framework<span class="text-orange-500">.pub</span></a>
    </header>

    <main class="mx-auto max-w-3xl px-6 py-16">
        <div class="flex items-center gap-5">
            <div class="flex size-16 items-center justify-center rounded-full bg-orange-500 text-2xl font-semibold text-white">
                {{ $user->initials() }}
            </div>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ $user->name }}</h1>
                <div class="text-neutral-500 dark:text-neutral-400">{{ '@'.$user->handle }}</div>
            </div>
        </div>

        @if ($affiliation)
            <p class="mt-6 text-neutral-600 dark:text-neutral-400">{{ $affiliation }}</p>
        @endif

        <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
            Using Framework since {{ $user->created_at->format('F Y') }}
        </p>
    </main>

    <footer class="mx-auto max-w-3xl border-t border-neutral-200 px-6 py-8 text-sm text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
        <a href="/" class="hover:underline">framework.pub</a> — structured, reproducible data science in R
    </footer>
</body>
</html>
