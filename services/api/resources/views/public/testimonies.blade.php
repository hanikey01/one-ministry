<x-layouts.public :settings="$settings" :features="$features" title="Testimonies">
    <section class="testimony-archive" aria-labelledby="testimony-archive-title">
        <header class="testimony-archive-heading">
            <div><p>Stories of grace</p><h1 id="testimony-archive-title">Testimonies</h1><span>Stories of faith, restoration, provision, and hope shared by our community.</span></div>
            <a class="testimony-share-link" href="{{ route('member.testimonies') }}">Share your story <span aria-hidden="true">→</span></a>
        </header>
        <nav class="testimony-filters" aria-label="Testimony categories">
            <a href="{{ route('public.testimonies') }}" @class(['active' => $activeCategory === ''])>All stories</a>
            @foreach($categories as $category)<a href="{{ route('public.testimonies', ['category' => $category]) }}" @class(['active' => $activeCategory === $category])>{{ str($category)->headline() }}</a>@endforeach
        </nav>
        <section class="testimony-archive-grid" aria-label="Published testimonies">
            @forelse($testimonies as $testimony)
                @php($authorVisible = (bool) $testimony->show_author_name)
                @php($authorName = $authorVisible ? $testimony->author_name : 'A member of our community')
                @php($authorInitial = $authorVisible ? str($testimony->author_name)->trim()->substr(0, 1)->upper() : '✦')
                @php($publishedAt = \Illuminate\Support\Carbon::parse($testimony->published_at))
                <article @class(['testimony-archive-card', 'is-featured' => $testimony->featured])>
                    <header class="testimony-card-meta"><span>{{ str($testimony->category)->headline() }}</span><time datetime="{{ $publishedAt->toDateString() }}">{{ $publishedAt->format('M j, Y') }}</time></header>
                    <h2><a href="{{ route('public.testimony', $testimony->slug) }}">{{ $testimony->title }}</a></h2>
                    <p>{{ str($testimony->body)->limit($testimony->featured ? 300 : 190) }}</p>
                    <footer class="testimony-card-contributor">
                        @if($authorVisible && $testimony->profile_image_url)<img src="{{ $testimony->profile_image_url }}" alt="{{ $authorName }}" loading="lazy">@else<span aria-hidden="true">{{ $authorInitial }}</span>@endif
                        <div><strong>{{ $authorName }}</strong><small>{{ $authorVisible ? 'Community member' : 'Shared with permission' }}</small></div>
                        <a href="{{ route('public.testimony', $testimony->slug) }}" aria-label="Read {{ $testimony->title }}">Read story <span aria-hidden="true">→</span></a>
                    </footer>
                </article>
            @empty
                <div class="testimony-archive-empty"><span aria-hidden="true">✦</span><strong>Stories are being prepared</strong><p>Published testimonies will appear here after review and consent.</p></div>
            @endforelse
        </section>
        <div class="pagination-wrap">{{ $testimonies->links() }}</div>
    </section>
</x-layouts.public>
