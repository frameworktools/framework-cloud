@props(['panels', 'files' => [], 'helper' => false, 'companions' => [], 'arrow' => false, 'title' => 'Example project', 'embedded' => false, 'expandAgents' => false, 'flatFiles' => false, 'compact' => false, 'maxHeight' => 'max-h-[38rem]', 'inTree' => false, 'openFolders' => null, 'priority' => ['settings.yml', 'settings/', 'inputs/', 'scripts/', 'functions/', 'notebooks/', 'outputs/', 'AGENTS.md', '.gitignore']])
<div data-project-editor class="{{ $compact ? 'project-editor-compact' : '' }} min-w-0 overflow-hidden {{ $embedded ? '' : 'rounded-xl ring-1 ring-slate-400/20' }} bg-[#0c1526] text-slate-100">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/10 px-3 py-2">
        <div class="{{ $embedded ? 'sr-only' : 'flex gap-1' }}" aria-label="{{ $title }} language">
            @foreach ($panels as $language => $panel)
                <button type="button" data-project-language="{{ $language }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" class="rounded-md px-3 py-1.5 text-xs text-slate-400 hover:text-white aria-pressed:bg-white/10 aria-pressed:text-white">{{ $panel['label'] }}</button>
            @endforeach
        </div>
        <span class="flex min-w-0 items-center gap-1.5 text-slate-400"><span data-project-terminal aria-hidden="true"><x-heroicon-o-command-line class="size-3.5 shrink-0" /></span><span data-project-path class="max-w-full truncate font-mono text-[11px]">{{ reset($panels)['path'] }}</span></span>
    </div>
    <div class="grid min-w-0 md:grid-cols-[10.5rem_minmax(0,1fr)]">
        <aside class="min-w-0 border-b border-white/10 bg-white/[0.02] md:border-r md:border-b-0" aria-label="Project files">
            <div class="px-3 py-3">
                <div class="mb-2 flex items-center gap-2 text-xs font-medium text-slate-400">@if ($arrow)<x-heroicon-o-arrow-right class="size-3.5" aria-hidden="true" />@endif Project files</div>
                @foreach ($panels as $language => $panel)
                    @php $fileList = $files[$language] ?? []; ksort($fileList); @endphp
                    <div data-project-files="{{ $language }}" @unless ($loop->first) hidden @endunless class="font-mono text-[11px] leading-[18px]">
                        @unless ($inTree)
                        <button type="button" data-project-open="main" aria-pressed="true" class="mb-1 flex min-h-6 w-full items-center gap-1.5 truncate rounded px-1.5 py-0.5 text-left text-slate-400 hover:bg-white/5 hover:text-white aria-pressed:bg-white/5 aria-pressed:text-emerald-300">@if (str_contains(strtolower($panel['path']), 'console') || $panel['path'] === 'Terminal')<x-heroicon-o-command-line class="size-3 shrink-0" aria-hidden="true" />@endif {{ str_ends_with($panel['path'], '/SKILL.md') ? basename(dirname($panel['path'])) : basename($panel['path']) }}</button>
                        @endunless
                        @php
                            $tree = [];
                            // Keep source and settings visible before the optional agent files.
                            $rank = function ($path) use ($priority) {
                                foreach ($priority as $index => $prefix) {
                                    if (str_starts_with($path, $prefix)) return $index;
                                }
                                return count($priority);
                            };
                            uksort($fileList, fn ($a, $b) => ($rank($a) <=> $rank($b)) ?: strcmp($a, $b));
                            foreach ($fileList as $path => $source) {
                                // A path ending in / is an empty folder
                                $folderOnly = str_ends_with($path, '/');
                                $parts = explode('/', rtrim($path, '/'));
                                $branch = &$tree;
                                foreach ($parts as $partIndex => $part) {
                                    if ($partIndex === count($parts) - 1 && ! $folderOnly) {
                                        $branch[$part] = ['path' => $path];
                                    } else {
                                        $branch[$part] ??= ['children' => []];
                                        $branch = &$branch[$part]['children'];
                                    }
                                }
                                unset($branch);
                            }
                            // In the starter demo, folders come first at every level, as in a file browser
                            if ($inTree) {
                                $sortTree = function (array $nodes) use (&$sortTree) {
                                    uksort($nodes, fn ($a, $b) => (isset($nodes[$a]['path']) <=> isset($nodes[$b]['path'])) ?: strcasecmp(ltrim($a, '._'), ltrim($b, '._')));
                                    return array_map(fn ($node) => isset($node['children']) ? ['children' => $sortTree($node['children'])] : $node, $nodes);
                                };
                                $tree = $sortTree($tree);
                            }
                        @endphp
                        @if ($flatFiles)
                            @foreach ($fileList as $path => $source)
                                <button type="button" data-project-open="{{ $path }}" aria-pressed="false" title="{{ $path }}" class="flex w-full items-center gap-1.5 rounded px-1.5 py-1 text-left text-slate-400 hover:text-white aria-pressed:bg-white/5 aria-pressed:text-emerald-300"><x-heroicon-o-document class="size-3 shrink-0" /><span class="truncate">{{ str_ends_with($path, '/SKILL.md') ? basename(dirname($path)) : basename($path) }}</span></button>
                            @endforeach
                        @else
                            <x-landing-files :nodes="$tree" :expand-agents="$expandAgents" :main="$inTree ? $panel['path'] : null" :open-folders="$openFolders" />
                        @endif
                        <p class="mt-1 px-1.5 text-base text-slate-500" title="Additional project files" aria-label="Additional project files">…</p>
                    </div>
                @endforeach
                <p class="mt-3 text-[11px] leading-4 text-slate-500">Select a file to inspect it.</p>
            </div>
        </aside>
        <div class="min-w-0">
            @foreach ($panels as $language => $panel)
                @php $sources = ['main' => ['path' => $panel['path'], 'code' => $panel['code']], ...collect($files[$language] ?? [])->reject(fn ($code, $path) => str_ends_with($path, '/'))->mapWithKeys(fn ($code, $path) => [$path => ['path' => $path, 'code' => $code]])->all()]; @endphp
                @foreach ($sources as $key => $source)
                    <pre data-project-content="{{ $key === 'main' ? '' : \App\Support\LandingProject::registerSource($source['code']) }}" data-project-source="{{ $key }}" data-project-source-language="{{ $language }}" data-project-source-path="{{ $source['path'] }}" @unless ($loop->parent->first && $loop->first) hidden @endunless class="project-code {{ $maxHeight }} overflow-y-auto px-3 py-5 font-mono text-[12px] leading-[18px] text-slate-200"><code>@if ($key === 'main'){!! \App\Support\ExampleCode::highlight($source['code']) !!}@endif</code></pre>
                @endforeach
            @endforeach
            @if ($helper)
                @foreach ($panels as $language => $panel)
                    @php $path = 'functions/data_prep.'.($language === 'py' ? 'py' : 'R'); @endphp
                    @continue(! isset($files[$language][$path]))
                    <div data-project-helper="{{ $language }}" @unless ($loop->first) hidden @endunless class="border-t border-white/10 bg-white/[0.02]">
                        <p class="border-b border-white/10 px-3 py-2 font-mono text-[11px] text-slate-400">{{ $path }}</p>
                        <pre class="project-code px-3 py-4 font-mono text-[12px] leading-[18px]"><code>{!! \App\Support\ExampleCode::highlight($files[$language][$path]) !!}</code></pre>
                    </div>
                @endforeach
            @endif
            @foreach ($companions as $language => $documents)
                @foreach ($documents as $forFile => $document)
                    <div data-project-helper="{{ $language }}" data-project-helper-for="{{ $forFile }}" @unless ($loop->parent->first && $forFile === 'main') hidden @endunless class="border-t border-white/10 bg-white/[0.02]">
                        <p class="border-b border-white/10 px-3 py-2 font-mono text-[11px] text-slate-400">{{ $document['path'] }}</p>
                        <pre class="project-code px-3 py-4 font-mono text-[12px] leading-[18px]"><code>{!! \App\Support\ExampleCode::highlight($document['code']) !!}</code></pre>
                    </div>
                @endforeach
            @endforeach

        </div>
    </div>
</div>
