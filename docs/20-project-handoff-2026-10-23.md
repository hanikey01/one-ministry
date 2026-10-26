# One Ministry — Project Handoff

**Prepared:** 2026-10-23  
**Current focus:** Complete the central public-page editor as a lightweight, structured page builder.  
**Read first:** [00-product-master.md](00-product-master.md), [06-build-plan.md](06-build-plan.md), [12-implementation-status.md](12-implementation-status.md), [18-experience-performance-contract.md](18-experience-performance-contract.md), and [19-page-editor-spec.md](19-page-editor-spec.md).

## Product decisions already made

1. One Ministry is a single-ministry installation. No shared cross-ministry people database or vendor-controlled central service.
2. The separate Homepage Editor is retired. The central Pages workspace is the only editor for public pages, including the page selected as the public homepage.
3. The page editor must work fully without AI. AI assists planning or a selected object only; it never saves, publishes, invents ministry facts, chooses media, or injects arbitrary HTML/CSS/JavaScript.
4. The target is a lightweight Elementor-style builder:

   ```text
   Page → Sections / containers → Columns → Elements
   ```

   Elements must not live directly at page level.
5. The editor has a canvas-first interface. The element library opens from a floating trigger; selecting an object opens a contextual inspector popup. The editor is intended to work on desktop and mobile.
6. Public, staff, member, worship, and meeting pages use platform-owned theme presets. A ministry selects one preset: **Neutral**, **Slate**, or **Warm**.
7. A ministry may optionally set one custom **chrome** colour only. It controls header, sidebar, and sticky mobile footer together. It does not control page surfaces, cards, controls, or editor content.
8. Buttons use the preset action token and its computed accessible foreground. Reusable controls must use shared semantic theme styles, never local browser-default selects or hard-coded page palette values.
9. Avoid Docker rebuilds unless the user explicitly asks. The compose environment is production-asset based, so source CSS/JS is not visible until assets have been built and deployed.

## Current theme status

The active theme contract lives in `services/api/app/Support/MinistryTheme.php` and shared CSS tokens in `services/api/resources/css/app.css`.

The legacy dark-green structural palette was just migrated from active styles. The following old structural values have been removed from active CSS and Blade source:

- `#18231d`, `#17231c`, `#1a281f`, `#101813`
- `#405548`, `#597060`, `#293a30`, `#34483c`
- related legacy green blends and shadows

Affected files:

- `services/api/resources/css/app.css`
- `services/api/resources/css/recovered-layout.css`
- `services/api/resources/views/public/home.blade.php`
- `services/api/resources/views/member/school/_styles.blade.php`

The migration also fixed self-referencing dark custom properties in the public and learning styles. Future theme work must use semantic tokens such as `--canvas`, `--surface`, `--surface-muted`, `--ink`, `--muted`, `--line`, `--line-strong`, `--action`, and `--action-foreground`.

Semantic success, warning, and destructive states may retain distinct colours. They are not ministry branding colours.

## Current page-editor implementation

### Present in the code

- Pages index, creation templates, draft saving, publishing, quick edit, page selection as homepage, and server-side page revisions.
- Visual canvas with desktop/tablet/mobile width preview.
- Floating element and section library.
- Block settings inspector with content/style/advanced presentation work in progress.
- Flat block add, select, top-level drag reorder, duplicate, delete, and session undo/redo.
- Existing elements: hero, heading, paragraph, image, gallery, video embed, audio, file, media and text, quote, list, accordion, call to action, divider, spacer, service times, events, and groups.
- Media picker with media-type filtering and upload support.
- Page-level AI planning and partial selected-block AI proposal handling.
- Public renderer exposes published pages only.

### Important current limitation

The implementation is still a **flat array of blocks** in `services/api/resources/views/staff/pages/form.blade.php` and `PageWorkspaceController`. The visual `section` block is not yet a reusable parent container with independently editable child columns and elements.

Do not try to solve this only with more inspector fields. The stored document and renderer need the versioned hierarchy in `19-page-editor-spec.md` first.

## Required page-editor batches

Complete these in order. Keep migrations, validation, rendering, client controls, and tests aligned within each batch.

### Batch 1 — Structured document foundation

- Implement document version 2: sections, columns, elements, stable editor IDs, settings, and responsive overrides.
- Add recursive server-side validation and hard limits.
- Convert legacy flat blocks on read/save without data loss or duplicate conversion.
- Preserve a revision before migration.

### Batch 2 — Recursive public renderer

- Render structured sections, columns, and elements from approved renderer templates.
- Generate only approved semantic classes and CSS variables.
- Keep disabled modules hidden and server-blocked.
- Ensure public pages do not load editor JavaScript.

### Batch 3 — Structure canvas

- Selection hierarchy and breadcrumb: Page / Section / Column / Element.
- Section and column add, duplicate, reorder, delete, and layout presets.
- Move and reorder elements within and across columns.
- Drop indicators, keyboard controls, undo points, and destructive-operation confirmation.

### Batch 4 — Inspector completeness

- Apply the consistent **Content**, **Style**, and **Advanced** tabs to each selected object.
- Section settings: content width, column template/gap, alignment, height, overflow, spacing, backgrounds, overlays, border, radius, shadow, and safe responsive controls.
- Column settings: width, min/max width, alignment, child gap, background, border, shadow, overflow, and order.
- Element settings: object-specific content, accessible labels, links, spacing, alignment, approved style settings, and visibility.

