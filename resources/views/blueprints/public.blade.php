<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($blueprint->visibility !== 'public' && ! $blueprint->isSystem())<meta name="robots" content="noindex">@endif
    <title>{{ $blueprint->name }} · Blueprint · {{ config('app.name') }}</title>
    <x-theme-script />
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-full bg-white text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<header class="border-b border-slate-800/10 dark:border-white/15">
    <div class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-3 px-6 py-4">
        <a href="{{ config('app.url') }}" class="inline-flex items-center gap-2 text-sm font-semibold tracking-tight"><img src="{{ asset('images/logo-80.webp') }}" alt="" width="73" height="80" class="h-6 w-auto">Framework <span class="font-normal text-slate-400">/ Blueprint</span></a>
        <div class="flex items-center gap-3"><x-present-link :target="$blueprint" /><x-theme-toggle /></div>
    </div>
</header>
<main class="mx-auto max-w-4xl px-6 py-10">
    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $blueprint->handle }} · version {{ $blueprint->latestVersion->version }}@if ($blueprint->owner) · by {{ $blueprint->owner->name }}@endif</p>
    <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $blueprint->name }}</h1>
    @if ($blueprint->description)<p class="mt-4 max-w-3xl whitespace-pre-line text-lg text-slate-600 dark:text-slate-300">{{ $blueprint->description }}</p>@endif

    <section class="mt-10" aria-labelledby="use-title">
        <h2 id="use-title" class="text-base font-medium">Project setup</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Start a project from this blueprint with fw, or in Framework Cloud.</p>
        <pre class="mt-3 overflow-x-auto whitespace-pre-wrap break-all rounded-lg border border-slate-800/10 px-4 py-3 font-mono text-sm dark:border-white/15">fw new my-project --from {{ $blueprint->handle }}</pre>
        <a href="{{ $appUrl }}" class="mt-3 inline-flex rounded-md bg-sky-700 px-4 py-2 text-sm font-medium text-white hover:bg-sky-800">Open in Framework Cloud</a>
    </section>

    <hr class="my-10 border-slate-800/10 dark:border-white/15">

    @if (count($languages) > 1)
        <nav class="flex gap-6 border-b border-slate-800/10 dark:border-white/15" aria-label="Language">
            @foreach ($languages as $lang)
                <a href="?language={{ $lang }}" @if ($lang === $language) aria-current="page" @endif
                    class="-mb-px border-b-2 px-1 pb-2 text-sm font-medium {{ $lang === $language ? 'border-sky-700 text-sky-700 dark:border-sky-400 dark:text-sky-400' : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white' }}">{{ \App\Models\Project::LANGUAGE_NAMES[$lang] ?? $lang }}</a>
            @endforeach
        </nav>
    @endif

    @if (! ($preview['available'] ?? false))
        <p class="mt-6 text-sm text-slate-600 dark:text-slate-300">{{ $preview['problem'] ?? 'This blueprint cannot be previewed for this language.' }}</p>
    @else
        <div class="mt-6 grid gap-10 md:grid-cols-[1fr_16rem]">
            <section aria-labelledby="files-title">
                <h2 id="files-title" class="text-base font-medium">Files and folders</h2>
                <ul class="mt-3 divide-y divide-slate-800/5 font-mono text-sm dark:divide-white/10">
                    @foreach ($preview['tree'] as $entry)
                        @php($depth = substr_count($entry['path'], '/'))
                        <li class="flex items-baseline justify-between gap-4 py-1.5" style="padding-left: {{ $depth * 1.25 }}rem">
                            <span class="{{ $entry['later'] ? 'text-slate-400' : '' }}">{{ basename($entry['path']) }}{{ $entry['type'] === 'dir' ? '/' : '' }}</span>
                            @if ($entry['note'] || $entry['later'])<span class="font-sans text-xs text-slate-500 dark:text-slate-400">{{ $entry['note'] ?? 'made on first use' }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </section>
            <aside class="flex flex-col gap-6 text-sm">
                @foreach ([
                    'Tools' => collect($preview['tools'])->pluck('name')->all(),
                    'Skills' => array_merge(collect($preview['skill_sets'])->flatMap(fn ($set) => $set['skills'])->all(), $preview['skills']),
                    'Package sets' => collect($preview['package_sets'])->pluck('name')->all(),
                ] as $heading => $names)
                    <div>
                        <h2 class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $heading }}</h2>
                        @if ($names)
                            <ul class="mt-2 space-y-1">@foreach ($names as $name)<li>{{ $name }}</li>@endforeach</ul>
                        @else
                            <p class="mt-2 text-slate-500 dark:text-slate-400">None</p>
                        @endif
                    </div>
                @endforeach
                <div>
                    <h2 class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Sensitive data</h2>
                    <p class="mt-2">{{ $preview['settings']['sensitive']['on'] ? 'Private and public data folders' : 'No' }}</p>
                </div>
            </aside>
        </div>
    @endif
</main>
<footer class="mx-auto max-w-4xl px-6 pb-10 text-xs text-slate-500 dark:text-slate-400">Published with Framework Cloud</footer>
</body>
</html>
