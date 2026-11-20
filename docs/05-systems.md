# 05 — Cross-Cutting Systems and Business Rules

**Status:** Canonical rules for identity, live state, attendance, care, communications, and giving.

## 1. Identity, authorization, and scope

Public guests may browse and watch public services anonymously. Form submission asks only for information needed to respond. Account registration verifies a contact method before sensitive self-service actions. Staff invitations require an administrator with assignment authority. Sessions can be revoked individually or all at once. Staff workflows use authentication, permission checks, verified email where required, and audit trails; they do not require a separate MFA session challenge.

AI provider credentials and page-editor model routes require the `ai.manage` permission and verified email, and are audited. Provider keys remain encrypted and write-only.

Authorization has three checks: action permission, object relationship, and assigned scope. A department coordinator can manage their department, not every person. Care Staff can read the active prayer queue and claim only an unassigned request (or continue their own); they can read care cases assigned to them. A Super Administrator is the explicit, audited emergency escalation role and can read all active prayer requests and care cases and assign or reassign a request to an active Care Staff member. Finance access never grants pastoral access. Ministry Administrator configuration rights do not silently grant restricted-data reading rights. Direct object URLs and exports follow the same checks as list screens.

The installation catalog is deliberately small: Ministry Administrator, Content & Media Editor, Ministry Operations Coordinator, Care Staff, Finance Staff, Service & Production Team, Children’s Team, Security Administrator, and Super Administrator. Member is ordinary account access. Department, department, service, and meeting responsibilities are contextual assignments. Roles that must be tied to an assigned service, meeting, department, or team are never granted through an installation-wide invitation workflow.

Department teams use the same scope rule. A person may belong to several department rosters. A coordinator or executive can add and remove ordinary members only in the department where they hold that active role. A ministry administrator may create departments and appoint or remove coordinators and executives. Care and Finance roster membership never grants private care-case or giving-record access; those domains retain their separate permissions, assignment checks, verified-email requirement, and audit trail.

## 2. Feature configuration

Feature settings have `disabled`, `staff_preview`, and `enabled` states. A disabled feature is hidden from staff, member, and public navigation, omitted from dashboard summaries and module-driven content, and returns 404 or a documented unavailable response at every applicable web and API route. Staff preview is available only to ministry staff with settings access and does not expose the feature publicly. An authorized ministry administrator may enable an implemented module; any provider-dependent workflow remains unavailable until its own required configuration validates. Changes are audited and take effect across web and API without a deployment. The Modules administration page remains available so authorized staff can inspect and change module state; setup pages required to configure a module before activation may remain available under their own permission checks.

### AI page drafting

**2026-11-13 routing amendment (D-043):** Replace the older all-models-must-pass rule below with independent per-model representative composition checks. A verified primary with a failing fallback is an active `degraded` route. Catalog disappearance of one selected model preserves remaining usable choices. Runtime readiness checks belong inside each model attempt, for both full-page and scoped proposals. A failed latest compatibility check disqualifies that model at runtime until retested. Discovery alone does not prove Gemini structured-output capability. The later compact compiler and durable-job workflow remain pending.

The `ai_page_editor` feature is disabled by default and must be checked server-side for every drafting request. Only staff with `content.manage` may request a page draft or selected-object revision, and this permission does not grant access to sensitive records. Each installation configures its own Gemini and/or OpenRouter connection. The route selects a text-input, text-output, structured-output capable primary model and ordered fallbacks. A route is inactive until every configured model passes a low-token live compatibility check. Enabled provider catalogs refresh daily and on administrator request. When a selected model disappears or loses required capabilities, the route becomes unavailable and an audit entry records the change; runtime failures may advance only through saved, validated fallbacks and never trigger an unreviewed model substitution. A scoped revision sends the full ordered public page draft as context but returns only a complete replacement for the selected section, column, or element. Staff review and explicitly apply the proposal; generation never saves the page. Prompts and page content are excluded from audit records.

Full-page planning accepts a bounded plain-text brief, optional title, and optional public details entered by the staff member. A scoped edit also receives the selected section, column, or element and current public page draft as context so its replacement fits the page. The interface states that current draft content is sent to the configured provider. Neither operation queries person, attendance, care, children, giving, or other private tables. The provider returns a structured version-2 document or a complete selected-object replacement using approved native elements and validated settings; bounded custom code is permitted only in its isolated sandbox element. Ministry inserts scoped output into a copy and validates the complete document, module gates, limits, URLs, and settings before review. Invented media URLs and destinations are removed. Staff explicitly apply a staged proposal as an undoable edit. Proposals stay unsaved until staff save through the existing page form, and publication still requires `content.publish`, verified email, recent MFA, and explicit staff review. Do not retain prompts or generated content in application logs or audit events. Audit provider, model, scope, fallback use, and latency only.

## 3. Live-service state machine

```text
draft → scheduled → ready → live → ended
                     ↘ cancelled
             live → interrupted → live
                     ↘ ended
```

Only a producer may start/end or resolve interruption. Starting requires a healthy stream or an explicitly acknowledged manual override. The homepage shows `live` only when the occurrence is live and playback is usable. On ingest loss, the service enters `interrupted` after a configured grace period and the homepage displays the fallback. On recovery, a producer may resume or the configured auto-resume rule may do so; the state transition is audited. Ending is idempotent. A recording remains unpublished until reviewed.

The homepage lead priority is: healthy live service explicitly marked primary; upcoming primary service inside the configured window; curated carousel. When two locations are live, the ministry's primary service selection decides the homepage, while both remain discoverable. A stream heartbeat updates health but does not by itself create a public service.

Homepage carousel slides and body sections are public publishing content. Editors may configure only approved section types and up to four carousel slides. A section referring to a disabled module is excluded from public output and navigation regardless of its stored visibility. The public carousel does not auto-play audio; manual carousel controls are keyboard accessible.

