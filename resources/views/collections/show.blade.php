<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    @if ($collection->visibility !== 'public' || $collection->password_hash)<meta name="robots" content="noindex">@endif
    <title>{{ $collection->title }} · Framework</title>
    <x-theme-script />
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-900 dark:text-slate-100">
<main class="mx-auto max-w-5xl px-6 py-12">
    <p class="text-sm text-slate-500">{{ $collection->project->name }} · Research collection</p>
    <h1 class="mt-3 text-4xl font-semibold">{{ $collection->title }}</h1>
    @if ($collection->description)<p class="mt-5 whitespace-pre-line text-lg text-slate-600 dark:text-slate-300">{{ $collection->description }}</p>@endif
    <div class="mt-10 grid gap-5 md:grid-cols-2">
        @forelse ($items as $item)
            <article class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-xl font-semibold"><a href="{{ $item->readerUrl() }}" class="text-sky-700 hover:underline dark:text-sky-300">{{ $item->document->title ?? $item->document->slug }}</a></h2>
                <p class="mt-2 text-sm text-slate-500">{{ $item->version_number ? 'Version '.$item->version_number : 'Latest version' }}</p>
                @if ($item->caption)<p class="mt-4 whitespace-pre-line">{{ $item->caption }}</p>@endif
            </article>
        @empty
            <p class="text-slate-500">No artifacts are available in this collection.</p>
        @endforelse
    </div>
    <p class="mt-10 text-sm text-slate-500">Individual artifacts may have their own access requirements.</p>
    <footer class="mt-12 text-sm"><a href="{{ config('app.url') }}" class="text-slate-500 hover:underline">Published with Framework</a></footer>
</main>
</body>
</html>
