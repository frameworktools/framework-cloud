@props(['nodes', 'depth' => 0, 'expandAgents' => false, 'main' => null, 'openFolders' => null])
@foreach ($nodes as $name => $node)
    @if (isset($node['path']))
        <button type="button" data-project-open="{{ $node['path'] === $main ? 'main' : $node['path'] }}" aria-pressed="{{ $node['path'] === $main ? 'true' : 'false' }}" title="{{ $node['path'] }}" style="padding-left: {{ $depth * 9 + 6 }}px" class="flex w-full items-center gap-1.5 rounded py-0.5 pr-1 text-left text-slate-400 hover:bg-white/5 hover:text-white aria-pressed:bg-white/5 aria-pressed:text-emerald-300"><x-heroicon-o-document class="size-3 shrink-0 opacity-50" /><span class="truncate">{{ $name }}</span></button>
    @else
        <details @if ($openFolders === null ? ! ($name === '.claude' && ! $expandAgents) : in_array($name, $openFolders, true)) open @endif>
            <summary style="padding-left: {{ $depth * 9 + 6 }}px" class="flex cursor-pointer list-none items-center gap-1.5 py-0.5 pr-1 text-slate-500 hover:text-slate-300"><x-heroicon-o-folder class="size-3 shrink-0" /><span class="truncate">{{ $name }}</span></summary>
            <x-landing-files :nodes="$node['children']" :depth="$depth + 1" :expand-agents="$expandAgents" :main="$main" :open-folders="$openFolders" />
        </details>
    @endif
@endforeach
