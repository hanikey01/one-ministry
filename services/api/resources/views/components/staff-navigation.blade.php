@php
    $user = auth()->user();
    $features = \App\Models\FeatureSetting::query()->pluck('key')->all();
    $menuEntries = collect(app(\App\Services\NavigationLayout::class)->treeEntries('staff', 'sidebar', $user, $features));
    $groups = $menuEntries->groupBy('group');
@endphp
@if ($user)
    @foreach ($groups as $group => $items)
        <div class="workspace-nav-group"><p>{{ $group }}</p>
        @foreach ($items as $item) @include('components.workspace-navigation-node', ['node' => $item]) @endforeach
        </div>
    @endforeach
@endif
