<x-layouts.member :title="$title" :ministry-name="$settings->name" :features="$features">
    <header class="workspace-page-heading staff-page-command">
        <div><p>MY MINISTRY</p><h1>Welcome back, {{ $person->preferred_name ?: $person->first_name }}.</h1><span>Your upcoming moments, community, and private requests are gathered here.</span></div>
        <a class="button button-secondary" href="{{ route('member.events') }}"><x-ui.icon name="calendar" :size="16"/> View my events</a>
    </header>

    <section class="metric-grid" aria-label="My ministry summary">
        <article><span class="metric-icon"><x-ui.icon name="departments"/></span><small>My {{ str(app(\App\Support\MinistryTerminology::class)->label('department', 'plural'))->lower() }}</small><strong>{{ number_format($metrics['departments']) }}</strong><p>Ministry communities you belong to</p></article>
        <article><span class="metric-icon metric-accent"><x-ui.icon name="calendar"/></span><small>Upcoming events</small><strong>{{ number_format($metrics['events']) }}</strong><p>Events on your calendar</p></article>
        <article><span class="metric-icon metric-blue"><x-ui.icon name="followup"/></span><small>Active next steps</small><strong>{{ number_format($metrics['journeys']) }}</strong><p>Requests with the ministry team</p></article>
    </section>

    <section class="staff-overview-grid member-dashboard-grid">
        <article class="staff-work-panel">
            <header><div><p>YOUR COMMUNITY</p><h2>Stay connected</h2><span>Open the spaces you use to take part in ministry life.</span></div><span class="staff-overview-heading-icon" aria-hidden="true"><x-ui.icon name="people" :size="19"/></span></header>
            <div class="staff-action-list">
                <a href="{{ route('member.events') }}"><span class="staff-action-icon"><x-ui.icon name="calendar" :size="18"/></span><span><strong>My events</strong><small>{{ number_format($metrics['events']) }} upcoming registration{{ $metrics['events'] === 1 ? '' : 's' }}</small></span><b>→</b></a>
                <a href="{{ route('member.departments') }}"><span class="staff-action-icon blue"><x-ui.icon name="departments" :size="18"/></span><span><strong>My {{ app(\App\Support\MinistryTerminology::class)->label('department', 'plural') }}</strong><small>{{ number_format($metrics['departments']) }} {{ $metrics['departments'] === 1 ? str(app(\App\Support\MinistryTerminology::class)->label('department'))->lower() : str(app(\App\Support\MinistryTerminology::class)->label('department', 'plural'))->lower() }} to stay connected with</small></span><b>→</b></a>
            </div>
        </article>

        <aside class="staff-focus-card member-dashboard-focus">
            @if($nextEvent)
                <p>NEXT FOR YOU</p><h2>{{ $nextEvent->title }}</h2>
                <span>{{ \Illuminate\Support\Carbon::parse($nextEvent->starts_at)->timezone($settings->timezone)->format('D, M j · g:i A') }}<br>{{ $nextEvent->location ?: 'Location to be announced' }}</span>
                <div><a class="button" href="{{ route('member.events') }}">View event details</a><a href="{{ route('member.next-steps') }}">Explore next steps</a></div>
            @else
                <p>YOUR COMMUNITY</p><h2>There is room for you here.</h2>
                <span>Explore ministry events, {{ str(app(\App\Support\MinistryTerminology::class)->label('department', 'plural'))->lower() }}, and ways to serve when you are ready.</span>
                <div><a class="button" href="{{ route('public.events') }}">Explore events</a><a href="{{ route('member.next-steps') }}">Take a next step</a></div>
            @endif
        </aside>
    </section>
</x-layouts.member>
