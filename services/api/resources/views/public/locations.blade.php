<x-layouts.public :title="app(\App\Support\MinistryTerminology::class)->label('location', 'plural')" :settings="$settings" :features="$features">
    @php($locationLabel = app(\App\Support\MinistryTerminology::class)->label('location'))
    <section class="location-public-archive">
        <header class="location-public-hero">
            <div>
                <p><x-ui.icon name="map" :size="15"/> VISIT WITH US</p>
                <h1>Find a {{ str($locationLabel)->lower() }}</h1>
                <span>Plan your visit, get directions, and find the ministry gathering place that is right for you.</span>
            </div>
            <span class="location-public-hero-mark" aria-hidden="true"><x-ui.icon name="location" :size="42"/></span>
        </header>
        <div class="location-public-grid">
            @forelse($locations as $location)
                <article class="location-public-card">
                    <div class="location-public-card-map" aria-hidden="true"><x-ui.icon name="location" :size="33"/><span>{{ $location->is_primary ? 'Main gathering place' : 'Ministry location' }}</span></div>
                    <div class="location-public-card-body">
                        <span class="location-public-badge">{{ $location->is_primary ? 'Primary '.$locationLabel : $locationLabel }}</span>
                        <h2><a href="{{ route('public.location', $location->slug) }}">{{ $location->name }}</a></h2>
                        <p class="location-public-address"><x-ui.icon name="pin" :size="17"/><span>{{ $location->address ?: 'Contact the ministry for location details.' }}</span></p>
                        <p class="location-public-timezone"><x-ui.icon name="clock" :size="16"/><span>{{ str_replace('_', ' ', $location->timezone) }}</span></p>
                        <a class="button location-public-details" href="{{ route('public.location', $location->slug) }}"><span>Plan your visit</span><x-ui.icon name="arrow-right" :size="16"/></a>
                    </div>
                </article>
            @empty
                <div class="location-public-empty"><span aria-hidden="true"><x-ui.icon name="location" :size="28"/></span><h2>Locations are coming soon</h2><p>Please contact the ministry for gathering details.</p></div>
            @endforelse
        </div>
    </section>
</x-layouts.public>
