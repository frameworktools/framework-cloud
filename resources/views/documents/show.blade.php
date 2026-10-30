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
<body class="flex h-full flex-col bg-slate-100 dark:bg-slate-900">
    <header class="flex items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 py-2.5 dark:border-slate-700 dark:bg-slate-800">
        <div class="min-w-0">
            <div class="truncate font-medium text-slate-900 dark:text-slate-100">{{ $document->title }}</div>
            <div class="truncate text-xs text-slate-500 dark:text-slate-400">
                {{ $project->name }} &middot;
                @if ($version->number === $latest)
                    updated {{ ($version->created_at ?? $document->updated_at)->diffForHumans() }}
                @else
                    <span class="font-medium text-sky-700 dark:text-sky-300">Version {{ $version->number }} of {{ $latest }}</span>,
                    published {{ $version->created_at?->toFormattedDateString() }}
                @endif
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-3 text-sm">
            @if ($versions->count() > 1)
                <label class="sr-only" for="version">Version</label>
                <select id="version" onchange="location.href = this.value"
                    class="rounded-md border border-slate-300 bg-white py-1 pr-7 pl-2 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                    @foreach ($versions as $v)
                        <option value="{{ $v['url'] }}" @selected($v['number'] === $version->number)>
                            Version {{ $v['number'] }}{{ $v['number'] === $latest ? ' (latest)' : '' }} &middot; {{ $v['date']?->toFormattedDateString() }}
                        </option>
                    @endforeach
                </select>
            @endif
            <a href="{{ $version->contentUrl() }}" class="text-sky-600 hover:underline dark:text-sky-400">View raw</a>
            <a href="{{ config('app.url') }}" class="text-slate-500 hover:underline max-sm:hidden dark:text-slate-400">
                published with Framework Cloud
            </a>
            <x-theme-toggle />
        </div>
    </header>

    <iframe src="{{ $version->contentUrl() }}" title="{{ $document->title }}" class="w-full grow border-0 bg-white"></iframe>
</body>
</html>
