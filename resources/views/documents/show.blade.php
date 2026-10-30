<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $document->title }}</title>
    <x-theme-script />
    @vite(['resources/css/app.css'])
</head>
<body class="flex h-full flex-col bg-neutral-100 dark:bg-neutral-900">
    <header class="flex items-center justify-between gap-4 border-b border-neutral-200 bg-white px-4 py-2.5 dark:border-neutral-700 dark:bg-neutral-800">
        <div class="min-w-0">
            <div class="truncate font-medium text-neutral-900 dark:text-neutral-100">{{ $document->title }}</div>
            <div class="truncate text-xs text-neutral-500 dark:text-neutral-400">
                {{ $project->name }} &middot;
                @if ($version->number === $latest)
                    updated {{ ($version->created_at ?? $document->updated_at)->diffForHumans() }}
                @else
                    <span class="font-medium text-orange-700 dark:text-orange-300">Version {{ $version->number }} of {{ $latest }}</span>,
                    published {{ $version->created_at?->toFormattedDateString() }}
                @endif
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-3 text-sm">
            @if ($versions->count() > 1)
                <label class="sr-only" for="version">Version</label>
                <select id="version" onchange="location.href = this.value"
                    class="rounded-md border border-neutral-300 bg-white py-1 pr-7 pl-2 text-sm text-neutral-800 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-200">
                    @foreach ($versions as $v)
                        <option value="{{ $v['url'] }}" @selected($v['number'] === $version->number)>
                            Version {{ $v['number'] }}{{ $v['number'] === $latest ? ' (latest)' : '' }} &middot; {{ $v['date']?->toFormattedDateString() }}
                        </option>
                    @endforeach
                </select>
            @endif
            <a href="{{ $version->contentUrl() }}" class="text-orange-600 hover:underline dark:text-orange-400">View raw</a>
            <a href="{{ config('app.url') }}" class="text-neutral-500 hover:underline max-sm:hidden dark:text-neutral-400">
                published with Framework Cloud
            </a>
            <x-theme-toggle />
        </div>
    </header>

    <iframe src="{{ $version->contentUrl() }}" title="{{ $document->title }}" class="w-full grow border-0 bg-white"></iframe>
</body>
</html>
