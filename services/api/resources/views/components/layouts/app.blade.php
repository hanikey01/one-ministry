<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ isset($title) ? $title.' · ' : '' }}{{ $ministryName ?? config('app.name') }}</title>
        @php($faviconPath = \App\Models\MinistrySetting::find(\App\Models\MinistrySetting::SINGLETON_ID)?->favicon_path)
        @if($faviconPath)<link rel="icon" href="{{ asset('storage/'.$faviconPath) }}">@endif
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            /* Final theme layer: kept with the shared shell so every rendered route gets
               the same contrast rules while the source-mounted development app is running. */
            .workspace-theme-toggle, .member-theme-toggle {
                border: 1px solid color-mix(in srgb, var(--brand) 58%, #6f8c7c) !important;
                background: linear-gradient(135deg, #ffffff, color-mix(in srgb, var(--brand) 7%, #f2f7f3)) !important;
                color: color-mix(in srgb, var(--brand) 88%, #13261b) !important;
                box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 11%, transparent), 0 7px 16px rgb(17 42 27 / 11%) !important;
            }
            .workspace-theme-toggle:hover, .member-theme-toggle:hover {
                border-color: var(--brand) !important;
                background: color-mix(in srgb, var(--brand) 12%, #ffffff) !important;
                transform: translateY(-1px);
            }
            .workspace-theme-track, .member-theme-track {
                background: color-mix(in srgb, var(--brand) 15%, #eaf3ed) !important;
                color: var(--brand) !important;
                box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--brand) 20%, transparent);
            }
            .workspace-theme-toggle:focus-visible, .member-theme-toggle:focus-visible {
                outline: 3px solid color-mix(in srgb, var(--brand) 35%, transparent);
                outline-offset: 3px;
            }
            .workspace-menu, .member-menu {
                border: 0 !important;
                border-radius: 10px;
                background: transparent !important;
                box-shadow: none !important;
                color: #40584a !important;
            }
            .workspace-menu .ui-icon, .member-menu .ui-icon { width: 22px; height: 22px; }
            .workspace-menu:hover, .member-menu:hover { background: color-mix(in srgb, var(--brand) 9%, transparent) !important; }
            .staff-theme-dark .workspace-menu, .member-theme-dark .member-menu, .public-theme-dark .workspace-menu { background: transparent !important; color: #dce7df !important; }
            @media (min-width: 761px) {
                .workspace-theme-toggle, .member-theme-toggle { width: auto !important; min-width: 88px; padding: 4px 11px 4px 5px !important; }
                .workspace-theme-label, .member-theme-label { display: inline !important; }
            }

            .staff-theme-dark, .member-theme-dark, .public-theme-dark { color-scheme: dark; }
            .staff-theme-dark .workspace-theme-toggle,
            .staff-theme-dark .workspace-theme-track,
            .member-theme-dark .member-theme-toggle,
            .member-theme-dark .member-theme-track,
            .public-theme-dark .workspace-theme-toggle,
            .public-theme-dark .workspace-theme-track {
                border-color: #597060 !important;
                background: #1a281f !important;
                color: #e5eee8 !important;
                box-shadow: none !important;
            }
            .staff-theme-dark .workspace-theme-track,
            .public-theme-dark .workspace-theme-track { background: color-mix(in srgb, var(--accent) 78%, #695321) !important; color: #18241c !important; }
            .member-theme-dark .member-theme-track { background: #314439 !important; color: #e7f0e9 !important; }

            .staff-theme-dark .workspace-content :is(.security-card:not(.security-card-emphasis), .security-enrollment, .security-recovery, .staff-role-guide, .staff-invite-form, .department-card, .department-policy, .department-add-form, .people-search, .workspace-create-form) {
                border-color: var(--line, #405548) !important;
                background: var(--surface, #17231c) !important;
                color: #e5eee8;
            }
            .staff-theme-dark .workspace-content :is(.security-card, .security-enrollment, .security-recovery, .staff-role-guide, .staff-invite-form, .department-card, .department-policy, .department-add-form, .people-search) :is(h1, h2, h3, strong) { color: #edf5ef; }
            .staff-theme-dark .workspace-content :is(.security-card, .security-enrollment, .security-recovery, .staff-role-guide, .staff-invite-form, .department-card, .department-policy, .department-add-form, .people-search) :is(p, small, span) { color: var(--muted, #a9b8ae); }

            .member-theme-dark .member-main { background: radial-gradient(circle at 92% 0%, color-mix(in srgb, var(--brand) 14%, transparent), transparent 27%), #101813; }
            .member-theme-dark .member-content { color: #e7efe9; }
            .member-theme-dark :is(.member-page-heading h1, .member-panel h1, .member-panel h2, .member-list strong, .member-tile-grid h2, .member-preference h2) { color: #edf5ef; }
            .member-theme-dark :is(.member-list>article, .member-panel, .member-tile-grid>article, .member-preference, .member-destination-grid>a) { border-color: #405548; }
            .member-theme-dark :is(.member-list>article, .member-panel, .member-tile-grid>article, .member-preference, .member-destination-grid>a) :is(p, small, span) { color: var(--member-muted, #a8b7ad); }
            .member-theme-dark .member-notice-success { border-color: #407958; background: #173724; color: #bde6c8; }
            .member-theme-dark .member-notice-error { border-color: #8f4c46; background: #3a1d1a; color: #f4c7c2; }

            /* A single neutral boundary keeps all cards coherent without tying their
               outline to an individual ministry's brand colour. */
            .workspace-shell, .member-shell, .public-workspace, .worship-shell { --app-card-border: #b8c8bd; }
            .staff-theme-dark, .member-theme-dark, .public-theme-dark { --app-card-border: #405548; }
            .workspace-content :is([class*="card"], [class*="panel"], [class*="tile"], .metric-grid article, .data-panel, .settings-form, .homepage-form, .table-toolbar),
            .member-content :is([class*="card"], [class*="panel"], [class*="tile"], .member-metrics article, .member-list > article, .member-destination-grid > a),
            .public-workspace :is(.public-card, .public-empty, .worship-panel, .worship-fallback, .producer-side, .homepage-help, .homepage-records article, .homepage-form, .home-service-card, .home-event-card, .home-resource-card, .home-prayer-card, .home-location-card) {
                border-color: var(--app-card-border) !important;
            }
            .workspace-content :is(.staff-focus-card, .workspace-welcome-card),
            .member-content :is(.member-hero, .member-welcome) { border-color: var(--app-card-border) !important; }

            .public-theme-dark .workspace-content { background: #101813; }
            .public-theme-dark :is(.public-card, .public-empty, .public-article, .worship-panel, .worship-fallback) {
                border-color: #405548 !important;
                background: #17231c !important;
                box-shadow: none !important;
                color: #e7efe9;
            }
            .public-theme-dark :is(.public-heading > span, .public-card p, .public-card small, .public-empty, .prose, .worship-grid > div > p, .worship-panel p, .worship-fallback p) { color: #aebdb4; }
            .public-theme-dark :is(.public-card h2, .public-article h1, .public-heading h1, .worship-grid h1, .worship-panel h2) { color: #edf5ef; }
            .public-theme-dark .public-card:hover { border-color: color-mix(in srgb, var(--brand) 58%, #405548) !important; }

            .worship-header-actions { display: flex; align-items: center; gap: 9px; }
            .worship-header > .worship-header-actions { border: 0; padding: 0; background: transparent; }
            .worship-header-actions > .worship-status { display: flex; align-items: center; gap: 7px; border: 1px solid var(--line, #d7e0da); border-radius: 999px; padding: 7px 11px; background: var(--surface, #fff); color: #536159; font-size: .62rem; font-weight: 750; }
            .worship-theme-dark { color-scheme: dark; background: #101813; color: #e7efe9; }
            .worship-theme-dark .worship-header { border-color: #405548; background: rgb(16 24 19 / 90%); }
            .worship-theme-dark .worship-header > a { color: #edf5ef; }
            .worship-theme-dark .worship-header-actions > .worship-status { border-color: #405548; background: #1a281f; color: #c4d2c8; }
            .worship-theme-dark .worship-content { background: #101813; color: #e7efe9; }
            .worship-theme-dark :is(.worship-panel, .worship-fallback) { border-color: #405548; background: #17231c; box-shadow: none; }
            .worship-theme-dark :is(.worship-grid h1, .worship-panel h2) { color: #edf5ef; }
            .worship-theme-dark :is(.worship-grid > div > p, .worship-panel p, .worship-fallback p) { color: #aebdb4; }
            .worship-theme-dark .workspace-theme-toggle, .worship-theme-dark .workspace-theme-track { border-color: #597060 !important; background: #1a281f !important; color: #e5eee8 !important; box-shadow: none !important; }
            .worship-theme-dark .workspace-theme-track { background: color-mix(in srgb, var(--accent) 78%, #695321) !important; color: #18241c !important; }

            /* Homepage editor entry controls: clear hierarchy and clear actions. */
            .homepage-theme-gallery { overflow: hidden; border: 0 !important; border-radius: 17px; background: transparent !important; box-shadow: none !important; }
            .homepage-theme-gallery > .homepage-theme-trigger,
            .homepage-advanced > .homepage-advanced-trigger { display: flex; min-height: 92px; align-items: center; justify-content: space-between; gap: 18px; border: 1px solid var(--line-strong); border-radius: 17px; padding: 17px 19px; background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 7%, var(--surface)), var(--surface)); color: var(--ink); cursor: pointer; list-style: none; box-shadow: var(--shadow-sm); }
            .homepage-theme-gallery > .homepage-theme-trigger::-webkit-details-marker,
            .homepage-advanced > .homepage-advanced-trigger::-webkit-details-marker { display: none; }
            .homepage-theme-gallery[open] > .homepage-theme-trigger { border-bottom-right-radius: 0; border-bottom-left-radius: 0; border-bottom-color: transparent; box-shadow: none; }
            .homepage-advanced[open] > .homepage-advanced-trigger { border-bottom-right-radius: 0; border-bottom-left-radius: 0; border-bottom-color: transparent; box-shadow: none; }
            .homepage-trigger-copy { display: grid; min-width: 0; gap: 4px; text-align: left; }
            .homepage-trigger-copy small { display: block; color: var(--brand); font-size: .55rem; font-weight: 840; letter-spacing: .13em; line-height: 1.2; }
            .homepage-trigger-copy strong { display: block; color: var(--ink); font-family: var(--staff-ui-font, Inter, sans-serif); font-size: .88rem; font-weight: 780; letter-spacing: -.025em; line-height: 1.2; }
            .homepage-trigger-copy em { display: block; overflow: hidden; max-width: 620px; color: var(--muted); font-size: .64rem; font-style: normal; font-weight: 530; line-height: 1.45; text-overflow: ellipsis; white-space: nowrap; }
            .homepage-trigger-action { display: inline-flex; flex: 0 0 auto; min-height: 35px; align-items: center; gap: 7px; border: 1px solid color-mix(in srgb, var(--brand) 34%, var(--line-strong)); border-radius: 9px; padding: 0 10px 0 11px; background: color-mix(in srgb, var(--brand) 10%, var(--surface)); color: color-mix(in srgb, var(--brand) 88%, #173023); font-size: .61rem; font-weight: 790; line-height: 1; }
            .homepage-trigger-action .ui-icon { width: 15px; height: 15px; transition: transform .16s ease; }
            .homepage-theme-gallery[open] > .homepage-theme-trigger .homepage-trigger-action .ui-icon,
            .homepage-advanced[open] > .homepage-advanced-trigger .homepage-trigger-action .ui-icon { transform: rotate(180deg); }
            .homepage-theme-gallery > .homepage-theme-trigger + .homepage-theme-gallery-heading,
            .homepage-theme-gallery > .homepage-theme-trigger ~ .homepage-theme-cards { margin-right: 0; margin-left: 0; }
            .homepage-theme-gallery[open] .homepage-theme-gallery-heading { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin-top: 0; border: 1px solid var(--line-strong); border-top: 0; padding: 21px 20px 17px; background: var(--surface); }
            .homepage-theme-gallery-heading p { margin: 0 0 6px; color: var(--brand); font-size: .54rem; font-weight: 840; letter-spacing: .13em; }
            .homepage-theme-gallery-heading h2 { margin: 0; color: var(--ink); font-size: 1.12rem; line-height: 1.15; }
            .homepage-theme-gallery-heading span { display: block; max-width: 680px; margin-top: 7px; color: var(--muted); font-size: .65rem; line-height: 1.55; }
            .homepage-gallery-note { max-width: 185px; color: var(--muted); font-size: .58rem; line-height: 1.45; text-align: right; }
            .homepage-theme-gallery[open] .homepage-theme-cards { margin-top: 0; border: 1px solid var(--line-strong); border-top: 0; padding: 0 20px 20px; background: var(--surface); }
            .homepage-mini-sections { display: grid !important; grid-template-columns: repeat(3, minmax(0, 1fr)) !important; gap: 5px !important; }
            .homepage-mini-sections span { display: block !important; min-width: 0; overflow: hidden; padding: 5px 4px; border-radius: 4px; color: rgb(255 255 255 / 84%); font-size: .43rem !important; line-height: 1.15; text-align: center; text-overflow: ellipsis; white-space: nowrap; }
            .homepage-inline-preview > summary { display: inline-flex; min-height: 34px; align-items: center; gap: 6px; border: 1px solid var(--line-strong); border-radius: 8px; padding: 0 10px; background: var(--surface); color: var(--brand); font-size: .6rem; font-weight: 780; line-height: 1; list-style: none; }
            .homepage-inline-preview > summary::-webkit-details-marker { display: none; }
            .homepage-inline-preview > summary::after { content: '⌄'; margin-left: 2px; font-size: .9rem; line-height: 1; transition: transform .16s ease; }
            .homepage-inline-preview[open] > summary { border-color: color-mix(in srgb, var(--brand) 40%, var(--line-strong)); background: color-mix(in srgb, var(--brand) 8%, var(--surface)); }
            .homepage-inline-preview[open] > summary::after { transform: rotate(180deg); }
            .homepage-inline-preview-body { display: block; margin-top: 10px; overflow: hidden; border: 1px solid var(--line-strong); border-radius: 11px; background: var(--surface-muted); }
            .homepage-inline-preview .homepage-preview-hero { display: block; min-height: 0; padding: 17px; background: linear-gradient(145deg, color-mix(in srgb, var(--brand) 84%, #183326), #183326); }
            .homepage-inline-preview .homepage-preview-hero > :is(p, h3, span) { display: block; }
            .homepage-inline-preview .homepage-preview-hero p { margin: 0 0 7px; color: var(--accent); font-size: .53rem; font-weight: 840; letter-spacing: .12em; line-height: 1.2; text-transform: uppercase; }
            .homepage-inline-preview .homepage-preview-hero h3 { margin: 0; color: #fff; font-family: var(--staff-ui-font, Inter, sans-serif); font-size: .92rem; font-weight: 760; letter-spacing: -.025em; line-height: 1.22; }
            .homepage-inline-preview .homepage-preview-hero span { margin-top: 7px; color: rgb(255 255 255 / 78%); font-size: .6rem; line-height: 1.45; }
            .homepage-inline-preview .homepage-preview-sections { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 7px !important; padding: 10px !important; }
            .homepage-inline-preview .homepage-preview-sections article { display: grid !important; min-width: 0; min-height: 60px !important; align-content: start; gap: 5px; border: 1px solid var(--line) !important; border-radius: 9px; padding: 9px !important; background: var(--surface) !important; }
            .homepage-inline-preview .homepage-preview-sections article small { display: block !important; overflow: hidden; color: var(--brand); font-size: .48rem; font-weight: 820; letter-spacing: .08em; line-height: 1.2; text-overflow: ellipsis; white-space: nowrap; }
            .homepage-inline-preview .homepage-preview-sections article strong { display: block !important; overflow: hidden; color: var(--ink); font-family: var(--staff-ui-font, Inter, sans-serif); font-size: .64rem; font-weight: 760; line-height: 1.2; text-overflow: ellipsis; white-space: nowrap; }
            .homepage-inline-preview form { margin: 0; padding: 0 10px 10px; }
            .homepage-inline-preview form .button { width: 100%; min-height: 35px; justify-content: center; font-size: .61rem; }
            .homepage-advanced { overflow: hidden; border: 0 !important; border-radius: 17px; background: transparent !important; box-shadow: none !important; }
            .homepage-advanced .homepage-form { margin: 0; border: 1px solid var(--line-strong); border-top: 0; border-radius: 0 0 17px 17px; padding: 18px; background: var(--surface); }
            .staff-theme-dark .homepage-theme-gallery > .homepage-theme-trigger,
            .staff-theme-dark .homepage-advanced > .homepage-advanced-trigger { border-color: #51665a; background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 19%, #17231c), #17231c); }
            .staff-theme-dark .homepage-trigger-copy strong { color: #edf5ef; }
            .staff-theme-dark .homepage-trigger-action { border-color: #597060; background: #1a281f; color: #e5eee8; }
            .staff-theme-dark .homepage-inline-preview > summary { border-color: #597060; background: #1a281f; color: #dce7df; }
            .staff-theme-dark .homepage-inline-preview-body { border-color: #51665a; background: #101813; }
            .staff-theme-dark .homepage-inline-preview .homepage-preview-sections article { border-color: #405548 !important; background: #17231c !important; }
            .staff-theme-dark .homepage-inline-preview .homepage-preview-sections article strong { color: #edf5ef; }
            .staff-theme-dark .homepage-theme-gallery[open] .homepage-theme-gallery-heading,
            .staff-theme-dark .homepage-theme-gallery[open] .homepage-theme-cards,
            .staff-theme-dark .homepage-advanced .homepage-form { border-color: #51665a; background: #17231c; }
            @media (max-width: 760px) {
                .homepage-theme-gallery > .homepage-theme-trigger, .homepage-advanced > .homepage-advanced-trigger { min-height: 86px; gap: 10px; padding: 15px; }
                .homepage-trigger-copy em { max-width: 235px; white-space: normal; }
                .homepage-trigger-action { min-height: 33px; padding: 0 8px; font-size: .56rem; }
                .homepage-theme-gallery[open] .homepage-theme-gallery-heading { display: block; padding: 18px 15px 14px; }
                .homepage-gallery-note { display: block; max-width: none; margin-top: 9px; text-align: left; }
                .homepage-theme-gallery[open] .homepage-theme-cards { padding: 0 15px 15px; }
                .homepage-inline-preview .homepage-preview-sections { grid-template-columns: 1fr !important; }
            }

            /* Compact workspace rhythm drawn from the approved dashboard preview. */
            .workspace-content { padding: clamp(24px, 3vw, 42px); }
            .workspace-page-heading { gap: 5px; margin-bottom: 21px; }
            .workspace-page-heading h1 { font-size: clamp(1.8rem, 3vw, 2.55rem); }
            .workspace-page-heading span { max-width: 760px; font-size: .71rem; line-height: 1.55; }
            .metric-grid { gap: 12px; margin-top: 22px; }
            .metric-grid article { border-radius: 15px; padding: 16px; }
            .metric-grid strong { margin-top: 12px; font-size: 1.6rem; }
            .metric-grid small { margin-top: 7px; }
            .staff-overview-grid { gap: 13px; margin-top: 14px; }
            .staff-work-panel, .staff-focus-card { border-radius: 17px; }
            .staff-work-panel > header { padding: 18px 19px 13px; }
            .staff-work-panel h2 { font-size: .93rem; }
            .staff-action-list { padding: 0 19px 8px; }
            .staff-action-list a { gap: 10px; padding: 11px 0; }
            .staff-action-icon { width: 32px; height: 32px; border-radius: 9px; }
            .staff-focus-card { min-height: 210px; padding: 24px; }
            .staff-focus-card h2 { font-size: clamp(1.2rem, 2vw, 1.55rem); }
            .data-panel th { padding: 11px 16px; }
            .data-panel td { padding: 14px 16px; }
            .workspace-top-actions { gap: 7px; }
            .workspace-top-actions > a.workspace-quick-action { display: inline-flex; min-height: 39px; align-items: center; gap: 7px; border: 1px solid color-mix(in srgb, var(--brand) 35%, #9aac9f); border-radius: 10px; padding: 0 12px; background: color-mix(in srgb, var(--brand) 7%, #fff); color: color-mix(in srgb, var(--brand) 88%, #173023); font-size: .66rem; font-weight: 780; text-decoration: none; }
            .workspace-top-actions > a.workspace-quick-action:hover { border-color: var(--brand); background: color-mix(in srgb, var(--brand) 13%, #fff); }
            .staff-theme-dark .workspace-top-actions > a.workspace-quick-action { border-color: #597060; background: #1a281f; color: #e5eee8; }

            /* Homepage editor: compact, structured controls for curating a public site. */
            .homepage-editor-grid { gap: 13px; margin-top: 19px; }
            .homepage-help { display: grid; align-content: start; gap: 7px; margin: 0; padding: 18px; border-color: var(--line); border-radius: 15px; background: linear-gradient(145deg, var(--surface), var(--surface-muted)); box-shadow: none; }
            .homepage-help strong { color: var(--ink); font-size: .76rem; }
            .homepage-help ol { gap: 7px; margin: 3px 0 0; padding-left: 17px; font-size: .64rem; }
            .homepage-editor-section { margin-top: 16px; border: 1px solid var(--line); border-radius: 17px; padding: 20px; background: var(--surface); box-shadow: var(--shadow-sm); }
            .homepage-editor-section > div:first-child { max-width: 720px; }
            .homepage-editor-section > div:first-child > p { margin-bottom: 5px; color: var(--brand); font-size: .56rem; letter-spacing: .14em; }
            .homepage-editor-section h2 { font-family: var(--staff-ui-font, Inter, sans-serif); font-size: 1.05rem; font-weight: 760; letter-spacing: -.035em; }
            .homepage-editor-section > div:first-child > span { margin-top: 5px; color: var(--muted); font-size: .66rem; line-height: 1.5; }
            .homepage-order-form { display: flex; align-items: center; justify-content: flex-end; margin-top: 13px; }
            .homepage-order-form .button { min-height: 34px; border-radius: 9px; padding: 0 10px; font-size: .61rem; }
            .homepage-editable-records { grid-template-columns: repeat(auto-fit, minmax(245px, 1fr)); gap: 10px; margin-top: 12px; }
            .homepage-editable-records article { position: relative; gap: 10px; min-height: 132px; border-color: var(--line); border-radius: 13px; padding: 14px; background: var(--surface-muted); box-shadow: none; }
            .homepage-editable-records article:hover { border-color: color-mix(in srgb, var(--brand) 36%, var(--line)); }
            .homepage-record-heading { display: grid; min-width: 0; gap: 4px; padding-right: 63px; }
            .homepage-record-heading strong { overflow: hidden; color: var(--ink); font-size: .73rem; font-weight: 780; text-overflow: ellipsis; white-space: nowrap; }
            .homepage-record-heading span { display: inline-flex; width: max-content; max-width: 100%; border-radius: 999px; padding: 4px 7px; background: color-mix(in srgb, var(--brand) 9%, #fff); color: color-mix(in srgb, var(--brand) 82%, #173023); font-size: .54rem; font-weight: 760; text-transform: uppercase; }
            .homepage-drag-handle { position: absolute; top: 13px; right: 13px; border: 1px solid var(--line-strong); border-radius: 8px; padding: 5px 7px; color: var(--muted) !important; font-size: .52rem !important; font-weight: 720; cursor: grab; user-select: none; }
            .homepage-editable-records details { border-top: 1px solid var(--line); padding-top: 9px; }
            .homepage-editable-records details > summary, .homepage-create > summary { display: inline-flex; min-height: 33px; align-items: center; border: 1px solid var(--line-strong); border-radius: 8px; padding: 0 9px; color: #385246; font-size: .6rem; font-weight: 760; cursor: pointer; list-style: none; }
            .homepage-editable-records details > summary::-webkit-details-marker, .homepage-create > summary::-webkit-details-marker { display: none; }
            .homepage-editable-records details[open] > summary { border-color: color-mix(in srgb, var(--brand) 38%, var(--line-strong)); background: color-mix(in srgb, var(--brand) 7%, #fff); color: var(--brand); }
            .homepage-editable-records .homepage-form { margin-top: 10px; padding: 14px; background: var(--surface); box-shadow: none; }
            .homepage-delete-form { position: absolute; right: 13px; bottom: 12px; }
            .homepage-delete-form button { min-height: 29px !important; border: 1px solid #d79b94 !important; border-radius: 8px !important; padding: 0 8px !important; background: #fff5f3 !important; color: #983f36 !important; box-shadow: none !important; font-size: .57rem !important; }
            .homepage-create { display: block; margin-top: 12px; border: 1px dashed color-mix(in srgb, var(--brand) 32%, var(--line-strong)); border-radius: 12px; padding: 9px; background: color-mix(in srgb, var(--brand) 3%, var(--surface)); }
            .homepage-create > summary { border-color: transparent; background: color-mix(in srgb, var(--brand) 10%, #fff); color: var(--brand); }
            .homepage-create[open] > summary { margin-bottom: 4px; }
            .homepage-create .homepage-form { margin-top: 6px; padding: 14px; box-shadow: none; }
            .homepage-form { gap: 10px; padding: 17px; border-color: var(--line); border-radius: 13px; box-shadow: none; }
            .homepage-form label { gap: 6px; color: #43584b; font-size: .62rem; }
            .homepage-form input, .homepage-form select, .homepage-form textarea { min-height: 39px; border-color: var(--line-strong); font-size: .68rem; }
            .homepage-form textarea { min-height: 78px; }
            .homepage-form .check-field { min-height: 36px; border-radius: 9px; padding: 0 9px; background: var(--surface-muted); }
            .staff-theme-dark .homepage-editor-section, .staff-theme-dark .homepage-help, .staff-theme-dark .homepage-editable-records article { border-color: var(--line); background: var(--surface); box-shadow: none; }
            .staff-theme-dark .homepage-help strong, .staff-theme-dark .homepage-record-heading strong { color: #edf5ef; }
            .staff-theme-dark .homepage-editable-records details { border-color: var(--line); }
            .staff-theme-dark .homepage-editable-records details > summary, .staff-theme-dark .homepage-create > summary { border-color: var(--line-strong); background: #1a281f; color: #dce7df; }
            .staff-theme-dark .homepage-record-heading span { background: #203126; color: #c9dbcE; }
            .staff-theme-dark .homepage-drag-handle { border-color: var(--line-strong); color: #b7c8bd !important; }
            .staff-theme-dark .homepage-editable-records .homepage-form, .staff-theme-dark .homepage-create .homepage-form { background: #17231c; }
            .staff-theme-dark .homepage-delete-form button { border-color: #8f4c46 !important; background: #3a1d1a !important; color: #f4c7c2 !important; }
            @media (max-width: 760px) {
                .homepage-editor-section { margin-top: 13px; padding: 16px; }
                .homepage-order-form { justify-content: stretch; }.homepage-order-form .button { width: 100%; justify-content: center; }
                .homepage-editable-records { grid-template-columns: 1fr; }
                .homepage-form { grid-template-columns: 1fr; }
            }

            /* Pages workspace: page templates and editing share the staff panel language. */
            .page-starters, .pages-records-panel { margin-top: 19px; border: 1px solid var(--line); border-radius: 17px; padding: 19px; background: var(--surface); box-shadow: var(--shadow-sm); }
            .page-starters-heading, .pages-records-panel > header { display: flex; align-items: end; justify-content: space-between; gap: 18px; }
            .page-starters-heading p, .pages-records-panel > header p, .page-editor-heading > p, .page-workflow p { margin: 0 0 5px; color: var(--brand); font-size: .55rem; font-weight: 840; letter-spacing: .13em; line-height: 1.2; }
            .page-starters h2, .pages-records-panel h2, .page-editor-heading h2 { margin: 0; color: var(--ink); font-family: var(--staff-ui-font, Inter, sans-serif); font-size: 1.02rem; font-weight: 780; letter-spacing: -.03em; line-height: 1.2; }
            .page-starters-heading > div > span, .pages-records-panel > header > div > span, .page-editor-heading > span { display: block; max-width: 690px; margin-top: 6px; color: var(--muted); font-size: .64rem; line-height: 1.5; }
            .page-starters-count, .pages-record-count { flex: 0 0 auto; border: 1px solid var(--line-strong); border-radius: 999px; padding: 6px 9px; color: var(--muted); font-size: .57rem; font-weight: 760; white-space: nowrap; }
            .page-starter-grid { display: grid !important; grid-template-columns: repeat(3, minmax(0, 1fr)) !important; gap: 10px !important; margin-top: 15px; }
            .page-starter-grid > a { position: relative; display: grid !important; min-height: 137px; align-content: start; gap: 7px; border: 1px solid var(--line-strong); border-radius: 13px; padding: 15px; background: var(--surface-muted); color: inherit; text-decoration: none; box-shadow: none; transition: border-color .16s ease, transform .16s ease, background .16s ease; }
            .page-starter-grid > a:hover { border-color: color-mix(in srgb, var(--brand) 52%, var(--line-strong)); background: color-mix(in srgb, var(--brand) 4%, var(--surface)); transform: translateY(-1px); }
            .page-starter-number { display: inline-grid; width: max-content; min-height: 20px; place-items: center; border-radius: 6px; padding: 0 6px; background: color-mix(in srgb, var(--brand) 10%, var(--surface)); color: var(--brand); font-size: .48rem; font-weight: 820; letter-spacing: .06em; }
            .page-starter-grid > a > strong { display: block; color: var(--ink); font-size: .72rem; font-weight: 780; line-height: 1.25; }
            .page-starter-grid > a > span:not(.page-starter-number) { display: block; color: var(--muted); font-size: .61rem; line-height: 1.48; }
            .page-starter-grid > a > small { display: flex; align-items: center; gap: 5px; margin-top: auto; color: var(--brand); font-size: .57rem; font-weight: 780; }
            .pages-records-panel > header { margin-bottom: 14px; }
            .pages-records-panel .data-panel { overflow: hidden; border: 1px solid var(--line); border-radius: 12px; box-shadow: none; }
            .pages-records-panel .data-panel code { border-radius: 6px; padding: 4px 6px; background: var(--surface-muted); color: #52665a; font: inherit; font-size: .62rem; }
            .pages-records-panel .table-action { display: inline-flex; align-items: center; gap: 5px; border: 1px solid var(--line-strong); border-radius: 8px; padding: 6px 8px; background: var(--surface); color: #294438; font-size: .58rem; font-weight: 780; text-decoration: none; }
            .pages-records-panel .table-action:hover { border-color: color-mix(in srgb, var(--brand) 48%, var(--line-strong)); background: color-mix(in srgb, var(--brand) 6%, var(--surface)); color: #173225; }
            .staff-theme-dark .pages-records-panel .table-action { border-color: #405548; background: #1a281f; color: #e4eee7; }
            .staff-theme-dark .pages-records-panel .table-action:hover { border-color: color-mix(in srgb, var(--brand) 52%, #597060); background: color-mix(in srgb, var(--brand) 13%, #1a281f); color: #f2f8f3; }

            .page-editor { display: grid; max-width: 960px; gap: 13px; margin-top: 18px; }
            .page-editor-panel { display: block; border: 1px solid var(--line); border-radius: 17px; padding: 19px; background: var(--surface); box-shadow: var(--shadow-sm); }
            .page-editor-heading { margin-bottom: 15px; }
            .page-editor-fields { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 12px !important; }
            .page-editor label { display: grid !important; min-width: 0; gap: 6px; color: #405549; font-size: .63rem; font-weight: 760; line-height: 1.3; }
            .page-editor label small { display: block; margin: -2px 0 1px; color: var(--muted); font-size: .56rem; font-weight: 560; line-height: 1.35; }
            .page-editor input, .page-editor textarea { box-sizing: border-box; width: 100%; min-width: 0; border: 1px solid var(--line-strong); border-radius: 10px; padding: 10px 11px; background: var(--surface-muted); color: var(--ink); font: inherit; font-size: .7rem; line-height: 1.5; }
            .page-editor input { min-height: 40px; }
            .page-editor textarea { resize: vertical; }
            .page-editor input:focus, .page-editor textarea:focus { outline: 2px solid color-mix(in srgb, var(--brand) 38%, transparent); outline-offset: 1px; border-color: var(--brand); }
            .page-content-panel { padding-bottom: 17px; }
            .page-body-label textarea { min-height: 330px; padding: 14px; background: linear-gradient(145deg, var(--surface-muted), var(--surface)); font-size: .74rem; line-height: 1.65; }
            .page-seo-panel { overflow: hidden; border: 1px solid var(--line); border-radius: 15px; background: var(--surface); box-shadow: var(--shadow-sm); }
            .page-seo-panel > summary { display: flex; min-height: 70px; align-items: center; justify-content: space-between; gap: 16px; padding: 13px 16px; cursor: pointer; list-style: none; }
            .page-seo-panel > summary::-webkit-details-marker { display: none; }
            .page-seo-panel > summary > span:first-child { display: grid; gap: 3px; }
            .page-seo-panel > summary small { color: var(--brand); font-size: .52rem; font-weight: 840; letter-spacing: .12em; }
            .page-seo-panel > summary strong { color: var(--ink); font-size: .7rem; font-weight: 780; }
            .page-seo-panel > summary em { color: var(--muted); font-size: .6rem; font-style: normal; line-height: 1.35; }
            .page-seo-panel > summary > span:last-child { display: inline-flex; flex: 0 0 auto; min-height: 33px; align-items: center; gap: 6px; border: 1px solid var(--line-strong); border-radius: 8px; padding: 0 9px; background: var(--surface-muted); color: var(--brand); font-size: .58rem; font-weight: 780; }
            .page-seo-panel > summary > span:last-child .ui-icon { width: 14px; height: 14px; transition: transform .16s ease; }
            .page-seo-panel[open] > summary { border-bottom: 1px solid var(--line); }
            .page-seo-panel[open] > summary > span:last-child .ui-icon { transform: rotate(180deg); }
            .page-editor-seo { padding: 16px; background: var(--surface-muted); }
            .page-editor-actions { display: flex; align-items: center; justify-content: space-between; gap: 16px; border: 1px solid var(--line); border-radius: 14px; padding: 13px 15px; background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 5%, var(--surface)), var(--surface)); }
            .page-editor-actions > div:first-child { display: grid; gap: 3px; }
            .page-editor-actions strong { color: var(--ink); font-size: .68rem; }
            .page-editor-actions span { color: var(--muted); font-size: .59rem; line-height: 1.4; }
            .page-editor-actions > div:last-child { display: flex; flex-wrap: wrap; gap: 8px; }
            .page-block-editor-form { display: grid; max-width: 1460px; gap: 13px; margin-top: 18px; }
            .page-editor-meta { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, .85fr) minmax(180px, .65fr); gap: 11px; border: 1px solid var(--line); border-radius: 15px; padding: 13px; background: var(--surface); box-shadow: var(--shadow-sm); }
            .page-editor-meta label { display: grid; gap: 6px; color: #405549; font-size: .62rem; font-weight: 760; }
            .page-editor-meta label small { color: var(--muted); font-size: .55rem; font-weight: 560; }
            .page-editor-meta input { min-width: 0; min-height: 39px; border: 1px solid var(--line-strong); border-radius: 9px; padding: 0 10px; background: var(--surface-muted); color: var(--ink); font: inherit; font-size: .68rem; }
            .page-editor-meta > span { display: grid; align-content: center; gap: 4px; border-left: 1px solid var(--line); padding-left: 13px; }
            .page-editor-meta > span strong { color: var(--ink); font-size: .66rem; }.page-editor-meta > span small { color: var(--muted); font-size: .57rem; line-height: 1.4; }
            .page-block-editor-shell { display: grid; grid-template-columns: 205px minmax(0, 1fr) 255px; align-items: start; gap: 12px; }
            .page-block-library, .page-block-inspector, .page-block-canvas-wrap { border: 1px solid var(--line); border-radius: 16px; background: var(--surface); box-shadow: var(--shadow-sm); }
            .page-block-library, .page-block-inspector { position: sticky; top: 88px; overflow: hidden; }
            .page-block-library > header, .page-block-inspector > header { padding: 16px 15px 13px; border-bottom: 1px solid var(--line); }
            .page-block-library p, .page-block-inspector p, .page-block-canvas-wrap header p { margin: 0 0 5px; color: var(--brand); font-size: .52rem; font-weight: 840; letter-spacing: .12em; }
            .page-block-library h2, .page-block-inspector h2 { margin: 0; color: var(--ink); font-family: var(--staff-ui-font, Inter, sans-serif); font-size: .82rem; font-weight: 780; }
            .page-block-library header span, .page-block-inspector header span { display: block; margin-top: 6px; color: var(--muted); font-size: .58rem; line-height: 1.45; }
            .page-block-library-group { display: grid; gap: 4px; padding: 12px 9px; border-bottom: 1px solid var(--line); }.page-block-library-group:last-child { border-bottom: 0; }
            .page-block-library-group > small { margin: 0 6px 3px; color: var(--muted); font-size: .49rem; font-weight: 820; letter-spacing: .1em; }
            .page-block-library-group button { min-height: 32px; border: 1px solid transparent; border-radius: 8px; padding: 0 8px; background: transparent; color: #43584b; font: inherit; font-size: .61rem; font-weight: 700; text-align: left; cursor: pointer; }.page-block-library-group button:hover { border-color: color-mix(in srgb, var(--brand) 24%, var(--line)); background: color-mix(in srgb, var(--brand) 7%, var(--surface)); color: var(--brand); }
            .page-block-canvas-wrap { overflow: hidden; min-height: 710px; background: var(--surface-muted); }.page-block-canvas-wrap > header { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid var(--line); padding: 14px 16px; background: var(--surface); }.page-block-canvas-wrap header strong { display: block; color: var(--ink); font-size: .75rem; }.page-block-canvas-wrap > header > span { border: 1px solid var(--line-strong); border-radius: 999px; padding: 5px 8px; color: var(--muted); font-size: .54rem; font-weight: 760; }
            .page-block-canvas { display: grid; width: min(100% - 28px, 780px); min-height: 580px; gap: 10px; margin: 14px auto; padding: 14px; border: 1px solid var(--line); border-radius: 13px; background: #fff; box-shadow: 0 9px 25px rgb(22 42 29 / 5%); }
            .page-canvas-block { position: relative; border: 1px solid transparent; border-radius: 10px; cursor: pointer; }.page-canvas-block:hover { border-color: color-mix(in srgb, var(--brand) 28%, var(--line)); }.page-canvas-block.is-selected { border-color: var(--brand); box-shadow: 0 0 0 2px color-mix(in srgb, var(--brand) 16%, transparent); }.page-canvas-block.is-dragging { opacity: .45; }
            .page-canvas-block > header { display: flex; align-items: center; gap: 7px; min-height: 27px; padding: 0 8px; border-bottom: 1px solid transparent; color: var(--muted); }.page-canvas-block.is-selected > header { border-color: color-mix(in srgb, var(--brand) 20%, var(--line)); background: color-mix(in srgb, var(--brand) 5%, #fff); }.page-canvas-block > header span { color: var(--brand); font-size: .5rem; font-weight: 820; }.page-canvas-block > header strong { color: #53675a; font-size: .55rem; font-weight: 760; }.page-canvas-block > header em { margin-left: auto; color: #95a49b; font-size: .5rem; font-style: normal; }
            .page-canvas-block-content { padding: 13px; }.page-canvas-block-content h2 { margin: 0; color: #17271e; font-size: 1.05rem; line-height: 1.2; }.page-canvas-block-content p { margin: 8px 0 0; color: #52665a; font-size: .68rem; line-height: 1.65; white-space: pre-wrap; }.page-canvas-block-content img { display: block; width: 100%; max-height: 270px; border-radius: 8px; object-fit: cover; }.page-canvas-block-content > small { display: block; margin-top: 6px; color: #708076; font-size: .55rem; }.page-canvas-block-content hr { border: 0; border-top: 1px solid #dce4de; }
            .page-canvas-hero, .page-canvas-cta, .page-canvas-module { border-radius: 9px; padding: 22px; }.page-canvas-hero { background: linear-gradient(135deg, #152a20, color-mix(in srgb, var(--brand) 80%, #183326)); color: #fff; }.page-canvas-hero small, .page-canvas-module small { color: var(--accent); font-size: .54rem; font-weight: 820; letter-spacing: .1em; }.page-canvas-hero h1 { margin: 8px 0 0; color: #fff; font-size: 1.7rem; letter-spacing: -.045em; }.page-canvas-hero p { color: #d8e4dc; }.page-canvas-hero b, .page-canvas-cta b { display: inline-flex; margin-top: 14px; border-radius: 7px; padding: 8px 10px; background: var(--accent); color: #18261e; font-size: .59rem; }.page-canvas-cta { background: color-mix(in srgb, var(--brand) 8%, #f7faf7); }.page-canvas-module { border: 1px dashed color-mix(in srgb, var(--brand) 35%, var(--line)); background: #f8faf8; }.page-canvas-module b { display: block; margin-top: 12px; color: var(--brand); font-size: .57rem; }.page-canvas-block-content blockquote { margin: 0; border-left: 3px solid var(--accent); padding: 6px 0 6px 14px; color: #34483d; font-size: .88rem; font-style: italic; line-height: 1.55; }.page-canvas-block-content blockquote small { display: block; margin-top: 8px; color: #728178; font-size: .56rem; font-style: normal; }.page-canvas-image-placeholder { display: grid; min-height: 160px; place-content: center; gap: 8px; border: 1px dashed #aebeb3; border-radius: 8px; color: #607269; font-size: .8rem; text-align: center; }.page-canvas-image-placeholder small { font-size: .56rem; }
            .page-block-add-canvas { display: flex; width: calc(100% - 28px); min-height: 37px; align-items: center; justify-content: center; margin: 0 14px 14px; border: 1px dashed color-mix(in srgb, var(--brand) 36%, var(--line-strong)); border-radius: 9px; background: color-mix(in srgb, var(--brand) 3%, var(--surface)); color: var(--brand); font: inherit; font-size: .61rem; font-weight: 780; cursor: pointer; }
            .page-block-inspector [data-page-block-inspector], .page-block-inspector [data-inspector] { display: grid; gap: 12px; padding: 16px 16px 18px; }.page-inspector-empty { color: var(--muted); font-size: .62rem; line-height: 1.55; }.page-inspector-selected { display: grid; gap: 3px; border-bottom: 1px solid var(--line); padding-bottom: 10px; }.page-inspector-selected span { color: var(--brand); font-size: .53rem; font-weight: 800; }.page-inspector-selected strong { color: var(--ink); font-size: .7rem; }.page-block-inspector label { display: grid; gap: 5px; color: #43584b; font-size: .59rem; font-weight: 760; }.page-block-inspector input, .page-block-inspector textarea, .page-block-inspector select { box-sizing: border-box; width: 100%; border: 1px solid var(--line-strong); border-radius: 8px; padding: 8px; background: var(--surface-muted); color: var(--ink); font: inherit; font-size: .63rem; line-height: 1.45; }.page-block-inspector textarea { resize: vertical; }.page-inspector-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; border-top: 1px solid var(--line); padding-top: 10px; }.page-inspector-actions button { min-height: 30px; border: 1px solid var(--line-strong); border-radius: 7px; background: var(--surface); color: #405549; font: inherit; font-size: .56rem; font-weight: 730; cursor: pointer; }.page-inspector-actions button.is-danger { border-color: #dba39c; background: #fff6f4; color: #9a4238; }
            .page-canvas-toolbar { display: inline-flex; gap: 3px; border: 1px solid var(--line); border-radius: 9px; padding: 3px; background: var(--surface-muted); }
            .page-canvas-toolbar button { min-height: 25px; border: 0; border-radius: 6px; padding: 0 7px; background: transparent; color: var(--muted); font: inherit; font-size: .52rem; font-weight: 760; cursor: pointer; }
            .page-canvas-toolbar button.is-active { background: var(--surface); box-shadow: 0 1px 3px rgb(20 35 25 / 14%); color: var(--brand); }
            .page-block-canvas[data-preview="tablet"] { width: min(100% - 28px, 630px); }
            .page-block-canvas[data-preview="mobile"] { width: min(100% - 28px, 390px); }
            .navigation-destinations { margin-top: 18px; border: 1px solid var(--line); border-radius: 18px; padding: clamp(16px, 2.5vw, 24px); background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 7%, var(--surface)), var(--surface)); box-shadow: var(--shadow-sm); }.navigation-destinations > header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 18px; }.navigation-destinations p { margin: 0 0 7px; color: var(--brand); font-size: .6rem; font-weight: 850; letter-spacing: .12em; }.navigation-destinations h2 { margin: 0; color: var(--ink); font-size: clamp(1rem, 2vw, 1.28rem); letter-spacing: -.025em; }.navigation-destinations header span { display: block; max-width: 680px; margin-top: 7px; color: var(--muted); font-size: .7rem; line-height: 1.6; }.navigation-destinations > header > span { flex: 0 0 auto; border: 1px solid var(--line-strong); border-radius: 999px; padding: 5px 9px; color: var(--brand); font-size: .57rem; font-weight: 780; }.navigation-destination-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }.navigation-destination-grid > section { border: 1px solid var(--line); border-radius: 12px; padding: 11px; background: color-mix(in srgb, var(--surface) 90%, transparent); }.navigation-destination-grid h3 { margin: 0 0 8px; color: var(--muted); font-size: .57rem; font-weight: 820; letter-spacing: .08em; text-transform: uppercase; }.navigation-destination-grid > section > div { display: grid; gap: 5px; }.navigation-destination-grid form { margin: 0; }.navigation-destination-grid button { display: flex; width: 100%; align-items: center; gap: 8px; border: 1px solid transparent; border-radius: 8px; padding: 8px; background: transparent; color: var(--ink); text-align: left; cursor: pointer; }.navigation-destination-grid button:hover { border-color: color-mix(in srgb, var(--brand) 28%, var(--line)); background: color-mix(in srgb, var(--brand) 7%, var(--surface)); }.navigation-destination-grid button > span { display: grid; min-width: 0; gap: 2px; }.navigation-destination-grid button strong { overflow: hidden; font-size: .65rem; text-overflow: ellipsis; white-space: nowrap; }.navigation-destination-grid button small { overflow: hidden; color: var(--muted); font-size: .53rem; text-overflow: ellipsis; white-space: nowrap; }.navigation-destination-grid button b { margin-left: auto; color: var(--brand); font-size: .9rem; }.navigation-list > header h2 { font-size: 1.08rem; letter-spacing: -.02em; }.navigation-list > header span:not(:last-child) { display: block; max-width: 580px; margin-top: 6px; color: var(--muted); font-size: .69rem; line-height: 1.55; }.navigation-list .empty-cell { display: grid; min-height: 170px; place-content: center; border: 1px dashed var(--line-strong); border-radius: 12px; padding: 20px; color: var(--muted); font-size: .72rem; line-height: 1.6; text-align: center; }
            .navigation-manager { display: grid; grid-template-columns: minmax(270px, .72fr) minmax(0, 1.5fr); align-items: start; gap: 16px; margin-top: 18px; }.navigation-add, .navigation-list { border: 1px solid var(--line); border-radius: 16px; padding: 17px; background: var(--surface); box-shadow: var(--shadow-sm); }.navigation-add p, .navigation-list header p { margin: 0 0 5px; color: var(--brand); font-size: .57rem; font-weight: 820; letter-spacing: .11em; }.navigation-add h2, .navigation-list h2 { margin: 0; color: var(--ink); font-size: 1rem; }.navigation-add form, .navigation-item details > form { display: grid; gap: 11px; margin-top: 15px; }.navigation-add label, .navigation-item details label { display: grid; gap: 5px; color: var(--ink); font-size: .61rem; font-weight: 730; }.navigation-add input, .navigation-add select, .navigation-item input, .navigation-item select { min-height: 36px; border: 1px solid var(--line-strong); border-radius: 8px; padding: 0 9px; background: var(--surface-muted); color: var(--ink); font: inherit; font-size: .65rem; }.navigation-field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }.navigation-checks { display: grid; gap: 6px; }.navigation-checks label { display: flex; align-items: center; gap: 7px; font-size: .61rem; }.navigation-list > header { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 12px; }.navigation-item { margin-bottom: 8px; border: 1px solid var(--line); border-radius: 11px; background: var(--surface-muted); }.navigation-item-summary { display: flex; min-height: 49px; align-items: center; gap: 9px; padding: 8px 10px; }.navigation-drag { color: var(--muted); cursor: grab; letter-spacing: -2px; }.navigation-item-summary > div { display: grid; min-width: 0; gap: 2px; }.navigation-item-summary strong { color: var(--ink); font-size: .67rem; }.navigation-item-summary small { overflow: hidden; color: var(--muted); font-size: .55rem; text-overflow: ellipsis; white-space: nowrap; }.navigation-item-summary .table-status { margin-left: auto; }.navigation-item details { margin-left: auto; }.navigation-item summary { color: var(--brand); font-size: .6rem; font-weight: 750; cursor: pointer; }.navigation-item details > form { border-top: 1px solid var(--line); padding: 12px; }.navigation-item.is-dragging { opacity: .48; }.public-nav-dropdown summary { display: flex; align-items: center; gap: 10px; padding: 9px 11px; color: inherit; cursor: pointer; list-style: none; }.public-nav-dropdown summary::-webkit-details-marker { display: none; }.public-nav-dropdown summary .ui-icon:last-child { margin-left: auto; }.public-nav-dropdown > div { display: grid; gap: 3px; margin: 0 8px 8px 36px; border-left: 1px solid var(--line); padding-left: 8px; }.public-nav-dropdown > div a { display: flex; align-items: center; gap: 8px; }
            .page-canvas-section { border-radius: 10px; padding: 24px; }.page-canvas-section h2 { margin: 0; }.page-canvas-section h3 { margin: 0; color: #203126; font-size: .78rem; }.page-canvas-columns { display: grid; gap: 10px; margin-top: 16px; }.page-canvas-columns > div { border: 1px solid rgb(44 74 55 / 14%); border-radius: 8px; padding: 12px; background: rgb(255 255 255 / 48%); }.page-canvas-columns > div p { margin-top: 5px; }.page-columns-two .page-canvas-columns { grid-template-columns: repeat(2, minmax(0, 1fr)); }.page-columns-three .page-canvas-columns { grid-template-columns: repeat(3, minmax(0, 1fr)); }.page-padding-compact { padding: 15px; }.page-padding-spacious { padding: 38px; }
            .public-page-section { border: 1px solid var(--line); border-radius: 18px; padding: clamp(24px, 4vw, 45px); }.public-page-section header { max-width: 680px; }.public-page-section h2 { margin: 0; color: var(--ink); font-size: clamp(1.35rem, 3vw, 2.1rem); letter-spacing: -.04em; }.public-page-section header > p, .public-page-section h3 + p { color: var(--muted); line-height: 1.7; }.public-page-columns { display: grid; gap: 14px; margin-top: 24px; }.public-page-columns > div { border-top: 1px solid color-mix(in srgb, var(--ink) 15%, transparent); padding-top: 14px; }.public-page-columns h3 { margin: 0; color: var(--ink); font-size: 1rem; }.public-page-section-two .public-page-columns { grid-template-columns: repeat(2, minmax(0, 1fr)); }.public-page-section-three .public-page-columns { grid-template-columns: repeat(3, minmax(0, 1fr)); }.public-page-section-compact { padding: 22px; }.public-page-section-spacious { padding: clamp(40px, 7vw, 82px); }            .public-page-blocks { display: grid; width: min(100% - 36px, 980px); gap: 20px; margin: 30px auto 70px; }
            .public-page-hero { padding: clamp(30px, 6vw, 72px); border: 1px solid color-mix(in srgb, var(--brand) 32%, var(--line)); border-radius: 22px; background: linear-gradient(135deg, #10261c, color-mix(in srgb, var(--brand) 76%, #173a29)); box-shadow: 0 20px 50px rgb(16 38 28 / 18%); color: #f7fbf7; }
            .public-page-hero small, .public-page-callout small { color: var(--accent); font-size: .67rem; font-weight: 820; letter-spacing: .12em; }.public-page-hero h1 { max-width: 760px; margin: 12px 0; color: #fff; font-size: clamp(2rem, 5vw, 4rem); line-height: 1.04; letter-spacing: -.055em; }.public-page-hero p { max-width: 620px; color: #d7e5dc; font-size: 1rem; line-height: 1.7; }.public-page-hero .button { margin-top: 13px; background: var(--accent); color: #16271d; }
            .public-page-copy { max-width: 760px; margin: 0 auto; }.public-page-copy h2 { margin: 0; color: var(--ink); font-size: clamp(1.45rem, 3vw, 2.2rem); letter-spacing: -.035em; }.public-page-copy p { margin: 0; color: var(--muted); font-size: 1rem; line-height: 1.8; }
            .public-page-image { margin: 0; overflow: hidden; border: 1px solid var(--line); border-radius: 18px; background: var(--surface); }.public-page-image img { display: block; width: 100%; max-height: 560px; object-fit: cover; }.public-page-image figcaption { padding: 11px 14px; color: var(--muted); font-size: .75rem; }
            .public-page-callout { padding: clamp(23px, 4vw, 38px); border: 1px solid color-mix(in srgb, var(--brand) 24%, var(--line)); border-radius: 18px; background: color-mix(in srgb, var(--brand) 6%, var(--surface)); }.public-page-callout h2 { margin: 8px 0; color: var(--ink); font-size: clamp(1.3rem, 2.7vw, 2rem); letter-spacing: -.035em; }.public-page-callout p { max-width: 650px; color: var(--muted); line-height: 1.7; }.public-page-callout a:not(.button) { display: inline-flex; min-height: 37px; align-items: center; margin-top: 12px; border: 1px solid var(--line-strong); border-radius: 9px; padding: 0 11px; background: var(--surface); color: #294438; font-size: .7rem; font-weight: 760; text-decoration: none; }.public-page-callout a:not(.button):hover { border-color: color-mix(in srgb, var(--brand) 48%, var(--line-strong)); background: color-mix(in srgb, var(--brand) 7%, var(--surface)); color: #173225; }.public-theme-dark .public-page-callout a:not(.button) { border-color: #405548; background: #1a281f; color: #e4eee7; }.public-theme-dark .public-page-callout a:not(.button):hover { border-color: color-mix(in srgb, var(--brand) 52%, #597060); background: color-mix(in srgb, var(--brand) 13%, #1a281f); color: #f2f8f3; }
            .public-page-quote { max-width: 760px; margin: 0 auto; border-left: 4px solid var(--accent); padding: 10px 0 10px 24px; color: var(--ink); font-family: var(--font-heading, inherit); font-size: clamp(1.3rem, 2.8vw, 2rem); font-style: italic; line-height: 1.45; }.public-page-quote small { display: block; margin-top: 12px; color: var(--muted); font-family: inherit; font-size: .75rem; font-style: normal; }.public-page-divider { width: 120px; margin: 12px auto; border: 0; border-top: 2px solid var(--accent); }            .page-canvas-block [contenteditable="true"] { border-radius: 4px; outline: 0; transition: background .15s ease, box-shadow .15s ease; cursor: text; }.page-canvas-block [contenteditable="true"]:hover { background: color-mix(in srgb, var(--brand) 7%, transparent); }.page-canvas-block [contenteditable="true"]:focus { background: color-mix(in srgb, var(--brand) 10%, transparent); box-shadow: 0 0 0 2px color-mix(in srgb, var(--brand) 35%, transparent); }
            .page-media-picker-trigger { min-height: 30px; border: 1px dashed color-mix(in srgb, var(--brand) 46%, var(--line-strong)); border-radius: 7px; padding: 0 8px; background: color-mix(in srgb, var(--brand) 5%, var(--surface)); color: var(--brand); font: inherit; font-size: .56rem; font-weight: 760; text-align: left; cursor: pointer; }
            .page-media-dialog[hidden] { display: none; }.page-media-dialog { position: fixed; z-index: 80; inset: 0; display: grid; place-items: center; padding: 18px; }.page-media-backdrop { position: absolute; inset: 0; border: 0; background: rgb(10 22 15 / 56%); }.page-media-dialog > section { position: relative; width: min(100%, 650px); max-height: min(720px, 90vh); overflow: auto; border: 1px solid var(--line-strong); border-radius: 18px; background: var(--surface); box-shadow: 0 24px 70px rgb(0 0 0 / 30%); }.page-media-dialog > section > header { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; border-bottom: 1px solid var(--line); padding: 18px; }.page-media-dialog header p { margin: 0 0 4px; color: var(--brand); font-size: .55rem; font-weight: 820; letter-spacing: .11em; }.page-media-dialog header h2 { margin: 0; color: var(--ink); font-size: 1rem; }.page-media-dialog header span { display: block; margin-top: 5px; color: var(--muted); font-size: .64rem; line-height: 1.5; }.page-media-dialog header button { display: grid; width: 30px; height: 30px; place-items: center; border: 1px solid var(--line-strong); border-radius: 8px; background: var(--surface-muted); color: var(--ink); font: inherit; font-size: 1.1rem; cursor: pointer; }.page-media-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; padding: 18px; }.page-media-list button { display: grid; min-height: 74px; gap: 5px; border: 1px solid var(--line); border-radius: 10px; padding: 12px; background: var(--surface-muted); color: var(--ink); text-align: left; cursor: pointer; }.page-media-list button:hover { border-color: var(--brand); background: color-mix(in srgb, var(--brand) 7%, var(--surface)); }.page-media-list button strong { font-size: .68rem; }.page-media-list button span { color: var(--muted); font-size: .55rem; }            .page-workflow { display: flex; max-width: 960px; align-items: center; justify-content: space-between; gap: 16px; margin-top: 13px; border: 1px solid var(--line); border-radius: 15px; padding: 15px 16px; background: var(--surface-muted); }
            .page-workflow strong { display: block; color: var(--ink); font-size: .73rem; }
            .page-workflow > div:first-child > span { display: block; margin-top: 4px; color: var(--muted); font-size: .6rem; line-height: 1.4; }
            .page-workflow-actions { display: flex; align-items: center; justify-content: flex-end; gap: 10px; }
            .page-workflow-actions form { margin: 0; }
            .page-workflow-actions > span, .page-workflow-actions small { max-width: 260px; color: var(--muted); font-size: .58rem; line-height: 1.4; text-align: right; }
            .staff-theme-dark :is(.page-editor-meta, .page-block-library, .page-block-inspector, .page-block-canvas-wrap) { border-color: var(--line); background: var(--surface); box-shadow: none; }
            .staff-theme-dark .page-editor-meta > span, .staff-theme-dark :is(.page-block-library > header, .page-block-inspector > header, .page-block-library-group) { border-color: var(--line); }
            .staff-theme-dark :is(.page-editor-meta label, .page-block-library h2, .page-block-inspector h2, .page-block-inspector label, .page-inspector-selected strong) { color: #e6eee8; }
            .staff-theme-dark :is(.page-editor-meta input, .page-block-inspector input, .page-block-inspector textarea) { border-color: var(--line-strong); background: #101813; color: #e6eee8; }
            .staff-theme-dark .page-block-library-group button { color: #c4d2c8; }.staff-theme-dark .page-block-library-group button:hover { border-color: #597060; background: #1a281f; }
            .staff-theme-dark .page-block-canvas { border-color: #405548; background: #17231c; box-shadow: none; }.staff-theme-dark .page-canvas-block:hover { border-color: #597060; }.staff-theme-dark .page-canvas-block.is-selected > header { background: #1a2b20; }.staff-theme-dark .page-canvas-block > header strong, .staff-theme-dark .page-canvas-block-content h2 { color: #edf5ef; }.staff-theme-dark .page-canvas-block-content p { color: #b4c4ba; }.staff-theme-dark .page-canvas-module { background: #1a281f; }.staff-theme-dark .page-canvas-block-content blockquote { color: #dce8e0; }.staff-theme-dark .page-canvas-image-placeholder { border-color: #597060; color: #b4c4ba; }.staff-theme-dark .page-inspector-actions button { border-color: var(--line-strong); background: #1a281f; color: #dce7df; }.staff-theme-dark .page-inspector-actions button.is-danger { border-color: #8f4c46; background: #3a1d1a; color: #f4c7c2; }
            .staff-theme-dark :is(.page-starters, .pages-records-panel, .page-editor-panel, .page-seo-panel) { border-color: var(--line); background: var(--surface); box-shadow: none; }
            .staff-theme-dark :is(.page-starter-grid > a, .pages-records-panel .data-panel, .page-editor-actions, .page-workflow) { border-color: var(--line); background: #17231c; box-shadow: none; }
            .staff-theme-dark .page-starter-grid > a:hover { border-color: #597060; background: #1a281f; }
            .staff-theme-dark :is(.page-starters h2, .pages-records-panel h2, .page-editor-heading h2, .page-editor label, .page-editor-actions strong, .page-workflow strong, .page-seo-panel > summary strong) { color: #edf5ef; }
            .staff-theme-dark .pages-records-panel .data-panel code, .staff-theme-dark .page-editor input, .staff-theme-dark .page-editor textarea, .staff-theme-dark .page-seo-panel > summary > span:last-child, .staff-theme-dark .page-editor-seo { border-color: var(--line-strong); background: #101813; color: #e5eee8; }
            .staff-theme-dark .page-editor-actions { background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 14%, #17231c), #17231c); }
            @media (max-width: 920px) { .page-starter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; } }
            @media (max-width: 760px) {
                .page-starters, .pages-records-panel, .page-editor-panel { padding: 15px; }
                .page-starters-heading, .pages-records-panel > header, .page-editor-actions, .page-workflow { align-items: flex-start; flex-direction: column; }
                .page-starters-count, .pages-record-count { margin-top: 2px; }
                .page-starter-grid, .page-editor-fields { grid-template-columns: 1fr !important; }
                .page-starter-grid > a { min-height: 121px; }
                .page-seo-panel > summary { min-height: 84px; padding: 14px; }
                .page-seo-panel > summary em { max-width: 230px; }
                .page-editor-actions, .page-workflow { gap: 11px; }
                .page-editor-actions > div:last-child, .page-workflow-actions { width: 100%; justify-content: stretch; }
                .page-editor-actions > div:last-child > :is(.button, a), .page-workflow-actions .button { width: 100%; justify-content: center; }
                .page-workflow-actions > span, .page-workflow-actions small { max-width: none; text-align: left; }
                .page-body-label textarea { min-height: 270px; }
                .page-editor-meta { grid-template-columns: 1fr; }.page-editor-meta > span { border-top: 1px solid var(--line); border-left: 0; padding: 10px 0 0; }
                .page-block-editor-shell { grid-template-columns: 1fr; }.page-block-library, .page-block-inspector { position: static; }.page-block-library-group { grid-template-columns: repeat(2, minmax(0, 1fr)); }.page-block-library-group > small { grid-column: 1 / -1; }.page-block-canvas-wrap { min-height: 0; }.page-block-canvas { width: calc(100% - 22px); margin: 11px; padding: 10px; }.page-block-add-canvas { width: calc(100% - 22px); margin: 0 11px 11px; }
            }

            .member-content { padding: clamp(24px, 3vw, 42px); }
            .member-page-heading { gap: 5px; margin-bottom: 20px; }
            .member-page-heading h1 { font-size: clamp(1.85rem, 3vw, 2.7rem); }
            .member-page-heading > span { font-size: .7rem; line-height: 1.55; }
            .member-hero { min-height: 224px; border-radius: 20px; padding: clamp(26px, 4vw, 45px); }
            .member-hero h1 { font-size: clamp(2rem, 4vw, 3.4rem); }
            .member-hero-copy > span { margin-top: 11px; font-size: .72rem; }
            .member-next-event { border-radius: 14px; padding: 17px; }
            .member-metrics { gap: 10px; margin-top: 14px; }
            .member-metrics article, .member-metrics > a { border-radius: 14px; padding: 14px; }
            .member-metrics article > span { width: 33px; height: 33px; border-radius: 10px; }
            .member-metrics > a > div { display: grid; min-width: 0; gap: 4px; }
            .member-metrics > a strong { display: block; line-height: 1; }
            .member-metrics > a small { display: block; overflow: hidden; color: var(--member-muted); font-size: .6rem; line-height: 1.25; text-overflow: ellipsis; white-space: nowrap; }
            .member-dashboard-grid, .member-page-grid { gap: 14px; margin-top: 14px; }
            .member-panel { border-radius: 16px; padding: clamp(18px, 2.4vw, 23px); }
            .member-panel > header { margin-bottom: 15px; }
            .member-panel > header h2 { font-size: 1.18rem; }
            .member-list > article { gap: 10px; padding: 11px 0; }
            .member-tile-grid, .member-destination-grid { gap: 9px; }
            .member-tile-grid > article, .member-preference, .member-destination-grid > a { border-radius: 13px; padding: 14px; }
            .workspace-mobile-nav { display: none; }
            .mobile-header-brand { display: none; }
            @media (max-width: 760px) {
                .workspace-content, .member-content { padding: 23px 17px 88px; }
                .app-topbar .workspace-context, .member-topbar > div:not(.member-top-actions) { display: none; }
                .mobile-header-brand { display: grid; width: 33px; height: 33px; flex: 0 0 auto; place-items: center; overflow: hidden; border: 1px solid color-mix(in srgb, var(--brand) 28%, #aab9af); border-radius: 10px; background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 78%, #f3bc56), var(--accent)); color: #173023; font-family: Georgia, serif; font-size: .76rem; font-weight: 800; box-shadow: 0 4px 11px rgb(18 42 29 / 9%); }
                .mobile-header-brand img { width: 100%; height: 100%; object-fit: contain; padding: 3px; background: #fff; }
                .staff-theme-dark .mobile-header-brand, .member-theme-dark .mobile-header-brand, .public-theme-dark .mobile-header-brand { border-color: #597060; background: #1a281f; color: #e7efe9; box-shadow: none; }
                .staff-theme-dark .mobile-header-brand img, .member-theme-dark .mobile-header-brand img, .public-theme-dark .mobile-header-brand img { background: #1a281f; }
                .workspace-top-actions .workspace-quick-action { width: 39px; justify-content: center; padding: 0; }
                .workspace-top-actions .workspace-quick-action span { display: none; }
                .metric-grid { gap: 10px; margin-top: 18px; }
                .metric-grid article { padding: 14px; }
                .member-hero { min-height: 0; padding: 25px 20px; }
                .workspace-mobile-nav { position: fixed; z-index: 40; right: 10px; bottom: 10px; left: 10px; display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); align-items: stretch; gap: 3px; min-height: 61px; border: 1px solid #aab9af; border-radius: 17px; padding: 5px; background: rgb(251 253 251 / 95%); box-shadow: 0 16px 38px rgb(12 31 20 / 18%); backdrop-filter: blur(18px); }
                .workspace-mobile-nav :is(a, button) { display: grid; min-width: 0; align-content: center; justify-items: center; gap: 3px; border: 0; border-radius: 12px; padding: 4px 2px; background: transparent; color: #63766a; font: inherit; font-size: .52rem; font-weight: 760; line-height: 1.1; text-decoration: none; }
                .workspace-mobile-nav :is(a, button) .ui-icon { width: 18px; height: 18px; }
                .workspace-mobile-nav a[aria-current='page'] { background: color-mix(in srgb, var(--brand) 12%, #fff); color: var(--brand); }
                .workspace-mobile-nav :is(a, button):active { background: color-mix(in srgb, var(--brand) 17%, #fff); }
                .navigation-open .workspace-mobile-nav, .public-navigation-open .workspace-mobile-nav { display: none; }
                .staff-theme-dark .workspace-mobile-nav, .public-theme-dark .workspace-mobile-nav { border-color: #405548; background: rgb(20 32 25 / 95%); box-shadow: 0 16px 38px rgb(0 0 0 / 32%); }
                .staff-theme-dark .workspace-mobile-nav :is(a, button), .public-theme-dark .workspace-mobile-nav :is(a, button) { color: #afc0b5; }
                .staff-theme-dark .workspace-mobile-nav a[aria-current='page'], .public-theme-dark .workspace-mobile-nav a[aria-current='page'] { background: color-mix(in srgb, var(--brand) 36%, #17271e); color: #fff; }
                .public-workspace .public-footer { padding-bottom: 96px; }
            }
        @media (max-width: 620px) { .page-columns-two .page-canvas-columns, .page-columns-three .page-canvas-columns, .public-page-section-two .public-page-columns, .public-page-section-three .public-page-columns { grid-template-columns: 1fr; } }
        
            .page-canvas-gallery { display: grid; gap: 8px; }.page-canvas-gallery img { width: 100%; min-height: 96px; max-height: 160px; border-radius: 8px; object-fit: cover; }.page-gallery-two { grid-template-columns: repeat(2, 1fr); }.page-gallery-three { grid-template-columns: repeat(3, 1fr); }.page-gallery-four { grid-template-columns: repeat(4, 1fr); }.page-canvas-video, .page-canvas-audio, .page-canvas-file { display: grid; gap: 7px; border: 1px solid color-mix(in srgb, var(--brand) 25%, var(--line)); border-radius: 10px; padding: 18px; background: color-mix(in srgb, var(--brand) 5%, var(--surface)); }.page-canvas-video b, .page-canvas-audio b, .page-canvas-file b { color: var(--brand); font-size: .55rem; letter-spacing: .12em; }.page-canvas-video strong, .page-canvas-audio strong, .page-canvas-file strong { color: var(--ink); font-size: .82rem; }.page-canvas-video small, .page-canvas-audio small, .page-canvas-file small { color: var(--muted); font-size: .6rem; overflow-wrap: anywhere; }.page-canvas-media-text { display: grid; grid-template-columns: .9fr 1.1fr; gap: 14px; align-items: center; }.page-media-right > :first-child { order: 2; }.page-canvas-list ul { margin: 10px 0 0; padding-left: 18px; color: #52665a; font-size: .68rem; line-height: 1.8; }.page-canvas-accordion { display: grid; gap: 7px; }.page-canvas-accordion > div { border: 1px solid var(--line); border-radius: 7px; padding: 9px; }.page-canvas-accordion > div p { margin-top: 4px; }.page-canvas-spacer { display: grid; place-content: center; border: 1px dashed var(--line-strong); border-radius: 7px; color: var(--muted); font-size: .55rem; }.page-spacer-small { min-height: 24px; }.page-spacer-medium { min-height: 52px; }.page-spacer-large { min-height: 84px; }
            .public-page-gallery { display: grid; gap: 12px; margin: 0; }.public-page-gallery img { width: 100%; height: 100%; min-height: 180px; border-radius: 14px; object-fit: cover; }.public-gallery-two { grid-template-columns: repeat(2, 1fr); }.public-gallery-three { grid-template-columns: repeat(3, 1fr); }.public-gallery-four { grid-template-columns: repeat(4, 1fr); }.public-page-gallery figcaption, .public-page-video figcaption { grid-column: 1 / -1; color: var(--muted); font-size: .78rem; }.public-page-video { margin: 0; }.public-page-video iframe { display: block; width: 100%; aspect-ratio: 16 / 9; border: 0; border-radius: 18px; background: #101713; }.public-page-audio, .public-page-file { display: flex; align-items: center; justify-content: space-between; gap: 24px; border: 1px solid var(--line); border-radius: 16px; padding: 22px; background: var(--surface); }.public-page-audio figcaption { display: grid; gap: 5px; }.public-page-audio figcaption strong, .public-page-file h2 { color: var(--ink); }.public-page-audio figcaption span, .public-page-file p { color: var(--muted); font-size: .85rem; }.public-page-audio audio { width: min(100%, 360px); }.public-page-file small { color: var(--brand); font-size: .65rem; font-weight: 800; letter-spacing: .1em; }.public-page-file h2 { margin: 6px 0; }.public-page-file p { margin: 0; }.public-page-media-text { display: grid; grid-template-columns: 1fr 1fr; align-items: stretch; overflow: hidden; border: 1px solid var(--line); border-radius: 18px; background: var(--surface); }.public-page-media-right > :first-child { order: 2; }.public-page-media-visual { display: grid; min-height: 280px; place-content: center; background: color-mix(in srgb, var(--brand) 10%, var(--surface-muted)); color: var(--muted); }.public-page-media-visual img { width: 100%; height: 100%; object-fit: cover; }.public-page-media-text > div:last-child { padding: clamp(24px, 4vw, 52px); }.public-page-media-text h2, .public-page-list h2, .public-page-accordion h2 { margin: 0; color: var(--ink); }.public-page-media-text p, .public-page-list li, .public-page-accordion p { color: var(--muted); line-height: 1.7; }.public-page-list { max-width: 760px; }.public-page-list ul, .public-page-list ol { display: grid; gap: 8px; margin: 18px 0 0; padding-left: 22px; }.public-page-accordion { display: grid; gap: 10px; }.public-page-accordion details { border: 1px solid var(--line); border-radius: 12px; padding: 0 16px; background: var(--surface); }.public-page-accordion summary { padding: 16px 0; color: var(--ink); font-weight: 750; cursor: pointer; }.public-page-accordion p { margin: 0 0 16px; }.public-page-spacer-small { min-height: 24px; }.public-page-spacer-medium { min-height: 56px; }.public-page-spacer-large { min-height: 96px; }
            @media (max-width: 680px) { .page-gallery-four, .page-gallery-three { grid-template-columns: repeat(2, 1fr); }.page-canvas-media-text, .public-page-media-text { grid-template-columns: 1fr; }.page-media-right > :first-child, .public-page-media-right > :first-child { order: 0; }.public-page-audio, .public-page-file { align-items: stretch; flex-direction: column; }.public-page-audio audio { width: 100%; }.public-gallery-four, .public-gallery-three { grid-template-columns: repeat(2, 1fr); } }

        
            /* Compact visual editor controls: text remains where it identifies a content block; repeated commands use labelled icons. */
            .page-editor-icon-button { min-width: 34px; min-height: 34px; padding: 0 !important; }.page-editor-icon-button span { display: none; }.page-editor-icon-button svg, .page-canvas-toolbar svg, .page-inspector-actions svg { display: block !important; width: 16px !important; min-width: 16px !important; max-width: 16px !important; height: 16px !important; min-height: 16px !important; flex: 0 0 16px !important; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.8; }
            .page-editor-meta { gap: 9px; padding: 11px; }.page-editor-meta input { min-height: 36px; }.page-block-library > header, .page-block-inspector > header { padding: 13px 13px 11px; }.page-block-library-group { gap: 2px; padding: 9px 7px; }.page-block-library-group button { min-height: 29px; padding: 0 7px; font-size: .58rem; }.page-block-inspector [data-page-block-inspector], .page-block-inspector [data-inspector] { gap: 9px; padding: 13px 14px 15px; }.page-block-inspector input, .page-block-inspector textarea, .page-block-inspector select { padding: 7px; }.page-inspector-actions { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 5px; padding-top: 9px; }.page-inspector-actions button { display: grid; min-height: 31px; place-items: center; padding: 0; }.page-canvas-toolbar button { display: grid; width: 28px; min-height: 27px; place-items: center; padding: 0; }.page-canvas-block > header { min-height: 23px; padding: 0 7px; }.page-canvas-block-content { padding: 11px; }.page-block-add-canvas { min-height: 32px; padding: 0 10px; }.page-editor-actions { padding: 10px 12px; }
            @media (max-width: 680px) { .page-editor-icon-button { min-width: 38px; min-height: 38px; }.page-block-library-group button { min-height: 34px; }.page-inspector-actions button { min-height: 36px; } }

            /* Digital church: provider-safe producer controls and the public worship room. */
            .producer-status-strip { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1px; overflow:hidden; margin:24px 0; border:1px solid var(--app-card-border,#b8c8bd); border-radius:15px; background:var(--app-card-border,#b8c8bd); }
            .producer-status-strip>div { display:grid; gap:6px; min-width:0; padding:17px 19px; background:var(--surface,#fff); }.producer-status-strip small,.producer-card-heading p { margin:0; color:#708077; font-size:.58rem; font-weight:800; letter-spacing:.13em; text-transform:uppercase; }.producer-status-strip strong { overflow:hidden; color:#293a30; font-size:.76rem; text-overflow:ellipsis; white-space:nowrap; }
            .stream-state { display:inline-flex; width:max-content; align-items:center; gap:6px; }.stream-state::before { width:7px; height:7px; border-radius:99px; background:#87948d; content:''; }.stream-state-healthy,.stream-state-live,.stream-state-ready { color:#1d6c46!important; }.stream-state-healthy::before,.stream-state-live::before,.stream-state-ready::before { background:#35a66e; }.stream-state-degraded,.stream-state-scheduled { color:#93671b!important; }.stream-state-degraded::before,.stream-state-scheduled::before { background:#d29a31; }.stream-state-offline,.stream-state-interrupted,.stream-state-cancelled { color:#a3463e!important; }.stream-state-offline::before,.stream-state-interrupted::before,.stream-state-cancelled::before { background:#d25d55; }
            .producer-grid-expanded { align-items:start; gap:18px; }.producer-card,.producer-control-card { border:1px solid var(--app-card-border,#b8c8bd); border-radius:17px; padding:clamp(21px,3vw,30px); background:var(--surface,#fff); box-shadow:var(--shadow-sm); }.producer-card-heading { display:flex; align-items:start; justify-content:space-between; gap:16px; }.producer-card-heading h2 { margin:6px 0 0; font-size:1.5rem; }.producer-card-heading>span { flex:none; border:1px solid var(--app-card-border,#b8c8bd); border-radius:999px; padding:5px 9px; color:#718077; font-size:.6rem; font-weight:750; }.producer-copy,.producer-empty { color:#66756d; font-size:.76rem; line-height:1.65; }.producer-form { margin-top:24px; }.producer-form label small,.producer-health-form label small { display:block; margin-top:3px; color:#819088; font-size:.59rem; font-weight:500; }.producer-primary-action { justify-self:start; gap:10px; }.producer-control-card { display:grid; gap:17px; }.producer-inline-form .button,.producer-watch-link { width:100%; justify-content:space-between; box-sizing:border-box; }.producer-health-form,.producer-transition-form { display:grid; gap:12px; border-top:1px solid var(--app-card-border,#b8c8bd); padding-top:17px; }.producer-transition-form label { display:grid; gap:7px; color:#526158; font-size:.67rem; font-weight:750; }.producer-events-panel { border-color:var(--app-card-border,#b8c8bd); padding:clamp(20px,3vw,29px); }.producer-events-panel .producer-card-heading { margin-bottom:14px; }
            .worship-room { display:grid; grid-template-columns:minmax(0,2fr) minmax(310px,.8fr); gap:22px; align-items:start; }.worship-main { min-width:0; }.worship-intro h1 { margin:8px 0 11px; }.worship-intro>p:last-child { max-width:680px; color:#65756c; line-height:1.65; }.worship-player { overflow:hidden; aspect-ratio:16/9; background:#0b100d; }.worship-player iframe,.worship-player video { width:100%; height:100%; border:0; object-fit:contain; }.worship-bandwidth-button { margin-top:11px; border:0; padding:0; background:transparent; color:var(--brand); font-size:.7rem; font-weight:750; text-decoration:underline; text-underline-offset:4px; }.worship-assurance { display:flex; gap:9px; margin-top:15px; color:#718078; font-size:.65rem; line-height:1.55; }.worship-assurance p { margin:0; }.worship-fallback { display:grid; justify-items:start; gap:11px; }.worship-fallback-icon { display:grid; width:40px; height:40px; place-items:center; border:1px solid #d9b36a; border-radius:12px; color:#9b6c1e; font-size:1.4rem; }.worship-panel-heading { display:flex; align-items:start; justify-content:space-between; gap:12px; }.worship-panel-heading h2 { margin:13px 0 0; }.worship-refresh { color:#84928a; font-size:.57rem; white-space:nowrap; }.worship-actions { display:flex; flex-wrap:wrap; gap:7px; margin:22px 0 16px; }.worship-actions button { border:1px solid var(--app-card-border,#b8c8bd); border-radius:999px; padding:8px 10px; background:var(--surface-soft,#f5f8f4); color:#395045; font-size:.62rem; font-weight:750; }.worship-actions button:hover { border-color:color-mix(in srgb,var(--brand) 55%,var(--app-card-border)); color:var(--brand); }.worship-tabs { display:grid; grid-template-columns:repeat(3,1fr); gap:3px; border:1px solid var(--app-card-border,#b8c8bd); border-radius:10px; padding:3px; background:var(--surface-soft,#f4f7f3); }.worship-tabs button { border:0; border-radius:7px; padding:8px 4px; background:transparent; color:#7a8980; font-size:.62rem; font-weight:800; }.worship-tabs button[aria-selected='true'] { background:var(--surface,#fff); color:#22382d; box-shadow:0 2px 6px rgb(22 38 28 / 8%); }.worship-tab-panel { margin-top:15px; }.worship-chat-feed { display:grid; gap:10px; max-height:315px; min-height:145px; overflow:auto; padding-right:5px; }.worship-chat-feed article { border-left:2px solid color-mix(in srgb,var(--brand) 28%,#d7e1da); padding:4px 0 4px 10px; }.worship-chat-feed strong { display:flex; align-items:center; gap:6px; color:#30463a; font-size:.67rem; }.worship-chat-feed p { margin:4px 0 0; color:#66766d; font-size:.68rem; line-height:1.55; }.worship-host-tag { border-radius:999px; padding:2px 5px; background:color-mix(in srgb,var(--brand) 12%,white); color:var(--brand); font-size:.48rem; letter-spacing:.07em; text-transform:uppercase; }.worship-chat-form,.worship-prayer-form,.worship-response-sheet form { display:grid; gap:8px; margin-top:14px; }.worship-chat-form { grid-template-columns:110px minmax(0,1fr) auto; }.worship-chat-form input,.worship-prayer-form input,.worship-prayer-form textarea,.worship-response-sheet input { min-width:0; border:1px solid var(--app-card-border,#b8c8bd); border-radius:9px; padding:10px; background:var(--surface,#fff); color:inherit; font:inherit; font-size:.67rem; }.worship-prayer-form textarea { min-height:92px; resize:vertical; }.worship-form-note,.worship-loading { margin:11px 0 0; color:#7b8981; font-size:.62rem; line-height:1.55; }.worship-form-note.is-error { color:#ae5048; }.worship-consent { display:flex; gap:7px; align-items:flex-start; color:#66766d; font-size:.61rem; line-height:1.45; }.worship-poll { display:grid; gap:8px; }.worship-poll>strong { color:#31483b; font-size:.75rem; line-height:1.45; }.worship-poll label { display:flex; gap:8px; align-items:center; border:1px solid var(--app-card-border,#b8c8bd); border-radius:9px; padding:9px; color:#53635a; font-size:.66rem; }.worship-chat-paused { border:1px solid #e0c582; border-radius:9px; padding:9px; background:#fffbef; color:#8a641d!important; }.worship-response-sheet { position:fixed; z-index:60; inset:0; display:grid; place-items:center; padding:18px; background:rgb(10 18 13 / 55%); }.worship-response-sheet[hidden] { display:none; }.worship-response-sheet>div { position:relative; width:min(100%,430px); border:1px solid var(--app-card-border,#b8c8bd); border-radius:18px; padding:27px; background:var(--surface,#fff); box-shadow:0 28px 70px rgb(0 0 0 / 25%); }.worship-response-sheet p:first-child { margin:0; color:var(--brand); font-size:.59rem; font-weight:800; letter-spacing:.15em; }.worship-response-sheet h3 { margin:7px 0 0; color:#263c30; font-size:1.45rem; }.worship-sheet-close { position:absolute; top:12px; right:14px; border:0; background:transparent; color:#6c7b72; font-size:1.45rem; }.worship-incident { display:flex; gap:11px; margin:25px 0; border:1px solid #e5c57e; border-radius:11px; padding:13px; background:#fffaf0; }.worship-incident>span { display:grid; width:21px; height:21px; flex:none; place-items:center; border-radius:50%; background:#d49b35; color:white; font-size:.67rem; font-weight:800; }.worship-incident strong { color:#69521e; font-size:.7rem; }.worship-incident p { margin:4px 0 0; color:#846f41; font-size:.64rem; line-height:1.5; }
            :is(.worship-theme-dark,.staff-theme-dark) .producer-status-strip,:is(.worship-theme-dark,.staff-theme-dark) .producer-card,:is(.worship-theme-dark,.staff-theme-dark) .producer-control-card,:is(.worship-theme-dark,.staff-theme-dark) .producer-events-panel,.worship-theme-dark .worship-tabs,.worship-theme-dark .worship-response-sheet>div { border-color:#405548; background:#17231c; box-shadow:none; }:is(.worship-theme-dark,.staff-theme-dark) .producer-status-strip>div,.worship-theme-dark .worship-tabs button[aria-selected='true'] { background:#1a281f; }:is(.worship-theme-dark,.staff-theme-dark) :is(.producer-status-strip strong,.producer-card-heading h2,.worship-intro h1,.worship-chat-feed strong,.worship-poll>strong,.worship-response-sheet h3) { color:#edf5ef; }:is(.worship-theme-dark,.staff-theme-dark) :is(.producer-copy,.producer-empty,.worship-intro>p:last-child,.worship-chat-feed p,.worship-poll label,.worship-consent) { color:#aebdb4; }.worship-theme-dark :is(.worship-chat-form input,.worship-prayer-form input,.worship-prayer-form textarea,.worship-response-sheet input,.worship-poll label) { border-color:#405548; background:#101813; color:#e7efe9; }.worship-theme-dark .worship-actions button { border-color:#405548; background:#1a281f; color:#c8d8cd; }.worship-theme-dark .worship-chat-paused,.worship-theme-dark .worship-incident { border-color:#6f5c32; background:#292417; }.worship-theme-dark .worship-incident strong { color:#f0d795; }.worship-theme-dark .worship-incident p { color:#cbbd90; }
            @media (max-width:760px) { .producer-status-strip { grid-template-columns:repeat(2,minmax(0,1fr)); }.worship-room { grid-template-columns:1fr; }.worship-panel { position:static; }.worship-chat-form { grid-template-columns:1fr; }.worship-chat-form .button { width:100%; }.producer-card,.producer-control-card,.producer-events-panel { padding:20px; }.producer-card-heading { gap:10px; }.producer-card-heading>span { display:none; } }
        </style>
    </head>
    <body class="ministry-app" style="--brand: {{ $primaryColor ?? '#1F6B55' }}; --accent: {{ $accentColor ?? '#D8AB5D' }}">
        {{ $slot }}
        <script>
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
                        const toggle = shell.querySelector('[data-workspace-toggle], [data-public-menu]');
                        if (!sidebar?.contains(event.target) && !toggle?.contains(event.target)) closeWorkspace(shell);
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
    </body>
</html>

