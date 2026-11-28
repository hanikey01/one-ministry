@props([
    'instant',
    'sourceTimezone' => config('app.timezone', 'UTC'),
    'format' => 'long',
    'showSource' => true,
])
@php
    $sourceZone = $sourceTimezone ?: config('app.timezone', 'UTC');
    $moment = \Carbon\CarbonImmutable::parse($instant)->utc();
    $sourceMoment = $moment->setTimezone($sourceZone);
    $sourceFormatted = match ($format) {
        'compact' => $sourceMoment->format('D, M j · g:i A'),
        'time' => $sourceMoment->format('g:i A'),
        default => $sourceMoment->format('l, F j · g:i A'),
    };
    $sourceLabel = str_replace('_', ' ', $sourceZone);
@endphp
<time {{ $attributes->class('viewer-time') }} datetime="{{ $moment->toIso8601String() }}" data-viewer-time data-viewer-time-format="{{ $format }}" data-source-timezone="{{ $sourceZone }}" data-source-formatted="{{ $sourceFormatted }}">
    <span data-viewer-time-primary>{{ $sourceFormatted }} · {{ $sourceLabel }}</span>
    @if($showSource)<small data-viewer-time-source hidden>Ministry time: {{ $sourceFormatted }} · {{ $sourceLabel }}</small>@endif
</time>
