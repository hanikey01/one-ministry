<article class="navigation-item" draggable="true" data-navigation-id="{{ $item->id }}">
    <div class="navigation-item-summary">
        <span class="navigation-drag" aria-hidden="true" title="Drag to reorder">⠿</span>
        <span class="navigation-public-icon">@if($item->icon)<x-ui.icon :name="$item->icon" :size="17"/>@endif</span>
        <div class="navigation-public-copy"><strong>{{ $item->label }}</strong><small>{{ $item->url }} @if($item->parent_id) · Dropdown item @endif @if($item->required_feature) · {{ str($item->required_feature)->headline() }} module @endif</small></div>
        <span class="table-status">{{ $item->visible_desktop ? 'Desktop' : 'Hidden desktop' }} · {{ $item->visible_mobile ? 'Mobile' : 'Hidden mobile' }}</span>
        <div class="navigation-public-order" aria-label="Reorder {{ $item->label }}"><button type="button" data-public-move="up" aria-label="Move {{ $item->label }} up">↑</button><button type="button" data-public-move="down" aria-label="Move {{ $item->label }} down">↓</button></div>
        <details data-dismiss-on-outside>
            <summary>Edit</summary>
            <form method="POST" action="{{ route('staff.navigation.update', $item->id) }}">@csrf @method('PUT') @include('staff.navigation-fields', ['item' => $item])<button class="button" type="submit">Save link</button></form>
            <form method="POST" action="{{ route('staff.navigation.destroy', $item->id) }}">@csrf @method('DELETE')<button class="button button-secondary" type="submit">Remove link</button></form>
        </details>
    </div>
</article>