## 4. Attendance semantics

Attendance is a ministry-defined operational record, not proof of worship or engagement. Keep these measures distinct:

- **Anonymous concurrent viewers:** estimated active playback sessions, aggregate only.
- **Anonymous unique devices:** optional estimate, labeled as such; never equated to people.
- **Identified online attendee:** signed-in person whose active presence satisfies the ministry's configured time threshold or is manually confirmed. Heartbeats alone are bounded against stale browser sessions.
- **In-person attendee:** checked in or manually confirmed by authorized staff.
- **Department/event attendee:** attached to that occurrence; does not automatically count as Sunday service attendance.
- **Household viewing:** may be self-reported, but other household members are not silently marked present.

Keep raw observation, derived attendance, and staff correction separate. Recompute derived attendance only through a versioned rule change and preserve history. Reports must show measure name and definition. Never label anonymous reach as members present. No general-purpose spiritual health score.

## 5. Follow-up rules

Intake may create a task when the person requests contact or when an administrator-configured, privacy-reviewed trigger fires. Examples: a new public contact with consent, unanswered prayer request, membership interest, missed assigned serving shift, or repeated absence from a department with an existing relationship. Rules have explicit audience, consent basis, cooldown, assignment queue, due time, and escalation path. They must not repeatedly create duplicate tasks for the same occurrence.

Every task records assigned person/team, due time, status (`open`, `in_progress`, `waiting`, `completed`, `closed_no_contact`), attempts, outcome, and next action. Restricted request details stay in the care module; general task notifications carry minimal information. The recipient may opt out of nonessential contact. Staff can suppress or dismiss a suggestion with a reason.

## 6. Birthdays and celebrations

Birthday collection is optional. Three independent preferences control storing the date, staff reminder, and public recognition. The member self-service profile asks for month and day, never birth year; the stored operational date uses a leap-year placeholder so it cannot produce an age. Public recognition defaults off. Automated greetings respect quiet hours, local time zone, preferred channel, and message consent. Revoking birthday storage clears the stored month/day, turns off birthday reminder and public-recognition preferences, and stops future scheduled greetings and public display. Profile changes are audit-recorded without placing the values themselves in the audit payload.

## 7. Prayer and pastoral care

Prayer intake asks visibility (`public`, `prayer_team`, `assigned_care_only`) and contact preference. A public request is moderated before display and may be redacted. Private request details never appear in service chat or broad reports. Care notes are attributed and timestamped, with append/correction history rather than silent edits. A person can request removal, subject to documented retention obligations. Emergency language may show local emergency guidance and alert a designated human team if configured; the software does not claim to provide crisis services.

## 8. Media, chat, and meetings

Live chat uses service-scoped identities and configurable guest naming. Hosts can remove messages, mute users, pause chat, and block repeat abuse. Moderation logs are restricted. Polls must declare whether answers are anonymous before a person responds. Timed service prompts can be fired manually or aligned to stream playback; wall-clock-only triggers are prohibited when broadcast latency would misplace them.

Private meetings require a room-scoped grant. Recording is off by default, starts only with authorized action, and gives visible notice. A meeting invitation does not automatically grant access to unrelated care records. Published recordings require review and a documented retention policy.

## 9. Messaging and giving

### Testimony moderation

A member testimony begins as `submitted` and is visible only to its owner and authorized content staff. Staff-authored records begin as drafts. Editing published testimony returns it to draft. Public rendering requires published status, an effective publication time, and explicit public-sharing consent. Public author-name display is independent from the retained internal authorship link. Members may withdraw only their own submitted or rejected testimony. Submission, withdrawal, editing, publication, rejection, and archival are audited. Publishing requires `content.publish` and verified email but not MFA.

Each outbound message has purpose, audience, template version, consent basis, delivery channel, and idempotency key. Essential operational notices and optional ministry campaigns are distinguished. Unsubscribe or preference changes are honored before sending. Failed delivery is visible to staff and retryable within a configured limit.

A gift begins as pending. Only a verified provider event or reviewed offline reconciliation makes it confirmed. Provider webhook retries are deduplicated. Reversal/refund appends a new event and updates the derived balance; the original event stays intact. Receipts are issued after confirmation. Donor data is excluded from ordinary engagement dashboards and follow-up targeting unless a ministry has explicitly defined and justified a specific finance workflow.

## 10. Data access, export, and deletion

Every report and export names its data class and allowed scope. Bulk exports require explicit permission, reason, audit record, and limited download lifetime. Restricted care, children, finance, and security data use separate export paths. Data deletion follows `02-database.md`; the interface must show what can be deleted, what must be retained, and why. Backups are encrypted and restore-tested.

## 11. Observability and audit

Track request IDs, service transitions, webhook verification failures, job failures, media health, stream start delay, playback failures, realtime reconnects, and follow-up queue age. Logs exclude form bodies, prayer text, access tokens, payment details, and private notes. Audit sensitive reads, edits, exports, permission changes, provider changes, recording controls, attendance corrections, and finance actions. Use bounded retention and access control for operational logs.
# Digital school rules

- A draft or archived course is never visible in the member catalog.
- Open enrollment activates immediately; approval enrollment remains pending; invitation-only enrollment can be created by authorized staff.
- Capacity counts active and completed enrollments.
- Only active learners can complete lessons or submit assessments.
- Completion requires all published lessons and all published assessments to be passed.
- Certificates are issued only when certificates are enabled and completion requirements are satisfied. Revocation never deletes credential history. A replacement may be issued only by authorized staff after revocation, receives a fresh number and verification code, and links to the superseded credential; the earlier public link continues to report revoked status.
- A live class is a meeting experience and must not be treated as an unlimited broadcast.

