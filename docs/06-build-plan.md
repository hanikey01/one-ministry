# 06 — Build Plan and Quality Gates

**Status:** Canonical implementation order. A checked gate must be demonstrated on a running installation; documentation alone does not satisfy it.

## 1. Delivery rules

Build vertical slices across database, API, UI, background jobs, provider adapter, documentation, and tests. No module is complete because its controller or page exists. Every phase has a demo path, security checks, failure checks, and install/upgrade verification. Keep features in `staff_preview` until their gate passes; release to the public through an audited setting change.

For each slice: read the Product Master and the applicable owners in the source-of-truth map → define contract → migrate → implement domain rules and policy → implement Laravel route/Livewire or Blade interface → preserve internal API only where it has an integration or polling consumer → add meaningful automated tests → run the end-to-end demo → update operations and docs. [18 — Application Experience and Performance Contract](18-experience-performance-contract.md) is mandatory for every user-facing feature. Media and provider integrations also need a simulated failure test. User-facing writes should use the shared viewport notification component and prefer progressive in-place saves when the endpoint can return authoritative state safely; keep the normal server-rendered submission as a fallback and migrate existing workflows route by route. Shared navigation must use versioned, cacheable assets; optional media SDKs load only on routes and actions that need them. Adopt seamless Livewire navigation incrementally after the affected JavaScript initializes correctly on each navigation event.

## 2. Phase 0 — Portable foundation

**Build:** Laravel-first shared-hosting foundation: Blade, Livewire, Alpine.js, Tailwind; MySQL/MariaDB and PostgreSQL compatibility; host-cron scheduler; database queue and bounded synchronous fallback; migrations; identity, scoped roles, sessions, audit, ministry settings, location settings, first-run setup, backups, health checks, CI, feature settings, and optional Docker/VPS enhancements.

**Gate:**

- A fresh installation can be configured without editing source code.
- Administrator can configure ministry-owned branding and light/dark canvas, panel, and sidebar colors, create two locations, invite staff, revoke a session, and switch a module into staff preview.
- A theme change keeps neutral light/dark defaults unless the ministry chooses its own palette; the shared shell resolves the saved palette consistently across staff, member, public, and worship surfaces.
- An administrator can set singular and plural ministry-facing labels for supported concepts; shared UI surfaces use the override while route names, permission keys, data values, and public/API identifiers remain stable.
- A representative enhanced settings save shows pending and completion/failure feedback in the current viewport without a page reload, while its server-rendered fallback still works.
- Unauthorized location/group access is denied through direct API calls.
- Backup is restored into a clean test installation and compared with expected records and media manifests.
- Clean migration and upgrade migration pass; queued jobs and scheduler run; failing dependency appears in operator health.
- A fresh installation works without Node.js, Redis, Docker, a persistent worker, or a realtime server.
- Supported MySQL/MariaDB and PostgreSQL migration paths are verified before a release claims both profiles.

## 3. Phase 1 — Public presence and digital church

**Build:** Pages, content and homepage carousel; service templates/occurrences/run sheet; media provider adapter; broadcast playback; live state machine; host dashboard; public worship room; chat/moderation; newcomer/prayer response forms; service notes and prompts; fallback and interruption flows.

The bounded AI page editor is a separately feature-gated content-authoring enhancement, not an AI service requirement for the core public site. Its foundation is built in one vertical slice: ministry-owned provider credentials, dynamic model catalog, structured-output route test, staff settings, and audit. Full-page planning consumes only the staff-entered brief and optional public details, validates a structured composition against approved page blocks, and shows an outline for staff approval before changing the normal editor. Section editing sends the ordered public page draft for context and returns only the selected block's existing fields for staff review and approval. Staff select media and link destinations, then save through the existing draft flow, which creates the page revision; publisher review remains mandatory before `ai_page_editor` can pass its release gate.

**Gate:**

- Producer schedules, rehearses, starts, interrupts, resumes, and ends a service; homepage displays the correct state each time.
- A missing stream never produces a healthy live banner; a viewer can still reach fallback content and contact forms.
- Public attendee watches on phone and desktop, uses chat and submits a request; moderator removes abusive content and the action is audited.
- Realtime disconnect and reconnect refetch authoritative service state.
- Broadcast distribution is load-tested against a declared audience target. Record origin, CDN, error rate, latency, and cost observations. This gate does not claim unlimited capacity.

