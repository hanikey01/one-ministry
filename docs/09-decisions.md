# 09 — Decisions and Open Questions

**Status:** Living record. This file distinguishes user-set direction from working assumptions and implementation choices awaiting validation.

## 1. Confirmed product direction

| ID | Decision | Reason/source |
| --- | --- | --- |
| P-001 | The finished code must be independently hostable by any ministry. | User requirement. |
| P-002 | Digital church, including a live homepage state and complete video meeting experience, is central to the product. | User requirement. |
| P-003 | The homepage shows live video during a service and a content/media carousel otherwise. | User requirement. |
| P-004 | People participation, attendance, follow-up needs, and birthday celebration are core concerns. | User requirement. |
| P-005 | Do not impose a product-level cap on live audience size. | User requirement. Each deployment still needs adequate infrastructure and a tested capacity target. |
| P-006 | Produce a documentation suite comparable in structure and usefulness to Ondred's before development. | User requirement. |
| P-007 | Each installation uses a feature-aware homepage composition. Activated modules determine which approved sections and navigation options are available; ministries choose a suitable homepage preset and arrange its enabled sections without code edits. | User requirement, 2026-10-03. |
| P-008 | The distributable product is Laravel-first and must be hostable as one PHP application on ordinary shared hosting. Blade, Livewire, Alpine.js, and Tailwind replace the separate Next.js production interface. Node.js, Docker, Redis, persistent workers, and self-hosted realtime are optional enhancement profiles. | User direction, 2026-10-14. |
| P-009 | Department teams are independently scoped rosters. A person may serve in more than one department; department coordinators manage only their own roster, while ministry administrators appoint department coordinators and executives. | User direction, 2026-10-15. |
| P-010 | Each installation has a Super Administrator role. The initial setup administrator receives it; it is not a routine invitation role and remains subject to verified-email and MFA gates on elevated actions. | User direction, 2026-10-15. |
| P-011 | Ministry staff should be able to use AI assistance to draft public pages through an installation-configured provider and model. AI output remains a draft for staff review and never publishes itself. | User direction, 2026-10-21. |
| P-012 | The interface should feel like one responsive application: routine saves should update in place when safe, and success, warning, and error feedback should appear in a shared viewport notification that follows the active light/dark theme. Migrate this behavior across workflows incrementally and retain server-rendered fallbacks. | User direction, 2026-10-21. |
| P-013 | Ministry administrators can choose destinations, order, and icons independently for staff and member sidebars/mobile footers, and choose public sidebar/mobile links through the public navigation editor. Published custom pages can be selected. Configuration never overrides route permissions, module state, or publication status. | User direction, 2026-10-21. |

## 2. Working architecture decisions

These are recommendations recorded for coherent planning. Validate in a proof of concept before treating exact products or providers as permanent.

| ID | Working choice | Validation needed |
| --- | --- | --- |
| A-001 | One ministry per installation, with multiple branches/campuses. | Confirm network/denomination deployment needs. |
| A-002 | Large services use broadcast distribution; small groups use interactive conferencing. | Prototype UX and load profiles. |
| A-003 | Laravel Blade + Livewire + Alpine.js web/application, MySQL/MariaDB baseline with PostgreSQL support, host cron and database queue baseline. | Confirm supported PHP/database version matrix and shared-host limits. |
| A-004 | Zoom Video SDK interactive media; HLS-based broadcast delivery; provider adapters. | Prototype Zoom Video SDK meeting, recording, latency, cost, and host controls. |
| A-005 | Docker Compose is an optional local-development and enhanced-server profile; a Laravel shared-host deployment is the baseline package. | Test on target shared hosting environments and upgrade path. |
| A-006 | No central ministry account or data service. | Validate license/distribution and support model separately. |

The existing Phase 0 scaffold uses Laravel 13 and a legacy Next.js 16 transition interface. The Laravel version and eventual Livewire/Alpine asset versions are implementation choices, not a claim that the full architecture or media stack has passed its proof of concept.

