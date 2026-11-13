@props(['title' => null, 'settings', 'features' => [], 'editorPreview' => false, 'fullBleed' => false])

@if($editorPreview)
    <x-layouts.app :title="$title ?? null" :ministry-name="$settings->name">
        <main class="page-editor-canvas-document">{{ $slot }}</main>
    </x-layouts.app>
@else
<x-layouts.app :title="$title ?? null" :ministry-name="$settings->name">
    @php($liveWorship = \App\Models\ServiceOccurrence::query()->where('rehearsal_mode', false)->where('state', 'live')->orderBy('starts_at')->first())
    @php($terminology = app(\App\Support\MinistryTerminology::class))
    @php($worshipUrl = $liveWorship ? route('public.worship', $liveWorship) : route('public.service-times'))
    @php($worshipLabel = $liveWorship ? 'Join worship' : $terminology->label('service', 'singular').' times')
    @php($guestDestination = static fn (string $url): string => match ($url) {
        '/next-steps' => route('member.next-steps'),
        '/prayer' => route('member.prayer.page'),
        '/testimonies/share' => route('member.testimonies'),
        default => $url,
    })
    <div class="workspace-shell public-workspace" data-public-shell>
        <button class="workspace-backdrop" type="button" data-public-close aria-label="Close navigation"></button>
        <aside class="workspace-sidebar app-sidebar" data-public-sidebar>
            <div class="workspace-brand">
                @if($settings->logo_path)<span class="workspace-brand-mark workspace-brand-logo"><img src="{{ asset('storage/'.$settings->logo_path) }}" alt="{{ $settings->name }} logo"></span>@endif
                <span><strong class="workspace-brand-wordmark">{{ $settings->logo_path ? $settings->name : mb_strtoupper($settings->name) }}</strong><small>{{ $settings->tagline ?: 'Faith · Community · Purpose' }}</small></span>
                <button class="workspace-close" type="button" data-public-close aria-label="Close navigation"><x-ui.icon name="close"/></button>
            </div>
            <nav class="workspace-nav" aria-label="Public navigation" data-public-navigation>
                @auth
                    <div class="workspace-nav-group"><p>Your space</p>
                        <a href="{{ route('member.home') }}" wire:navigate.hover><x-ui.icon name="people"/><span>Member dashboard</span></a>
                        @php($staffPermissions = app(\App\Services\PermissionChecker::class)->installationPermissions(auth()->user()))
                        @if($staffPermissions !== [])<a href="{{ route('staff.overview') }}" wire:navigate.hover><x-ui.icon name="settings"/><span>Staff dashboard</span></a>@endif
                    </div>
                @endauth
                @php($navigation = \Illuminate\Support\Facades\DB::table('navigation_items')->whereNull('parent_id')->where('is_enabled', true)->where('visible_desktop', true)->orderBy('position')->get()->filter(fn ($item) => ! $item->required_feature || in_array($item->required_feature, $features, true)))
                @forelse($navigation->groupBy(fn ($item) => $item->section ?: 'Main') as $section => $sectionItems)
                    <div class="workspace-nav-group public-nav-section"><p>{{ $section }}</p>
                        @foreach($sectionItems as $item)
                            @php($children = \Illuminate\Support\Facades\DB::table('navigation_items')->where('parent_id', $item->id)->where('is_enabled', true)->where('visible_desktop', true)->orderBy('position')->get())
                            @php($itemUrl = $guestDestination($item->url))
                            @if($children->isEmpty())<a href="{{ $itemUrl }}" @if(!$item->open_new_tab && str_starts_with($itemUrl, '/')) wire:navigate.hover @endif @if($item->open_new_tab) target="_blank" rel="noopener noreferrer" @endif><x-ui.icon :name="$item->icon ?: 'external'"/><span>{{ $terminology->navigationLabel($itemUrl, $item->label) }}</span></a>@else<details class="public-nav-dropdown" data-dismiss-on-outside><summary><x-ui.icon :name="$item->icon ?: 'external'"/><span>{{ $terminology->navigationLabel($itemUrl, $item->label) }}</span><x-ui.icon name="chevron-down" :size="14"/></summary><div>@foreach($children as $child)@if(!$child->required_feature || in_array($child->required_feature, $features, true))@php($childUrl = $guestDestination($child->url))<a href="{{ $childUrl }}" @if(!$child->open_new_tab && str_starts_with($childUrl, '/')) wire:navigate.hover @endif @if($child->open_new_tab) target="_blank" rel="noopener noreferrer" @endif>@if($child->icon)<x-ui.icon :name="$child->icon" :size="15"/>@endif<span>{{ $terminology->navigationLabel($childUrl, $child->label) }}</span></a>@endif@endforeach</div></details>@endif
                        @endforeach
                    </div>
                @empty
                    <div class="workspace-nav-group"><p>Explore</p><a href="{{ route('public.home') }}"><x-ui.icon name="home"/><span>Home</span></a><a href="{{ route('public.events') }}"><x-ui.icon name="calendar"/><span>Events</span></a><a href="{{ route('public.departments') }}"><x-ui.icon name="departments"/><span>{{ $terminology->label('department', 'plural') }}</span></a><a href="{{ route('public.locations') }}"><x-ui.icon name="location"/><span>{{ $terminology->label('location', 'plural') }}</span></a><a href="{{ route('public.giving') }}"><x-ui.icon name="giving"/><span>Give</span></a><a href="{{ route('public.contact') }}"><x-ui.icon name="people"/><span>Contact</span></a></div><div class="workspace-nav-group"><p>Resources</p>@if(in_array('books_library', $features, true))<a href="{{ route('public.library', 'books') }}"><x-ui.icon name="book"/><span>Books</span></a>@endif @if(in_array('audio_sermons', $features, true))<a href="{{ route('public.library', 'audio-sermons') }}"><x-ui.icon name="audio"/><span>Audio sermons</span></a>@endif @if(in_array('video_sermons', $features, true))<a href="{{ route('public.library', 'video-sermons') }}"><x-ui.icon name="video"/><span>Video sermons</span></a>@endif @if(in_array('sermon_series', $features, true))<a href="{{ route('public.library', 'sermon-series') }}"><x-ui.icon name="archive"/><span>Sermon series</span></a>@endif</div>
                @endforelse
            </nav>
            <div class="workspace-sidebar-actions"><a href="{{ $worshipUrl }}" wire:navigate.hover><x-ui.icon name="worship"/><span>{{ $worshipLabel }}</span></a></div>
            @if(auth()->check())
                <div class="workspace-account"><span class="workspace-account-avatar">@if($authenticatedPersonAvatarUrl)<img src="{{ $authenticatedPersonAvatarUrl }}" alt="">@else{{ mb_strtoupper(mb_substr(auth()->user()?->name ?? 'M', 0, 1)) }}@endif</span><div><strong>My ministry</strong><small>Member portal</small></div><a href="{{ route('member.home') }}" wire:navigate.hover aria-label="Open member portal"><x-ui.icon name="external" :size="16"/></a></div>
            @else
                <a class="workspace-account workspace-account-link" href="{{ route('login') }}" wire:navigate.hover aria-label="Sign in to {{ $settings->name }}"><span><x-ui.icon name="people" :size="17"/></span><div><strong>Welcome back</strong><small>Sign in to continue</small></div><x-ui.icon name="external" :size="16"/></a>
            @endif
        </aside>
        <main class="workspace-main">
            <header class="workspace-topbar app-topbar">
                <button class="workspace-menu" type="button" aria-expanded="false" data-public-menu aria-label="Open navigation"><x-ui.icon name="menu" :size="20"/><x-ui.icon name="close" :size="19"/></button>
                <a class="public-header-brand" href="{{ route('public.home') }}" wire:navigate.hover aria-label="{{ $settings->name }} home">
                    @if($settings->logo_path)
                        <img src="{{ asset('storage/'.$settings->logo_path) }}" alt="{{ $settings->name }}">
                    @else
                        <strong class="public-header-brand-wordmark">{{ mb_strtoupper($settings->name) }}</strong>
                    @endif
                </a>
                <div class="workspace-top-actions"><button class="workspace-theme-toggle" type="button" data-public-theme-toggle aria-pressed="false" aria-label="Switch to dark mode"><span class="workspace-theme-track" aria-hidden="true"></span><span class="workspace-theme-label">Light</span></button><a class="public-worship-action" href="{{ $worshipUrl }}" wire:navigate.hover><span>{{ $worshipLabel }}</span><x-ui.icon name="worship" :size="16"/></a></div>
            </header>
            <section @class(['workspace-content', 'public-content', 'public-content-full-bleed' => $fullBleed])>{{ $slot }}</section>
            <footer class="public-footer">
    @php($footerLogo = $settings->dark_logo_path ?: $settings->logo_path)
    <a class="public-footer-mark" href="{{ route('public.home') }}" aria-label="{{ $settings->name }} home">@if($footerLogo)<img src="{{ asset('storage/'.$footerLogo) }}" alt="{{ $settings->name }}">@else<span>{{ mb_strtoupper(mb_substr($settings->name, 0, 1)) }}</span>@endif</a>
    <p class="public-footer-copyright">© {{ now()->year }} {{ $settings->name }}. All rights reserved.</p>
