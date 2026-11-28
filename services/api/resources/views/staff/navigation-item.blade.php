@php($enabled = (bool) ($item->is_enabled ?? true))
@php($parentLabel = $item->parent_id ? optional($items->firstWhere('id', $item->parent_id))->label : null)
<article class="navigation-destination-row navigation-public-destination" data-navigation-row data-navigation-id="{{ $item->id }}" data-selected="{{ $enabled ? 'true' : 'false' }}" data-nested="{{ $item->parent_id ? 'true' : 'false' }}">
    <input id="public-menu-choice-{{ $item->id }}" type="checkbox" name="enabled[]" value="{{ $item->id }}" aria-label="Include {{ $item->label }} in the public menu" data-public-enabled @checked($enabled)>
    <div class="navigation-destination-label">
        <span class="navigation-destination-icon"><x-ui.icon :name="$item->icon ?: 'external'" :size="17"/></span>
        <span class="navigation-destination-copy"><strong>{{ $item->label }}</strong><small>{{ $item->url }}@if($parentLabel) · Under {{ $parentLabel }} @endif @if($item->required_feature) · {{ str($item->required_feature)->headline() }} module @endif</small></span>
    </div>
    <div class="navigation-destination-controls" data-public-controls @if(!$enabled) hidden @endif>
        <div class="navigation-move-controls" aria-label="Reorder {{ $item->label }}">
            <button class="navigation-move-button navigation-move-button-up" type="button" data-public-move="up" aria-label="Move {{ $item->label }} up"><x-ui.icon name="chevron-down" :size="15"/></button>
            <button class="navigation-move-button navigation-move-button-down" type="button" data-public-move="down" aria-label="Move {{ $item->label }} down"><x-ui.icon name="chevron-down" :size="15"/></button>
        </div>
        <div class="navigation-parent-control navigation-public-parent">
            <label class="sr-only" for="public-navigation-parent-{{ $item->id }}">Parent menu for {{ $item->label }}</label>
            <select id="public-navigation-parent-{{ $item->id }}" name="parents[{{ $item->id }}]" aria-label="Parent menu for {{ $item->label }}" title="Parent menu">
                <option value="">Top level</option>
                @foreach($items as $parentItem)@if($parentItem->id !== $item->id)<option value="{{ $parentItem->id }}" @selected($item->parent_id === $parentItem->id)>Under {{ $parentItem->label }}</option>@endif @endforeach
            </select>
        </div>
        <div class="navigation-section-control navigation-public-section">
            <label class="sr-only" for="public-navigation-section-{{ $item->id }}">Section for {{ $item->label }}</label>
            <select id="public-navigation-section-{{ $item->id }}" name="sections[{{ $item->id }}]" aria-label="Section for {{ $item->label }}" title="{{ $item->section }}">
                @foreach($publicSections as $section)<option value="{{ $section }}" @selected($item->section === $section)>{{ $section }}</option>@endforeach
            </select>
        </div>
        <div class="navigation-icon-control navigation-public-icon-control"><x-ui.icon-picker :name="'icons['.$item->id.']'" :value="$item->icon ?: 'external'" :label="$item->label.' icon'" :icons="$icons" :id="'public-navigation-icon-'.$item->id"/></div>
        <span class="navigation-order-display" data-navigation-order-display>{{ str_pad((string) $item->position, 2, '0', STR_PAD_LEFT) }}</span>
    </div>
    <input type="hidden" name="items[]" value="{{ $item->id }}">
</article>
