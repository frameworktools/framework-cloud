@extends('docs.layout')

@section('description', $site['tagline'] ?: 'Documentation for '.$site['title'].'.')

@php
    // The home page reads like a guide: the sidebar, a title, the install
    // command, then each section's pages with their descriptions.
    $first = collect($site['sections'])->pluck('guides')->flatten()->first();
    $groups = collect($site['sections'])
        ->map(fn ($section) => [
            'id' => str_replace('_', '-', $section['id']),
            'title' => $section['title'],
            'reference' => $section['reference'],
            'items' => $section['reference']
                ? collect($site['categories'])->map(fn ($c) => ['url' => '/reference#'.$c['id'], 'title' => $c['title'], 'text' => $c['description']])->all()
                : collect($section['guides'])->map(fn ($id) => ['url' => '/guides/'.$id, 'title' => \App\Support\Docs\Sites::navTitle($site, $section, $id), 'text' => $site['guides'][$id]['description']])->all(),
        ])
        ->filter(fn ($group) => $group['items'] !== [])
        ->values();
    $toc = [
        ...($site['install'] ? [['level' => 2, 'id' => 'install', 'text' => 'Install']] : []),
        ...$groups->map(fn ($g) => ['level' => 2, 'id' => $g['id'], 'text' => $g['title']])->all(),
    ];
    // Colored at import; sites imported before that are colored here
    $installHtml = $site['install']
        ? ($site['install_html'] ?? \App\Support\Docs\CodeBlocks::install($site['install'], $site['product'] ?? $product))
        : null;
@endphp

@section('content')
    <article>
        <header class="mb-9">
            <p class="font-display text-sm font-medium text-sky-500">Overview</p>
            <h1 @class(['mt-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white', 'font-mono' => $site['title'] === 'fw'])>{{ $site['title'] }}</h1>
            @if ($site['tagline'])
                <p class="mt-3 text-lg text-slate-600 dark:text-slate-400">{{ $site['tagline'] }}</p>
            @endif
        </header>

        <div class="docs-prose">
            @if ($site['install'])
                <h2 id="install">Install</h2>
                {{-- One dark panel: the command scrolls in its own column, and Copy sits beside it --}}
                <div data-has-copy class="my-6 flex items-start rounded-xl bg-slate-900 shadow-sm dark:bg-slate-800/60 dark:ring-1 dark:ring-slate-300/10">
                    <pre class="my-0! min-w-0 flex-1 rounded-none! bg-transparent! pr-3! shadow-none! ring-0!"><code id="docs-install">@if ($installHtml){!! $installHtml !!}@else{{ $site['install'] }}@endif</code></pre>
                    <div class="shrink-0 pt-2.5 pr-2.5 pl-2">
                        <button type="button" data-copy="docs-install" class="rounded-lg bg-slate-700 px-3 py-1 text-xs font-medium text-slate-100 ring-1 ring-white/10 hover:bg-slate-600 hover:text-white"><span data-copy-label>Copy</span></button>
                    </div>
                </div>
                <p>
                    @if ($first)
                        <a href="/guides/{{ $first }}">Read {{ $site['guides'][$first]['title'] }}</a> for the requirements and setup.
                    @endif
                    @if ($site['repository'])
                        The source is <a href="{{ $site['repository'] }}">on GitHub</a>.
                    @endif
                </p>
            @endif

            @foreach ($groups as $group)
                <h2 id="{{ $group['id'] }}">
                    @if ($group['reference'])
                        <a href="/reference" class="text-inherit!">{{ $group['title'] }}</a>
                    @else
                        {{ $group['title'] }}
                    @endif
                </h2>
                <dl class="not-prose mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($group['items'] as $item)
                        <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4">
                            <dt><a href="{{ $item['url'] }}" class="font-medium text-sky-600 hover:underline dark:text-sky-400">{{ $item['title'] }}</a></dt>
                            <dd class="mt-1 text-slate-600 sm:col-span-2 sm:mt-0 dark:text-slate-400">{{ $item['text'] }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($group['reference'])
                    <p><a href="/reference">All {{ count($site['entries']) }} entries</a></p>
                @endif
            @endforeach
        </div>
    </article>

    @if ($first)
        <nav class="mt-12 flex border-t border-slate-200 pt-6 dark:border-slate-800" aria-label="Next">
            <div class="ml-auto text-right">
                <p class="font-display text-sm font-medium text-slate-900 dark:text-white">Next</p>
                <a href="/guides/{{ $first }}" class="mt-1 block text-base font-semibold text-slate-500 hover:text-slate-600 dark:text-slate-400 dark:hover:text-slate-300">{{ $site['guides'][$first]['title'] }} <span aria-hidden="true">→</span></a>
            </div>
        </nav>
    @endif
@endsection

@section('toc')
    @include('docs.partials.toc', ['items' => $toc])
@endsection
