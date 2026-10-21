@php
    $user = auth()->user();
    $menuEntries = collect(app(\App\Services\NavigationLayout::class)->entries('staff', 'sidebar', $user, []));
    $groups = $menuEntries->groupBy('group');
@endphp
@if ($user)
    @foreach ($groups as $group => $items)
        <div class="workspace-nav-group"><p>{{ $group }}</p>
        @foreach ($items as $item)
            <a href="{{ $item['url'] }}" wire:navigate.hover @if(request()->routeIs($item['match'], $item['match'].'.*') && (!str_starts_with($item['key'], 'page:') || request()->route('slug') === str($item['key'])->after(':'))) aria-current="page" @endif><x-ui.icon :name="$item['icon']"/><span>{{ $item['label'] }}</span></a>
        @endforeach
        </div>
    @endforeach
@endif