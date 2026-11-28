@php($value = fn ($key, $default = '') => old($key, $item?->{$key} ?? $default))
<label>Label<input name="label" maxlength="80" required value="{{ $value('label') }}" placeholder="Plan your visit"></label>
<label>Destination<input name="url" maxlength="1200" required value="{{ $value('url') }}" placeholder="# or /pages/visit or https://…"><small>Use # for a parent menu that only expands its child links.</small></label>
@php($parentFieldId = 'public-navigation-parent-'.($item?->id ?? 'new'))
@php($featureFieldId = 'public-navigation-feature-'.($item?->id ?? 'new'))
<div class="navigation-field-grid">
    @if($showIcon ?? true)
        <div class="navigation-form-field"><span class="navigation-form-label">Icon</span><x-ui.icon-picker name="icon" :value="$value('icon')" label="Menu icon" :icons="$icons" :id="'public-navigation-icon-'.($item?->id ?? 'new')"/><span class="navigation-form-hint">Choose from {{ count($icons) }} icons.</span></div>
    @endif
    <div class="navigation-form-field"><label class="navigation-form-label" for="{{ $parentFieldId }}">Parent link</label><select id="{{ $parentFieldId }}" name="parent_id"><option value="">Top level</option>@foreach($items->whereNull('parent_id')->where('id','!=',$item?->id) as $parent)<option value="{{ $parent->id }}" @selected($value('parent_id') === $parent->id)>{{ $parent->label }}</option>@endforeach</select></div>
</div>
<div class="navigation-form-field"><label class="navigation-form-label" for="{{ $featureFieldId }}">Required module</label><select id="{{ $featureFieldId }}" name="required_feature"><option value="">Always available</option>@foreach($features as $feature)<option value="{{ $feature }}" @selected($value('required_feature') === $feature)>{{ str($feature)->headline() }}</option>@endforeach</select></div>
<div class="navigation-checks"><label><input type="checkbox" name="visible_desktop" value="1" @checked(old('visible_desktop',$item->visible_desktop ?? true))> Public sidebar</label><label><input type="checkbox" name="visible_mobile" value="1" @checked(old('visible_mobile',$item->visible_mobile ?? true))> Public mobile footer</label><label><input type="checkbox" name="open_new_tab" value="1" @checked(old('open_new_tab',$item->open_new_tab ?? false))> Open new tab</label></div><small>The mobile footer shows the first five enabled links in menu order.</small>
