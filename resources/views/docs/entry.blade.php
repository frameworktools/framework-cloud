@extends('docs.layout')

@php
    $display = $entry['kind'] === 'function' ? $entry['name'].'()' : $entry['name'];
    $category = collect($site['categories'])->firstWhere('id', $entry['category']);
    $hasType = collect($entry['parameters'])->contains(fn ($p) => $p['type'] !== null);
    $hasDefault = collect($entry['parameters'])->contains(fn ($p) => $p['default'] !== null);
    $parametersTitle = match ($entry['kind']) {
        'command' => 'Options',
        'class' => 'Fields',
        default => 'Arguments',
    };
    $toc = array_values(array_filter([
        $entry['usage'] ? ['level' => 2, 'id' => 'usage', 'text' => 'Usage'] : null,
        $entry['parameters'] ? ['level' => 2, 'id' => 'parameters', 'text' => $parametersTitle] : null,
        $entry['returns'] ? ['level' => 2, 'id' => 'returns', 'text' => 'Value'] : null,
        $entry['details'] ? ['level' => 2, 'id' => 'details', 'text' => 'Details'] : null,
    ]));
    foreach ($entry['sections'] as $s) {
        $toc[] = ['level' => 2, 'id' => $s['id'], 'text' => $s['title']];
        foreach ($s['subsections'] as $sub) {
            $toc[] = ['level' => 3, 'id' => $sub['id'], 'text' => $sub['title']];
        }
    }
    if ($entry['examples']) {
        $toc[] = ['level' => 2, 'id' => 'examples', 'text' => 'Examples'];
    }
    if ($entry['see_also']) {
        $toc[] = ['level' => 2, 'id' => 'see-also', 'text' => 'See also'];
    }
    $h2 = 'mt-10 scroll-mt-24 text-xl font-semibold tracking-tight text-slate-900 dark:text-white';
    $code = 'mt-4 overflow-x-auto rounded-xl bg-slate-900 p-4 font-mono text-sm text-slate-50 shadow-sm dark:bg-slate-800/60 dark:ring-1 dark:ring-slate-300/10';
@endphp

@section('title', $display)
@section('description', $entry['summary'])

