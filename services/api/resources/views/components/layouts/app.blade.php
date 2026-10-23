<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ isset($title) ? $title.' · ' : '' }}{{ $ministryName ?? config('app.name') }}</title>
        @php($themeSettings = \App\Models\MinistrySetting::find(\App\Models\MinistrySetting::SINGLETON_ID))
        @if($themeSettings?->favicon_path)<link rel="icon" href="{{ asset('storage/'.$themeSettings->favicon_path) }}">@endif
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="ministry-app" style="{{ \App\Support\MinistryTheme::cssVariables($themeSettings) }}">
        {{ $slot }}
        <x-ui.notifications />
        <script data-navigate-once>
            (() => {
                const closeWorkspace = (shell) => {
                    shell.classList.remove('navigation-open', 'public-navigation-open');
                    shell.querySelector('[data-workspace-sidebar], [data-public-sidebar]')?.classList.remove('is-open');
                    shell.querySelector('.workspace-backdrop')?.classList.remove('is-open');
                    shell.querySelector('[data-workspace-toggle], [data-public-menu]')?.setAttribute('aria-expanded', 'false');
                };
                const closeMember = (shell) => {
                    shell.classList.remove('member-navigation-open');
                    shell.querySelector('[data-member-sidebar]')?.classList.remove('is-open');
                    shell.querySelector('.member-backdrop')?.classList.remove('is-open');
                    shell.querySelectorAll('[data-member-toggle]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
                };
                document.addEventListener('pointerdown', (event) => {
                    document.querySelectorAll('[data-workspace-shell], [data-public-shell]').forEach((shell) => {
                        if (!shell.classList.contains('navigation-open') && !shell.classList.contains('public-navigation-open')) return;
                        const sidebar = shell.querySelector('[data-workspace-sidebar], [data-public-sidebar]');
                        const toggles = [...shell.querySelectorAll('[data-workspace-toggle], [data-public-menu]')];
                        if (!sidebar?.contains(event.target) && !toggles.some((toggle) => toggle.contains(event.target))) closeWorkspace(shell);
                    });
                    document.querySelectorAll('[data-member-shell]').forEach((shell) => {
                        if (!shell.classList.contains('member-navigation-open')) return;
                        const sidebar = shell.querySelector('[data-member-sidebar]');
                        const toggles = [...shell.querySelectorAll('[data-member-toggle]')];
                        if (!sidebar?.contains(event.target) && !toggles.some((toggle) => toggle.contains(event.target))) closeMember(shell);
                    });
                    document.querySelectorAll('details[open][data-dismiss-on-outside]').forEach((popup) => {
                        if (!popup.contains(event.target)) popup.removeAttribute('open');
                    });
                });
            })();
        </script>
        @livewireScripts
    </body>
</html>

