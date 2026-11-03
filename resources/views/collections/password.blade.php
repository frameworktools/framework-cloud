<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex"><meta name="referrer" content="no-referrer">
    <title>Protected collection · Framework</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 px-6 py-20 text-slate-900">
<main class="mx-auto max-w-md rounded-xl bg-white p-8 shadow-sm">
    <h1 class="text-2xl font-semibold">Password-protected collection</h1>
    <p class="mt-3 text-slate-600">Enter the password shared by its author to view the collection.</p>
    <form method="post" action="{{ route('collections.unlock', $collection->key) }}" class="mt-6 space-y-4">
        @csrf
        <label for="password" class="block text-sm font-medium">Password</label>
        <input id="password" name="password" type="password" required autofocus autocomplete="current-password" class="w-full rounded-md border border-slate-300 px-3 py-2">
        @error('password') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        <button type="submit" class="rounded-md bg-sky-700 px-4 py-2 text-white">Open collection</button>
    </form>
</main>
</body>
</html>