## 3. Open questions that affect build scope

| ID | Question | Default until decided | Decision deadline |
| --- | --- | --- | --- |
| O-001 | Must interactive meetings ship in the first public release? | Phase 4, but architectural proof in Phase 1. | Before Phase 1 scope freeze. |
| O-002 | Which countries and languages are the initial examples? | Country-neutral core with one English default locale; no hardcoded payment provider. | Before provider and localization implementation. |
| O-003 | Are denominational networks expected to share administration across ministries? | No; independent installations. | Before identity/data implementation. |
| O-004 | What privacy/retention defaults should ship? | Conservative configurable defaults, reviewed for initial jurisdictions. | Before sensitive-data release. |
| O-005 | Should youth/children check-in be included? | Excluded until safeguarding specification. | Before any youth module. |
| O-006 | Which media providers and self-hosting profiles are supported in v1? | Prove one self-hosted path and one adapter interface; do not advertise untested alternatives. | Before media release. |
| O-007 | What exact audience load should release testing demonstrate? | Each deployment declares its own peak and headroom; reference tests should cover several scales. | Before production capacity gate. |
| O-008 | Which contact and giving workflows are needed for the first launch ministry? | General intake and configurable funds; provider chosen later. | Before Phase 2/4 implementation. |

## 4. How to record decisions

When a question is resolved, record date, decision maker, evidence, alternatives considered, and consequences. Update the owning specification in the same change and mark the open question closed here. If a prototype overturns a working architecture choice, change the relevant documents together; do not let implementation silently become the only source of truth.

### D-024 — Public page video embedding

**Decision:** The visual page editor accepts only YouTube and Vimeo video URLs, normalizes them to responsive public embeds, and never stores arbitrary iframe HTML.

**Reason:** This gives ministries the video capability they expect while preventing script injection and provider markup from becoming executable page content. The validated block document remains portable across shared-hosting installations.

**Status:** Accepted.

### D-025 — Interactive meeting product shape

**Decision:** Deliver a ministry-branded interactive-meetings module as a separate, feature-gated product area. Laravel owns policy, room access, stage state, attendance, care handoff, and audit. Zoom Video SDK carries camera, microphone, screen sharing, and room controls. Google Meet remains an external-link compatibility option only.

**Reason:** This supports Zoom-style participant video and host control while preserving the required distinction between a small interactive gathering and a potentially large public broadcast. It also keeps provider accounts, cost, and media credentials owned by the installing ministry.

**Status:** Superseded and made concrete by D-026.


### D-026 — Zoom Video SDK is the sole in-product meetings provider

**Decision:** Zoom Video SDK is the only supported provider for the ministry-branded interactive-meetings module. LiveKit Cloud and self-hosted LiveKit are removed from the product scope and documentation. A ministry owns and configures its Zoom Video SDK account. The application stores credentials encrypted, issues short-lived SDK JWTs only after its own authorization checks, and keeps Laravel as the authority for meeting policy, attendance, care, stage state, and audit.

**Reason:** User direction, 2026-10-17. A single provider avoids a confusing provider-selection experience and concentrates implementation, documentation, and support on a familiar Zoom-style feature set.

**Status:** Accepted. A Zoom credential/configuration and browser/provider proof of concept is still required before the feature can be released.

### D-027 — Signed release package manifest

**Decision:** Tag builds publish a shared-hosting package, a JSON manifest containing the package SHA-256 and signing-key identifier, and a detached RSA/SHA-256 signature. Stable tags have no prerelease suffix; tags containing a prerelease suffix publish to the Preview channel. The release job reads the private key only from the repository Actions secret `RELEASE_SIGNING_PRIVATE_KEY_PEM`; the matching key identifier is stored as the `RELEASE_SIGNING_KEY_ID` Actions variable.

**Reason:** Ministries need a versioned package that does not require GitHub credentials, `git pull`, or a Node.js runtime on their host. Detached signatures allow an installation to verify package metadata before any update operation. Signing-key rotation and the installation-side public-key trust store remain part of the updater work.

