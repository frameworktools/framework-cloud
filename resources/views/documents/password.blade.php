<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Password required · Framework Cloud</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 p-8 text-slate-900">
    <main class="mx-auto mt-16 max-w-md rounded-xl bg-white p-8 shadow-sm">
        <h1 class="text-xl font-semibold">Password-protected artifact</h1>
        <p class="mt-3 text-sm text-slate-600">Enter the password provided by its author to view the artifact and its files.</p>
        <form method="post" action="{{ route('documents.unlock', ['key' => $document->key]) }}" class="mt-6 space-y-4">
            @csrf
            @if ($number)<input type="hidden" name="version" value="{{ $number }}">@endif
            <label for="password" class="block text-sm font-medium">Password</label>
            <input type="password" id="password" name="password" required autofocus autocomplete="current-password" class="block w-full rounded border border-slate-300 px-3 py-2">
            @error('password')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <button class="rounded bg-sky-700 px-4 py-2 text-white">View artifact</button>
        </form>
    </main>
</body>
</html>
