<x-layouts.public :title="app(\App\Support\MinistryTerminology::class)->label('service', 'plural')" :settings="$settings" :features="$features">
    <header class="public-heading service-times-heading">
        <p>GATHER WITH US</p>
        <h1>{{ app(\App\Support\MinistryTerminology::class)->label('service', 'plural') }}</h1>
        <span>Find the next opportunity to worship, connect, and take part in the life of this ministry.</span>
    </header>
    @if(session('status'))<div class="workspace-notice workspace-notice-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="workspace-notice workspace-notice-error" role="alert">{{ $errors->first() }}</div>@endif
    <section class="service-times-list" aria-labelledby="upcoming-services-title">
        <div class="service-times-section-heading">
            <div><p>UPCOMING</p><h2 id="upcoming-services-title">Plan your visit</h2></div>
            <a href="{{ route('public.locations') }}">View locations</a>
        </div>
        @forelse($services as $service)
            @php($startsAt = \Illuminate\Support\Carbon::parse($service->starts_at)->timezone($service->timezone ?: $settings->timezone))
            @php($instant = \Illuminate\Support\Carbon::parse($service->starts_at)->utc()->toIso8601String())
            @php($serviceReminders = $reminders->filter(fn ($reminder) => $reminder->service_occurrence_id === $service->id || ($service->service_schedule_id && $reminder->service_schedule_id === $service->service_schedule_id)))
            <article class="service-time-card">
                <div class="service-time-date" data-viewer-date data-instant="{{ $instant }}"><strong data-viewer-month>{{ $startsAt->format('M') }}</strong><span data-viewer-day>{{ $startsAt->format('d') }}</span></div>
                <div class="service-time-details">
                    <x-ui.viewer-time :instant="$service->starts_at" :source-timezone="$service->timezone ?: $settings->timezone"/>
                    <h3>{{ $service->title }}</h3>
                    <span>{{ $service->location_name ?: 'Location details will be shared by the ministry.' }}</span>
                    @if($service->location_address)<small>{{ $service->location_address }}</small>@endif
                </div>
                <div class="service-time-meta">
                    <span>{{ str($service->service_type ?: 'service')->replace('_', ' ')->headline() }}</span>
                    @if(in_array($service->state, ['live', 'interrupted'], true) && in_array('digital_church', $features, true))
                        <a class="button" href="{{ route('public.worship', $service->id) }}">Join live</a>
                    @endif
                    @if(in_array($service->state, ['scheduled', 'ready'], true) && \Illuminate\Support\Carbon::parse($service->starts_at)->isFuture() && ($remindersAvailable || $serviceReminders->isNotEmpty()))
                        @auth
                            @if($serviceReminders->isNotEmpty())
                                @foreach($serviceReminders as $reminder)
                                    <form method="POST" action="{{ route('member.service-reminders.destroy', $reminder->id) }}" class="service-reminder-active">@csrf @method('DELETE')<span>{{ $reminder->service_schedule_id ? 'Every service' : 'One time' }} · {{ $reminder->lead_minutes === 60 ? '1 hour before' : '1 day before' }}</span><button type="submit">Cancel</button></form>
                                @endforeach
                            @elseif($remindersAvailable)
                                <details class="service-reminder-picker" data-dismiss-on-outside data-viewport-popover><summary>Set reminder</summary><form method="POST" action="{{ route('member.service-reminders.store') }}">@csrf<input type="hidden" name="service_occurrence_id" value="{{ $service->id }}"><input type="hidden" name="timezone" data-viewer-timezone-input><label>Remind me<select name="scope"><option value="occurrence">For this service</option>@if($service->service_schedule_id)<option value="schedule">For every service in this schedule</option>@endif</select></label><label>Send it<select name="lead_minutes"><option value="1440">1 day before</option><option value="60">1 hour before</option></select></label><button class="button" type="submit">Enable reminder</button></form></details>
                            @endif
                        @else
                            <a class="service-reminder-sign-in" href="{{ route('login') }}">Sign in to set a reminder</a>
                        @endauth
                    @endif
                </div>
            </article>
        @empty
            <div class="public-empty"><strong>No upcoming service times are published yet.</strong><span>Please check back soon or visit a ministry location for more information.</span></div>
        @endforelse
    </section>
</x-layouts.public>