**Status:** Accepted for release packaging. The self-hosted update feed and installation update/rollback workflow remain pending.

### D-028 — Neutral defaults and ministry-owned interface palette

**Decision:** New installations start with neutral light surfaces (white and gray) and neutral dark surfaces (charcoal and near-black). Each ministry controls primary/accent colors plus light/dark canvas, panel, and sidebar colors. The default sidebar treatment matches the content surface, and separate custom sidebar fields appear directly under the custom sidebar choice only when selected. The application derives foreground text colors for contrast; status colors retain their semantic meaning. These palette values apply consistently across staff, member, public, worship, and meeting surfaces.

**Reason:** The product serves ministries across cultures and denominations. A fixed green or gold identity should not be imposed by the platform, and ministries need control of their navigation sidebar as well as their primary accents.

**Status:** Accepted by user direction, 2026-10-20. Existing stored primary/accent choices are preserved as ministry-owned settings; new neutral surface defaults are supplied through an additive migration.

### D-030 — Structured responsive page documents

**Decision:** The central page editor will evolve from a flat ordered block list to a versioned page document containing sections/containers, columns, and child elements. Sections own full-width/background/spacing settings; columns own responsive width, stacking, alignment, and gap behavior; elements own content and element-specific settings. Responsive overrides are explicit for desktop, tablet, and mobile, with safe defaults when an override is absent. A compatibility reader will continue to open existing flat documents while they are migrated.

**Reason:** A flat list cannot express reusable parent containers, multiple elements in one column, nested layout, or breakpoint-specific behavior. The structured model is required for an Elementor-style editor while preserving the shared-hosting renderer, approved element allowlist, module gates, and draft-first publication workflow.

**Status:** Accepted for the next page-editor implementation batch, 2026-10-22.

### D-029 — Bounded AI page-editor provider configuration

**Decision:** Amend the first-release AI exclusion in `01-architecture.md` to allow a feature-gated AI page-editor assistant. The first supported provider connections are Google Gemini directly and OpenRouter. Each installation owns and configures its credentials. The staff AI settings page maintains a provider-neutral model catalog and an ordered model route for page drafting. Catalog discovery does not activate a model: an administrator must select a route, run a structured-output compatibility check, and explicitly activate it. Enabled model catalogs refresh daily and on demand. Removed or incompatible models make the route unavailable and require review; the system does not silently replace the selected primary model. Generated page copy is validated and placed in the existing visual editor; it is persisted as a draft revision only when staff explicitly save it, then goes through the existing publishing review. The assistant receives only the staff-entered brief, title, and optional public details; it does not read pastoral-care, giving, attendance, or children's records.

**Reason:** User direction, 2026-10-21. The AI page editor is a bounded public-content authoring aid, not general access to ministry records. Direct Gemini and OpenRouter provide initial choice while a provider adapter boundary allows future model/API changes without tying page content to a vendor format.

**Status:** Accepted for implementation behind the `ai_page_editor` feature flag, default off. Shared-host portability, provider credential encryption, model capability validation, audit, operator guidance, and a manual staff review remain required. This resolves the earlier general-purpose AI exclusion narrowly; it does not approve AI use for pastoral, children's, attendance, or giving decisions.

### D-030 — AI provider settings do not require MFA enrollment

**Decision:** Saving or managing installation-owned AI provider credentials, refreshing provider catalogs, and configuring or checking the page-editor model route require the `ai.manage` permission and a verified staff email, and are audited. These configuration actions do not require MFA enrollment or a recent MFA challenge. Provider credentials remain encrypted at rest and write-only in the interface. Existing MFA requirements for elevated role management, security administration, restricted care and finance access, exports, publication, and meeting controls remain in force.

**Reason:** User direction, 2026-10-21. Requiring MFA setup before an authorized ministry administrator can configure an AI provider creates an unexpected onboarding barrier. Permission checks, verified email, encryption, rate limits, and audit preserve safeguards while ministries can configure the provider. MFA remains appropriate for the separately defined higher-risk actions.

