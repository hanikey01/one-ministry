# 04 — Modules and Repository Structure

**Status:** Canonical module ownership and intended layout. Paths become required when implementation begins.

## 1. Repository layout

```text
services/api/             Laravel application: Blade, Livewire, HTTP contracts, domain modules, jobs
apps/web/                 Legacy Next.js transition reference; not a target production deployment
infra/                    Optional Docker/VPS profiles, media examples, monitoring
docs/                     Product and implementation specifications
tools/                    Setup validation, import/export, backup verification
```

The Laravel application is deployable as one PHP codebase. On shared hosting, host cron runs the scheduler and the database queue or bounded synchronous work provides the baseline. Redis workers and persistent schedulers are optional enhancement profiles. Video, database, Redis, and object storage are deployment dependencies, not forks of the ministry's business application. Avoid an extra service until a measured scaling or ownership boundary justifies it.

## 2. Backend module layout

Within `services/api/app/Modules/<Module>/`, keep `Models`, `Data`, `Policies`, `Actions`, `Services`, `Jobs`, `Events`, `Listeners`, `Http/Controllers`, `Http/Requests`, and `Http/Resources` only where needed. Use framework conventions where a specialized folder adds no clarity. Migrations live under the API database directory and carry the owning module in their name or metadata. Tests mirror module behavior. Cross-module calls use explicit application interfaces or events, not direct writes to another module's private tables.

| Module | Owns | Emits/consumes |
| --- | --- | --- |
| Installation | Settings, locations, terminology, feature switches, setup checks | Configuration changed |
| Identity | Accounts, sessions, invitations, role assignments | Login, role and session events |
| People | Person, household, contacts, consent, merge | Person created/merged, consent changed |
| Publishing | Pages, sermons, series, revisions, homepage theme, carousel, and section composition | Content published/unpublished |
| Media | Asset metadata, upload authorization, recording review | Asset ready/failed/published |
| Services | Schedule, run sheet, service lifecycle, homepage live state | Service prepared/started/interrupted/ended |
| Meetings | Meeting policy, invitations, room grants, recording intent | Meeting started/ended, recording requested |
| Interaction | Chat, moderation, polls, service response actions | New response, moderation action |
| Attendance | Presence, check-in, computed records, corrections | Attendance confirmed/corrected |
| Care | Prayer, case assignment, restricted notes | Care request and status events |
| FollowUp | Rules, tasks, assignments, reminders | Task assigned/completed/escalated |
| Community | Groups, membership, events, registrations | Group/event participation |
| Volunteers | Teams, opportunities, shifts, substitutions | Assignment changed |
| Messaging | Templates, campaigns, preferences, deliveries | Delivery result |
| Giving | Funds, donation attempts, verified gifts, receipts, reconciliation | Donation verified/reversed |
| Reporting | Defined read models and scoped exports | Consumes authorized domain events |
| Audit | Sensitive action and access trail | Consumes security-relevant events |
| Integrations | Provider adapter contracts and implementations | Verified provider callbacks |

## 3. Interface areas

| Area | Responsibility |
| --- | --- |
| `resources/views/public` | Home, public pages, content, locations, events, live watch |
| `resources/views/auth` | Login, recovery, invitation acceptance, verification |
| `resources/views/member` | Profile, preferences, groups, events, serving, own giving |
| `resources/views/staff` | People, care, follow-up, service production, content, reports, settings |
| `app/Livewire` | Server-authorized interactive workflows, tables, forms, and dashboards |
| `resources/js` | Alpine.js progressive enhancement, media controls, drawers, and light browser behavior |
| `app/Services` | Playback and meeting adapter abstractions; server remains authority |

Do not put secret keys or policy authority in the web bundle. Public server-rendered pages may cache only data classified public. The live homepage data requires a short freshness period and realtime refresh.

## 4. Provider interfaces

Define `MailProvider`, `SmsProvider`, `PaymentProvider`, `ObjectStore`, `BroadcastProvider`, `MeetingProvider`, and `RecordingProvider` contracts. Each adapter declares capabilities, required configuration, health checks, webhook verification, and failure mapping. A module may be disabled if no valid provider is configured. Provider-specific identifiers are stored alongside internal IDs and never replace them.

At least one documented implementation or local simulator is required for each module's end-to-end test. Do not claim generic provider support until an adapter contract has been tested with a second implementation or a conformance suite.

## 5. Cross-module workflows

- **Live service:** Services checks readiness → Integrations starts/observes media → Services commits state → outbox broadcasts state → Publishing resolves homepage lead → Interaction receives chat/responses → Attendance derives identified records → FollowUp receives opted-in response events.
- **First-time visitor:** Interaction intake → People lookup/create with consent → FollowUp assigns task → Messaging delivers approved acknowledgement → assigned staff records outcome.
- **Donation:** Giving creates attempt → provider adapter initiates payment → verified webhook appends event → Giving finalizes donation → Messaging issues receipt. A browser success page cannot finalize payment.
- **Recording:** Media callback verifies asset → content producer reviews → Publishing links approved media to service.

Each workflow needs a sequence diagram and failure cases during implementation. `05-systems.md` owns the business rules; this document owns module responsibilities.

## 6. File-level completion rule

A module is complete only when migration, model/data objects, policies, actions, jobs/events, controllers, route contract, frontend consumer, observability, and meaningful tests exist as applicable. Do not scaffold empty layers merely to match the folder list. Keep contract examples in `03-api-contracts.md` and public operational instructions in `08-operations.md` synchronized with code.
# Digital school module

`digital_school` is independently enabled per installation. Its staff navigation appears only to users with `school.manage`; its member navigation appears only while enabled. Disabling the module returns a server-side 404 for every staff and member school endpoint. The module contains catalog, curriculum, assessments, enrollment, progress, certificates, and live classes while reusing the platform meeting domain when an interactive class is hosted internally.