### Batch 5 — Responsive authoring

- Desktop/tablet/mobile overrides with inheritance indicators and reset controls.
- Section/column width, stacking, order, visibility, gap, alignment, margin, and padding by breakpoint.
- Use CSS variables and approved classes in the renderer. Do not generate arbitrary inline CSS.

### Batch 6 — Element library and media controls

- Complete all approved element types from the specification.
- Add image focal point, aspect ratio, captions, decorative-image option, and accessible alt-text workflow.
- Add gallery management, hosted video controls, safe embeds, audio, downloads, and ministry-content configuration.
- Support background image/video selection, overlays, and contrast warnings for sections.

### Batch 7 — AI, productivity, and release quality

- Scoped page, section, column, and element AI proposals with explicit review/diff/apply.
- Navigator or outline panel, reusable sections/templates, copy/paste, page duplication, revision restore, unsaved-change warning, and autosave indicator.
- Browser, keyboard, screen-reader, contrast, mobile, performance, migration, and public-renderer verification.

## Page-editor UX rules agreed during work

- Canvas occupies the main width. The block library stays hidden until its floating trigger is used.
- On desktop, the trigger must sit within the main workspace, after the application sidebar. It must not overlap the sidebar in its closed state.
- On mobile, the floating trigger sits around the vertical centre of the left workspace edge, not at the bottom.
- The library panel header must remain sticky while its content scrolls. The trigger hides while the library is open.
- The inspector is a centred modal on mobile, not a bottom sheet. Its header and action footer remain sticky.
- The library trigger must hide while the inspector is open.
- The inspector’s shared form controls must reuse the platform control system. Native browser select presentation is a defect.
- The current element library and inspector should remain neutral and preset-aware; they must not inherit custom ministry chrome colour.

## Related media-library decisions

- Uploaded media is immediately usable; there is no media draft state.
- Latest uploads appear first in both the library and picker.
- An element accepts only compatible media types: an image element only images; video only video; audio only audio; file download only compatible files.
- Selecting or uploading an item in the picker must immediately update the picker list and selected edit field without a browser reload.
- Media cards need a working thumbnail/preview, edit popup, and delete action.

## Local environment and live-preview notes

Current local preview: `http://localhost:8080/`

For active development, use the source-mounted overlay rather than the production-only Compose profile:

```powershell
docker compose -f compose.yaml -f compose.dev.yaml up -d --force-recreate api proxy vite
```

This is the verified fast-preview setup. `compose.dev.yaml` mounts the API source into the PHP container, mounts `services/api` into the Vite container, keeps dependencies in the persistent `vite_node_modules` volume, and runs Vite in watch/HMR mode. Caddy proxies the Vite development assets and HMR connection through port `8080`, so CSS and JavaScript changes appear without rebuilding the API image or downloading dependencies again. Blade, PHP, route, and resource changes are picked up from the bind mounts; refresh the browser after those changes.

Do not delete `vite_node_modules`, `postgres_data`, `redis_data`, or `public_build` during ordinary cleanup. Do not use `docker compose down -v` for routine development.

If routes, configuration, or cached Blade output appear stale, run:

```powershell
docker compose -f compose.yaml -f compose.dev.yaml exec api php artisan optimize:clear
```

Rebuild only after changing Dockerfiles, Composer/PHP dependencies or extensions, Node dependencies, or when validating the production asset bundle. The production-only Compose profile still uses copied compiled assets and is not the live-preview workflow.

For phone preview, set `DEV_HOST` to the computer's LAN IPv4 address and `DEV_PORT=8080` in the Git-ignored root `.env`, recreate only Vite, and open `http://<LAN-IP>:8080` on the phone while both devices share the same network.

The PHP Redis PECL extension cannot currently be installed from the upstream PECL endpoint during the image build. The successful local rebuild command was:

```powershell
docker compose build --build-arg INSTALL_REDIS_EXTENSION=false api
docker compose up -d --remove-orphans
```

If Caddy returns `502` immediately after recreating API, restart its upstream resolver:

```powershell
docker compose restart proxy
```

Then verify:

```powershell
Invoke-WebRequest -UseBasicParsing http://localhost:8080/
docker compose ps
```

Do not prune Docker images, containers, volumes, or build cache unless explicitly requested. When asked to clean, inspect first and preserve named project volumes (`postgres_data`, `redis_data`, `public_build`) unless the user explicitly authorises their removal.

## Working-tree handoff

At handoff, the working tree has uncommitted changes. Do not discard them or reset the tree. They include:

- theme preset migration and legacy palette cleanup;
- page quick-edit status/close behaviour;
- recent page editor, media picker, and inspector work;
- current routes and JavaScript lifecycle changes.

Run before editing:

```powershell
git status --short
git diff --check
```

Do not include `.codex-remote-attachments/` in commits.

## First task for the next chat

Start with **Batch 1: structured document foundation**. Read the page-editor specification and inspect the existing controller, editor Blade template, public renderer, page schema, migrations, and revision logic before writing code. The next implementation must move the stored page shape from flat blocks to validated sections, columns, and elements; cosmetic additions to the existing flat editor should not precede that work.
