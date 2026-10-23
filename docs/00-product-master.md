# Ministry Platform — Product Master Document

**Status:** Canonical product baseline; implementation details remain subject to review  
**Version:** 0.4
**Date:** 2026-10-22

## 1. Purpose

Build a Christian ministry web application that an independent ministry can deploy and operate under its own name and domain. The product combines a public website, digital church, people management, and ministry operations. A local church, campus fellowship, prayer ministry, or outreach organization should be able to use the same codebase through configuration.

The primary distribution target is ordinary shared PHP hosting. The finished application runs as one Laravel application and does not require a Node.js runtime, Docker, Redis, a daemon worker, or a self-hosted realtime service. Those are optional enhancement profiles. Video delivery, payments, mail, storage, meetings, and optional realtime services remain installation-owned external providers.

This document defines product behavior. The companion documents define the architecture, data model, interfaces, modules, design, build sequence, and operations. Technical choices are working recommendations; validation gates are identified in the build plan.

## Source of truth

This document is the product baseline. Read it with the following owners before designing or changing a feature:

| Concern | Owning document |
| --- | --- |
| Shared application experience, navigation, theming, app-like interactions, and performance rules | [18 — Application Experience and Performance Contract](18-experience-performance-contract.md) |
| Interface components, accessibility, page editor, and visual-detail rules | [07 — Experience and Design System](07-design.md) |
| Laravel/shared-hosting boundaries and deployment profiles | [01 — Architecture](01-architecture.md) and [13 — Shared-Hosting Transition](13-shared-hosting-transition.md) |
| Domain state rules, feature gating, privacy, and authorization | [05 — Systems](05-systems.md), [02 — Database](02-database.md), and [10 — Permissions Matrix](10-permissions-matrix.md) |
| APIs and browser/internal contracts | [03 — API Contracts](03-api-contracts.md) |
| Work sequencing and quality gates | [06 — Build Plan](06-build-plan.md) |
| Accepted and unresolved product choices | [09 — Decisions](09-decisions.md) |
| Implemented and actually verified work | [12 — Implementation Status](12-implementation-status.md) |

Historical audits and status notes describe context; they do not override the documents above. When a requirement conflicts, record the proposed resolution in `09-decisions.md` before implementation.

## 2. Product principles

1. **One ministry per installation.** Each installation controls its own data and configuration. A ministry may have multiple campuses, branches, or locations.
2. **Digital church is a first-class experience.** A live service has a dedicated worship room, people who host it, meaningful interaction, and a path to follow-up.
3. **People before metrics.** Participation data helps staff offer care. The system must never present an automated engagement score as a judgment of spiritual health.
4. **Configurable ministry structure.** Ministries choose terminology, teams, locations, forms, and enabled modules without code edits.
5. **Private by design.** Prayer, pastoral care, children, and giving records have distinct access rules. Public recognition requires consent.
6. **Accessible and usable on modest connections.** Core pages work on phones; live experiences offer lower-bandwidth options and useful fallbacks.
7. **Portable ownership.** A ministry can export its records and media, restore backups, and change configured providers.

## 3. Intended users

| User | Primary needs |
| --- | --- |
| Public visitor | Learn about the ministry, watch a service, find a location, submit a request, and make contact without mandatory registration. |
| Member or regular attendee | Join worship and groups, manage profile and preferences, see events and serving commitments, and request care. |
| Online host or moderator | Welcome attendees, moderate chat, assist with prayer and next steps, and handle disruptions. |
| Group or ministry leader | Manage an assigned group or team, attendance, schedules, and follow-up within their scope. |
| Care team member | Receive assigned requests and record restricted care actions. |
| Content or service producer | Publish content, prepare service schedules, operate live sessions, and review recordings. |
| Administrator | Configure the ministry, permissions, locations, forms, policies, and reports. |
| Finance user | Manage giving and financial reports without access to unrelated pastoral records. |

Roles are permission bundles, not a rigid hierarchy. A person may hold more than one role. Every sensitive action must be checked against both permission and assigned scope, such as campus, group, or care case.

## 4. Ministry setup and public presence

### 4.1 Installation and branding

