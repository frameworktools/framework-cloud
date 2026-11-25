@props(['tree'])
@php
    use App\Support\Trees\ArtifactTree;

    $data = $tree['tree'];
    $base = $data['base'] ?? '';
    // The folders above the published one, as a breadcrumb
    $crumb = $base !== '' && str_contains($base, '/') ? substr($base, 0, strrpos($base, '/') + 1) : '';
@endphp
<figure class="min-w-0 overflow-x-auto rounded-xl border border-slate-800/10 bg-white p-4 dark:border-white/15 dark:bg-slate-950" data-tree aria-label="{{ ArtifactTree::alt($tree) }}">
    <div class="tree font-mono text-[0.8125rem] leading-6 text-slate-800 dark:text-slate-200" style="--tree-names: {{ ArtifactTree::nameWidth($tree) }}ch">
        <div class="tree-row tree-row--root"><span class="tree-name">@if ($crumb !== '')<span class="tree-crumb">{{ $crumb }}</span>@endif{{ $data['root'] }}/</span></div>
        <x-artifact-tree.nodes :children="$data['children']" prefix="" />
    </div>
</figure>
