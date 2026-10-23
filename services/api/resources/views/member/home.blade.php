<x-layouts.member :title="$title" :ministry-name="$settings->name" :features="$features">
    <header class="workspace-page-heading staff-page-command">
        <div><p>MY MINISTRY</p><h1>Welcome back, {{ $person->preferred_name ?: $person->first_name }}.</h1><span>Your upcoming moments, community, and private requests are gathered here.</span></div>
        <a class="button button-secondary" href="{{ route('member.events') }}"><x-ui.icon name="calendar" :size="16"/> View my events</a>
    </header>

    <section class="metric-grid" aria-label="My ministry summary">
        <article><span class="metric-icon"><x-ui.icon name="groups"/></span><small>My groups</small><strong>{{ number_format($metrics['groups']) }}</strong><p>Communities you belong to</p></article>
        <article><span class="metric-icon metric-accent"><x-ui.icon name="calendar"/></span><small>Upcoming events</small><strong>{{ number_format($metrics['events']) }}</strong><p>Events on your calendar</p></article>
        <article><span class="metric-icon metric-care"><x-ui.icon name="volunteer"/></span><small>Serving commitments</small><strong>{{ number_format($metrics['serving']) }}</strong><p>Upcoming assignments</p></article>
        <article><span class="metric-icon metric-blue"><x-ui.icon name="followup"/></span><small>Active next steps</small><strong>{{ number_format($metrics['journeys']) }}</strong><p>Requests with the ministry team</p></article>
    </section>

    <section class="staff-overview-grid member-dashboard-grid">
        <article class="staff-work-panel">
            <header><div><p>YOUR COMMUNITY</p><h2>Stay connected</h2><span>Open the spaces you use to take part in ministry life.</span></div><span class="staff-overview-heading-icon" aria-hidden="true"><x-ui.icon name="people" :size="19"/></span></header>
            <div class="staff-action-list">
                <a href="{{ route('member.events') }}"><span class="staff-action-icon"><x-ui.icon name="calendar" :size="18"/></span><span><strong>My events</strong><small>{{ number_format($metrics['events']) }} upcoming registration{{ $metrics['events'] === 1 ? '' : 's' }}</small></span><b>→</b></a>
                @if(in_array('groups', $features, true))<a href="{{ route('member.groups') }}"><span class="staff-action-icon blue"><x-ui.icon name="groups" :size="18"/></span><span><strong>My groups</strong><small>{{ number_format($metrics['groups']) }} {{ Str::plural('group', $metrics['groups']) }} to stay connected with</small></span><b>→</b></a>@endif
                <a href="{{ route('member.serving') }}"><span class="staff-action-icon gold"><x-ui.icon name="volunteer" :size="18"/></span><span><strong>Serving schedule</strong><small>{{ number_format($metrics['serving']) }} upcoming assignment{{ $metrics['serving'] === 1 ? '' : 's' }}</small></span><b>→</b></a>
            </div>
        </article>

        <aside class="staff-focus-card member-dashboard-focus">
            @if($nextEvent)
                <p>NEXT FOR YOU</p><h2>{{ $nextEvent->title }}</h2>
                <span>{{ \Illuminate\Support\Carbon::parse($nextEvent->starts_at)->timezone($settings->timezone)->format('D, M j · g:i A') }}<br>{{ $nextEvent->location ?: 'Location to be announced' }}</span>
                <div><a class="button" href="{{ route('member.events') }}">View event details</a><a href="{{ route('member.next-steps') }}">Explore next steps</a></div>
            @else
                <p>YOUR COMMUNITY</p><h2>There is room for you here.</h2>
                <span>Explore ministry events, groups, and ways to serve when you are ready.</span>
                <div><a class="button" href="{{ route('public.events') }}">Explore events</a><a href="{{ route('member.next-steps') }}">Take a next step</a></div>
            @endif
        </aside>
    </section>
</x-layouts.member>
