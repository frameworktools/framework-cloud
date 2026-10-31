@extends('docs.layout')

@section('title', $guide['title'])
@section('description', $guide['description'] ?: $site['tagline'])

@section('content')
    @php
        $section = collect($site['sections'])->firstWhere('id', $guide['section']);
    @endphp
    <article>
        <header class="mb-9">
            @if ($section)
                <p class="font-display text-sm font-medium text-sky-500">{{ $section['title'] }}</p>
            @endif
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $guide['title'] }}</h1>
            @if ($guide['description'])
                <p class="mt-3 text-lg text-slate-600 dark:text-slate-400">{{ $guide['description'] }}</p>
            @endif
        </header>

        <div class="docs-prose">{!! $guide['html'] !!}</div>
    </article>

    @if ($guide['prev'] || $guide['next'])
        <nav class="mt-12 flex border-t border-slate-200 pt-6 dark:border-slate-800" aria-label="Previous and next">
            @if ($guide['prev'])
                <div>
                    <p class="font-display text-sm font-medium text-slate-900 dark:text-white">Previous</p>
                    <a href="/guides/{{ $guide['prev']['id'] }}" class="mt-1 block text-base font-semibold text-slate-500 hover:text-slate-600 dark:text-slate-400 dark:hover:text-slate-300"><span aria-hidden="true">←</span> {{ $guide['prev']['title'] }}</a>
                </div>
            @endif
            @if ($guide['next'])
                <div class="ml-auto text-right">
                    <p class="font-display text-sm font-medium text-slate-900 dark:text-white">Next</p>
                    <a href="/guides/{{ $guide['next']['id'] }}" class="mt-1 block text-base font-semibold text-slate-500 hover:text-slate-600 dark:text-slate-400 dark:hover:text-slate-300">{{ $guide['next']['title'] }} <span aria-hidden="true">→</span></a>
                </div>
            @endif
        </nav>
    @endif
@endsection

@section('toc')
    @include('docs.partials.toc', ['items' => $guide['toc']])
@endsection
