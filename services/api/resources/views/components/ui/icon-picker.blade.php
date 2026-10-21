@props(['name', 'value' => '', 'label' => 'Choose icon', 'icons', 'id', 'disabled' => false])
<div class="icon-picker" data-icon-picker data-disabled="{{ $disabled ? 'true' : 'false' }}">
    <input type="hidden" id="{{ $id }}-value" name="{{ $name }}" value="{{ $value }}" data-icon-picker-value data-navigation-icon @disabled($disabled)>
    <button class="icon-picker-trigger" type="button" id="{{ $id }}" aria-label="{{ $label }}: {{ $value ? str($value)->headline() : 'Choose icon' }}" aria-haspopup="dialog" aria-expanded="false" aria-controls="{{ $id }}-panel" data-icon-picker-trigger @disabled($disabled)>
        <span class="icon-picker-trigger-icon" data-icon-picker-current>@if($value)<x-ui.icon :name="$value" :size="19"/>@else<x-ui.icon name="sparkles" :size="19"/>@endif</span>
        <span class="icon-picker-trigger-label" data-icon-picker-label>{{ $value ? str($value)->headline() : 'Choose icon' }}</span>
        <x-ui.icon name="chevron-down" :size="15"/>
    </button>
    <div class="icon-picker-panel" id="{{ $id }}-panel" role="dialog" aria-label="{{ $label }}" hidden data-icon-picker-panel>
        <label class="icon-picker-search"><x-ui.icon name="search" :size="16"/><input type="search" placeholder="Search {{ count($icons) }} icons" aria-label="Search icons" data-icon-picker-search></label>
        <div class="icon-picker-grid" role="grid" aria-label="Available icons" data-icon-picker-grid>
            <p class="icon-picker-empty" data-icon-picker-empty hidden>No icons match your search.</p>
        </div>
    </div>
</div>
