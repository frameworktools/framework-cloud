@extends('docs.layout')

@section('title', 'Reference')

@section('content')
    @php
        $label = fn ($e) => $e['kind'] === 'function' ? $e['name'].'()' : $e['name'];
    @endphp
    <header class="mb-9">
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">Reference</h1>
        <p class="mt-3 text-lg text-slate-600 dark:text-slate-400">
            Every public {{ collect($site['entries'])->pluck('kind')->unique()->sort()->implode(' and ') }}, by category.
        </p>
    </header>

    <div class="space-y-12">
        @foreach ($site['categories'] as $category)
            @php
                $others = array_values(array_diff($category['entries'], $category['common']));
            @endphp
            <section id="{{ $category['id'] }}" class="scroll-mt-24">
                <h2 class="border-b border-slate-200 pb-2 text-xl font-semibold tracking-tight text-slate-900 dark:border-slate-800 dark:text-white">{{ $category['title'] }}</h2>
                @if ($category['description'])
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $category['description'] }}</p>
                @endif

                @foreach (array_filter(['Common' => $category['common'], ($category['common'] !== [] ? 'More' : '') => $others]) as $heading => $ids)
                    @if ($heading !== '')
                        <h3 class="mt-5 text-xs font-semibold tracking-wide text-slate-400 uppercase dark:text-slate-500">{{ $heading }}</h3>
                    @endif
                    <dl class="mt-3 grid gap-x-8 gap-y-2.5 sm:grid-cols-[minmax(10rem,max-content)_1fr]">
                        @foreach ($ids as $id)
                            @php($e = $site['entries'][$id])
                            <dt>
                                <a href="/reference/{{ $id }}" class="font-mono text-sm text-sky-600 hover:underline dark:text-sky-400">{{ $label($e) }}</a>
                                @if ($e['lifecycle'] === 'deprecated')
                                    <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-400/10 dark:text-amber-300">Deprecated</span>
                                @endif
                            </dt>
                            <dd class="docs-inline text-sm text-slate-600 dark:text-slate-400">{!! $e['summary_html'] ?? e($e['summary']) !!}</dd>
                        @endforeach
                    </dl>
                @endforeach
            </section>
        @endforeach
    </div>
@endsection

@section('toc')
    @include('docs.partials.toc', ['items' => collect($site['categories'])->map(fn ($c) => ['level' => 2, 'id' => $c['id'], 'text' => $c['title']])->all()])
@endsection
