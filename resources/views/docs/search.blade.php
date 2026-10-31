@extends('docs.layout')

@section('title', $query !== '' ? 'Search: '.$query : 'Search')

@section('content')
    @php
        $groups = ['guide' => 'Guides', 'heading' => 'Sections of guides', 'entry' => 'Reference'];
    @endphp
    <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">Search</h1>

    <form action="/search" method="get" class="mt-6">
        <input type="search" name="q" value="{{ $query }}" placeholder="Search guides and reference" autofocus
               class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-base outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-800">
    </form>

    @if ($query !== '')
        @if ($results === [])
            <p class="mt-8 text-slate-600 dark:text-slate-400">Nothing matches “{{ $query }}”.</p>
        @endif

        @foreach (collect($results)->pluck('type')->unique() as $type)
            @php($label = $groups[$type])
            @php($hits = array_values(array_filter($results, fn ($r) => $r['type'] === $type)))
            @continue($hits === [])
            <h2 class="mt-10 text-xs font-semibold tracking-wide text-slate-400 uppercase dark:text-slate-500">{{ $label }}</h2>
            <ul role="list" class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($hits as $hit)
                    <li class="py-3">
                        <a href="{{ $hit['url'] }}" @class(['font-medium text-sky-600 hover:underline dark:text-sky-400', 'font-mono' => $type === 'entry'])>{{ $hit['title'] }}</a>
                        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ collect([$hit['text'], $hit['group']])->filter()->implode(' · ') }}</p>
                    </li>
                @endforeach
            </ul>
        @endforeach
    @endif
@endsection