</footer>
        </main>
        @php($publicMobileItems = \Illuminate\Support\Facades\DB::table('navigation_items')->whereNull('parent_id')->where('is_enabled', true)->where('visible_mobile', true)->orderBy('position')->get()->filter(fn ($item) => ! $item->required_feature || in_array($item->required_feature, $features, true))->take(5))
        <nav class="workspace-mobile-nav public-mobile-nav" aria-label="Public mobile navigation">
            @forelse($publicMobileItems as $item)@php($itemUrl = $guestDestination($item->url))<a href="{{ $itemUrl }}" @if(!$item->open_new_tab && str_starts_with($itemUrl, '/')) wire:navigate.hover @endif @if($item->open_new_tab) target="_blank" rel="noopener noreferrer" @endif @if(str_starts_with($itemUrl, '/') && request()->path() === trim(parse_url($itemUrl, PHP_URL_PATH) ?: '/', '/')) aria-current="page" @endif><x-ui.icon :name="$item->icon ?: 'external'"/><span>{{ $terminology->navigationLabel($itemUrl, $item->label) }}</span></a>@empty<a href="{{ route('public.home') }}" wire:navigate.hover @if(request()->routeIs('public.home')) aria-current="page" @endif><x-ui.icon name="home"/><span>Home</span></a><a href="{{ route('public.events') }}" wire:navigate.hover @if(request()->routeIs('public.events')) aria-current="page" @endif><x-ui.icon name="calendar"/><span>Events</span></a><a href="{{ route('public.service-times') }}" wire:navigate.hover @if(request()->routeIs('public.service-times')) aria-current="page" @endif><x-ui.icon name="services"/><span>Service times</span></a><a href="{{ route('member.prayer.page') }}" wire:navigate.hover @if(request()->routeIs('member.prayer.page')) aria-current="page" @endif><x-ui.icon name="care"/><span>Prayer</span></a><a href="{{ route('public.testimonies') }}" wire:navigate.hover @if(request()->routeIs('public.testimonies*')) aria-current="page" @endif><x-ui.icon name="sparkles"/><span>Testimonies</span></a>@endforelse
        </nav>
    </div>
        </x-layouts.app>
@endif
