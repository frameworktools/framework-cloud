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
    // The install command, or labeled options such as the latest version and CRAN.
    // Colored at import; sites imported before that are colored here.
    $installs = collect(is_array($site['install']) ? $site['install'] : ($site['install'] ? [['code' => $site['install']]] : []))
        ->map(fn ($option, $i) => [
            'title' => $option['title'] ?? null,
            'note' => $option['note'] ?? null,
            'code' => $option['code'],
            'html' => (is_array($site['install_html'] ?? null) ? $site['install_html'][$i] ?? null : ($site['install_html'] ?? null))
                ?? \App\Support\Docs\CodeBlocks::install($option['code'], $site['product'] ?? $product),
            'id' => $i ? 'docs-install-'.($i + 1) : 'docs-install',
            'language' => \App\Support\Docs\CodeBlocks::installLanguage($option['code'], $site['product'] ?? $product),
        ]);
    $toc = [
        ...($site['install'] ? [['level' => 2, 'id' => 'install', 'text' => 'Install']] : []),
        ...$installs->filter(fn ($o) => $o['title'])->map(fn ($o) => ['level' => 3, 'id' => \Illuminate\Support\Str::slug($o['title']), 'text' => $o['title']])->values()->all(),
        ...$groups->map(fn ($g) => ['level' => 2, 'id' => $g['id'], 'text' => $g['title']])->all(),
    ];
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
            @if ($installs->isNotEmpty())
                <h2 id="install">Install</h2>
                @foreach ($installs as $option)
                    @if ($option['title'])
                        <h3 id="{{ \Illuminate\Support\Str::slug($option['title']) }}">{{ $option['title'] }}</h3>
                    @endif
                    @if ($option['note'])
                        <p>{{ $option['note'] }}</p>
                    @endif
                    <pre class="language-{{ $option['language'] }}"><code id="{{ $option['id'] }}">@if ($option['html']){!! $option['html'] !!}@else{{ $option['code'] }}@endif</code></pre>
                @endforeach
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
