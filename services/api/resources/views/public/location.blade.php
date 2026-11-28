<x-layouts.public :title="$location->name" :settings="$settings" :features="$features">
    @php($locationLabel = app(\App\Support\MinistryTerminology::class)->label('location'))
    <section class="location-public-detail">
        <a class="public-back-link" href="{{ route('public.locations') }}"><x-ui.icon name="arrow-left" :size="16"/><span>All {{ str($locationLabel)->plural()->lower() }}</span></a>
        <header class="location-detail-hero">
            <div class="location-detail-hero-map" aria-hidden="true"><x-ui.icon name="location" :size="54"/><span>{{ $location->is_primary ? 'Primary '.$locationLabel : $locationLabel }}</span></div>
            <div class="location-detail-hero-copy"><p>PLAN YOUR VISIT</p><h1>{{ $location->name }}</h1><span>{{ $location->address ?: 'Contact the ministry for directions and gathering details.' }}</span></div>
        </header>
        <div class="location-detail-grid">
            <article class="location-detail-visit">
                <p class="location-detail-eyebrow">VISIT DETAILS</p><h2>Everything you need to find us</h2>
                <dl>
                    <div><dt><x-ui.icon name="pin" :size="18"/> Address</dt><dd>{{ $location->address ?: 'Address details are available from the ministry.' }}</dd></div>
                    <div><dt><x-ui.icon name="clock" :size="18"/> Time zone</dt><dd>{{ str_replace('_', ' ', $location->timezone) }}</dd></div>
                </dl>
                @if($location->latitude && $location->longitude)<a class="button location-directions" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination={{ $location->latitude }},{{ $location->longitude }}"><x-ui.icon name="location" :size="17"/><span>Get directions</span></a>@elseif($location->address)<a class="button location-directions" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($location->address) }}"><x-ui.icon name="map" :size="17"/><span>Open in Google Maps</span></a>@endif
            </article>
            <aside class="location-detail-help"><span aria-hidden="true"><x-ui.icon name="people" :size="23"/></span><h2>Need help planning?</h2><p>We would be glad to help you find the right gathering, service time, or next step.</p><a href="{{ route('public.service-times') }}">View service times <x-ui.icon name="arrow-right" :size="15"/></a></aside>
        </div>
    </section>
</x-layouts.public>
