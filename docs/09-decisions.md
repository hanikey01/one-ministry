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
