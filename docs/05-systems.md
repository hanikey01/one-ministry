# 05 — Cross-Cutting Systems and Business Rules

**Status:** Canonical rules for identity, live state, attendance, care, communications, and giving.

## 1. Identity, authorization, and scope

Visitors may browse and watch public services anonymously. Form submission asks only for information needed to respond. Account registration verifies a contact method before sensitive self-service actions. Staff invitations require an administrator with assignment authority. Sessions can be revoked individually or all at once; elevated roles require multifactor authentication before restricted care, finance, security, or export actions.

Authorization has three checks: action permission, object relationship, and assigned scope. A group leader can see their group, not every person. A care worker can see assigned cases, not all prayer requests. Finance access never grants pastoral access. Admin configuration rights do not silently grant restricted-data reading rights. Direct object URLs and exports follow the same checks as list screens.

Department teams use the same scope rule. A person may belong to several department rosters. A coordinator or executive can add and remove ordinary members only in the department where they hold that active role. A ministry administrator may create departments and appoint or remove coordinators and executives. Care and Finance roster membership never grants private care-case or giving-record access; those domains retain their separate permissions, assignment checks, verified-email requirement, MFA requirement, and audit trail.

## 2. Feature configuration

Feature settings have `disabled`, `staff_preview`, and `enabled` states. A disabled feature is hidden in navigation and returns 404 or a documented unavailable response from the server. Staff preview is available only to specifically authorized staff and does not expose the feature publicly. An authorized ministry administrator may enable an implemented module; any provider-dependent workflow remains unavailable until its own required configuration validates. Changes are audited and take effect across web and API without a deployment.

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
- **Group/event attendee:** attached to that occurrence; does not automatically count as Sunday service attendance.
- **Household viewing:** may be self-reported, but other household members are not silently marked present.

Keep raw observation, derived attendance, and staff correction separate. Recompute derived attendance only through a versioned rule change and preserve history. Reports must show measure name and definition. Never label anonymous reach as members present. No general-purpose spiritual health score.

## 5. Follow-up rules

Intake may create a task when the person requests contact or when an administrator-configured, privacy-reviewed trigger fires. Examples: first-time visitor with consent, unanswered prayer request, membership interest, missed assigned volunteer shift, or repeated absence from a group with an existing relationship. Rules have explicit audience, consent basis, cooldown, assignment queue, due time, and escalation path. They must not repeatedly create duplicate tasks for the same occurrence.

Every task records assigned person/team, due time, status (`open`, `in_progress`, `waiting`, `completed`, `closed_no_contact`), attempts, outcome, and next action. Restricted request details stay in the care module; general task notifications carry minimal information. The recipient may opt out of nonessential contact. Staff can suppress or dismiss a suggestion with a reason.

## 6. Birthdays and celebrations

Birthday collection is optional. Three independent preferences control storing the date, staff reminder, and public recognition. The member self-service profile asks for month and day, never birth year; the stored operational date uses a leap-year placeholder so it cannot produce an age. Public recognition defaults off. Automated greetings respect quiet hours, local time zone, preferred channel, and message consent. Revoking birthday storage clears the stored month/day, turns off birthday reminder and public-recognition preferences, and stops future scheduled greetings and public display. Profile changes are audit-recorded without placing the values themselves in the audit payload.

## 7. Prayer and pastoral care

Prayer intake asks visibility (`public`, `prayer_team`, `assigned_care_only`) and contact preference. A public request is moderated before display and may be redacted. Private request details never appear in service chat or broad reports. Care notes are attributed and timestamped, with append/correction history rather than silent edits. A person can request removal, subject to documented retention obligations. Emergency language may show local emergency guidance and alert a designated human team if configured; the software does not claim to provide crisis services.

## 8. Media, chat, and meetings

Live chat uses service-scoped identities and configurable guest naming. Hosts can remove messages, mute users, pause chat, and block repeat abuse. Moderation logs are restricted. Polls must declare whether answers are anonymous before a person responds. Timed service prompts can be fired manually or aligned to stream playback; wall-clock-only triggers are prohibited when broadcast latency would misplace them.

Private meetings require a room-scoped grant. Recording is off by default, starts only with authorized action, and gives visible notice. A meeting invitation does not automatically grant access to unrelated care records. Published recordings require review and a documented retention policy.

## 9. Messaging and giving

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
- Certificates are issued once, only when certificates are enabled and completion requirements are satisfied.
- A live class is a meeting experience and must not be treated as an unlimited broadcast.

