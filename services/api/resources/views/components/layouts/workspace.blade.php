<x-layouts.app :title="$title ?? null" :ministry-name="$ministryName ?? null" :primary-color="$primaryColor ?? null" :accent-color="$accentColor ?? null">
    @php($staffAppearance = \App\Models\MinistrySetting::find(\App\Models\MinistrySetting::SINGLETON_ID))
    @php($staffPermissions = auth()->check() ? app(\App\Services\PermissionChecker::class) : null)
    @php($canManagePeople = auth()->check() && $staffPermissions?->allows(auth()->user(), 'services.manage'))
    @php($canManageCommunity = auth()->check() && $staffPermissions?->allows(auth()->user(), 'community.manage'))
    <div class="workspace-shell staff-font-{{ $staffAppearance?->staff_font ?? 'modern' }} staff-sidebar-{{ $staffAppearance?->staff_sidebar ?? 'deep' }} staff-density-{{ $staffAppearance?->staff_density ?? 'comfortable' }}" data-workspace-shell>
        <button class="workspace-backdrop" type="button" data-workspace-close aria-label="Close navigation"></button>
        <aside class="workspace-sidebar app-sidebar" data-workspace-sidebar>
            <div class="workspace-brand">
                @if($staffAppearance?->dark_logo_path || $staffAppearance?->logo_path)<span class="workspace-brand-mark workspace-brand-logo"><img src="{{ asset('storage/'.($staffAppearance->dark_logo_path ?: $staffAppearance->logo_path)) }}" alt="{{ $ministryName }} logo"></span>@else<span class="workspace-brand-mark">{{ mb_strtoupper(mb_substr($ministryName ?? 'M', 0, 1)) }}</span>@endif
                <span><strong>{{ $ministryName ?? config('app.name') }}</strong><small>Ministry workspace</small></span>
                <button class="workspace-close" type="button" data-workspace-close aria-label="Close navigation"><x-ui.icon name="close"/></button>
            </div>
            <nav class="workspace-nav" aria-label="Staff navigation">
                @auth <x-staff-navigation /> @else {{ $navigation ?? '' }} @endauth
            </nav>
            <div class="workspace-sidebar-actions"><a href="{{ route('public.home') }}" target="_blank"><x-ui.icon name="external"/><span>View public site</span></a></div>
            <div class="workspace-account">
                <span>{{ $userInitials ?? 'AD' }}</span>
                <div><strong>{{ $userName ?? 'Administrator' }}</strong><small>{{ $userRole ?? 'Staff workspace' }}</small></div><a href="{{ route('staff.security') }}" aria-label="Open account security"><x-ui.icon name="settings" :size="16"/></a>
            </div>
        </aside>
        <style>
            /* The static development bundle can lag behind source edits. Keep field-style
               selects visually correct in the workspace while its next asset build runs. */
            .workspace-content .enhanced-select-trigger { background: var(--surface, #fff) !important; border: 1px solid var(--line-strong, #a9b8ae) !important; color: var(--ink, #18261e) !important; box-shadow: 0 1px 2px rgb(15 37 24 / 4%) !important; justify-content: space-between !important; text-align: left !important; }
            .workspace-content .enhanced-select-options { background: var(--surface, #fff) !important; border-color: var(--line-strong, #a9b8ae) !important; }
            .workspace-content .enhanced-select-options button { background: transparent !important; border: 0 !important; box-shadow: none !important; color: var(--ink, #18261e) !important; justify-content: flex-start !important; text-align: left !important; }
            .workspace-content .enhanced-select-options button:hover, .workspace-content .enhanced-select-options button[aria-selected='true'] { background: color-mix(in srgb, var(--brand) 10%, var(--surface, #fff)) !important; color: var(--brand) !important; }
            @media (max-width: 760px) {
                .workspace-content .enhanced-select { width: 100% !important; max-width: 100% !important; min-width: 0 !important; }
                .workspace-content .enhanced-select-trigger { width: 100% !important; max-width: 100% !important; min-width: 0 !important; }
                .workspace-content .enhanced-select-trigger > span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                .workspace-content .enhanced-select-options { right: 0 !important; left: auto !important; box-sizing: border-box; width: 100% !important; min-width: 0 !important; max-width: 100% !important; max-height: min(240px, 42dvh); }
                .workspace-content .enhanced-select-options button { min-width: 0; overflow-wrap: anywhere; white-space: normal; line-height: 1.35; }
            }
            /* Each staff sidebar is intentionally distinct. These rules follow the shared
               application chrome, so a chosen variant cannot inherit the wrong surface. */
            .workspace-shell.staff-sidebar-deep .workspace-sidebar.app-sidebar {
                background: radial-gradient(circle at 88% 0%, rgb(255 255 255 / 7%), transparent 26%), linear-gradient(180deg, #17271e, #0b1410) !important;
                color: #d7e2da;
            }
            .workspace-shell.staff-sidebar-brand .workspace-sidebar.app-sidebar {
                background: radial-gradient(circle at 14% 0%, rgb(255 255 255 / 16%), transparent 29%), linear-gradient(180deg, color-mix(in srgb, var(--brand) 86%, #14251c), color-mix(in srgb, var(--brand) 56%, #0b1510)) !important;
                color: #eef6f0;
            }
            .workspace-shell.staff-sidebar-light .workspace-sidebar.app-sidebar {
                background: #f9fbf8 !important;
                color: #203229;
                box-shadow: 12px 0 35px rgb(18 34 25 / 7%);
            }
            .workspace-shell.staff-sidebar-deep .workspace-brand > span strong,
            .workspace-shell.staff-sidebar-brand .workspace-brand > span strong { color: #f7faf8 !important; }
            .workspace-shell.staff-sidebar-deep .workspace-brand > span small,
            .workspace-shell.staff-sidebar-brand .workspace-brand > span small { color: #aabbb0 !important; }
            .workspace-shell.staff-sidebar-deep .workspace-nav a { color: #b7c6bd; }
            .workspace-shell.staff-sidebar-deep .workspace-nav-group > p { color: #82968a; }
            .workspace-shell.staff-sidebar-brand .workspace-nav a { color: #eff7f1; }
            .workspace-shell.staff-sidebar-brand .workspace-nav-group > p { color: rgb(255 255 255 / 68%); }
            .workspace-shell.staff-sidebar-brand .workspace-nav a:hover { background: rgb(255 255 255 / 11%); color: #fff; }
            .workspace-shell.staff-sidebar-brand .workspace-nav a[aria-current='page'] { background: rgb(255 255 255 / 15%); color: #fff; box-shadow: inset 3px 0 var(--accent), inset 0 1px rgb(255 255 255 / 15%); }
            .workspace-shell.staff-sidebar-light .workspace-brand { border-bottom-color: #d5e0d8 !important; }
            .workspace-shell.staff-sidebar-light .workspace-brand > span strong { color: #15271c !important; opacity: 1 !important; }
            .workspace-shell.staff-sidebar-light .workspace-brand > span small { color: #4c6255 !important; opacity: 1 !important; }
            .workspace-shell.staff-sidebar-light .workspace-nav a { color: #344a3d !important; font-weight: 720; }
            .workspace-shell.staff-sidebar-light .workspace-nav a .ui-icon { color: #527061; }
            .workspace-shell.staff-sidebar-light .workspace-nav-group > p { color: #647a6d !important; }
            .workspace-shell.staff-sidebar-light .workspace-nav a:hover { background: #e9f2eb !important; color: #123b27 !important; }
            .workspace-shell.staff-sidebar-light .workspace-nav a[aria-current='page'] { background: color-mix(in srgb, var(--brand) 13%, #ffffff) !important; color: color-mix(in srgb, var(--brand) 88%, #11271b) !important; box-shadow: inset 3px 0 var(--accent), inset 0 0 0 1px color-mix(in srgb, var(--brand) 18%, #ffffff) !important; }
            .workspace-shell.staff-sidebar-light .workspace-sidebar-actions a { border-color: #c9d7cd !important; background: #f1f6f2 !important; color: #294338 !important; }
            .workspace-shell.staff-sidebar-light .workspace-account { border-top-color: #d5e0d8 !important; }
            .workspace-shell.staff-sidebar-light .workspace-account strong { color: #1d3226 !important; }
            .workspace-shell.staff-sidebar-light .workspace-account small { color: #5c7165 !important; }
            /* Dark mode has a deliberate counterpart for every sidebar treatment. It never
               simply flips the surface while leaving the light-mode navigation colours behind. */
            .staff-theme-dark.workspace-shell.staff-sidebar-deep .workspace-sidebar.app-sidebar { background: radial-gradient(circle at 18% 0%, color-mix(in srgb, var(--brand) 24%, transparent), transparent 28%), #111c16 !important; color: #dce6df; }
            .staff-theme-dark.workspace-shell.staff-sidebar-brand .workspace-sidebar.app-sidebar { background: radial-gradient(circle at 14% 0%, rgb(255 255 255 / 12%), transparent 29%), linear-gradient(180deg, color-mix(in srgb, var(--brand) 78%, #0b1510), color-mix(in srgb, var(--brand) 42%, #07110b)) !important; color: #eef6f0; }
            .staff-theme-dark.workspace-shell.staff-sidebar-light .workspace-sidebar.app-sidebar { background: radial-gradient(circle at 92% 0%, color-mix(in srgb, var(--brand) 17%, transparent), transparent 29%), #17231c !important; border-right-color: #34483c !important; box-shadow: 12px 0 35px rgb(0 0 0 / 18%); color: #dbe6de; }
            .staff-theme-dark.workspace-shell .workspace-brand > span strong { color: #f7faf8 !important; }
            .staff-theme-dark.workspace-shell .workspace-brand > span small { color: #a8b9ae !important; }
            .staff-theme-dark.workspace-shell .workspace-nav a { color: #c5d2c9 !important; }
            .staff-theme-dark.workspace-shell .workspace-nav-group > p { color: #8ea297 !important; }
            .staff-theme-dark.workspace-shell .workspace-nav a .ui-icon { color: #a9beb0 !important; }
            .staff-theme-dark.workspace-shell .workspace-nav a:hover { background: rgb(255 255 255 / 8%) !important; color: #fff !important; }
            .staff-theme-dark.workspace-shell .workspace-nav a[aria-current='page'] { background: color-mix(in srgb, var(--brand) 28%, #1b2c22) !important; color: #fff !important; box-shadow: inset 3px 0 var(--accent), inset 0 0 0 1px rgb(255 255 255 / 8%) !important; }
            .staff-theme-dark.workspace-shell .workspace-sidebar-actions a { border-color: rgb(255 255 255 / 15%) !important; background: rgb(255 255 255 / 7%) !important; color: #f2f8f3 !important; }
            .staff-theme-dark.workspace-shell .workspace-account { border-top-color: rgb(255 255 255 / 11%) !important; }
            .staff-theme-dark.workspace-shell .workspace-account strong { color: #f3f8f4 !important; }
            .staff-theme-dark.workspace-shell .workspace-account small, .staff-theme-dark.workspace-shell .workspace-account > a { color: #aabbb0 !important; }
            @media (max-width: 760px) {
                .workspace-shell .workspace-brand .workspace-close {
                    top: 5px;
                    right: 3px;
                    width: 32px;
                    height: 32px;
                    border: 1px solid rgb(255 255 255 / 13%);
                    border-radius: 10px;
                    background: rgb(255 255 255 / 8%);
                }
                .workspace-shell.staff-sidebar-light .workspace-brand .workspace-close {
                    border-color: #cbd8cf;
                    background: #eef4ef;
                    color: #284136;
                }
            }
            .staff-theme-dark .workspace-content .enhanced-select-trigger, .staff-theme-dark .workspace-content .enhanced-select-options, .staff-theme-dark .workspace-content .enhanced-select-options button { background: #17231c !important; color: #e7efe9 !important; }
            .staff-page-command .workspace-create { width: max-content; margin-left: auto; }
            .staff-page-command .workspace-create > summary.button {
                width: auto;
                min-height: 43px;
                border: 1px solid color-mix(in srgb, var(--brand) 74%, #0f271c) !important;
                border-radius: 12px;
                padding: 0 15px;
                background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 92%, #183326), var(--brand)) !important;
                color: #fff !important;
                box-shadow: 0 8px 18px color-mix(in srgb, var(--brand) 20%, transparent) !important;
                font-size: .7rem;
                font-weight: 780;
                letter-spacing: .005em;
            }
            .staff-page-command .workspace-create > summary.button:hover { transform: translateY(-1px); box-shadow: 0 12px 23px color-mix(in srgb, var(--brand) 28%, transparent) !important; }
            .staff-page-command .workspace-create[open] > summary.button { background: color-mix(in srgb, var(--brand) 88%, #132c20) !important; color: #fff !important; }
            .service-schedule-panel { margin-top: 24px; overflow: hidden; border-color: var(--line-strong, #a9b8ae); border-radius: 18px; box-shadow: 0 8px 24px rgb(19 43 30 / 5%); }
            .service-schedule-intro { display: flex; align-items: end; justify-content: space-between; gap: 18px; padding: 22px 22px 17px; border-bottom: 1px solid var(--line, #d8e1da); }
            .service-schedule-intro p { margin: 0 0 6px; color: var(--brand); font-size: .58rem; font-weight: 820; letter-spacing: .14em; }
            .service-schedule-intro h2 { margin: 0; color: var(--ink, #18261e); font-size: 1.1rem; font-weight: 760; letter-spacing: -.035em; }
            .service-schedule-intro span { display: block; max-width: 560px; margin-top: 7px; color: var(--muted, #68776e); font-size: .69rem; line-height: 1.55; }
            .service-schedule-panel table { margin: 0; }
            .service-schedule-panel th { padding: 13px 18px; color: var(--muted, #68776e); font-size: .56rem; font-weight: 820; letter-spacing: .1em; }
            .service-schedule-panel td { padding: 17px 18px; color: #405248; font-size: .72rem; line-height: 1.45; vertical-align: middle; }
            .service-schedule-panel td strong { display: block; color: var(--ink, #18261e); font-size: .78rem; font-weight: 760; letter-spacing: -.015em; }
            .service-schedule-panel td small { display: block; margin-top: 3px; color: var(--muted, #68776e); font-size: .61rem; line-height: 1.35; }
            .service-row-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 7px; }
            .service-row-actions .button-secondary, .service-row-actions .button-danger { min-height: 32px; border-radius: 8px; padding: 0 9px; font-size: .61rem; font-weight: 750; text-decoration: none; }
            .service-row-actions .button-danger { border: 1px solid #d99a93; background: #fff5f3; color: #963d34; }
            .staff-theme-dark .service-schedule-panel { border-color: #34483c; background: #17231c; box-shadow: none; }
            .staff-theme-dark .service-schedule-panel table,
            .staff-theme-dark .service-schedule-panel tbody,
            .staff-theme-dark .service-schedule-panel tbody tr,
            .staff-theme-dark .service-schedule-panel tbody td { background: #17231c !important; }
            .staff-theme-dark .service-schedule-intro { border-color: #2b3b31; }.staff-theme-dark .service-schedule-intro h2, .staff-theme-dark .service-schedule-panel td strong { color: #e7efe9; }.staff-theme-dark .service-schedule-panel td { color: #c9d6ce; }.staff-theme-dark .service-row-actions .button-danger { border-color: #864d47; background: #341c1a; color: #f3c4bf; }
            .module-panel { overflow: hidden; border: 1px solid var(--line, #d7e0da); border-radius: 16px; background: var(--surface, #fff); box-shadow: 0 8px 24px rgb(19 43 30 / 5%); }
            .module-panel table { width: 100%; border-collapse: collapse; }
            .module-panel th { color: var(--muted, #68776e); font-size: .57rem; font-weight: 820; letter-spacing: .1em; text-transform: uppercase; }
            .module-panel td { color: #405248; font-size: .72rem; }
            .module-panel .module-name strong { color: var(--ink, #18261e); font-size: .78rem; font-weight: 760; }
            .module-panel .inline-form { display: flex; min-width: 208px; align-items: center; gap: 8px; }
            .module-panel .inline-form select { min-width: 124px; }
            .module-panel .inline-form .button { min-height: 36px; padding: 0 12px; }
            .staff-theme-dark .module-panel { border-color: #34483c; background: #17231c; box-shadow: none; }
            .staff-theme-dark .module-panel th { border-color: #34483c; color: #9caea2; }
            .staff-theme-dark .module-panel td { border-color: #2c3f34; color: #c9d6ce; }
            .staff-theme-dark .module-panel .module-name strong { color: #edf5ef; }
            .staff-theme-dark .module-panel .inline-form select { border-color: #405548; background: #101813; color: #e7efe9; }
            /* Shared staff content must never fall back to its light canvas when the
               workspace switches theme. These cover status chips, editor canvases,
               dialogs, notices, and the reusable compact controls used by staff pages. */
            .staff-theme-dark .workspace-content .table-status,
            .staff-theme-dark .workspace-content .status-badge { background: #203126; color: #cce3d2; }
            .staff-theme-dark .workspace-content .data-panel table,
            .staff-theme-dark .workspace-content .data-panel tbody,
            .staff-theme-dark .workspace-content .data-panel tbody tr,
            .staff-theme-dark .workspace-content .data-panel tbody td,
            .staff-theme-dark .workspace-content .data-panel .empty-cell { background: #17231c; }
            .workspace-content .data-panel { max-width: 100%; overscroll-behavior-inline: contain; scrollbar-color: var(--line-strong, #a9b8ae) transparent; }
            .workspace-main, .workspace-content { overflow-x: clip; }
            .pages-records-panel .data-panel { overflow-x: auto !important; overflow-y: hidden; }
            .staff-theme-dark .workspace-content .form-success { border-color: #407958; background: #173724; color: #bde6c8; }
            .staff-theme-dark .workspace-content .form-error,
            .staff-theme-dark .workspace-content .form-errors { border-color: #8f4c46; background: #3a1d1a; color: #f4c7c2; }
            .staff-theme-dark .workspace-content .page-block-canvas,
            .staff-theme-dark .workspace-content .page-canvas-module,
            .staff-theme-dark .workspace-content .page-block-canvas-wrap { border-color: var(--line); background: #101813; box-shadow: none; }
            .staff-theme-dark .workspace-content .page-canvas-cta { background: #1b2a20; }
            .staff-theme-dark .workspace-content .page-canvas-block-content blockquote { color: #d8e4dc; }
            .staff-theme-dark .workspace-content .page-canvas-block-content blockquote small,
            .staff-theme-dark .workspace-content .page-canvas-image-placeholder { color: #9fb1a5; }
            .staff-theme-dark .workspace-content .page-block-library,
            .staff-theme-dark .workspace-content .page-block-inspector,
            .staff-theme-dark .workspace-content .page-editor-meta,
            .staff-theme-dark .workspace-content .page-editor-actions,
            .staff-theme-dark .workspace-content .page-seo-panel { border-color: var(--line); background: var(--surface); box-shadow: none; }
            .staff-theme-dark .workspace-content .page-editor-meta label,
            .staff-theme-dark .workspace-content .page-editor label,
            .staff-theme-dark .workspace-content .page-block-library-group button,
            .staff-theme-dark .workspace-content .page-block-inspector label { color: #dce7df; }
            .staff-theme-dark .workspace-content .page-editor-meta input,
            .staff-theme-dark .workspace-content .page-block-inspector input,
            .staff-theme-dark .workspace-content .page-block-inspector textarea,
            .staff-theme-dark .workspace-content .page-block-inspector select,
            .staff-theme-dark .workspace-content .page-inspector-actions button { border-color: var(--line-strong); background: #101813; color: #e7efe9; }
            .staff-theme-dark .workspace-content .page-block-inspector .is-danger { border-color: #8f4c46; background: #3a1d1a; color: #f4c7c2; }
            .staff-theme-dark .workspace-content .page-starter-grid > a { border-color: var(--line) !important; background: #17231c !important; color: #dce7df !important; }
            .staff-theme-dark .workspace-content .page-starter-grid > a > strong { color: #edf5ef !important; }
            .staff-theme-dark .workspace-content .page-starter-grid > a > span:not(.page-starter-number) { color: #aebdb4 !important; }
            .staff-theme-dark .workspace-content dialog { border-color: var(--line-strong); background: var(--surface); color: #e7efe9; }
            @media (max-width: 760px) { .staff-page-command .workspace-create { width: 100%; margin-left: 0; } .staff-page-command .workspace-create > summary.button { width: 100%; justify-content: center; } }
            @media (max-width: 760px) { .service-schedule-panel { overflow: visible; }.service-schedule-intro { align-items: start; padding: 19px 17px 15px; }.service-schedule-panel table { min-width: 0 !important; }.service-schedule-panel table, .service-schedule-panel tbody, .service-schedule-panel tr, .service-schedule-panel td { display: block; width: 100%; box-sizing: border-box; }.service-schedule-panel thead { display: none; }.service-schedule-panel tr { margin: 10px; border: 1px solid var(--line, #d8e1da); border-radius: 13px; padding: 4px 0; }.service-schedule-panel td { display: grid; grid-template-columns: minmax(102px, .72fr) minmax(0, 1fr); gap: 11px; border: 0; padding: 9px 13px; font-size: .72rem; }.service-schedule-panel td::before { content: attr(data-label); color: var(--muted, #68776e); font-size: .56rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }.service-schedule-panel td:first-child { padding-top: 13px; }.service-schedule-panel td:last-child { padding-bottom: 13px; }.service-row-actions { justify-content: flex-start; }.staff-theme-dark .service-schedule-panel tr { border-color: #34483c; } }
            @media (max-width: 760px) { .security-sessions-panel { overflow: visible; }.security-sessions-panel table, .security-sessions-panel tbody { display: grid; width: 100%; min-width: 0 !important; }.security-sessions-panel thead { display: none; }.security-sessions-panel tbody { gap: 9px; }.security-sessions-panel tr { display: grid; gap: 0; overflow: hidden; border: 1px solid var(--line); border-radius: 12px; }.security-sessions-panel td { display: grid; grid-template-columns: minmax(84px,.55fr) minmax(0,1fr); gap: 9px; border: 0; padding: 10px 12px; overflow-wrap: anywhere; }.security-sessions-panel td + td { border-top: 1px solid var(--line); }.security-sessions-panel td::before { content: attr(data-label); color: var(--muted); font-size: .54rem; font-weight: 820; letter-spacing: .08em; text-transform: uppercase; }.staff-theme-dark .security-sessions-panel tr { border-color: var(--line); } }
            @media (max-width: 760px) { .responsive-records { overflow: visible; }.responsive-records table, .responsive-records tbody { display: grid; width: 100%; min-width: 0 !important; }.responsive-records thead { display: none; }.responsive-records tbody { gap: 10px; }.responsive-records tr { display: grid; grid-template-columns:repeat(2,minmax(0,1fr)); overflow: hidden; border: 1px solid var(--line); border-radius: 13px; background: var(--surface); }.responsive-records td { display: grid; gap: 5px; min-width: 0; border: 0; padding: 11px 12px; overflow-wrap: anywhere; }.responsive-records td::before { content: attr(data-label); color: var(--muted); font-size: .53rem; font-weight: 820; letter-spacing: .08em; text-transform: uppercase; }.responsive-records td:first-child { grid-column: 1 / -1; border-bottom: 1px solid var(--line); }.responsive-records .empty-cell { display: block; grid-column: 1 / -1; }.staff-theme-dark .responsive-records tr { border-color: var(--line); background: #17231c; }.staff-theme-dark .responsive-records td:first-child { border-color: var(--line); } }
            @media (max-width: 760px) { .mobile-record-table { overflow: visible !important; }.mobile-record-table table, .mobile-record-table tbody { display: grid; width: 100%; min-width: 0 !important; }.mobile-record-table thead { display: none; }.mobile-record-table tbody { gap: 10px; }.mobile-record-table tr { display: grid; grid-template-columns:repeat(2,minmax(0,1fr)); overflow: hidden; border: 1px solid var(--line); border-radius: 13px; background: var(--surface); }.mobile-record-table td { display: grid; gap: 5px; min-width: 0; border: 0; padding: 11px 12px; overflow-wrap: anywhere; }.mobile-record-table td::before { content: attr(data-label); color: var(--muted); font-size: .53rem; font-weight: 820; letter-spacing: .08em; text-transform: uppercase; }.mobile-record-table td:first-child { grid-column: 1 / -1; border-bottom: 1px solid var(--line); }.mobile-record-table .empty-cell { display: block; grid-column: 1 / -1; }.staff-theme-dark .mobile-record-table tr { border-color: var(--line); background: #17231c; }.staff-theme-dark .mobile-record-table td:first-child { border-color: var(--line); } }
            @media (max-width: 760px) {
                .module-panel { overflow: visible; background: transparent; box-shadow: none; }
                .module-panel.data-panel { overflow: visible !important; }
                .module-panel table { min-width: 0 !important; table-layout: fixed; }
                .module-panel table, .module-panel tbody { display: block; width: 100%; }
                .module-panel thead { display: none; }
                .module-panel tbody { display: grid; gap: 10px; }
                .module-panel tr { display: grid; grid-template-columns: 1fr 1fr; overflow: hidden; border: 1px solid var(--line, #d7e0da); border-radius: 14px; background: var(--surface, #fff); }
                .module-panel td { display: grid; gap: 5px; min-width: 0; border: 0; padding: 12px 14px; }
                .module-panel td::before { content: attr(data-label); color: var(--muted, #68776e); font-size: .54rem; font-weight: 820; letter-spacing: .09em; text-transform: uppercase; }
                .module-panel .module-name, .module-panel .module-control { grid-column: 1 / -1; }
                .module-panel .module-name { border-bottom: 1px solid var(--line, #d7e0da); padding-top: 14px; }
                .module-panel .module-control { border-top: 1px solid var(--line, #d7e0da); padding-bottom: 14px; }
                .module-panel .inline-form { display: grid; grid-template-columns: minmax(0, 1fr) 72px; width: 100%; min-width: 0; gap: 9px; }
                .module-panel .inline-form > *, .module-panel .inline-form .enhanced-select { min-width: 0 !important; width: 100%; }
                .module-panel .inline-form select { min-width: 0; width: 100%; }
                .module-panel .inline-form .button { width: 72px; min-width: 72px; padding-inline: 8px; }
                .workspace-content:has(.module-panel) { padding-bottom: 124px; }
                .staff-theme-dark .module-panel tr { border-color: #34483c; background: #17231c; }
                .staff-theme-dark .module-panel .module-name, .staff-theme-dark .module-panel .module-control { border-color: #34483c; }
            }
            /* Producer desk: maintain visual rhythm and keep controls in a deliberate action row. */
            .workspace-content .producer-experience-card { margin-bottom: 26px !important; }
            .workspace-content .producer-grid-expanded { margin-bottom: 26px !important; }
            .workspace-content .producer-meeting-card { margin-top: 26px !important; }
            .workspace-content .producer-events-panel { margin-top: 26px !important; }
            .workspace-content .producer-experience-card .producer-inline-form {
                display: grid !important;
                grid-template-columns: minmax(0, 1fr) auto !important;
                align-items: end !important;
                gap: 14px !important;
                margin-top: 18px !important;
            }
            .workspace-content .producer-experience-card .producer-inline-form > label {
                min-width: 0 !important;
                width: 100% !important;
            }
            .workspace-content .producer-experience-card .producer-inline-form > .button {
                width: auto !important;
                min-width: 142px;
                margin: 0 !important;
                justify-content: center !important;
            }
            .workspace-content .producer-form > label:first-child { width: min(100%, 340px); }
            @media (max-width: 760px) {
                .workspace-content .producer-experience-card { margin-bottom: 18px !important; }
                .workspace-content .producer-grid-expanded { margin-bottom: 18px !important; }
                .workspace-content .producer-meeting-card,
                .workspace-content .producer-events-panel { margin-top: 18px !important; }
                .workspace-content .producer-experience-card .producer-inline-form { grid-template-columns: minmax(0, 1fr) !important; }
                .workspace-content .producer-experience-card .producer-inline-form > .button { width: 100% !important; }
            }            /* Services: readable dark surfaces and tables that retain every field at narrow widths. */
            .workspace-content .service-schedule-panel { min-width: 0; }
            .workspace-content .service-schedule-panel table { width: 100%; table-layout: auto; }
            .workspace-content .service-schedule-panel td { overflow-wrap: anywhere; }
            .workspace-content .service-schedule-panel .table-status { white-space: nowrap; }
            .workspace-content .service-schedule-panel .service-row-actions > * { min-width: 0; }
            .workspace-content .service-schedule-panel .service-row-actions details { max-width: 100%; }
            .workspace-content .service-schedule-panel .service-row-actions .button-secondary { white-space: nowrap; }
            .workspace-content .service-schedule-panel .service-edit-form { max-width: min(720px, calc(100vw - 40px)); }
            .staff-theme-dark .workspace-content .service-schedule-panel { border-color: #3a5144 !important; background: #17231c !important; }
            .staff-theme-dark .workspace-content .service-schedule-intro { border-color: #3a5144 !important; }
            .staff-theme-dark .workspace-content .service-schedule-intro p { color: #91d3aa !important; }
            .staff-theme-dark .workspace-content .service-schedule-intro h2,
            .staff-theme-dark .workspace-content .service-schedule-panel td strong { color: #f1f7f2 !important; }
            .staff-theme-dark .workspace-content .service-schedule-intro span,
            .staff-theme-dark .workspace-content .service-schedule-panel th,
            .staff-theme-dark .workspace-content .service-schedule-panel td small { color: #afc2b5 !important; }
            .staff-theme-dark .workspace-content .service-schedule-panel td { color: #d4e0d7 !important; }
            .staff-theme-dark .workspace-content .service-schedule-panel .table-status { border-color: #456553; background: #203d2b; color: #c7f0d4; }
            @media (max-width: 760px) {
                .workspace-content .service-schedule-panel { overflow: visible !important; }
                .workspace-content .service-schedule-panel tr { margin: 10px 12px !important; min-width: 0; }
                .workspace-content .service-schedule-panel td { grid-template-columns: minmax(88px, .65fr) minmax(0, 1fr) !important; overflow-wrap: anywhere; }
                .workspace-content .service-schedule-panel td[data-label="Actions"] { display: flex; flex-direction: column; align-items: stretch; gap: 9px; }
                .workspace-content .service-schedule-panel td[data-label="Actions"]::before { display: block; }
                .workspace-content .service-schedule-panel .service-row-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); width: 100%; }
                .workspace-content .service-schedule-panel .service-row-actions > * { width: 100%; }
                .workspace-content .service-schedule-panel .service-row-actions .button-secondary,
                .workspace-content .service-schedule-panel .service-row-actions .button-danger { width: 100%; justify-content: center; }
                .workspace-content .service-schedule-panel .service-edit-form { max-width: calc(100vw - 48px); }
                .workspace-content .service-schedule-panel .table-status { width: max-content; max-width: 100%; white-space: normal; }
            }            /* Service table cards: margins must not add width beyond their panel. */
            @media (max-width: 760px) {
                .workspace-content .service-schedule-panel table,
                .workspace-content .service-schedule-panel tbody { width: 100% !important; max-width: 100%; }
                .workspace-content .service-schedule-panel tr {
                    width: calc(100% - 24px) !important;
                    max-width: calc(100% - 24px);
                    margin-inline: 12px !important;
                    box-sizing: border-box;
                }
                .workspace-content .service-schedule-panel .service-row-actions {
                    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                    align-items: stretch;
                }
                .workspace-content .service-schedule-panel .service-row-actions > details {
                    display: block;
                    width: 100% !important;
                    min-width: 0 !important;
                    box-sizing: border-box;
                }
                .workspace-content .service-schedule-panel .service-row-actions > details > summary,
                .workspace-content .service-schedule-panel .service-row-actions > form,
                .workspace-content .service-schedule-panel .service-row-actions > form > button,
                .workspace-content .service-schedule-panel .service-row-actions > a {
                    display: flex;
                    width: 100% !important;
                    min-width: 0 !important;
                    box-sizing: border-box;
                    justify-content: center;
                    align-items: center;
                }
            }            /* Recurring service edits use a focused modal, keeping the timetable stable. */
            .service-edit-dialog { width: min(680px, calc(100vw - 32px)); max-height: min(760px, calc(100vh - 32px)); border: 1px solid var(--line-strong, #a9b8ae); border-radius: 20px; padding: 0; background: var(--surface, #fff); color: var(--ink, #18261e); box-shadow: 0 28px 80px rgb(8 22 13 / 34%); }
            .service-edit-dialog::backdrop { background: rgb(9 22 14 / 58%); backdrop-filter: blur(4px); }
            .service-edit-dialog > header { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; border-bottom: 1px solid var(--line, #d8e1da); padding: 22px 24px 18px; }
            .service-edit-dialog > header p { margin: 0 0 6px; color: var(--brand); font-size: .56rem; font-weight: 820; letter-spacing: .13em; }
            .service-edit-dialog > header h2 { margin: 0; color: var(--ink, #18261e); font-size: 1.08rem; letter-spacing: -.035em; }
            .service-edit-dialog > header span { display: block; margin-top: 7px; color: var(--muted, #68776e); font-size: .68rem; line-height: 1.5; }
            .service-edit-dialog > header form { margin: 0; }
            .service-edit-dialog > header button { display: grid; width: 34px; height: 34px; place-items: center; border: 1px solid var(--line-strong, #a9b8ae); border-radius: 9px; background: var(--surface-muted, #f3f6f3); color: var(--ink, #18261e); cursor: pointer; }
            .service-edit-dialog-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; padding: 22px 24px 24px; }
            .service-edit-dialog-form > label { display: grid; min-width: 0; gap: 7px; color: var(--ink, #18261e); font-size: .66rem; font-weight: 760; }
            .service-edit-dialog-form :is(input, select) { width: 100%; min-width: 0; min-height: 42px; box-sizing: border-box; border: 1px solid var(--line-strong, #a9b8ae); border-radius: 9px; padding: 0 11px; background: var(--surface, #fff); color: var(--ink, #18261e); font: inherit; }
            .service-edit-dialog-form .check-field { grid-column: 1 / -1; display: flex; align-items: center; gap: 9px; color: var(--muted, #68776e); }
            .service-edit-dialog-form .check-field input { width: auto; min-height: auto; }
            .service-edit-dialog-form footer { grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: 10px; margin-top: 5px; }
            .staff-theme-dark .service-edit-dialog { border-color: #405548; background: #17231c; color: #e9f1eb; }
            .staff-theme-dark .service-edit-dialog > header { border-color: #405548; }
            .staff-theme-dark .service-edit-dialog > header p { color: #91d3aa; }
            .staff-theme-dark .service-edit-dialog > header :is(h2, button), .staff-theme-dark .service-edit-dialog-form > label { color: #edf5ef; }
            .staff-theme-dark .service-edit-dialog > header span, .staff-theme-dark .service-edit-dialog-form .check-field { color: #b2c4b7; }
            .staff-theme-dark .service-edit-dialog > header button, .staff-theme-dark .service-edit-dialog-form :is(input, select) { border-color: #4a6152; background: #101813; color: #e7efe9; }
            @media (max-width: 600px) { .service-edit-dialog { width: calc(100vw - 20px); max-height: calc(100vh - 20px); border-radius: 16px; }.service-edit-dialog > header { padding: 18px; }.service-edit-dialog-form { grid-template-columns: 1fr; padding: 18px; }.service-edit-dialog-form footer { flex-direction: column-reverse; }.service-edit-dialog-form footer > * { width: 100%; justify-content: center; } }            /* Empty timetable states occupy their card intentionally rather than reading like a table value. */
            .workspace-content .service-schedule-panel .empty-cell {
                min-height: 118px;
                padding: 32px 24px !important;
                color: var(--muted, #68776e);
                text-align: center;
                vertical-align: middle;
            }
            .staff-theme-dark .workspace-content .service-schedule-panel .empty-cell { color: #afc2b5; }
            @media (max-width: 760px) {
                .workspace-content .service-schedule-panel td.empty-cell {
                    display: grid !important;
                    grid-template-columns: 1fr !important;
                    min-height: 112px;
                    width: calc(100% - 24px) !important;
                    margin: 10px 12px !important;
                    place-items: center;
                    border: 1px solid var(--line, #d8e1da) !important;
                    border-radius: 13px;
                    box-sizing: border-box;
                    text-align: center;
                }
                .workspace-content .service-schedule-panel td.empty-cell::before { display: none; }
                .staff-theme-dark .workspace-content .service-schedule-panel td.empty-cell { border-color: #3a5144 !important; }
            }            /* Mobile service editor: centred in the visual viewport with always-reachable actions. */
            .service-edit-dialog {
                position: fixed;
                inset: 0;
                width: min(680px, calc(100vw - 32px));
                max-height: calc(100dvh - 32px);
                margin: auto;
                overflow: auto;
                overscroll-behavior: contain;
            }
            .service-edit-dialog > header {
                position: sticky;
                z-index: 2;
                top: 0;
                background: var(--surface, #fff);
            }
            .service-edit-dialog-form footer {
                position: sticky;
                z-index: 2;
                bottom: -24px;
                margin: 5px -24px -24px;
                border-top: 1px solid var(--line, #d8e1da);
                padding: 14px 24px;
                background: var(--surface, #fff);
            }
            .staff-theme-dark .service-edit-dialog > header,
            .staff-theme-dark .service-edit-dialog-form footer { background: #17231c; border-color: #405548; }
            @media (max-width: 600px) {
                .service-edit-dialog {
                    inset: 0;
                    width: calc(100vw - 20px);
                    max-height: calc(100dvh - 20px);
                    margin: auto;
                }
                .service-edit-dialog > header { padding: 17px 18px; }
                .service-edit-dialog-form { padding-bottom: 88px; }
                .service-edit-dialog-form footer {
                    bottom: -18px;
                    margin: 4px -18px -18px;
                    padding: 12px 18px;
                    box-shadow: 0 -10px 22px rgb(15 30 20 / 7%);
                }
                .staff-theme-dark .service-edit-dialog-form footer { box-shadow: 0 -10px 22px rgb(0 0 0 / 20%); }
            }        </style>
        <main class="workspace-main">
            <header class="workspace-topbar app-topbar">
                <button class="workspace-menu" type="button" data-workspace-toggle aria-expanded="false" aria-label="Open navigation"><x-ui.icon name="menu" :size="20"/><x-ui.icon name="close" :size="19"/></button>
                <span class="mobile-header-brand" aria-label="{{ $ministryName ?? config('app.name') }}">@if($staffAppearance?->logo_path)<img src="{{ asset('storage/'.$staffAppearance->logo_path) }}" alt="">@else{{ mb_strtoupper(mb_substr($ministryName ?? 'M', 0, 1)) }}@endif</span>
                <div class="workspace-context"><span>{{ now()->timezone(config('app.timezone'))->format('l, F j') }}</span><strong>{{ $title ?? 'Workspace' }}</strong></div>
                <div class="workspace-top-actions">@if($canManagePeople)<a class="workspace-quick-action" href="{{ route('staff.people') }}" title="Open people directory to add a person"><x-ui.icon name="people" :size="16"/><span>Add person</span></a>@endif<button class="workspace-theme-toggle" type="button" data-workspace-theme-toggle aria-pressed="false" aria-label="Switch to dark mode"><span class="workspace-theme-track" aria-hidden="true"></span><span class="workspace-theme-label">Light</span></button><a href="{{ route('public.home') }}" target="_blank" aria-label="Open public site"><x-ui.icon name="external" :size="17"/></a></div>
            </header>
            <section class="workspace-content">
                {{ $slot }}
            </section>
        </main>
        <nav class="workspace-mobile-nav" aria-label="Staff mobile navigation">
            <a href="{{ route('public.home') }}"><x-ui.icon name="home"/><span>Home</span></a>
            @if($canManagePeople)<a href="{{ route('staff.people') }}" @if(request()->routeIs('staff.people*')) aria-current="page" @endif><x-ui.icon name="people"/><span>People</span></a>@endif
            @if($canManagePeople)<a href="{{ route('staff.services') }}" @if(request()->routeIs('staff.services*')) aria-current="page" @endif><x-ui.icon name="services"/><span>Services</span></a>@endif
            @if($canManageCommunity)<a href="{{ route('staff.events') }}" @if(request()->routeIs('staff.events*')) aria-current="page" @endif><x-ui.icon name="calendar"/><span>Events</span></a>@endif
            <button type="button" data-workspace-more aria-label="Open staff navigation"><x-ui.icon name="menu"/><span>More</span></button>
        </nav>
        <script>
            const workspaceShell = document.currentScript.closest('[data-workspace-shell]');
            workspaceShell?.querySelector('[data-workspace-more]')?.addEventListener('click', () => workspaceShell.querySelector('[data-workspace-toggle]')?.click());
            workspaceShell?.querySelectorAll('[data-service-edit-open]').forEach((trigger) => {
                trigger.addEventListener('click', () => document.getElementById(trigger.dataset.serviceEditOpen)?.showModal());
            });
            workspaceShell?.querySelectorAll('[data-service-edit-dialog]').forEach((dialog) => {
                dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
                dialog.querySelectorAll('[data-service-edit-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
            });
            workspaceShell?.querySelectorAll('.workspace-content .data-panel').forEach((panel) => {
                if (panel.matches('.module-panel, .service-schedule-panel, .security-sessions-panel, .responsive-records')) return;
                const table = panel.querySelector('table');
                const labels = [...(table?.querySelectorAll('thead th') ?? [])].map((header, index) => header.textContent.trim() || `Details ${index + 1}`);
                if (!table || labels.length === 0) return;
                panel.classList.add('mobile-record-table');
                table.querySelectorAll('tbody tr').forEach((row) => {
                    const cells = [...row.querySelectorAll(':scope > td')];
                    if (cells.length === 1 && cells[0].colSpan > 1) return;
                    cells.forEach((cell, index) => cell.dataset.label = labels[index] || `Details ${index + 1}`);
                });
            });
        </script>
    </div>
</x-layouts.app>
