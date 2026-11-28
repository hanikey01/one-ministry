@php
    $isCurrent = request()->routeIs($node['match'], $node['match'].'.*')
        && (!str_starts_with($node['key'], 'page:') || request()->route('slug') === str($node['key'])->after(':'));
    $branchIsActive = function (array $entry) use (&$branchIsActive): bool {
        $current = request()->routeIs($entry['match'], $entry['match'].'.*')
            && (!str_starts_with($entry['key'], 'page:') || request()->route('slug') === str($entry['key'])->after(':'));
        return $current || collect($entry['children'] ?? [])->contains(fn (array $child): bool => $branchIsActive($child));
    };
    $hasChildren = ! empty($node['children']);
    $activeBranch = $branchIsActive($node);
@endphp

@if($hasChildren)
    <details @class(['workspace-nav-tree', 'is-active' => $activeBranch]) @if($activeBranch) open @endif>
        <summary><x-ui.icon :name="$node['icon']"/><span>{{ $node['label'] }}</span><x-ui.icon name="chevron-down" :size="14"/></summary>
        <div class="workspace-nav-tree-children">
            <a href="{{ $node['url'] }}" wire:navigate.hover @class(['workspace-nav-tree-overview', 'is-active' => $isCurrent]) @if($isCurrent) aria-current="page" @endif><span>Overview</span></a>
            @foreach($node['children'] as $child)
                @include('components.workspace-navigation-node', ['node' => $child])
            @endforeach
        </div>
    </details>
@else
    <a href="{{ $node['url'] }}" wire:navigate.hover @class(['is-active' => $isCurrent]) @if($isCurrent) aria-current="page" @endif><x-ui.icon :name="$node['icon']"/><span>{{ $node['label'] }}</span></a>
@endif
