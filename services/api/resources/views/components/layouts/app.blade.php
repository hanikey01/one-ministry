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
                const setupWorkspaceChrome = () => {
                    document.querySelectorAll('[data-workspace-shell], [data-public-shell]').forEach((shell) => {
                        if (shell.dataset.workspaceChromeReady === 'true') return;
                        shell.dataset.workspaceChromeReady = 'true';

                        const isPublic = shell.hasAttribute('data-public-shell');
                        const sidebar = shell.querySelector('[data-workspace-sidebar], [data-public-sidebar]');
                        const toggle = shell.querySelector('[data-workspace-toggle], [data-public-menu]');
                        const backdrop = shell.querySelector('.workspace-backdrop');
                        const themeToggle = shell.querySelector('[data-workspace-theme-toggle], [data-public-theme-toggle]');
                        const storageKey = 'ministry-theme';
                        const setNavigation = (open) => {
                            shell.classList.toggle('navigation-open', open);
                            sidebar?.classList.toggle('is-open', open);
                            backdrop?.classList.toggle('is-open', open);
                            toggle?.setAttribute('aria-expanded', String(open));
                        };
                        const setTheme = (theme) => {
                            const dark = theme === 'dark';
                            shell.classList.toggle('public-theme-dark', isPublic && dark);
                            shell.classList.toggle('staff-theme-dark', !isPublic && dark);
                            if (shell.dataset.workspaceSurface === 'member') shell.classList.toggle('member-theme-dark', dark);
                            themeToggle?.setAttribute('aria-pressed', String(dark));
                            themeToggle?.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
                            const label = themeToggle?.querySelector('.workspace-theme-label');
                            if (label) label.textContent = dark ? 'Dark' : 'Light';
                        };

                        const legacyTheme = isPublic
                            ? window.localStorage.getItem('ministry-public-theme')
                            : shell.dataset.workspaceSurface === 'member'
                                ? window.localStorage.getItem('ministry-member-theme')
                                : window.localStorage.getItem('ministry-staff-theme');
                        setTheme(window.localStorage.getItem(storageKey) ?? legacyTheme ?? 'light');
                        toggle?.addEventListener('click', () => setNavigation(!shell.classList.contains('navigation-open')));
                        shell.querySelectorAll('[data-workspace-close], [data-public-close]').forEach((button) => button.addEventListener('click', () => setNavigation(false)));
                        shell.querySelectorAll('[data-public-navigation] a').forEach((link) => link.addEventListener('click', () => setNavigation(false)));
                        shell.addEventListener('keydown', (event) => {
                            if (event.key === 'Escape') setNavigation(false);
                        });
                        const toggleTheme = () => {
                            const theme = (isPublic ? shell.classList.contains('public-theme-dark') : shell.classList.contains('staff-theme-dark')) ? 'light' : 'dark';
                            window.localStorage.setItem(storageKey, theme);
                            setTheme(theme);
                        };
                        let themePointerHandled = false;
                        themeToggle?.addEventListener('pointerup', (event) => {
                            event.preventDefault();
                            themePointerHandled = true;
                            toggleTheme();
                        });
                        themeToggle?.addEventListener('click', () => {
                            if (themePointerHandled) {
                                themePointerHandled = false;
                                return;
                            }
                            toggleTheme();
                        });
                    });
                };
                setupWorkspaceChrome();
                document.addEventListener('livewire:navigated', setupWorkspaceChrome);

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
                        if (event.target.closest('.enhanced-select-options.is-portaled')) return;
                        if (!popup.contains(event.target) && !popup.contains(document.activeElement)) popup.removeAttribute('open');
                    });
                });
            })();
        </script>
        @livewireScripts
    </body>
</html>

