<x-layouts.workspace :title="$service->title" :ministry-name="$settings->name" :user-name="$user->name" user-role="Service producer">
    <header class="workspace-page-heading producer-heading">
        <p>PRODUCER DESK</p>
        <h1>{{ $service->title }}</h1>
        <span>{{ $service->starts_at->timezone($settings->timezone)->format('D, M j · g:i A') }} · {{ str($service->state)->headline() }}</span>
    </header>
    @if(session('status'))<div class="form-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="form-errors">{{ $errors->first() }}</div>@endif

    @php($usesBroadcast = in_array($service->service_type, ['broadcast', 'hybrid'], true))
    @php($usesMeeting = $interactiveMeetingsEnabled && in_array($service->service_type, ['meeting', 'hybrid'], true))
    <section class="producer-status-strip" aria-label="Service status">
        <div><small>Service</small><strong class="stream-state stream-state-{{ $service->state }}">{{ str($service->state)->headline() }}</strong></div>
        <div><small>Ingest</small><strong class="stream-state stream-state-{{ $session?->ingest_health ?? 'unknown' }}">{{ str($session?->ingest_health ?? 'not configured')->headline() }}</strong></div>
        <div><small>Viewer playback</small><strong class="stream-state stream-state-{{ $session?->playback_health ?? 'unknown' }}">{{ str($session?->playback_health ?? 'not configured')->headline() }}</strong></div>
        <div><small>Last check</small><strong>{{ $session?->last_probed_at ? \Illuminate\Support\Carbon::parse($session->last_probed_at)->timezone($settings->timezone)->format('g:i A') : 'Not yet checked' }}</strong></div>
    </section>

    <section class="producer-card producer-experience-card"><div class="producer-card-heading"><div><p>PUBLIC ENTRY</p><h2>Service experience</h2></div><span>{{ str($service->service_type)->replace('_', ' ')->headline() }}</span></div><p class="producer-copy">{{ $service->service_type === 'broadcast' ? 'Join worship opens the public broadcast only.' : ($service->service_type === 'meeting' ? 'Join worship opens the Zoom interactive meeting only.' : ($service->service_type === 'hybrid' ? 'Join worship opens the broadcast first, with a separate interactive-room action.' : 'This service has no online entry point.')) }}</p><form class="producer-inline-form" method="POST" action="{{ route('staff.services.experience.update', $service) }}">@csrf @method('PATCH')<label>Primary experience<select name="service_type"><option value="broadcast" @selected($service->service_type === 'broadcast')>Broadcast only</option>@if($interactiveMeetingsEnabled)<option value="meeting" @selected($service->service_type === 'meeting')>Interactive Zoom meeting only</option><option value="hybrid" @selected($service->service_type === 'hybrid')>Broadcast + interactive room</option>@endif<option value="in_person" @selected($service->service_type === 'in_person')>In person only</option></select></label><button class="button button-secondary">Save experience</button></form></section>

    <div class="producer-grid producer-grid-expanded">
        @if($usesBroadcast)<section class="producer-card">
            <div class="producer-card-heading"><div><p>VIDEO DELIVERY</p><h2>Connect your broadcast</h2></div><span>Provider-owned</span></div>
            <p class="producer-copy">Use the public viewing URL from your ministry’s YouTube Live, Vimeo, or HLS provider. Stream keys stay with your encoder or provider account.</p>
            <form class="settings-form producer-form" method="POST" action="{{ route('staff.services.stream',$service) }}">
                @csrf @method('PUT')
                <label>Provider<select name="provider"><option value="youtube" @selected($session?->provider==='youtube')>YouTube Live</option><option value="vimeo" @selected($session?->provider==='vimeo')>Vimeo Live</option><option value="hls" @selected($session?->provider==='hls')>HTTPS HLS</option></select></label>
                <label class="field-wide">Public playback URL<input type="url" name="playback_url" value="{{ old('playback_url',$playbackConfigUrl) }}" placeholder="https://youtube.com/watch?v=…" required></label>
                <label>Lower-bandwidth HLS URL <small>Optional .m3u8 fallback</small><input type="url" name="lower_bandwidth_url" value="{{ old('lower_bandwidth_url',$session?->lower_bandwidth_url) }}" placeholder="https://…/low.m3u8"></label>
                <label>Captions URL <small>Optional .vtt file</small><input type="url" name="captions_url" value="{{ old('captions_url',$session?->captions_url) }}" placeholder="https://…/captions.vtt"></label>
                <label class="field-wide">Viewer fallback message<input name="fallback_message" value="{{ old('fallback_message',$session?->fallback_message) }}" placeholder="A helpful message when playback is unavailable"></label>
                <button class="button producer-primary-action">Save broadcast setup <span aria-hidden="true">→</span></button>
            </form>
        </section>@endif

        <aside class="producer-side producer-control-card">
            <div class="producer-card-heading"><div><p>GO LIVE CHECKLIST</p><h2>Operate the service</h2></div></div>
            @if($session)
                <form class="producer-inline-form" method="POST" action="{{ route('staff.services.stream.probe', $service) }}">@csrf<button class="button button-secondary">Run playback check <span aria-hidden="true">↗</span></button></form>
                <form class="settings-form producer-health-form" method="POST" action="{{ route('staff.services.stream.health', $service) }}">@csrf @method('PATCH')
                    <label>Encoder ingest<select name="ingest_health"><option value="healthy" @selected($session->ingest_health==='healthy')>Healthy</option><option value="degraded" @selected($session->ingest_health==='degraded')>Degraded</option><option value="offline" @selected($session->ingest_health==='offline')>Offline</option><option value="unknown" @selected($session->ingest_health==='unknown')>Unknown</option></select></label>
                    <label>Viewer playback<select name="playback_health"><option value="healthy" @selected($session->playback_health==='healthy')>Healthy</option><option value="degraded" @selected($session->playback_health==='degraded')>Degraded</option><option value="offline" @selected($session->playback_health==='offline')>Offline</option><option value="unknown" @selected($session->playback_health==='unknown')>Unknown</option></select></label>
                    <label>Operator note <small>Optional</small><input name="reason" maxlength="500" placeholder="What did you confirm?"></label>
                    <button class="button button-secondary">Record health</button>
                </form>
            @else
                <p class="producer-empty">Save a video setup first. Then run a playback check and record separate encoder and viewer checks.</p>
            @endif
            @if($nextStates !== [])
                <form class="producer-transition-form" method="POST" action="{{ route('staff.services.transition',$service) }}">@csrf
                    <label>Next service state<select name="state">@foreach($nextStates as $state)<option value="{{ $state }}">{{ str($state)->headline() }}</option>@endforeach</select></label>
                    <button class="button">Update service state</button>
                </form>
            @else
                <p class="producer-empty">This service is {{ str($service->state)->lower() }} and has no further lifecycle transition.</p>
            @endif
            @if(in_array($service->state,['live','interrupted'],true))<a class="button producer-watch-link" target="_blank" rel="noopener" href="{{ route('public.worship',$service) }}">Open public worship room <span aria-hidden="true">↗</span></a>@endif
        </aside>
    </div>

    @if($usesMeeting)<section class="producer-card producer-meeting-card">
        <div class="producer-card-heading"><div><p>INTERACTIVE ROOM</p><h2>Zoom Video SDK meeting</h2></div><span class="{{ $zoomReady ? 'stream-state stream-state-healthy' : 'stream-state stream-state-unknown' }}">{{ $zoomReady ? 'Ready to use' : 'Setup required' }}</span></div>
        @if($meeting)
            <p class="producer-copy">This service has a separate interactive room for hosted discussion, prayer ministry, or a small gathering. It remains distinct from the public broadcast.</p>
            <div class="producer-meeting-actions"><a class="button" href="{{ route('meetings.show', $meeting->id) }}">Open interactive room <span aria-hidden="true">↗</span></a><span>Entry: {{ str($meeting->entry_policy)->replace('_', ' ')->headline() }} · Participant media: {{ str($meeting->participant_media_policy)->replace('_', ' ')->headline() }}</span></div>
        @elseif($zoomReady)
            <p class="producer-copy">Create one Zoom-powered room for this service. Members join through the ministry interface; hosts admit people and control the room separately from the broadcast.</p>
            <form method="POST" action="{{ route('staff.services.meeting.create', $service) }}">@csrf<button class="button">Create interactive room <span aria-hidden="true">→</span></button></form>
        @else
            <p class="producer-copy">Interactive rooms are disabled until this ministry connects its own Zoom Video SDK account. The broadcast setup above works independently.</p>
            <a class="button button-secondary" href="{{ route('staff.settings') }}#zoom-video-sdk">Configure Zoom Video SDK <span aria-hidden="true">→</span></a>
        @endif
    </section>@endif

    <section class="data-panel producer-events-panel">
        <div class="producer-card-heading"><div><p>ACTIVITY LOG</p><h2>Recent broadcast events</h2></div><span>{{ $events->count() }} recorded</span></div>
        <div class="table-scroll"><table><thead><tr><th>Event</th><th>Source</th><th>Time</th></tr></thead><tbody>@forelse($events as $event)<tr><td>{{ str($event->event_type)->headline() }}</td><td>{{ str($event->source)->headline() }}</td><td>{{ \Illuminate\Support\Carbon::parse($event->created_at)->timezone($settings->timezone)->format('M j · g:i A') }}</td></tr>@empty<tr><td colspan="3" class="empty-cell">No stream events recorded yet.</td></tr>@endforelse</tbody></table></div>
    </section>
</x-layouts.workspace>
