<x-layouts.app :title="$title ?? null" :ministry-name="$ministryName ?? null" :primary-color="$primaryColor ?? null" :accent-color="$accentColor ?? null">
    @php($staffAppearance = \App\Models\MinistrySetting::find(\App\Models\MinistrySetting::SINGLETON_ID))
    @php($staffPermissions = auth()->check() ? app(\App\Services\PermissionChecker::class) : null)
    @php($canManagePeople = auth()->check() && $staffPermissions?->allows(auth()->user(), 'services.manage'))
    @php($canManageCommunity = auth()->check() && $staffPermissions?->allows(auth()->user(), 'community.manage'))
    @php($surface = $surface ?? 'staff')
    @php($surfaceFeatures = $features ?? [])
    <div class="workspace-shell staff-font-{{ $staffAppearance?->staff_font ?? 'modern' }} staff-sidebar-{{ $staffAppearance?->staff_sidebar ?? 'light' }} staff-density-{{ $staffAppearance?->staff_density ?? 'comfortable' }}" data-workspace-shell data-workspace-surface="{{ $surface }}">
        <button class="workspace-backdrop" type="button" data-workspace-close aria-label="Close navigation"></button>
        <aside class="workspace-sidebar app-sidebar" data-workspace-sidebar>
            <div class="workspace-brand">
                @if($staffAppearance?->dark_logo_path || $staffAppearance?->logo_path)<span class="workspace-brand-mark workspace-brand-logo"><img src="{{ asset('storage/'.($staffAppearance->dark_logo_path ?: $staffAppearance->logo_path)) }}" alt="{{ $ministryName }} logo"></span>@else<span class="workspace-brand-mark">{{ mb_strtoupper(mb_substr($ministryName ?? 'M', 0, 1)) }}</span>@endif
                <span><strong>{{ $ministryName ?? config('app.name') }}</strong><small>{{ $surface === 'member' ? 'Member community' : 'Ministry workspace' }}</small></span>
                <button class="workspace-close" type="button" data-workspace-close aria-label="Close navigation"><x-ui.icon name="close"/></button>
            </div>
            <nav class="workspace-nav" aria-label="{{ $surface === 'member' ? 'Member' : 'Staff' }} navigation">
                @if($surface === 'member')
                    @foreach(collect(app(\App\Services\NavigationLayout::class)->entries('member', 'sidebar', auth()->user(), $surfaceFeatures))->groupBy('group') as $group => $entries)
                        <p>{{ $group }}</p>
                        @foreach($entries as $entry)
                            <a href="{{ $entry['url'] }}" wire:navigate.hover @class(['is-active' => request()->routeIs($entry['match'], $entry['match'].'.*') && (!str_starts_with($entry['key'], 'page:') || request()->route('slug') === str($entry['key'])->after(':'))]) @if(request()->routeIs($entry['match'], $entry['match'].'.*') && (!str_starts_with($entry['key'], 'page:') || request()->route('slug') === str($entry['key'])->after(':'))) aria-current="page" @endif><x-ui.icon :name="$entry['icon']"/><span>{{ $entry['label'] }}</span></a>
                        @endforeach
                    @endforeach
                @else
                    @auth <x-staff-navigation /> @else {{ $navigation ?? '' }} @endauth
                @endif
            </nav>
            @if($surface === 'member')
                <div class="workspace-sidebar-actions"><a href="{{ route('public.home') }}"><x-ui.icon name="external"/><span>Visit public site</span></a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-ui.icon name="security"/><span>Sign out</span></button></form></div>
                <div class="workspace-account"><span>{{ mb_strtoupper(mb_substr(auth()->user()?->name ?? 'M', 0, 1)) }}</span><div><strong>{{ auth()->user()?->name ?? 'Member' }}</strong><small>Member community</small></div><a href="{{ route('member.profile') }}" wire:navigate.hover aria-label="Open profile"><x-ui.icon name="settings" :size="16"/></a></div>
            @else
                <div class="workspace-sidebar-actions"><a href="{{ route('public.home') }}" target="_blank"><x-ui.icon name="external"/><span>View public site</span></a></div>
                <div class="workspace-account"><span>{{ $userInitials ?? 'AD' }}</span><div><strong>{{ $userName ?? 'Administrator' }}</strong><small>{{ $userRole ?? 'Staff workspace' }}</small></div><a href="{{ route('staff.security') }}" wire:navigate.hover aria-label="Open account security"><x-ui.icon name="settings" :size="16"/></a></div>
            @endif
        </aside>
        <main class="workspace-main">
            <header class="workspace-topbar app-topbar">
                <button class="workspace-menu" type="button" data-workspace-toggle aria-expanded="false" aria-label="Open navigation"><x-ui.icon name="menu" :size="20"/><x-ui.icon name="close" :size="19"/></button>
                <span class="mobile-header-brand" aria-label="{{ $ministryName ?? config('app.name') }}">@if($staffAppearance?->logo_path)<img src="{{ asset('storage/'.$staffAppearance->logo_path) }}" alt="">@else{{ mb_strtoupper(mb_substr($ministryName ?? 'M', 0, 1)) }}@endif</span>
                <div class="workspace-context"><span>{{ $surface === 'member' ? 'Member portal' : now()->timezone(config('app.timezone'))->format('l, F j') }}</span><strong>{{ $title ?? ($surface === 'member' ? 'My ministry' : 'Workspace') }}</strong></div>
                <div class="workspace-top-actions">@if($surface === 'member')<span class="member-top-avatar" aria-label="Signed in as {{ auth()->user()?->name }}">{{ mb_strtoupper(mb_substr(auth()->user()?->name ?? 'M', 0, 1)) }}</span>@else @if($canManagePeople && app(\App\Services\ModuleAvailability::class)->visibleToStaff('people', auth()->user()))<a class="workspace-quick-action" href="{{ route('staff.people') }}" wire:navigate.hover title="Open people directory to add a person"><x-ui.icon name="people" :size="16"/><span>Add person</span></a>@endif<a href="{{ route('public.home') }}" target="_blank" aria-label="Open public site"><x-ui.icon name="external" :size="17"/></a>@endif<button class="workspace-theme-toggle" type="button" data-workspace-theme-toggle aria-pressed="false" aria-label="Switch to dark mode"><span class="workspace-theme-track" aria-hidden="true"></span><span class="workspace-theme-label">Light</span></button></div>
            </header>
            <section @class(['workspace-content', 'member-content' => $surface === 'member'])>
                {{ $slot }}
            </section>
        </main>
        <nav class="workspace-mobile-nav" aria-label="{{ $surface === 'member' ? 'Member' : 'Staff' }} mobile navigation">
            @foreach(app(\App\Services\NavigationLayout::class)->entries($surface, 'mobile', auth()->user(), $surfaceFeatures) as $entry)<a href="{{ $entry['url'] }}" wire:navigate.hover @if(request()->routeIs($entry['match'], $entry['match'].'.*') && ($surface !== 'member' || !str_starts_with($entry['key'], 'page:') || request()->route('slug') === str($entry['key'])->after(':'))) aria-current="page" @endif><x-ui.icon :name="$entry['icon']"/><span>{{ $entry['label'] }}</span></a>@endforeach
        </nav>
    </div>
</x-layouts.app>
