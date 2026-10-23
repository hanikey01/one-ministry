# 18 — Application Experience and Performance Contract

**Status:** Canonical cross-cutting product contract.  
**Version:** 1.0  
**Date:** 2026-10-22  
**Owners:** `00-product-master.md` owns product intent; this document owns the shared application experience and performance rules. `07-design.md` owns component and accessibility detail; `01-architecture.md` owns technical boundaries.

## 1. Outcome

One Ministry is a ministry-owned web application that feels immediate, coherent, and trustworthy on a phone or desktop. It is not a collection of unrelated administration pages. Staff, members, and visitors encounter one deliberate product system whose content, terminology, navigation, colours, and enabled capabilities belong to the installing ministry.

The Laravel server-rendered baseline is mandatory. Enhanced navigation and in-place updates improve the experience when JavaScript is available; every essential journey still works as an ordinary server-rendered request.

## 2. Product experience principles

1. **One application, three audience workspaces.** Public, member, and staff experiences share the same visual language and theme tokens while preserving their own information architecture and permissions.
2. **Ministry-owned presentation.** A ministry controls its name, logo, terminology, navigation selection, sidebar treatment, primary/accent colours, and supported light/dark surfaces. Configuration changes presentation only; routes, permission keys, stored values, and server authorization remain stable.
3. **Neutral by default.** New installations use white/neutral-gray light surfaces and charcoal/near-black dark surfaces. Green or any other brand tint is never a platform default.
4. **One primary action rule.** A primary action uses the ministry accent and its calculated readable foreground. There is no separate default button-colour setting. Secondary, quiet, destructive, and status actions use semantic tokens with accessible contrast.
5. **Direct, scoped editing.** A user edits the item or section they selected. AI assistance receives enough approved page context to make a coherent suggestion, but can change only the selected section unless a staff member explicitly applies a reviewed full-page plan.
6. **Visible system feedback.** Every save, background request, warning, and failure has a pending, success, error, or recovery state in the current viewport. Field validation stays beside the field; completed notices use the shared notification stack.
7. **No false affordances.** A visible action must work, explain why it is unavailable, or be absent. Placeholder controls, hidden global click targets, and links that lead to a known module/permission error are defects.

## 3. Experience model

| Surface | Primary job | Desktop navigation | Narrow-screen navigation | Required behavior |
| --- | --- | --- | --- | --- |
| Public | Discover, watch, connect, register, give, and contact | Branded public navigation | Drawer; optional configured footer with up to five destinations | Fast first render, readable content, module-aware links, no authenticated data leak |
| Member | Complete personal ministry activity | Persistent member sidebar | Same drawer; configured high-frequency footer may expose up to five destinations | Personal data, clear next actions, privacy controls, focused routes |
| Staff | Operate ministry work safely | Persistent grouped sidebar | Same focus-managed drawer | Permission/module-aware links, compact operational controls, durable confirmation |
| Worship/meeting | Participate in a live gathering | Focused contextual controls | Focused contextual controls | Media-first layout, explicit outage state, accessible interaction panels |

The current ministry name, logo/avatar, and close control remain visible at the top of an open mobile drawer. Fixed mobile footer surfaces are full-width and square-cornered. They use neutral mode-aware surfaces; only the active destination may use the ministry accent.

## 4. Theme and component contract

### 4.1 Semantic tokens

Every interface surface resolves through shared semantic tokens: `canvas`, `surface`, `surface-raised`, `text`, `text-muted`, `sidebar`, `border`, `border-interactive`, `selected`, `focus`, `brand`, `accent`, `action`, `action-foreground`, `success`, `warning`, and `danger`.

No page-level literal colour may decide a shared surface, text, border, icon, menu, or action state. Intentional ministry identity areas use `brand`; primary controls use `action`. The action foreground is calculated from contrast against the configured accent. Every light/dark pair preserves visible text, focus, status, and icon contrast.

### 4.2 Ministry appearance controls

The appearance screen has one purpose per control:

- Primary and accent: identity and primary actions.
- Light/dark canvas and panels: shared application surfaces.
- Sidebar: match the current content surface by default; reveal separate light/dark sidebar pickers directly below **Custom light and dark colours** only when selected.
- Typography and density: curated system choices only.

The settings UI previews the current configuration, preserves saved choices, and uses the same components as the rest of the workspace. A colour picker never exposes a configuration whose resulting foreground is unreadable.

### 4.3 Shared controls

Buttons, icon buttons, inputs, selects, menus, cards, tables, badges, dialogs, toasts, empty states, skeletons, and incident banners share tokens, focus treatment, radii, and spacing. A visual icon picker uses bundled SVGs, compact tiles, search, theme-aware surfaces, and visible selected/focus states. Select menus and icon panels render in a viewport-aware overlay so they cannot be clipped by cards, tables, or scrolling containers.

## 5. Navigation contract

Navigation configuration is ministry-owned, ordered, auditable, and audience-specific.

- Staff, member, and public destinations support selection, ordering, an approved icon, and a controlled section. Public links retain their label, target, visibility, feature requirement, parent relationship, and new-tab behavior in link details.
- A checkbox is the only control that includes or excludes a destination. Clicking a card, nearby whitespace, section picker, or icon picker never silently toggles destination selection.
- Destination rows follow one compact pattern across all three audiences: checkbox, label, reorder control, section selector, and icon selector. The selector row has predictable hit areas, clipped long labels, equal control heights, and no overlap with disclosure controls.
- Navigation settings never grant a permission or bypass a disabled module. Rendering and direct routes remain server-gated.
- Disabled modules are absent from navigation, quick destinations, dashboard summaries, and public composition. Their routes return the documented unavailable result.
- Public navigation changes, staff/member layout changes, terminology changes, and module-state changes invalidate only their affected cached configuration and become visible without a deployment.