@section('content')
    <article>
        <header class="mb-8">
            <p class="font-display text-sm font-medium text-sky-500">
                <a href="/reference#{{ $entry['category'] }}" class="hover:underline">{{ $category['title'] ?? 'Reference' }}</a>
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 @class(['text-3xl font-semibold tracking-tight text-slate-900 dark:text-white', 'font-mono' => ! $entry['title']])>{{ $entry['title'] ?: $display }}</h1>
                @if ($entry['lifecycle'] === 'deprecated')
                    <span class="rounded-md bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-400/10 dark:text-amber-300">Deprecated</span>
                @elseif ($entry['lifecycle'] === 'experimental')
                    <span class="rounded-md bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-800 dark:bg-sky-400/10 dark:text-sky-300">Experimental</span>
                @endif
            </div>
            @if ($entry['title'])
                <p class="mt-1 font-mono text-sm text-slate-500 dark:text-slate-400">{{ $display }}</p>
            @endif
            <p class="docs-inline mt-3 text-lg text-slate-600 dark:text-slate-400">{!! $entry['summary_html'] ?? e($entry['summary']) !!}</p>
            @if ($entry['since'] || $entry['aliases'])
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    @if ($entry['since']) Added in {{ $entry['since'] }}.@endif
                    @if ($entry['aliases']) Also available as <span class="font-mono">{{ implode(', ', $entry['aliases']) }}</span>.@endif
                </p>
            @endif
        </header>

        @if ($entry['lifecycle'] === 'deprecated')
            <div class="mb-8 rounded-xl bg-amber-50 px-5 py-4 text-sm text-amber-900 dark:bg-amber-400/10 dark:text-amber-100" role="note">
                <p class="font-semibold text-amber-700 dark:text-amber-300">Deprecated</p>
                <p class="mt-1">
                    @if ($entry['replaced_by'])
                        Use
                        @if ($entry['replaced_by']['url'])
                            <a href="{{ $entry['replaced_by']['url'] }}" class="font-mono font-medium underline">{{ $entry['replaced_by']['name'] }}</a>
                        @else
                            <span class="font-mono font-medium">{{ $entry['replaced_by']['name'] }}</span>
                        @endif
                        instead.
                    @else
                        This will be removed in a later release.
                    @endif
                </p>
            </div>
        @endif

        @if ($entry['description'])
            <div class="docs-prose">{!! $entry['description'] !!}</div>
        @endif

        @if ($entry['usage'])
            <h2 id="usage" class="{{ $h2 }}">Usage</h2>
            <pre class="{{ $code }} whitespace-pre-wrap [overflow-wrap:anywhere]"><code>{{ $entry['usage'] }}</code></pre>
        @endif

        @if ($entry['parameters'])
            <h2 id="parameters" class="{{ $h2 }}">{{ $parametersTitle }}</h2>
            <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold tracking-wide text-slate-500 uppercase dark:bg-slate-800/60 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-4 py-2.5">Name</th>
                            @if ($hasType)<th scope="col" class="px-4 py-2.5">Type</th>@endif
                            @if ($hasDefault)<th scope="col" class="px-4 py-2.5">Default</th>@endif
                            <th scope="col" class="px-4 py-2.5">Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach ($entry['parameters'] as $parameter)
                            <tr class="align-top">
                                <td class="px-4 py-3 font-mono whitespace-nowrap text-slate-900 dark:text-white">
                                    {{ $parameter['name'] }}
                                    @if ($parameter['required'])
                                        <span class="ml-1 font-sans text-xs font-medium text-red-600 dark:text-red-400">required</span>
                                    @endif
                                </td>
                                @if ($hasType)<td class="px-4 py-3 font-mono text-[13px] text-slate-600 dark:text-slate-300">{{ $parameter['type'] }}</td>@endif
                                @if ($hasDefault)<td class="px-4 py-3 font-mono text-[13px] whitespace-nowrap text-slate-600 dark:text-slate-300">{{ $parameter['default'] }}</td>@endif
                                <td class="docs-prose px-4 py-3 text-sm [&>p]:my-0">
                                    {!! $parameter['description'] !!}
                                    @if ($parameter['env'])
                                        <p class="mt-1! text-xs text-slate-500 dark:text-slate-400">Environment variable: <code>{{ $parameter['env'] }}</code></p>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($entry['returns'])
            <h2 id="returns" class="{{ $h2 }}">Value</h2>
            <div class="docs-prose mt-3">{!! $entry['returns'] !!}</div>
        @endif

        @if ($entry['details'])
            <h2 id="details" class="{{ $h2 }}">Details</h2>
            <div class="docs-prose mt-3">{!! $entry['details'] !!}</div>
        @endif

        @foreach ($entry['sections'] as $section)
            <h2 id="{{ $section['id'] }}" class="{{ $h2 }}">{{ $section['title'] }}</h2>
            <div class="docs-prose mt-3">{!! $section['html'] !!}</div>
            @foreach ($section['subsections'] as $sub)
                <h3 id="{{ $sub['id'] }}" class="mt-6 scroll-mt-24 text-base font-semibold text-slate-900 dark:text-white">{{ $sub['title'] }}</h3>
                <div class="docs-prose mt-2">{!! $sub['html'] !!}</div>
            @endforeach
        @endforeach

        @if ($entry['note'])
            <div class="docs-prose mt-8"><blockquote class="callout callout-note"><p class="callout-title">Note</p>{!! $entry['note'] !!}</blockquote></div>
        @endif

        @if ($entry['examples'])
            <h2 id="examples" class="{{ $h2 }}">Examples</h2>
            @foreach ($entry['examples'] as $example)
                @if ($example['title'] || ! $example['runnable'])
                    <p class="mt-5 flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ $example['title'] }}
                        @if (! $example['runnable'])
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400" title="This example needs a project, a server, or credentials, so it is not run when the docs are built.">Not run</span>
                        @endif
                    </p>
                @endif
                <pre class="{{ $code }}"><code>{{ $example['code'] }}</code></pre>
            @endforeach
        @endif

        @if ($entry['see_also'])
            <h2 id="see-also" class="{{ $h2 }}">See also</h2>
            <ul role="list" class="mt-4 flex flex-wrap gap-2">
                @foreach ($entry['see_also'] as $link)
                    <li><a href="{{ $link['url'] }}" class="inline-block rounded-lg border border-slate-200 px-3 py-1.5 font-mono text-sm text-sky-600 hover:border-sky-500 dark:border-slate-700 dark:text-sky-400">{{ $link['title'] }}</a></li>
                @endforeach
            </ul>
        @endif
    </article>
@endsection

@section('toc')
    @include('docs.partials.toc', ['items' => $toc])
@endsection