## 4. Phase 2 — People, attendance, and follow-up

**Build:** Person and household records; visitor intake and consent; identified presence; in-person check-in; attendance derivation/correction; prayer and care queues; tasks, assignment and escalation; member profile and communication preferences; scoped reports.

**Gate:**

- Anonymous viewer totals remain separate from identified attendance.
- A guest response creates a consented person or links through a reviewed match; it creates one assigned follow-up, not duplicates on retry.
- Assigned care worker can see the case; ordinary host, finance user, and unrelated administrator cannot.
- Staff correction preserves original attendance observation and audit reason.
- A missed or overdue task appears in an escalation queue without disclosing private prayer text in a general notification.

## 5. Phase 3 — Community and communication

**Build:** Groups, events, registrations, volunteer teams and schedules, announcements, email/SMS adapters, birthday preferences, and celebration scheduling.

**Gate:**

- A member joins a group and event; leader records group attendance only within assigned scope.
- Volunteer accepts a shift and receives a reminder; cancellation or substitution updates the schedule.
- Birthday display and greetings occur only for opted-in people and obey local time/quiet hours.
- Campaign excludes unsubscribed people and logs provider failures for retry.

## 6. Phase 4 — Interactive meetings, media library, and giving

**Build:** Private interactive rooms, invitations, waiting room, participant permissions, recording notice and review, approved sermon/media library; ministry funds, donation flow, verified webhooks, receipts, reconciliation, and finance reports.

**Gate:**

- A group runs a meeting with host controls and room-scoped access. An expired or unrelated token cannot join.
- Recording notice appears to participants; rejected or failed recordings do not publish.
- Provider callback replay results in one confirmed gift and one receipt. Browser redirect alone cannot confirm a gift.
- Finance user can reconcile giving but cannot read pastoral care. Care worker cannot see donation details.

## 7. Phase 5 — Capacity, hardening, and additional adapters

**Build:** Additional media/payment/messaging adapters as demand warrants; import/export; large-event operational profile; advanced reports; accessibility and disaster-recovery hardening.

**Gate:**

- Documented restore meets the installation's agreed recovery target.
- Public service load test reaches that ministry's declared peak plus agreed headroom, with monitored error and latency budgets.
- Provider failure simulation shows the intended fallback and alerting.
- Export/import is checked for identity, consent, attendance, media, and restricted-data handling.
- Accessibility review covers public site, worship room, meeting controls, and staff workflows.

## 8. Required test layers

- **Domain tests:** state machines, consent, attendance derivation, follow-up dedupe, giving reconciliation.
- **Policy tests:** cross-role and cross-scope denial, direct-object access, restricted exports.
- **Contract tests:** request/response schemas, disabled modules, stale state conflicts, webhook verification.
- **End-to-end tests:** fresh setup, live service, newcomer follow-up, meeting, donation.
- **Operational tests:** migration upgrade, backup restore, provider outage, media interruption, declared-audience load.

Avoid tests that merely repeat implementation details. A failing test should reveal a product rule or operational risk.

## 9. Release criteria

Before production release: all phase gates for shipped modules pass; security and privacy review has no unresolved critical issue; backups have been restored; production configuration passes setup checks; service operators have a rehearsal and incident runbook; current public capacity target has passed a load test; and release notes identify migration and configuration changes. Promotion to production is an explicit operator action after CI and staging checks.

## 10. Ordering dependencies

Identity and audit precede sensitive data. Service lifecycle precedes attendance derivation. People and consent precede follow-up automation. Provider adapter and webhook verification precede giving confirmation. Recording review precedes public replay. Child check-in remains outside the release plan until a dedicated safeguarding specification and review are approved.

## 11. Shared-hosting transition order

The route-by-route interface migration is owned by `13-shared-hosting-transition.md`. Build SH-0 through SH-2 before porting feature work: compatibility baseline, Laravel interface foundation, then setup/identity/administration. Continue with staff operations, member/public routes, Digital Church, and portable deployment. The Next.js frontend remains a transition reference only and must not receive new production-only features once the Laravel interface work starts.
