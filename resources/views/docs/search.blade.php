@extends('docs.layout')

@section('title', 'Search — Framework docs')

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">
        @if ($query !== '') Results for “{{ $query }}” @else Search @endif
    </h1>

    @if ($query !== '')
        @php($functions = $results->get('functions', collect()))
        @php($guideHits = $results->get('guides', collect()))

        @if ($functions->isEmpty() && $guideHits->isEmpty())
            <p class="mt-4 text-neutral-600 dark:text-neutral-400">Nothing matched. Try a function name like <code class="font-mono">data_read</code>.</p>
        @endif

        @if ($guideHits->isNotEmpty())
            <h2 class="mt-8 text-lg font-semibold">Guides</h2>
            <ul class="mt-3 flex flex-col gap-2">
                @foreach ($guideHits as $g)
                    <li>
                        <a href="{{ route('docs.guide', $g['slug']) }}" class="text-orange-600 hover:underline dark:text-orange-400">{{ $g['title'] }}</a>
                        <span class="text-sm text-neutral-500">— {{ $g['description'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($functions->isNotEmpty())
            <h2 class="mt-8 text-lg font-semibold">Functions</h2>
            <dl class="mt-3 grid gap-x-8 gap-y-3 sm:grid-cols-[max-content_1fr]">
                @foreach ($functions as $fn)
                    <dt><a href="{{ route('docs.function', $fn->name) }}" class="font-mono text-sm text-orange-600 hover:underline dark:text-orange-400">{{ $fn->name }}()</a></dt>
                    <dd class="text-sm text-neutral-600 dark:text-neutral-400">{{ $fn->title }}</dd>
                @endforeach
            </dl>
        @endif
    @endif
@endsection