- First-run setup collects ministry name, domain, logo, colors, time zone, primary location, service schedule, initial administrator, and contact details. New installations begin with neutral light surfaces (white and gray) and neutral dark surfaces (charcoal and near-black); every later interface palette choice belongs to that installation.
- Administrators control the ministry's primary and accent colors, light/dark canvas, panels, and sidebar. Shared theme tokens apply these choices consistently to staff, member, public, worship, and meeting interfaces; readable foregrounds are derived for ministry-selected surfaces.
- Administrators can configure terminology such as *campus* versus *branch*, and can enable or hide modules. Core interface terms use installation-owned singular/plural display overrides; these never change internal route names, permission keys, or stored domain values.
- Site navigation, homepage sections, footer, social links, and public contact methods are editable.
- Administrators select a feature-aware homepage preset and arrange approved sections. The available section set is derived from activated modules, while branding, content, and section order remain ministry-owned.
- The homepage editor provides four theme families and four lead layouts. It supports up to four curated carousel slides, each using a ministry-owned image, hosted video, or approved external video URL, with accessible text and an optional action. Below the lead, ministries select and configure approved sections such as service times, locations, events, groups, sermons, libraries, giving, prayer, volunteering, announcements, newsletter, FAQ, and custom welcome content.
- The application ships with sample content that can be removed before launch.

### 4.2 Public pages and content

- Home, about, beliefs or mission, leadership, locations, service times, contact, and ministry or department pages.
- Sermons, series, articles, devotionals, audio, video, testimonies, and downloadable resources.
- Search, categories, publication status, scheduled publishing, and editorial review.
- Event calendar with detail pages, registration, capacity, reminders, and cancellation notices.
- Public contact, newcomer, prayer, and testimony forms with configurable fields and routing.
- Optional AI-assisted page planning and section revision through an installation-configured provider. AI works with staff-entered instructions and public page content, returns approved page blocks for staff review, and never saves or publishes without staff action. Provider credentials remain installation-owned; see D-029 and D-031 for the boundary and review rules.

### 4.3 Dynamic homepage lead section

The lead section follows an explicit state model:

| State | Public display |
| --- | --- |
| No upcoming or live service | Curated content and media carousel. |
| Upcoming service | Curated content plus an optional countdown and join reminder. |
| Live service | Live video preview or player, title, live indicator, and prominent **Join worship** action. |
| Service ended | Return to curated content; show recording only after approval. |
| Stream unavailable | Show service information, retry action, and an administrator-selected fallback message or media. |

The live state depends on the actual service state and stream health, not only a calendar time. Authorized staff can start, end, or correct a service state manually. A scheduled service must not display as live when there is no usable stream. Administrators can preview each homepage state before publishing.

## 5. Digital church

### 5.1 Service preparation

- Recurring and special services, linked to a location or designated online campus.
- Each service chooses one public experience: **broadcast only** for a public provider such as YouTube, **interactive meeting only** for Zoom Video SDK, or **broadcast plus interactive room**. The hybrid option opens broadcast first and offers an explicit interactive-room action; it never automatically launches two media experiences.
- Service run sheet: segments, speakers, hosts, notes, scriptures, links, polls, and timed calls to action.
- Rehearsal mode with no public attendance or notifications.
- Service lifecycle: draft, scheduled, ready, live, ended, cancelled, and interrupted.
- Permissions for producer, speaker, host, moderator, and attendee.

### 5.2 Online worship room

- Broadcast player; full-screen and lower-bandwidth playback choices where supported.
- Public chat with moderation, optional slow mode, and clear host identification.
- Service notes, scripture references, captions when available, polls, and reactions.
- **I’m new**, **Request prayer**, **Speak to someone**, **Join a group**, and other configurable response actions.
- Private conversation or prayer handoff to an available authorized host.
- Anonymous viewing is allowed unless the ministry explicitly makes a particular event private. Participation actions may ask for contact details with clear consent.
- Hosts can mute or remove a disruptive user, delete chat content, and pause interaction during an incident. Moderation actions are logged.

### 5.3 Interactive meetings

- Smaller meetings support participant camera and microphone, screen sharing, hand raising, chat, waiting room, host controls, and breakout rooms.
- Meeting privacy options: public registration, invited members, or invitation only.
- Recording is off by default and requires visible notice when enabled.
- Group, class, prayer, and pastoral meeting templates may choose different defaults.

### 5.4 After the service

- A producer reviews a recording before publication.
- Published replay can include the service notes, linked scriptures, and relevant next actions.
- Unanswered prayer and connection requests enter assigned follow-up queues.
- Staff can review service attendance and response counts without exposing confidential request content to general hosts.

### 5.5 Broadcast and meeting distinction

A large service uses a broadcast viewing experience with app-based chat and responses. A small interactive gathering uses a conference experience. Both appear within the same product, but their capacity, permissions, latency, recording, and attendance rules are specified separately.

## 6. People, participation, and care

### 6.1 People records

- Individual profiles, households, preferred name, contact methods, consent, location, groups, ministry teams, and optional milestones.
- Visitor, regular attendee, member, volunteer, and leader are configurable relationship states; one person may satisfy several.
- Duplicate detection and a reviewed merge process preserve attendance, donations, and care history.
- People can update their own basic profile and communication preferences. Staff edits are audited.

### 6.2 Attendance and participation

