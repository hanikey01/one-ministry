@props(['name' => 'region_code', 'country' => null, 'selected' => null, 'required' => false, 'placeholder' => 'Choose a state or region'])
@php($subdivisions = filled($country) ? app(\App\Support\AddressCatalog::class)->subdivisions((string) $country) : [])
<select name="{{ $name }}" data-searchable-select data-address-subdivision autocomplete="address-level1" @required($required) @disabled(blank($country)) {{ $attributes }}>
    <option value="">{{ blank($country) ? 'Choose a country first' : ($subdivisions === [] ? 'No listed state or region' : $placeholder) }}</option>
    @foreach($subdivisions as $code => $label)
        <option value="{{ $code }}" data-search-text="{{ $label }} {{ $code }}" @selected((string) $selected === (string) $code)>{{ $label }}</option>
    @endforeach
</select>
