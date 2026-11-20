@php($authorVisible = (bool) $testimony->show_author_name)
@php($authorName = $authorVisible ? $testimony->author_name : 'A member of our community')
@php($authorInitial = $authorVisible ? str($authorName)->trim()->substr(0, 1)->upper() : '✦')
@php($publishedAt = \Illuminate\Support\Carbon::parse($testimony->published_at))
@php($paragraphs = array_values(array_filter(preg_split('/\r?\n\r?\n/', trim($testimony->body)))))
<x-layouts.public :settings="$settings" :features="$features" :title="$testimony->title">
    <main class="testimony-story" aria-labelledby="testimony-title">
        <a class="testimony-story-back" href="{{ route('public.testimonies') }}"><span aria-hidden="true">←</span> All testimonies</a>
        <header class="testimony-story-hero">
            <div class="testimony-story-category"><span aria-hidden="true">✦</span> {{ str($testimony->category)->headline() }} story</div>
            <h1 id="testimony-title">{{ $testimony->title }}</h1>
            <div class="testimony-story-byline">@if($authorVisible && $testimony->profile_image_url)<img src="{{ $testimony->profile_image_url }}" alt="{{ $authorName }}">@else<span class="testimony-story-initial" aria-hidden="true">{{ $authorInitial }}</span>@endif<span><strong>{{ $authorName }}</strong><small>Shared {{ $publishedAt->format('F j, Y') }}</small></span></div>
        </header>
        <section class="testimony-story-layout">
            <article class="testimony-story-reading"><div class="testimony-story-mark" aria-hidden="true">“</div><div class="testimony-story-copy">@forelse($paragraphs as $paragraph)<p>{{ $paragraph }}</p>@empty<p>This testimony is being prepared for publication.</p>@endforelse</div></article>
            <aside class="testimony-story-aside"><section class="testimony-story-note"><span aria-hidden="true">✦</span><h2>Shared with care</h2><p>Every story is shared with permission and reviewed before publication.</p></section><a class="testimony-story-share" href="{{ route('member.testimonies') }}"><span>Have a story to share?</span><strong>Share your testimony <span aria-hidden="true">→</span></strong></a></aside>
        </section>
        @if($relatedTestimonies->isNotEmpty())<section class="testimony-related" aria-labelledby="related-stories-title"><header><div><p>KEEP READING</p><h2 id="related-stories-title">More stories of grace</h2></div><a href="{{ route('public.testimonies') }}">View all <span aria-hidden="true">→</span></a></header><div>@foreach($relatedTestimonies as $related)<a href="{{ route('public.testimony', $related->slug) }}"><small>{{ str($related->category)->headline() }}</small><h3>{{ $related->title }}</h3><p>{{ str($related->body)->limit(110) }}</p><span>Read story <b aria-hidden="true">→</b></span></a>@endforeach</div></section>@endif
    </main>
</x-layouts.public>