- Track in-person service check-in, identified online service attendance, anonymous viewer totals, group attendance, event attendance, and serving separately.
- Attendance records include event, channel, source, time window, and whether the record was automatic, self-reported, or manually corrected.
- A page view alone must not be recorded as confirmed individual attendance. An anonymous stream viewer contributes only to aggregate viewing figures.
- Household viewing cannot automatically imply that every household member attended.
- Staff can correct attendance with a reason and audit history.
- A person's timeline may show ministry-relevant actions such as service attendance, group participation, volunteering, registrations, prayer requests, and follow-up. Confidential details appear only to authorized users.

### 6.3 Follow-up workflows

- Configurable workflows for first-time visitors, connection requests, prayer requests, missed gatherings, membership interest, baptism or discipleship interest, and serving interest.
- Each task has an assignee, scope, due date, status, contact history, and next action.
- Suggestions such as repeated absences are review prompts, not automatic declarations that a person needs pastoral intervention.
- Members can state preferred contact channel and opt out of nonessential outreach.
- Escalation and reassignment prevent requests from being stranded when a volunteer becomes unavailable.

### 6.4 Celebrations and care

- Birthday and anniversary dates are optional. A person chooses whether staff may see them and whether public recognition is allowed.
- Optional greetings and reminders use local time and the person's communication preferences.
- Prayer requests may be public, shared with a chosen team, or confidential. The requester can ask for follow-up and can request removal.
- Pastoral notes are separate from general member notes, restricted by assignment, and auditable.
- Children's records and check-in require a dedicated permissions and safeguarding specification before release.

## 7. Community and operations

- **Groups and discipleship:** group discovery, applications or invitations, schedules, resources, attendance, leader tools, and configurable learning pathways.
- **Volunteering:** teams, opportunities, availability, rosters, substitutions, reminders, and service history.
- **Events:** registration, capacity, waitlist, ticket or fee settings where applicable, attendance, and communications.
- **Messaging:** announcements and targeted email or SMS, templates, delivery history, unsubscribe and preference controls.
- **Giving:** ministry-defined funds and campaigns, online and manually recorded gifts, receipts, donor statements, reconciliation, and finance-only reports. Payment credentials belong to the installing ministry.
- **Reports:** service attendance by channel, visitor follow-up completion, group participation, volunteer coverage, content reach, and giving. Reports show definitions and time periods so numbers are interpretable.
- **Digital school:** optional course catalogues, enrolment policies, ordered modules and lessons, learner progress, assessments, completion certificates, instructor tools, and live classes linked to the ministry's interactive meeting provider.

## 8. Cross-cutting rules

### 8.1 Configuration and portability

- An installation is owned by one ministry, with optional multiple locations. There is no shared central member database across installations.
- Modules and public features can be enabled independently. Disabled modules disappear from navigation and reject access on the server.
- Forms, content, people, attendance, and financial records have documented export formats. Backups include a restore procedure and a way to verify restoration.
- External providers for email, SMS, payments, media storage, and video are configured per installation. A provider outage should fail gracefully and leave actionable status for administrators.

### 8.2 Privacy, safety, and access

- Define retention and deletion policies for each record category. Document exceptions for financial records and safeguarding obligations according to the installing ministry's jurisdiction.
- Require explicit consent for optional communications and public celebrations. Record consent changes.
- Separate financial, pastoral, and children's permissions. Sensitive record views and changes are audited.
- Minimize personally identifiable information in chat, reports, notifications, and analytics.
- Private video sessions and recordings need explicit access controls and participant notice.

### 8.3 Reliability and accessibility

- The public website remains useful when the live media service is unavailable.
- Live status, stream health, and service incidents are visible to operators.
- Core interactions support keyboard use, readable contrast, captions where available, and mobile layouts.
- Common successful actions and warnings appear in a shared, dismissible notification area inside the current viewport. Notifications use the active light/dark theme, announce state accessibly, and remain visible long enough to read; field validation stays beside the affected field.
- Prefer in-place form saves when the server can return a safe, bounded result. Show pending, success, and failure states, prevent duplicate submissions, preserve entered values on failure, and retain ordinary server-submission fallback. Extend this behavior route by route rather than assuming every workflow has already migrated.
- Important jobs such as reminders and follow-up routing are retryable and visible when they fail.
- Define measurable performance, availability, recovery, and video capacity targets before implementation.

## 9. Suggested delivery phases

