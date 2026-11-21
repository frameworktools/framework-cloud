@props(['dag'])
@php
    use App\Support\Dags\ArtifactDag;
    use App\Support\Dags\DagLayout;
    use App\Support\Snippets\StyledText;

    $graph = $dag['dag'];
    $labels = [];
    foreach ($graph['nodes'] as $node) {
        $labels[$node['name']] = $node['label'] ?? $node['name'];
    }
    $roles = array_values(array_intersect(array_keys(ArtifactDag::ROLE_LABELS), collect($graph['nodes'])->pluck('roles')->flatten()->unique()->all()));
    $analysis = $dag['analysis'] ?? null;
    $names = fn (array $list) => implode(', ', array_map(fn ($name) => '<code class="font-mono text-[0.8125rem] text-slate-900 dark:text-slate-100">'.e($labels[$name] ?? $name).'</code>', $list));
    $panels = [['label' => 'DAGitty', 'text' => $dag['dagitty']['text'], 'lines' => $dag['dagitty']['lines']]];
    if (isset($dag['code'])) {
        $panels[] = ['label' => ArtifactDag::LANGUAGES[$dag['code']['language']] ?? 'Code', 'text' => $dag['code']['text'], 'lines' => $dag['code']['lines']];
    }
    $button = 'ml-auto inline-flex h-6 items-center gap-1 rounded-md px-2 font-medium text-slate-600 hover:bg-slate-800/5 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white';
@endphp
<div class="flex min-w-0 flex-col gap-8" data-dag>
    <figure class="min-w-0 overflow-x-auto rounded-xl border border-slate-800/10 bg-white p-4 dark:border-white/15 dark:bg-slate-950">
        <svg class="dag-graph mx-auto block h-auto max-w-full" viewBox="0 0 {{ $graph['width'] }} {{ $graph['height'] }}" width="{{ $graph['width'] }}" height="{{ $graph['height'] }}" style="min-width: {{ intdiv($graph['width'] * 3, 4) }}px" role="img" aria-label="{{ ArtifactDag::alt($dag) }}">
            @foreach ($graph['edges'] as $edge)
                <path class="dag-edge{{ $edge['type'] === '<->' ? ' dag-edge--bidirected' : '' }}" d="{{ $edge['path'] }}" />
                @foreach ($edge['heads'] as $head)
                    <path class="dag-head" d="{{ $head }}" />
                @endforeach
            @endforeach
            @foreach ($graph['nodes'] as $node)
                @php($shown = DagLayout::shownLabel($labels[$node['name']]))
                <g class="dag-node {{ collect($node['roles'])->map(fn ($role) => 'dag-node--'.$role)->implode(' ') }}">
                    @if ($shown !== $labels[$node['name']])<title>{{ $labels[$node['name']] }}</title>@endif
                    <rect x="{{ $node['x'] }}" y="{{ $node['y'] }}" width="{{ $node['width'] }}" height="{{ $node['height'] }}" rx="6" />
                    <text x="{{ $node['x'] + intdiv($node['width'], 2) }}" y="{{ $node['y'] + intdiv($node['height'], 2) }}">{{ $shown }}</text>
                </g>
            @endforeach
        </svg>
        @if ($roles !== [])
            <figcaption class="mt-3 flex flex-wrap justify-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                @foreach ($roles as $role)
                    <span class="inline-flex items-center gap-1.5"><span class="dag-swatch dag-swatch--{{ $role }}" aria-hidden="true"></span>{{ ArtifactDag::ROLE_LABELS[$role] }}</span>
                @endforeach
            </figcaption>
        @endif
    </figure>

    @if ($analysis && (isset($analysis['adjustment_sets']) || ! empty($analysis['implications'])))
        <section class="flex flex-col gap-4 text-sm text-slate-700 dark:text-slate-300">
            @isset($analysis['adjustment_sets'])
                <div>
                    <h2 class="text-base font-medium text-slate-900 dark:text-slate-100">Adjustment sets</h2>
                    <p class="mt-1 text-slate-500 dark:text-slate-400">Minimal sufficient adjustment sets for the {{ $analysis['effect'] }} effect of {!! $names($analysis['exposures']) !!} on {!! $names($analysis['outcomes']) !!}.</p>
                    @if ($analysis['adjustment_sets'] === [])
                        <p class="mt-2">No set of observed variables identifies the effect.</p>
                    @elseif ($analysis['adjustment_sets'] === [[]])
                        <p class="mt-2">No adjustment is needed.</p>
                    @else
                        <ul class="mt-2 flex flex-col gap-1">
                            @foreach ($analysis['adjustment_sets'] as $set)
                                <li>{ {!! $names($set) !!} }</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endisset
            @if (! empty($analysis['implications']))
                <div>
                    <h2 class="text-base font-medium text-slate-900 dark:text-slate-100">Testable implications</h2>
                    <ul class="mt-2 flex flex-col gap-1">
                        @foreach ($analysis['implications'] as $implication)
                            <li>{!! $names([$implication['x']]) !!} ⫫ {!! $names([$implication['y']]) !!}@if ($implication['given'] !== []) | {!! $names($implication['given']) !!}@endif</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    @endif

    <section class="flex flex-col gap-3">
        <h2 class="text-base font-medium text-slate-900 dark:text-slate-100">Code</h2>
        @foreach ($panels as $panel)
            <figure class="min-w-0 overflow-hidden rounded-xl border border-slate-800/10 bg-white dark:border-white/15 dark:bg-slate-950" data-code-panel>
                <figcaption class="flex items-center gap-3 border-b border-slate-800/10 px-4 py-2 text-xs text-slate-500 dark:border-white/15 dark:text-slate-400">
                    <span class="font-medium text-slate-700 dark:text-slate-200">{{ $panel['label'] }}</span>
                    <button type="button" data-copy-code class="{{ $button }}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-9.201a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" /></svg>
                        <span data-copy-label>Copy code</span>
                    </button>
                </figcaption>
                <pre class="snippet-code overflow-x-auto bg-slate-50 px-4 py-3 font-mono text-[0.8125rem] leading-relaxed whitespace-pre text-slate-800 dark:bg-slate-900 dark:text-slate-200" data-code><code class="block">{!! StyledText::html($panel['lines']) !!}</code></pre>
            </figure>
        @endforeach
    </section>
</div>
<script>
    // Each panel copies its own code
    (() => {
        const root = document.currentScript.previousElementSibling;
        root.querySelectorAll('[data-code-panel]').forEach((panel) => {
            const button = panel.querySelector('[data-copy-code]');
            const label = button.querySelector('[data-copy-label]');
            button.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(panel.querySelector('[data-code]').textContent);
                    label.textContent = 'Copied';
                } catch {
                    label.textContent = 'Copy failed';
                }
                setTimeout(() => { label.textContent = 'Copy code'; }, 2000);
            });
        });
    })();
</script>
