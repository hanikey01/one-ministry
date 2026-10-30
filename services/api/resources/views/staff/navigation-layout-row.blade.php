@php($fieldKey = str_replace([':', '.'], '_', $key))
@php($configuredIcon = is_array($configured) ? ($configured['icon'] ?? $option['icon']) : $option['icon'])
@php($configuredGroup = is_array($configured) ? ($configured['group'] ?? $option['group']) : $option['group'])
<article class="navigation-destination-row" data-navigation-row data-selected="{{ $selected ? 'true' : 'false' }}" data-search-text="{{ mb_strtolower($option['label']) }}">
    <input id="menu-choice-{{ $surface }}-{{ $menuSlot }}-{{ $fieldKey }}" type="checkbox" name="menus[{{ $surface }}][{{ $menuSlot }}][]" value="{{ $key }}" data-navigation-checkbox @checked($selected) @disabled($fixed)>
    @if($fixed)<input type="hidden" name="menus[{{ $surface }}][{{ $menuSlot }}][]" value="{{ $key }}">@endif
    <div class="navigation-destination-label">
        <span class="navigation-destination-icon"><x-ui.icon :name="$configuredIcon" :size="17"/></span>
        <span class="navigation-destination-copy"><strong>{{ $option['label'] }}</strong>@if($fixed)<small>Required for content managers</small>@elseif(str_starts_with($key, 'page:'))<small>Published page</small>@endif</span>
    </div>
    <div class="navigation-destination-controls">
        <div class="navigation-move-controls" @if(!$selected) hidden @endif>
            <button class="navigation-move-button navigation-move-button-up" type="button" data-move-up aria-label="Move {{ $option['label'] }} up"><x-ui.icon name="chevron-down" :size="15"/></button>
            <button class="navigation-move-button navigation-move-button-down" type="button" data-move-down aria-label="Move {{ $option['label'] }} down"><x-ui.icon name="chevron-down" :size="15"/></button>
        </div>
        @if($menuSlot === 'sidebar')
            <div class="navigation-section-control" data-navigation-section-control @if(!$selected) hidden @endif>
                <label class="sr-only" for="navigation-section-{{ $surface }}-{{ $fieldKey }}">Section for {{ $option['label'] }}</label>
                <select id="navigation-section-{{ $surface }}-{{ $fieldKey }}" name="groups[{{ $surface }}][sidebar][{{ $fieldKey }}]" aria-label="Section for {{ $option['label'] }}" title="{{ $configuredGroup }}" data-navigation-group @disabled(!$selected)>
                    @foreach($sidebarSections as $section)
                        <option value="{{ $section }}" @selected($configuredGroup === $section)>{{ $section }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="navigation-icon-control" @if(!$selected) hidden @endif>
            <x-ui.icon-picker :name="'icons['.$surface.']['.$menuSlot.']['.$fieldKey.']'" :value="$configuredIcon" :label="$option['label'].' icon'" :icons="$icons" :id="'navigation-icon-'.$surface.'-'.$menuSlot.'-'.$fieldKey" :disabled="!$selected"/>
        </div>
        <span class="navigation-order-display" data-navigation-order-display>{{ $position ? str_pad((string) $position, 2, '0', STR_PAD_LEFT) : '' }}</span>
        <input type="hidden" name="positions[{{ $surface }}][{{ $menuSlot }}][{{ $fieldKey }}]" value="{{ $position ?? '' }}" data-navigation-position @disabled(!$selected)>
    </div>
</article>