**Status:** Accepted by user direction, 2026-10-21. The permissions matrix, systems rules, API contract, and AI settings routes must reflect this exception.

### D-031 — AI page composition and section-scoped editing

**Decision:** The AI page editor proposes a complete ordered page outline using approved section types and ministry-owned theme tokens. Staff choose whether to apply the proposed page plan; manual section insertion remains available. Every section can be edited independently by staff or by AI. An AI section edit receives the selected section, page purpose, ordered page outline, and the current page's section content as coherence context, then may return only a replacement for the selected section. Staff review and accept or reject that replacement. Adding, removing, or reordering sections is a separate explicit action. The interface clearly states that current draft page content is sent to the installation's configured provider for a scoped AI edit. No private member, attendance, care, children's, or giving records are included, and prompts or generated copy are not retained in logs or audits.

**Reason:** Staff need local control over each page section without losing tone and continuity across the page. Sending page context helps the selected section fit its neighbors; constraining the returned change to one section protects all other edits.

**Status:** Accepted by user direction, 2026-10-21. Implemented as a section-scoped revision endpoint and inspector workflow. Staff may still add, remove, duplicate, reorder, or edit blocks manually. Full-page context is sent to the configured provider, but the response schema is limited to the selected block's existing fields; URL values are preserved. Staff review and explicitly apply revisions, then save as a normal draft for publication review. Browser/provider and automated release checks remain pending.

### D-032 — Ministry-controlled display terminology

**Decision:** Ministries may override singular and plural labels for supported interface concepts. Overrides affect human-facing display copy only; stable routes, permissions, stored domain values, and API identifiers remain unchanged. Broadcast services and interactive meetings remain distinct concepts with separate labels.

**Reason:** Ministries use different words for their gatherings and community structure, and those words should appear consistently without changing application behavior or creating a separate codebase.

**Status:** Accepted by user direction, 2026-10-21. Initial terminology configuration is implemented for service, meeting, group, location, volunteer, department, and member labels. Labels now flow into the shared staff/member/public navigation, primary directory headings, staff overview summaries, and public fallback links. Remaining user-facing labels are being covered alongside the navigation configuration work.

### D-033 — Public navigation sections and inline presentation controls

**Decision:** Public menu rows use the same compact checkbox, label, order, section, and icon controls as Staff and Member navigation. The checkbox includes or excludes a configured link from public rendering without deleting its saved configuration; permanent deletion remains in Manage link details. Inline selection, order, section, and icon values save together. Public sidebar entries are grouped by the selected section. The initial controlled sections are Main, Connect, Resources, About, and Member area. Label, URL, device visibility, module requirement, parent link, open-new-tab behavior, and permanent removal remain in a separate Manage link details area.

**Reason:** User direction, 2026-10-21. The Public menu should follow the same direct row pattern as Staff and Member navigation, while preserving the existing public-link management capabilities.

**Status:** Accepted and implemented with additive section and menu-selection migrations. Existing links default to Main and remain selected. Both migrations are applied in the local Compose database; the compact responsive Public rows and selection interaction were reviewed in the local browser.

### D-034 — Unified application-experience and performance contract

**Decision:** The product uses one explicit cross-cutting contract for the application experience: neutral defaults; ministry-owned branding, terminology, navigation, and sidebar treatment; semantic tokens; accent-derived primary action foregrounds; theme-aware overlays and notifications; scoped, app-like navigation and saves; and measured performance rules. The contract is `18-experience-performance-contract.md` and is read with the Product Master before feature design.

**Reason:** Design and performance decisions had accumulated across an audit, implementation notes, and individual feature discussions. A dedicated contract prevents future features from reintroducing inconsistent styling, full-page saves, clipping overlays, duplicate navigation behavior, or deployment assumptions that break shared-host portability.

**Status:** Accepted by user direction, 2026-10-22. It formalizes existing accepted directions and does not claim unverified implementation work as complete.
