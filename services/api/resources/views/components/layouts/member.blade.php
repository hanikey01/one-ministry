<x-layouts.app :title="$title ?? null" :ministry-name="$ministryName ?? null" :primary-color="$primaryColor ?? null" :accent-color="$accentColor ?? null">
    @php($memberAppearance = \App\Models\MinistrySetting::find(\App\Models\MinistrySetting::SINGLETON_ID))
    <div class="member-shell member-font-{{ $memberAppearance?->staff_font ?? 'modern' }} staff-sidebar-{{ $memberAppearance?->staff_sidebar ?? 'deep' }}" data-member-shell>
        <button class="member-backdrop" type="button" data-member-close aria-label="Close navigation"></button>
        <aside class="member-sidebar app-sidebar" data-member-sidebar>
            <div class="member-sidebar-brand">@if($memberAppearance?->dark_logo_path || $memberAppearance?->logo_path)<span class="workspace-brand-mark workspace-brand-logo"><img src="{{ asset('storage/'.($memberAppearance->dark_logo_path ?: $memberAppearance->logo_path)) }}" alt="{{ $ministryName }} logo"></span>@else<span class="workspace-brand-mark">{{ mb_strtoupper(mb_substr($ministryName ?? 'M', 0, 1)) }}</span>@endif<div><strong>{{ $ministryName ?? config('app.name') }}</strong><small>Member community</small></div><button class="member-close" type="button" data-member-close aria-label="Close navigation"><x-ui.icon name="close"/></button></div>
            <nav class="member-navigation" aria-label="Member navigation">
                <p>My ministry</p>
                <a href="{{ route('member.home') }}" @class(['is-active' => request()->routeIs('member.home')])><x-ui.icon name="home"/><span>Overview</span></a>
                @if(in_array('groups',$features ?? [],true))<a href="{{ route('member.groups') }}" @class(['is-active' => request()->routeIs('member.groups')])><x-ui.icon name="groups"/><span>My groups</span></a>@endif
                <a href="{{ route('member.events') }}" @class(['is-active' => request()->routeIs('member.events')])><x-ui.icon name="calendar"/><span>Events</span></a>
                <a href="{{ route('member.serving') }}" @class(['is-active' => request()->routeIs('member.serving')])><x-ui.icon name="volunteer"/><span>Serving</span></a>
                @if(in_array('digital_school',$features ?? [],true))<a href="{{ route('member.school.index') }}" @class(['is-active' => request()->routeIs('member.school.*')])><x-ui.icon name="school"/><span>Digital school</span></a>@endif
                <a href="{{ route('member.activity') }}" @class(['is-active' => request()->routeIs('member.activity')])><x-ui.icon name="services"/><span>Participation</span></a>
                <a href="{{ route('member.next-steps') }}" @class(['is-active' => request()->routeIs('member.next-steps')])><x-ui.icon name="followup"/><span>Next steps</span></a>
                <a href="{{ route('member.prayer.page') }}" @class(['is-active' => request()->routeIs('member.prayer.page')])><x-ui.icon name="care"/><span>Prayer</span></a>
                <p>My account</p>
                @if(in_array('giving',$features ?? [],true))<a href="{{ route('member.giving') }}" @class(['is-active' => request()->routeIs('member.giving')])><x-ui.icon name="giving"/><span>Giving</span></a>@endif
                <a href="{{ route('member.profile') }}" @class(['is-active' => request()->routeIs('member.profile')])><x-ui.icon name="people"/><span>Profile & preferences</span></a>
            </nav>
            <div class="member-sidebar-footer"><a href="{{ route('public.home') }}"><x-ui.icon name="external"/><span>Visit public site</span></a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-ui.icon name="security"/><span>Sign out</span></button></form></div>
        </aside>
        <div class="member-main"><header class="member-topbar app-topbar"><button class="member-menu" type="button" data-member-toggle aria-expanded="false" aria-label="Open navigation"><x-ui.icon name="menu"/><x-ui.icon name="close"/></button><span class="mobile-header-brand" aria-label="{{ $ministryName ?? config('app.name') }}">@if($memberAppearance?->logo_path)<img src="{{ asset('storage/'.$memberAppearance->logo_path) }}" alt="">@else{{ mb_strtoupper(mb_substr($ministryName ?? 'M', 0, 1)) }}@endif</span><div><small>Member portal</small><strong>{{ $title ?? 'My ministry' }}</strong></div><div class="member-top-actions"><button class="member-theme-toggle" type="button" data-member-theme-toggle aria-pressed="false" aria-label="Switch to dark mode"><span class="member-theme-track" aria-hidden="true"></span><span class="member-theme-label">Light</span></button><span class="member-top-avatar" aria-label="Signed in as {{ auth()->user()?->name }}">{{ mb_strtoupper(mb_substr(auth()->user()?->name ?? 'M', 0, 1)) }}</span></div></header><main class="member-content">{{ $slot }}</main></div>
        <nav class="member-mobile-nav" aria-label="Member mobile navigation"><a href="{{ route('public.home') }}"><x-ui.icon name="home"/><span>Home</span></a>@if(in_array('groups',$features ?? [],true))<a href="{{ route('member.groups') }}" @if(request()->routeIs('member.groups')) aria-current="page" @endif><x-ui.icon name="groups"/><span>Groups</span></a>@else<a href="{{ route('member.next-steps') }}" @if(request()->routeIs('member.next-steps')) aria-current="page" @endif><x-ui.icon name="followup"/><span>Next steps</span></a>@endif<a href="{{ route('member.events') }}" @if(request()->routeIs('member.events')) aria-current="page" @endif><x-ui.icon name="calendar"/><span>Events</span></a>@if(in_array('giving',$features ?? [],true))<a href="{{ route('member.giving') }}" @if(request()->routeIs('member.giving')) aria-current="page" @endif><x-ui.icon name="giving"/><span>Giving</span></a>@else<a href="{{ route('member.prayer.page') }}" @if(request()->routeIs('member.prayer.page')) aria-current="page" @endif><x-ui.icon name="care"/><span>Prayer</span></a>@endif<button type="button" data-member-toggle aria-label="Open more member options"><x-ui.icon name="menu"/><span>More</span></button></nav>
    </div>
    <style>
        .member-shell.staff-sidebar-deep .member-sidebar.app-sidebar { background: radial-gradient(circle at 88% 0%, rgb(255 255 255 / 7%), transparent 26%), linear-gradient(180deg, #17271e, #0b1410) !important; color: #d7e2da; }
        .member-shell.staff-sidebar-brand .member-sidebar.app-sidebar { background: radial-gradient(circle at 14% 0%, rgb(255 255 255 / 16%), transparent 29%), linear-gradient(180deg, color-mix(in srgb, var(--brand) 86%, #14251c), color-mix(in srgb, var(--brand) 56%, #0b1510)) !important; color: #eef6f0; }
        .member-shell.staff-sidebar-light .member-sidebar.app-sidebar { background: #f9fbf8 !important; color: #203229; border-right-color: #d5e0d8 !important; box-shadow: 12px 0 35px rgb(18 34 25 / 7%); }
        .member-shell.staff-sidebar-deep .member-sidebar-brand strong, .member-shell.staff-sidebar-brand .member-sidebar-brand strong { color: #f7faf8 !important; }
        .member-shell.staff-sidebar-deep .member-sidebar-brand small, .member-shell.staff-sidebar-brand .member-sidebar-brand small { color: #aabbb0 !important; }
        .member-shell.staff-sidebar-deep .member-navigation > p { color: #82968a !important; }
        .member-shell.staff-sidebar-deep .member-navigation a, .member-shell.staff-sidebar-deep .member-sidebar-footer :is(a, button) { color: #b7c6bd !important; }
        .member-shell.staff-sidebar-brand .member-navigation > p { color: rgb(255 255 255 / 68%) !important; }
        .member-shell.staff-sidebar-brand .member-navigation a, .member-shell.staff-sidebar-brand .member-sidebar-footer :is(a, button) { color: #eff7f1 !important; }
        .member-shell.staff-sidebar-brand .member-navigation a:hover, .member-shell.staff-sidebar-brand .member-navigation a.is-active { background: rgb(255 255 255 / 13%) !important; color: #fff !important; }
        .member-shell.staff-sidebar-light .member-sidebar-brand { border-bottom-color: #d5e0d8 !important; }
        .member-shell.staff-sidebar-light .member-sidebar-brand strong { color: #15271c !important; opacity: 1 !important; }
        .member-shell.staff-sidebar-light .member-sidebar-brand small { color: #4c6255 !important; opacity: 1 !important; }
        .member-shell.staff-sidebar-light .member-navigation > p { color: #647a6d !important; }
        .member-shell.staff-sidebar-light .member-navigation a, .member-shell.staff-sidebar-light .member-sidebar-footer :is(a, button) { color: #344a3d !important; font-weight: 720; }
        .member-shell.staff-sidebar-light .member-navigation a .ui-icon { color: #527061; }
        .member-shell.staff-sidebar-light .member-navigation a:hover { background: #e9f2eb !important; color: #123b27 !important; }
        .member-shell.staff-sidebar-light .member-navigation a.is-active { background: color-mix(in srgb, var(--brand) 13%, #ffffff) !important; color: color-mix(in srgb, var(--brand) 88%, #11271b) !important; box-shadow: inset 3px 0 var(--accent), inset 0 0 0 1px color-mix(in srgb, var(--brand) 18%, #ffffff) !important; }
        .member-shell.staff-sidebar-light .member-sidebar-footer { border-top-color: #d5e0d8 !important; }
        .member-theme-dark.member-shell.staff-sidebar-deep .member-sidebar.app-sidebar { background: radial-gradient(circle at 18% 0%, color-mix(in srgb, var(--brand) 24%, transparent), transparent 28%), #111c16 !important; color: #dce6df; }
        .member-theme-dark.member-shell.staff-sidebar-brand .member-sidebar.app-sidebar { background: radial-gradient(circle at 14% 0%, rgb(255 255 255 / 12%), transparent 29%), linear-gradient(180deg, color-mix(in srgb, var(--brand) 78%, #0b1510), color-mix(in srgb, var(--brand) 42%, #07110b)) !important; color: #eef6f0; }
        .member-theme-dark.member-shell.staff-sidebar-light .member-sidebar.app-sidebar { background: radial-gradient(circle at 92% 0%, color-mix(in srgb, var(--brand) 17%, transparent), transparent 29%), #17231c !important; border-right-color: #34483c !important; box-shadow: 12px 0 35px rgb(0 0 0 / 18%); color: #dbe6de; }
        .member-theme-dark.member-shell .member-sidebar-brand { border-bottom-color: rgb(255 255 255 / 11%) !important; }
        .member-theme-dark.member-shell .member-sidebar-brand strong { color: #f7faf8 !important; }
        .member-theme-dark.member-shell .member-sidebar-brand small { color: #a8b9ae !important; }
        .member-theme-dark.member-shell .member-navigation > p { color: #8ea297 !important; }
        .member-theme-dark.member-shell .member-navigation a, .member-theme-dark.member-shell .member-sidebar-footer :is(a, button) { color: #c5d2c9 !important; }
        .member-theme-dark.member-shell .member-navigation a .ui-icon { color: #a9beb0 !important; }
        .member-theme-dark.member-shell .member-navigation a:hover { background: rgb(255 255 255 / 8%) !important; color: #fff !important; }
        .member-theme-dark.member-shell .member-navigation a.is-active { background: color-mix(in srgb, var(--brand) 28%, #1b2c22) !important; color: #fff !important; box-shadow: inset 3px 0 var(--accent), inset 0 0 0 1px rgb(255 255 255 / 8%) !important; }
        .member-theme-dark.member-shell .member-sidebar-footer { border-top-color: rgb(255 255 255 / 11%) !important; }
        /* Functional links use a neutral, bordered treatment so a ministry's chosen
           brand colour never becomes the only contrast mechanism. */
        .member-content .member-panel > header > a,
        .member-content .member-empty-state > a {
            display: inline-flex;
            min-height: 34px;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: 1px solid var(--member-line, #c7d3cb);
            border-radius: 9px;
            padding: 0 10px;
            background: var(--member-surface, #fff);
            color: #294438;
            box-shadow: 0 1px 2px rgb(15 37 24 / 4%);
            font-size: .62rem;
            font-weight: 780;
            text-decoration: none;
        }
        .member-content .member-panel > header > a:hover,
        .member-content .member-empty-state > a:hover { border-color: color-mix(in srgb, var(--brand) 42%, var(--member-line, #c7d3cb)); background: color-mix(in srgb, var(--brand) 7%, var(--member-surface, #fff)); color: #173225; }
        .member-theme-dark .member-content .member-panel > header > a,
        .member-theme-dark .member-content .member-empty-state > a { border-color: #405548; background: #1a281f; color: #e4eee7; box-shadow: none; }
        .member-theme-dark .member-content .member-panel > header > a:hover,
        .member-theme-dark .member-content .member-empty-state > a:hover { border-color: color-mix(in srgb, var(--brand) 50%, #597060); background: #203126; color: #f2f8f3; }

        /* Page and panel headings remain readable even when a custom brand colour
           is very pale, very dark, or close to the active canvas colour. */
        .member-content .member-page-heading h1,
        .member-content .member-panel > header h2,
        .member-content .member-list article > div > strong,
        .member-content .member-tile-grid strong,
        .member-content .member-mini-history span,
        .member-content .member-empty-state strong,
        .member-content .member-preference strong { color: #1d3327; }
        .member-theme-dark .member-content .member-page-heading h1,
        .member-theme-dark .member-content .member-panel > header h2,
        .member-theme-dark .member-content .member-list article > div > strong,
        .member-theme-dark .member-content .member-tile-grid strong,
        .member-theme-dark .member-content .member-mini-history span,
        .member-theme-dark .member-content .member-empty-state strong,
        .member-theme-dark .member-content .member-preference strong { color: #edf5ef; }
        .member-content .member-page-heading > p,
        .member-content .member-panel > header p { color: #3a6950; }
        .member-theme-dark .member-content .member-page-heading > p,
        .member-theme-dark .member-content .member-panel > header p { color: #a8cfb1; }

        /* Overview shortcuts need their own icon contrast system. The brand
           colour is intentionally excluded here because it may be too dark for
           a dark dashboard card. */
        .member-content .member-metrics > a > .ui-icon,
        .member-content .member-destination-grid > a > .ui-icon {
            background: transparent !important;
            color: #245d42 !important;
        }
        .member-theme-dark .member-content .member-metrics > a > .ui-icon,
        .member-theme-dark .member-content .member-destination-grid > a > .ui-icon {
            background: transparent !important;
            color: #e1f5e7 !important;
        }

        /* Match the selected state used by the staff mobile navigation. */
        .member-mobile-nav a[aria-current='page'] { background: color-mix(in srgb, var(--brand) 12%, #fff) !important; color: var(--brand) !important; }
        .member-theme-dark .member-mobile-nav a[aria-current='page'] { background: color-mix(in srgb, var(--brand) 36%, #17271e) !important; color: #fff !important; }
        .member-mobile-nav a[aria-current='page'] :is(.ui-icon, span) { color: inherit !important; }

        /* Use the same compact mobile navigation component as the staff and
           public workspaces. */
        @media (max-width: 760px) {
            .member-mobile-nav {
                right: 10px;
                bottom: 10px;
                left: 10px;
                align-items: stretch;
                gap: 3px;
                min-height: 61px;
                border-color: #aab9af;
                border-radius: 17px;
                padding: 5px;
                background: rgb(251 253 251 / 95%);
                box-shadow: 0 16px 38px rgb(12 31 20 / 18%);
            }
            .member-mobile-nav :is(a, button) {
                align-content: center;
                gap: 3px;
                border-radius: 12px;
                padding: 4px 2px;
                color: #63766a;
                font-size: .52rem;
                font-weight: 760;
                line-height: 1.1;
            }
            .member-mobile-nav :is(a, button) .ui-icon { width: 18px; height: 18px; }
            .member-theme-dark .member-mobile-nav {
                border-color: #405548;
                background: rgb(20 32 25 / 95%);
                box-shadow: 0 16px 38px rgb(0 0 0 / 32%);
            }
            .member-theme-dark .member-mobile-nav :is(a, button) { color: #afc0b5; }
        }
    </style>
</x-layouts.app>
