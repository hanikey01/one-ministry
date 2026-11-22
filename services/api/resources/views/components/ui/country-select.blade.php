@props(['name' => 'country_code', 'selected' => null, 'required' => false, 'placeholder' => 'Choose a country'])
@php($countries = app(\App\Support\AddressCatalog::class)->countries())
<select name="{{ $name }}" data-searchable-select data-address-country autocomplete="country" @required($required) {{ $attributes }}>
    <option value="">{{ $placeholder }}</option>
    @foreach($countries as $code => $label)
        <option value="{{ $code }}" data-search-text="{{ $label }} {{ $code }}" @selected((string) $selected === (string) $code)>{{ $label }} ({{ $code }})</option>
    @endforeach
</select>
