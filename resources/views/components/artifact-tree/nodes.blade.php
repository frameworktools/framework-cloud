@props(['children'])
@php
    use App\Support\Trees\ArtifactTree;

    $badge = function (array $node): ?string {
        $parts = [];
        if (isset($node['private'])) {
            $parts[] = 'private';
        }
        if (isset($node['ignored'])) {
            $parts[] = $node['type'] === 'ignored' ? 'ignored' : 'ignored · '.$node['ignored']['rule'];
        }
        if (isset($node['hidden'])) {
            $parts[] = ArtifactTree::fileCount($node['hidden']);
        }

        return $parts === [] ? null : implode(' · ', $parts);
    };
@endphp
<ul class="tree-list">
    @foreach ($children as $node)
        @php
            $stub = isset($node['hidden']);
            $label = $badge($node);
            $source = isset($node['ignored']) ? ArtifactTree::ruleSource($node['ignored']) : null;
            $row = 'tree-row'.($stub || $node['type'] === 'ignored' ? ' tree-row--muted' : '').(isset($node['highlight']) ? ' tree-row--highlight' : '');
        @endphp
        <li>
            @if ($node['type'] === 'dir' && ! $stub && $node['children'] !== [])
                <details @if ($node['open']) open @endif>
                    <summary class="{{ $row }}">
                        <span class="tree-name">{{ $node['name'] }}/</span>
                        @isset($node['note'])<span class="tree-note">{{ $node['note'] }}</span>@endisset
                    </summary>
                    <x-artifact-tree.nodes :children="$node['children']" />
                </details>
            @else
                <div class="{{ $row }}">
                    <span class="tree-name">{{ $node['name'] }}{{ $node['type'] === 'dir' ? '/' : '' }}</span>
                    @if ($label)<span class="tree-badge" @if ($source) title="{{ $source }}" @endif>{{ $label }}</span>@endif
                    @isset($node['note'])<span class="tree-note">{{ $node['note'] }}</span>@endisset
                </div>
            @endif
        </li>
    @endforeach
</ul>
