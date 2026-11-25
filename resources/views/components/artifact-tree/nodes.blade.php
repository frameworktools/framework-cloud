@props(['children', 'prefix'])
@php
    use App\Support\Trees\ArtifactTree;

    $badge = function (array $node): ?string {
        $parts = [];
        if (isset($node['private'])) {
            $parts[] = 'private';
        }
        if (isset($node['ignored'])) {
            $parts[] = 'ignored';
            $parts[] = $node['ignored']['rule'];
        }
        if (isset($node['hidden'])) {
            $parts[] = ArtifactTree::fileCount($node['hidden']);
        }

        return $parts === [] ? null : implode(' · ', $parts);
    };
    $last = count($children) - 1;
@endphp
@foreach ($children as $index => $node)
    @php
        $end = $index === $last;
        $folder = $node['type'] === 'dir' && ! isset($node['hidden']) && $node['children'] !== [];
        // Folders and files share one guide; a folder's chevron follows its name
        $guide = $prefix.($end ? '└── ' : '├── ');
        $label = $badge($node);
        $source = isset($node['ignored']) ? ArtifactTree::ruleSource($node['ignored']) : null;
        $row = 'tree-row'.(isset($node['hidden']) || $node['type'] === 'ignored' ? ' tree-row--muted' : '').($folder ? ' tree-row--dir' : '').(isset($node['highlight']) ? ' tree-row--highlight' : '');
        $name = $node['name'].($node['type'] === 'dir' ? '/' : '');
    @endphp
    @if ($folder)
        <details class="tree-dir" @if ($node['open']) open @endif>
            <summary class="{{ $row }}"><span class="tree-name"><span class="tree-guide" aria-hidden="true">{{ $guide }}</span>{{ $name }}</span>@isset($node['note'])<span class="tree-note"># {{ $node['note'] }}</span>@endisset</summary>
            <x-artifact-tree.nodes :children="$node['children']" :prefix="$prefix.($end ? '    ' : '│   ')" />
        </details>
    @else
        <div class="{{ $row }}"><span class="tree-name"><span class="tree-guide" aria-hidden="true">{{ $guide }}</span>{{ $name }}</span>@if ($label)<span class="tree-badge" @if ($source) title="{{ $source }}" @endif># {{ $label }}</span>@endif @isset($node['note'])<span class="tree-note"># {{ $node['note'] }}</span>@endisset</div>
    @endif
@endforeach