## 6. App-like interaction contract

### 6.1 Navigation

Use enhanced Livewire navigation for compatible internal workspace and public routes. Prefetch on deliberate hover/focus where safe. Preserve the same application shell and do not re-download or reinitialize shared behavior on every route change. A normal anchor remains the no-JavaScript fallback.

Every page-specific script registers through the shared lifecycle utility and releases global listeners, timers, pollers, overlays, and media clients when the route changes. A route never accumulates duplicate listeners or leaves a hidden overlay above future controls.

### 6.2 Writes

For bounded actions that can return authoritative state, submit in place with immediate pending feedback, a disabled duplicate-submit control, inline field errors, a shared viewport success/error notification, and an in-place state update or a single intentional navigation when a new resource needs its canonical URL.

Normal form posts and redirects remain the fallback. Large, sensitive, multi-step, or irreversible actions may use a confirmation flow and a normal redirect when that is clearer or safer.

### 6.3 Loading and recovery

Keep the existing screen visible during a transition whenever possible. Use a local skeleton or pending treatment for a changing panel, not a blank page. Preserve entered values after recoverable errors. Explain stale data, offline/retry states, permission loss, and provider failure in plain language.

## 7. AI page editor contract

AI is a staff drafting assistant, not a page publisher or free-form code generator.

- Staff can ask AI for a reviewed page outline, including a suggested number and sequence of approved sections, then apply or reject it.
- Staff can add, remove, reorder, duplicate, and edit sections manually.
- Selecting a section opens a scoped assistant request. The request includes the ordered public draft as context so the result fits neighboring sections, but the response schema limits changes to the selected section's existing approved fields.
- AI never receives private person, attendance, care, children, or giving data. It cannot provide arbitrary HTML, scripts, media URLs, internal destinations, or publish a page.
- AI proposals remain staged until staff explicitly applies them. A normal draft save and separate permission-gated publication remain required.

## 8. Performance contract

Performance is a product requirement measured with browser and server evidence, not a framework claim.

### 8.1 Targets

Targets are measured on a representative production-like installation, a mid-range mobile device, and a realistic network profile. They are release targets, not guarantees under an unbounded provider outage or an undeclared media audience.

| Journey | Target | Measurement |
| --- | --- | --- |
| Public content first render | Useful content and navigation appear without waiting for JavaScript | LCP and server timing on a mobile network profile |
| Internal route transition | The existing shell responds immediately; typical cached/prefetched routes feel complete within 1 second | Browser navigation timing and interaction review |
| Bounded save | Pending state appears immediately; successful state/notice appears without a document reload | Browser interaction review and request timing |
| Staff/member route query | Only route-required data is loaded; list views are paginated | Query inspection and route profiling |
| Optional media SDK | Never part of the normal application entry bundle | Production asset manifest |

The initial measurable budget is: record debug server request timing during development; profile regular public and workspace HTML responses that exceed 500 ms on a representative local/staging dataset; review JavaScript/CSS size after a production asset build; and record LCP, interaction delay, and route-transition observations before a release gate passes. Production thresholds are refined from those measurements and recorded in release evidence.

### 8.2 Data and cache policy

- Cache stable shared configuration: ministry branding/theme, terminology, navigation layout, enabled feature state, and published public composition.
- Every cached setting has a named invalidation path on the relevant staff write. Time-to-live is a safety net, never the only way an administrator sees a saved change.
- Do not cache authenticated people, care, giving, session, permission-scope, or draft content as shared data. Per-user caching requires scope-safe keys and explicit invalidation.
- Select exact columns, eager-load only required relationships, paginate collections, index common listing/filter paths, and measure queries before adding a cache.
- Fingerprinted assets receive long-lived immutable caching. Versioned non-fingerprinted shared assets use a content or modification-time version. HTML is not shared-cached by default.

### 8.3 Deployment profiles

The shared-host baseline uses Laravel's supported database/file cache, host cron, regular PHP-FPM/Apache/LiteSpeed request model, and external media providers. A managed deployment may add Redis, database queue workers, a CDN, and containerized immutable releases. It must not change the product’s ownership, authorization, data isolation, or ability to operate under the baseline profile.

Production releases compile front-end assets once, then cache Laravel configuration, routes, events, and views in the deployed environment. OPcache settings must match the release strategy: disabled timestamp validation is permitted only for immutable releases that always restart or replace PHP workers after deployment.

## 9. Completion evidence

A design or performance change is not complete because code exists. Its release evidence includes the applicable route/browser review in light and dark modes, narrow and desktop layouts, keyboard/focus review, build output, query/asset review, and regression tests required by the owning plan. `12-implementation-status.md` records what is implemented, what was actually verified, and what remains a gate.

## 10. Decision hierarchy

When documents differ, apply them in this order:

1. `00-product-master.md` for product behavior and ownership.
2. This document for application experience and performance behavior.
3. `01-architecture.md`, `02-database.md`, `03-api-contracts.md`, `05-systems.md`, `07-design.md`, and `08-operations.md` for their named technical/domain concerns.
4. `06-build-plan.md` for delivery sequencing and gates.
5. `09-decisions.md` for accepted choices and unresolved decisions.
6. `12-implementation-status.md` for implementation and verification state.

An older audit, implementation note, mockup, or current UI does not override this contract. A new conflict is recorded in `09-decisions.md` before implementation.
