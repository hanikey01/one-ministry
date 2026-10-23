<x-layouts.workspace :title="'Overview'" :ministry-name="$settings->name" :user-name="$user->name" :user-initials="str($user->name)->explode(' ')->map(fn ($part) => str($part)->substr(0, 1))->take(2)->implode('')" user-role="Staff workspace">
    <x-slot:navigation>
        <a href="{{ url('/staff') }}" aria-current="page">Overview</a>
        @if($visibleModules['people'])<a href="{{ route('staff.people') }}">People</a>@endif @if($visibleModules['digital_church'])<a href="{{ route('staff.services') }}">{{ app(\App\Support\MinistryTerminology::class)->label('service', 'plural') }}</a>@endif<a href="{{ route('staff.settings') }}">Settings</a>
    </x-slot:navigation>

    <header class="workspace-page-heading staff-page-command">
        <div><p>MINISTRY WORKSPACE</p><h1>Good morning, {{ str($user->name)->explode(' ')->first() }}.</h1><span>Here is the ministry work that needs attention today.</span></div>
        @if($visibleModules['people'])<a class="button button-secondary" href="{{ route('staff.people') }}"><x-ui.icon name="people" :size="16"/> Open people</a>@endif
    </header>

    <section class="metric-grid" aria-label="Ministry overview">
        @if($visibleModules['people'])<article><span class="metric-icon"><x-ui.icon name="people"/></span><small>Total people</small><strong>{{ number_format($metrics['people']) }}</strong><p>People in your ministry records</p></article>@endif
        @if($visibleModules['digital_church'])<article><span class="metric-icon metric-accent"><x-ui.icon name="services"/></span><small>Upcoming {{ str(app(\App\Support\MinistryTerminology::class)->label('service', 'plural'))->lower() }}</small><strong>{{ number_format($metrics['upcomingServices']) }}</strong><p>Scheduled, rehearsing, or live</p></article>@endif
        @if($visibleModules['people'])<article><span class="metric-icon metric-care"><x-ui.icon name="followup"/></span><small>Open follow-ups</small><strong>{{ number_format($metrics['openFollowUps']) }}</strong><p>Items that need a response</p></article>@endif
        @if($visibleModules['groups'])<article><span class="metric-icon metric-blue"><x-ui.icon name="groups"/></span><small>Active {{ str(app(\App\Support\MinistryTerminology::class)->label('group', 'plural'))->lower() }}</small><strong>{{ number_format($metrics['activeGroups']) }}</strong><p>{{ app(\App\Support\MinistryTerminology::class)->label('group', 'plural') }} currently available</p></article>@endif
    </section>

    <section class="staff-overview-grid">
        <article class="staff-work-panel">
            <header><div><p>OPERATIONS</p><h2>Keep ministry moving</h2><span>Open the workspaces your team needs most today.</span></div><span class="staff-overview-heading-icon" aria-hidden="true"><x-ui.icon name="followup" :size="19"/></span></header>
            <div class="staff-action-list">
                @if($visibleModules['people'])<a href="{{ route('staff.follow-up') }}"><span class="staff-action-icon"><x-ui.icon name="followup" :size="18"/></span><span><strong>Follow-up queue</strong><small>{{ number_format($metrics['openFollowUps']) }} open item{{ $metrics['openFollowUps'] === 1 ? '' : 's' }} need a response</small></span><b>→</b></a>@endif
                @if($visibleModules['digital_church'])<a href="{{ route('staff.services') }}"><span class="staff-action-icon gold"><x-ui.icon name="services" :size="18"/></span><span><strong>{{ app(\App\Support\MinistryTerminology::class)->label('service') }} preparation</strong><small>{{ number_format($metrics['upcomingServices']) }} upcoming {{ str(app(\App\Support\MinistryTerminology::class)->label('service', 'plural'))->lower() }}</small></span><b>→</b></a>@endif
                @if($visibleModules['groups'])<a href="{{ route('staff.groups') }}"><span class="staff-action-icon blue"><x-ui.icon name="groups" :size="18"/></span><span><strong>Community {{ str(app(\App\Support\MinistryTerminology::class)->label('group', 'plural'))->lower() }}</strong><small>{{ number_format($metrics['activeGroups']) }} active {{ str(app(\App\Support\MinistryTerminology::class)->label('group', 'plural'))->lower() }} available</small></span><b>→</b></a>@endif
            </div>
        </article>
        <aside class="staff-focus-card"><p>FOCUS FOR THE WEEK</p><h2>Keep ministry work moving.</h2><span>Use the available workspaces to coordinate ministry activity and manage your installation settings.</span><div>@if($visibleModules['digital_church'])<a class="button" href="{{ route('staff.services') }}">Review {{ str(app(\App\Support\MinistryTerminology::class)->label('service', 'plural'))->lower() }}</a>@endif<a href="{{ route('staff.settings') }}">Workspace settings</a></div></aside>
    </section>
</x-layouts.workspace>
