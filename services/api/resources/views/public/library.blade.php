@php
    $labels = ['books_library' => 'Books', 'audio_sermons' => 'Audio sermons', 'video_sermons' => 'Video sermons', 'sermon_series' => 'Sermon series'];
    $descriptions = [
        'books_library' => 'Thoughtful books and practical resources selected to help you learn, reflect, and grow.',
        'audio_sermons' => 'Listen anywhere, continue where you left off, or download a message for later.',
        'video_sermons' => 'Watch recent teaching and ministry messages online or save available videos offline.',
        'sermon_series' => 'Explore complete teaching collections, organized so every message is easy to find.',
    ];
    $enabled = $features ?? [];
    $coverUrl = fn ($item) => $item->cover_image_path ? route('public.library.cover', [$librarySlug, $item->id]) : null;
    $fileSize = static function ($bytes): string {
        if (!$bytes) return '';
        $units = ['B', 'KB', 'MB', 'GB']; $power = min((int) floor(log($bytes, 1024)), 3);
        return number_format($bytes / (1024 ** $power), $power > 1 ? 1 : 0).' '.$units[$power];
    };
@endphp
<x-layouts.public :title="$labels[$library]" :settings="$settings" :features="$features">
    <section class="library-hero">
        <div><p>MINISTRY LIBRARY</p><h1>{{ $labels[$library] }}</h1><span>{{ $descriptions[$library] }}</span></div>
        <div class="library-hero-mark"><x-ui.icon :name="$library === 'books_library' ? 'book' : ($library === 'audio_sermons' ? 'audio' : ($library === 'video_sermons' ? 'video' : 'archive'))" :size="30"/></div>
    </section>

    <nav class="library-switcher" aria-label="Library sections">
        @foreach(['books_library' => 'books', 'audio_sermons' => 'audio-sermons', 'video_sermons' => 'video-sermons', 'sermon_series' => 'sermon-series'] as $key => $slug)
            @if(in_array($key, $enabled, true))<a href="{{ route('public.library', $slug) }}" @if($library === $key) aria-current="page" @endif>{{ $labels[$key] }}</a>@endif
        @endforeach
    </nav>

    <section class="library-toolbar">
        <form method="GET" role="search"><label><span class="sr-only">Search {{ strtolower($labels[$library]) }}</span><x-ui.icon name="search" :size="17"/><input type="search" name="q" value="{{ $query }}" placeholder="Search {{ strtolower($labels[$library]) }}"></label>@if($category)<input type="hidden" name="category" value="{{ $category }}">@endif<button class="button" type="submit">Search</button>@if($query || $category)<a href="{{ route('public.library', $librarySlug) }}">Clear</a>@endif</form>
        <span><strong>{{ number_format($items->total()) }}</strong> {{ str('resource')->plural($items->total()) }}</span>
    </section>
    @if($categories->isNotEmpty())<nav class="library-categories" aria-label="Resource categories"><a href="{{ route('public.library', [$librarySlug, 'q' => $query ?: null]) }}" @if(!$category) aria-current="page" @endif>All</a>@foreach($categories as $categoryItem)<a href="{{ route('public.library', [$librarySlug, 'category' => $categoryItem->slug, 'q' => $query ?: null]) }}" @if($category === $categoryItem->slug) aria-current="page" @endif>{{ $categoryItem->name }}</a>@endforeach</nav>@endif

    @if($library === 'books_library')
        <section class="library-book-grid">
            @forelse($items as $item)<article class="library-book-card"><div class="library-book-cover">@if($coverUrl($item))<img src="{{ $coverUrl($item) }}" alt="{{ $item->cover_image_alt ?: $item->title.' cover' }}" loading="lazy">@else<div><x-ui.icon name="book" :size="30"/><strong>{{ mb_substr($item->title, 0, 1) }}</strong></div>@endif<span>{{ $item->category_name ?: 'Book' }}</span></div><div class="library-book-copy"><p>{{ $item->author ?: 'Ministry resource' }}</p><h2>{{ $item->title }}</h2><span>{{ str($item->description)->limit(130) ?: 'A resource selected for this ministry library.' }}</span><small>{{ $fileSize($item->file_size_bytes) }}</small><footer><a class="button" href="{{ route('public.library.download', [$librarySlug, $item->id]) }}"><x-ui.icon name="download" :size="15"/> Download</a></footer></div></article>@empty@include('public.partials.library-empty', ['type' => 'books'])@endforelse
        </section>
    @elseif($library === 'audio_sermons')
        <section class="library-audio-list">
            @forelse($items as $item)<article class="library-audio-card"><div class="library-media-art">@if($coverUrl($item))<img src="{{ $coverUrl($item) }}" alt="{{ $item->cover_image_alt ?: '' }}" loading="lazy">@else<x-ui.icon name="audio" :size="34"/>@endif</div><div class="library-media-copy"><div class="library-media-meta"><span>{{ $item->category_name ?: 'Audio message' }}</span>@if($item->series_title)<b>{{ $item->series_title }}</b>@endif</div><h2>{{ $item->title }}</h2><p>{{ $item->speaker ?: 'Ministry speaker' }}</p>@if($item->description)<span>{{ str($item->description)->limit(180) }}</span>@endif<audio controls preload="metadata" src="{{ route('public.library.stream', [$librarySlug, $item->id]) }}">Your browser does not support audio playback.</audio><footer><a href="{{ route('public.library.download', [$librarySlug, $item->id]) }}"><x-ui.icon name="download" :size="15"/> Download audio @if($item->file_size_bytes)· {{ $fileSize($item->file_size_bytes) }}@endif</a></footer></div></article>@empty@include('public.partials.library-empty', ['type' => 'audio messages'])@endforelse
        </section>
    @elseif($library === 'video_sermons')
        <section class="library-video-grid">
            @forelse($items as $item)<article class="library-video-card"><div class="library-video-stage">@if($item->provider === 'youtube')<iframe src="https://www.youtube-nocookie.com/embed/{{ $item->provider_key }}" title="{{ $item->title }}" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>@else<video controls preload="metadata" @if($coverUrl($item)) poster="{{ $coverUrl($item) }}" @endif><source src="{{ route('public.library.stream', [$librarySlug, $item->id]) }}" type="{{ $item->file_mime }}">Your browser does not support video playback.</video>@endif</div><div class="library-video-copy"><div class="library-media-meta"><span>{{ $item->category_name ?: 'Video message' }}</span>@if($item->series_title)<b>{{ $item->series_title }}</b>@endif</div><h2>{{ $item->title }}</h2><p>{{ $item->speaker ?: 'Ministry speaker' }}</p>@if($item->description)<span>{{ str($item->description)->limit(150) }}</span>@endif@if($item->provider === 'local')<footer><a href="{{ route('public.library.download', [$librarySlug, $item->id]) }}"><x-ui.icon name="download" :size="15"/> Download video @if($item->file_size_bytes)· {{ $fileSize($item->file_size_bytes) }}@endif</a></footer>@endif</div></article>@empty@include('public.partials.library-empty', ['type' => 'video messages'])@endforelse
        </section>
    @else
        <section class="library-series-grid">
            @forelse($items as $item)<a class="library-series-card" href="{{ route('public.library.series', $item->id) }}"><div>@if($item->cover_image_path)<img src="{{ route('public.library.cover', [$librarySlug, $item->id]) }}" alt="{{ $item->cover_image_alt ?: $item->title.' artwork' }}" loading="lazy">@else<span><x-ui.icon name="archive" :size="32"/></span>@endif</div><section><p>TEACHING SERIES</p><h2>{{ $item->title }}</h2><span>{{ str($item->description)->limit(155) ?: 'Explore every message in this teaching series.' }}</span><footer><b>{{ number_format($item->message_count) }} {{ str('message')->plural($item->message_count) }}</b><em>View series <x-ui.icon name="arrow-right" :size="14"/></em></footer></section></a>@empty@include('public.partials.library-empty', ['type' => 'sermon series'])@endforelse
        </section>
    @endif
    <div class="pagination-wrap library-pagination">{{ $items->links() }}</div>
</x-layouts.public>
