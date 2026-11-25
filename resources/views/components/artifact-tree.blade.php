@props(['tree'])
@php
    use App\Support\Trees\ArtifactTree;

    $data = $tree['tree'];
@endphp
<figure class="min-w-0 overflow-x-auto rounded-xl border border-slate-800/10 bg-white p-4 dark:border-white/15 dark:bg-slate-950" data-tree aria-label="{{ ArtifactTree::alt($tree) }}">
    <div class="tree font-mono text-[0.8125rem] leading-6 text-slate-800 dark:text-slate-200">
        <div class="flex items-baseline gap-2 font-medium text-slate-900 dark:text-slate-100">
            <span>{{ $data['root'] }}/</span>
            @if (($data['base'] ?? '') !== '')
                <span class="font-sans text-xs font-normal text-slate-500 dark:text-slate-400">{{ $data['base'] }}</span>
            @endif
        </div>
        <x-artifact-tree.nodes :children="$data['children']" />
    </div>
</figure>