| Phase | Deliverable | Exit demonstration |
| --- | --- | --- |
| 0 — Foundation | Install, configuration, identity, roles, audit, backups, public site shell | A ministry deploys a fresh instance, brands it, creates an administrator, and restores a backup. |
| 1 — Digital church | Schedule, dynamic homepage, online worship room, hosting, chat, response forms | A ministry prepares and runs a live service; the homepage enters and exits live mode correctly. |
| 2 — People and care | Profiles, attendance definitions, visitor intake, prayer, assigned follow-up | A visitor joins, submits a request, and an assigned team member completes a documented follow-up. |
| 3 — Community | Groups, events, volunteering, messaging, celebrations | A member joins a group and event; a leader records attendance; opted-in birthdays are recognized. |
| 4 — Advanced media and giving | Interactive meetings, recordings, content library expansion, giving | A group holds a private meeting and a finance user reconciles a gift without accessing pastoral notes. |
| 5 — Scale and extensions | Capacity improvements, additional provider adapters, advanced reporting | A larger ministry validates peak service capacity and disaster recovery against agreed targets. |

The phases express dependencies, not a promise that a ministry must wait until Phase 4 to collect offerings or run interactive meetings. The first release scope should be set after the capacity and hosting decisions in §12.

## 10. Documentation set

1. **Architecture:** `01-architecture.md` owns services, deployment profiles, media paths, failure behavior, and security boundaries.
2. **Data model:** `02-database.md` owns entities, relationships, constraints, and retention classifications.
3. **HTTP and event contracts:** `03-api-contracts.md` owns public polling, integration, staff, and video lifecycle interfaces.
4. **Module specifications:** `04-services.md` owns module boundaries and canonical file responsibilities.
5. **Cross-cutting systems:** `05-systems.md` owns attendance, identity, privacy, live status, and follow-up rules.
6. **Build plan:** `06-build-plan.md` owns implementation order, acceptance tests, capacity tests, and release gates.
7. **Design system:** `07-design.md` owns navigation, responsive patterns, accessibility, and live-service states.
8. **Operations:** `08-operations.md` owns installation, upgrades, backups, restore, monitoring, and incidents.
9. **Permissions:** `10-permissions-matrix.md` defines default role grants and scopes.
10. **Media delivery:** `11-media-delivery.md` details broadcast and meeting behavior, timing, and capacity validation.
11. **Shared-hosting transition:** `13-shared-hosting-transition.md` owns the Laravel-first migration sequence, shared-host baseline, and Next.js retirement criteria.

Keep one canonical owner for each rule. Other documents should link to that owner instead of copying requirements.

## 11. Proposed technical direction

**Confirmed direction:** Laravel renders public and authenticated interfaces with Blade, Livewire, Alpine.js, and Tailwind CSS. Laravel also owns domain rules, background work, and provider adapters. MySQL/MariaDB is a required shared-hosting database target; PostgreSQL remains supported. The baseline uses host cron plus a database queue or bounded synchronous fallback. Redis, realtime broadcasting, Docker Compose, and self-hosted media are optional enhancement profiles. Large services use a provider-owned broadcast/HLS path; small interactive meetings use a configured external meeting provider.

The application must not assume a single commercial cloud account or a central operator. Large-service video remains provider-configurable. The in-product interactive meeting experience uses ministry-owned Zoom Video SDK credentials; its exact integration and capacity targets belong in the architecture document after §12 is resolved. Optional public-page AI drafting uses provider-neutral model routes configured by the installing ministry, with Gemini and OpenRouter as the initial provider connections; see D-029 for its data and release boundaries.

## 12. Open product decisions

| Decision | Why it matters | Initial working assumption |
| --- | --- | --- |
| Peak concurrent viewers and interactive participants | Each deployment must prove adequate media and network capacity. | No product-level viewer cap. Broadcast delivery scales elastically; each installation declares and load-tests its expected peak. Interactive room limits are separately documented. |
| Required video ownership | Affects cost, operational burden, and portability. | Self-hosting is possible; managed media is optional. |
| First public release scope | Controls delivery order. | Prioritize live services, people, attendance, and follow-up. |
| Ministry types and terminology | Determines configuration and default templates. | Church defaults with configurable language and modules. |
| Countries and payment methods | Determines privacy, messaging, and giving requirements. | No country-specific provider is hardcoded. |
| Children and youth workflows | Requires safeguarding and special permissions. | Design separately before enabling. |
| Content and recording rights | Determines publishing, consent, and retention flows. | Recordings require review and publication approval. |

## 13. Source references for product discovery

- [Church Online Platform feature overview](https://churchonlineplatform.com/features): examples of chat, private prayer, hosts, and in-service response actions.
- [Planning Center People](https://www.planningcenter.com/people): examples of people records and follow-up workflows.
- [Zoom Video SDK documentation](https://developers.zoom.us/docs/video-sdk/): provider capabilities for branded interactive media.
- [Laravel deployment documentation](https://laravel.com/docs/deployment): deployment considerations for the Laravel-first web layer.

These are references, not specifications for this product. The requirements above are this project's own proposed rules.

