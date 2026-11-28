@php
    $itemUrl = $guestDestination($node['url']);
    $isCurrent = $isActivePublicUrl($itemUrl);
    $branchIsActive = function (array $entry) use (&$branchIsActive, $guestDestination, $isActivePublicUrl): bool {
        return $isActivePublicUrl($guestDestination($entry['url']))
            || collect($entry['children'] ?? [])->contains(fn (array $child): bool => $branchIsActive($child));
    };
    $hasChildren = ! empty($node['children']);
    $isGroupOnly = $itemUrl === '#';
    $activeBranch = $branchIsActive($node);
    $label = $terminology->navigationLabel($itemUrl, $node['label']);
@endphp

@if($hasChildren)
    <details @class(['workspace-nav-tree', 'public-nav-dropdown', 'is-active' => $activeBranch]) @if($activeBranch) open @endif>
        <summary><x-ui.icon :name="$node['icon']"/><span>{{ $label }}</span><x-ui.icon name="chevron-down" :size="14"/></summary>
        <div class="workspace-nav-tree-children">
            @unless($isGroupOnly)<a href="{{ $itemUrl }}" @class(['workspace-nav-tree-overview', 'is-active' => $isCurrent]) @if($isCurrent) aria-current="page" @endif @if(!$node['open_new_tab'] && str_starts_with($itemUrl, '/')) wire:navigate.hover @endif @if($node['open_new_tab']) target="_blank" rel="noopener noreferrer" @endif><span>Overview</span></a>@endunless
            @foreach($node['children'] as $child)
                @include('components.public-navigation-node', ['node' => $child, 'guestDestination' => $guestDestination, 'isActivePublicUrl' => $isActivePublicUrl, 'terminology' => $terminology])
            @endforeach
        </div>
    </details>
@elseif($isGroupOnly)
    <span class="workspace-nav-static" aria-disabled="true"><x-ui.icon :name="$node['icon']"/><span>{{ $label }}</span></span>
@else
    <a href="{{ $itemUrl }}" @class(['is-active' => $isCurrent]) @if($isCurrent) aria-current="page" @endif @if(!$node['open_new_tab'] && str_starts_with($itemUrl, '/')) wire:navigate.hover @endif @if($node['open_new_tab']) target="_blank" rel="noopener noreferrer" @endif><x-ui.icon :name="$node['icon']"/><span>{{ $label }}</span></a>
@endif
