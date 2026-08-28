<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $document->title }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex h-full flex-col bg-neutral-100 dark:bg-neutral-900">
    <header class="flex items-center justify-between gap-4 border-b border-neutral-200 bg-white px-4 py-2.5 dark:border-neutral-700 dark:bg-neutral-800">
        <div class="min-w-0">
            <div class="truncate font-medium text-neutral-900 dark:text-neutral-100">{{ $document->title }}</div>
            <div class="truncate text-xs text-neutral-500 dark:text-neutral-400">
                {{ $project->name }} &middot; updated {{ $document->updated_at->diffForHumans() }}
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-3 text-sm">
            <a href="{{ $document->contentUrl() }}" class="text-orange-600 hover:underline dark:text-orange-400">View raw</a>
            <a href="{{ config('app.url') }}" class="text-neutral-500 hover:underline dark:text-neutral-400">
                published with framework
            </a>
        </div>
    </header>

    <iframe src="{{ $document->contentUrl() }}" title="{{ $document->title }}" class="w-full grow border-0 bg-white"></iframe>
</body>
</html>
