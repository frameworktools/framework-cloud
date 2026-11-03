<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex"><meta name="referrer" content="no-referrer">
    <title>Protected report · Framework</title>
    <x-theme-script />
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-stone-50 px-6 py-20 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <main class="mx-auto mt-8 max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-xs font-semibold uppercase tracking-widest text-sky-700 dark:text-sky-400">Framework research report</p>
        <h1 class="mt-5 text-2xl font-semibold tracking-tight">Password-protected report</h1>
        <p class="mt-4 text-sm leading-relaxed text-slate-500 dark:text-slate-400">Enter the password shared by its author to read the report. Included artifacts may have their own access requirements.</p>
        <form method="post" action="{{ route('reports.unlock', ['key' => $report->key]) }}" class="mt-7 space-y-4">
            @csrf
            @if (isset($number))<input type="hidden" name="version" value="{{ $number }}">@endif
            <label for="password" class="block text-sm font-medium">Report password</label>
            <input id="password" name="password" type="password" required autofocus autocomplete="current-password" class="w-full rounded-lg border border-slate-300 bg-transparent px-3 py-2 dark:border-slate-600">
            @error('password')<p class="text-sm text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
            <button type="submit" class="w-full rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-sky-800">View report</button>
        </form>
    </main>
    <p class="mx-auto mt-8 max-w-md text-center text-xs text-slate-500"><a href="{{ config('app.url') }}" class="hover:underline">Published with Framework</a></p>
</body>
</html>
