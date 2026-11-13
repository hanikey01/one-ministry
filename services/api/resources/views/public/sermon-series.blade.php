@php
    $cover = $item->cover_image_path ? route('public.library.cover', ['sermon-series', $item->id]) : null;
    $fileSize = static function ($bytes): string { if (!$bytes) return ''; $units=['B','KB','MB','GB']; $power=min((int) floor(log($bytes,1024)),3); return number_format($bytes/(1024**$power),$power>1?1:0).' '.$units[$power]; };
@endphp
<x-layouts.public :title="$item->title" :settings="$settings" :features="$features">
    <a class="library-back-link" href="{{ route('public.library', 'sermon-series') }}"><x-ui.icon name="arrow-left" :size="15"/> All sermon series</a>
    <section class="series-detail-hero">
        <div class="series-detail-cover">@if($cover)<img src="{{ $cover }}" alt="{{ $item->cover_image_alt ?: $item->title.' artwork' }}">@else<x-ui.icon name="archive" :size="42"/>@endif</div>
        <div><p>TEACHING SERIES</p><h1>{{ $item->title }}</h1><span>{{ $item->description ?: 'Explore every published message in this series.' }}</span><small>{{ $messages->count() }} {{ str('message')->plural($messages->count()) }}</small></div>
    </section>
    <section class="series-message-list">
        @forelse($messages as $message)
            @php($slug = \App\Support\PublicLibraryRoute::slugFor($message->library))
            <article><div class="series-message-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div><div class="series-message-copy"><p>{{ $message->library === 'audio_sermons' ? 'AUDIO MESSAGE' : 'VIDEO MESSAGE' }}</p><h2>{{ $message->title }}</h2><span>{{ $message->speaker ?: 'Ministry speaker' }}@if($message->description) · {{ str($message->description)->limit(110) }}@endif</span>@if($message->library === 'audio_sermons')<audio controls preload="metadata" src="{{ route('public.library.stream', [$slug, $message->id]) }}"></audio>@elseif($message->provider === 'youtube')<div class="series-video-frame"><iframe src="https://www.youtube-nocookie.com/embed/{{ $message->provider_key }}" title="{{ $message->title }}" loading="lazy" allowfullscreen></iframe></div>@else<div class="series-video-frame"><video controls preload="metadata" @if($message->cover_image_path) poster="{{ route('public.library.cover', [$slug, $message->id]) }}" @endif><source src="{{ route('public.library.stream', [$slug, $message->id]) }}" type="{{ $message->file_mime }}"></video></div>@endif</div>@if($message->provider === 'local')<a class="series-download" href="{{ route('public.library.download', [$slug, $message->id]) }}"><x-ui.icon name="download" :size="15"/> Download @if($message->file_size_bytes)<small>{{ $fileSize($message->file_size_bytes) }}</small>@endif</a>@endif</article>
        @empty
            @include('public.partials.library-empty', ['type' => 'messages'])
        @endforelse
    </section>
</x-layouts.public>
