@props([
    'name' => 'timezone',
    'selected' => null,
    'required' => false,
    'placeholder' => null,
])
@php
    $selectedTimezone = old($name, $selected);
    $timezoneGroups = \App\Support\TimezoneCatalog::groups();
@endphp
<select name="{{ $name }}" data-searchable-select @required($required) {{ $attributes }}>
    @if($placeholder)<option value="">{{ $placeholder }}</option>@endif
    @foreach($timezoneGroups as $group => $timezones)
        <optgroup label="{{ $group }}">
            @foreach($timezones as $timezone)
                <option value="{{ $timezone['value'] }}" data-search-text="{{ $timezone['search'] }}" @selected($selectedTimezone === $timezone['value'])>{{ $timezone['label'] }}</option>
            @endforeach
        </optgroup>
    @endforeach
</select>
