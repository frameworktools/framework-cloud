<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Open a link · {{ config('app.name') }}</title>
    <x-theme-script />
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-full items-center justify-center bg-white px-6 py-16 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<main class="w-full max-w-md text-center">
    <h1 class="text-2xl font-semibold">Open a link</h1>
    <p class="mt-2 text-slate-500 dark:text-slate-400">Type the code shown on screen.</p>
    <form method="get" action="{{ route('go') }}" class="mt-8 flex flex-col gap-4">
        <label for="code" class="sr-only">Code</label>
        <input id="code" name="code" value="{{ $typed }}" required autofocus autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="40"
            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-4 text-center font-mono text-4xl tracking-[0.2em] lowercase dark:border-slate-600 dark:bg-slate-900"
            aria-describedby="code-help">
        @if ($missing)
            <p id="code-help" class="text-sm text-red-600 dark:text-red-400">No page has the code “{{ $typed }}”. Check it and try again.</p>
        @else
            <p id="code-help" class="text-sm text-slate-500 dark:text-slate-400">Codes are four letters and numbers, such as k7mp.</p>
        @endif
        <button type="submit" class="rounded-md bg-sky-700 px-4 py-3 font-medium text-white hover:bg-sky-800">Open</button>
    </form>
</main>
</body>
</html>
