<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex"><meta name="referrer" content="no-referrer">
    <title>Link expired · Framework</title>
    <x-theme-script />
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-50 px-6 py-20 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<main class="mx-auto max-w-md rounded-xl bg-white p-8 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
    <h1 class="text-2xl font-semibold">Expired link</h1>
    <p class="mt-3 text-slate-600 dark:text-slate-300">The collection “{{ $collection->title }}” was shared until {{ $collection->expires_at->toFormattedDayDateString() }}. Ask its author for a new link.</p>
</main>
</body>
</html>
