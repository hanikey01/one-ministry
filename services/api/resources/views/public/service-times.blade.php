<x-layouts.public :title="app(\App\Support\MinistryTerminology::class)->label('service', 'plural')" :settings="$settings" :features="$features">
    <header class="public-heading service-times-heading">
        <p>GATHER WITH US</p>
        <h1>{{ app(\App\Support\MinistryTerminology::class)->label('service', 'plural') }}</h1>
        <span>Find the next opportunity to worship, connect, and take part in the life of this ministry.</span>
    </header>
    <section class="service-times-list" aria-labelledby="upcoming-services-title">
        <div class="service-times-section-heading">
            <div><p>UPCOMING</p><h2 id="upcoming-services-title">Plan your visit</h2></div>
            <a href="{{ route('public.locations') }}">View locations</a>
        </div>
        @forelse($services as $service)
            @php($startsAt = \Illuminate\Support\Carbon::parse($service->starts_at)->timezone($service->timezone ?: $settings->timezone))
            <article class="service-time-card">
                <div class="service-time-date"><strong>{{ $startsAt->format('M') }}</strong><span>{{ $startsAt->format('d') }}</span></div>
                <div class="service-time-details">
                    <p>{{ $startsAt->format('l, F j · g:i A') }} · {{ $service->timezone ?: $settings->timezone }}</p>
                    <h3>{{ $service->title }}</h3>
                    <span>{{ $service->location_name ?: 'Location details will be shared by the ministry.' }}</span>
                    @if($service->location_address)<small>{{ $service->location_address }}</small>@endif
                </div>
                <div class="service-time-meta">
                    <span>{{ str($service->service_type ?: 'service')->replace('_', ' ')->headline() }}</span>
                    @if(in_array($service->state, ['live', 'interrupted'], true) && in_array('digital_church', $features, true))
                        <a class="button" href="{{ route('public.worship', $service->id) }}">Join live</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="public-empty"><strong>No upcoming service times are published yet.</strong><span>Please check back soon or visit a ministry location for more information.</span></div>
        @endforelse
    </section>
</x-layouts.public>
