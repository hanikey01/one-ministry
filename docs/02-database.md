# 02 — Data Model and Migration Rules

**Status:** Logical schema. Column-level migrations follow the approved contracts. MySQL/MariaDB is the shared-hosting baseline; PostgreSQL remains supported.

## 1. Conventions

- `uuid` primary keys for externally referenced records; `created_at`, `updated_at` UTC timestamps; explicit `deleted_at` only where recoverable soft deletion is required. Migrations must work on supported MySQL/MariaDB and PostgreSQL versions; database-specific shortcuts require an application-level equivalent and tests on both engines.
- Every record belongs to its installation implicitly. There is no `tenant_id` or shared database across ministries. Scoped records carry `location_id`, `group_id`, or `case_id` where needed.
- Store times in UTC with the event's IANA time zone. Store a person's birthday as month/day and optional year; do not derive age when year is absent.
- Monetary amounts are integer minor units with ISO currency. Never use floating point for money.
- Foreign keys are enforced. Every relationship specifies `RESTRICT`, `SET NULL`, or `CASCADE` intentionally. Audit, giving, attendance correction, and care history must not be erased by a cascading person delete.
- Use enums or controlled reference tables for states with documented transitions. Check constraints protect nonnegative quantities and valid intervals.
- Private fields are encrypted at application level where a database administrator should not see plaintext; keys are managed outside the database.

## 2. Core entities

### 2.0 Department teams

`departments` stores ministry-owned operational units: Media & Production, Prayer, Worship, Hospitality & Ushering, Follow-up & Connections, Outreach & Missions, Technical & Livestream, Protocol, Communications, Care, and Finance. Each has a UUID, unique name and slug, optional purpose, active/archived status, optional creator, and timestamps. A ministry administrator may create further departments.

`department_memberships` links one `person_id` to one `department_id` with a controlled `member`, `coordinator`, or `executive` role, active status, grantor, and timestamps. The composite department/person key is unique, while the same person may hold active memberships in any number of departments. Deleting a person or department is restricted while a membership exists; a deleted grantor becomes null. Membership changes are audit events.

Team membership is a roster and a scoped team-management grant when the linked person has a user account. It does not carry care, finance, children, or other restricted-record permissions.

### 2.1 Phase 0 installation schema

The first implementation slice uses the following exact fields. Later migrations add only fields required by an approved feature.

| Table | Fields and constraints |
| --- | --- |
| `ministry_settings` | `id uuid PK`; `name varchar(160) NOT NULL`; `tagline varchar(240) NULL`; `public_domain varchar(253) NOT NULL UNIQUE`; `contact_email varchar(254) NULL`; `timezone varchar(64) NOT NULL`; `theme_preset varchar(24) NOT NULL DEFAULT 'neutral'` constrained to platform-owned `neutral`, `slate`, or `warm`; `chrome_mode varchar(24) NOT NULL DEFAULT 'preset'` constrained to `preset` or `custom`; `chrome_color char(7) NULL`, accepted only when `chrome_mode=custom`; `staff_font varchar(24) NOT NULL`; `staff_density varchar(24) NOT NULL`; `terminology json NULL` for ministry-owned singular/plural interface-label overrides keyed by stable domain concept; `created_at`, `updated_at`. Exactly one row is allowed by application setup and a fixed singleton key. The application owns all canvas, surface, text, border, input, control, and action colors. A ministry may select one platform preset and optionally one accessible chrome color shared by its sidebar, header, and mobile footer. Arbitrary CSS is not accepted. Terminology affects presentation labels only, never route names, permission keys, stored domain values, or API identifiers. |
| `locations` | `id uuid PK`; `name varchar(160) NOT NULL`; `slug varchar(160) NOT NULL UNIQUE`; `timezone varchar(64) NOT NULL`; `address text NULL`; `is_primary boolean NOT NULL DEFAULT false`; `is_public boolean NOT NULL DEFAULT true`; `created_at`, `updated_at`. At most one primary location is enforced by a partial unique index where supported. |
| `feature_settings` | `key varchar(80) PK`; `state varchar(16) NOT NULL` with check in `disabled`, `staff_preview`, `enabled`; `changed_by uuid NULL FK users.id ON DELETE SET NULL`; `created_at`, `updated_at`. |
| `users` | `id uuid PK`; `name varchar(160)`; `email varchar(254) UNIQUE`; `email_verified_at timestamp NULL`; password hash; remember token; timestamps. The first administrator is created by the setup command. |

Phase 0 authorization and audit tables: `roles(key PK, name, is_system)`; `permissions(key PK, description)`; `role_permissions(role_key FK CASCADE, permission_key FK CASCADE, composite PK)`; `user_role_assignments(id uuid PK, user_id FK RESTRICT, role_key FK RESTRICT, scope_type, scope_id nullable, granted_by FK SET NULL, expires_at, revoked_at, timestamps)`; and `audit_events(id uuid PK, actor_user_id FK RESTRICT, action, subject_type, subject_id, changes_json, ip_address, created_at)`. A revoked or expired assignment does not authorize requests. Audit events are append-only and must never include passwords, session cookies, or provider secrets.

The Phase 0 administration upgrade adds `locations.read`, `locations.manage`, `features.read`, and `features.manage` to the permission catalog, granting them to the installation-wide ministry administrator role. Existing installations receive those grants through an idempotent migration; fresh installations receive the same catalog during first-run setup.

Staff invitations use `staff_invitations(id uuid PK, email varchar(254), name varchar(160), role_key FK roles RESTRICT, token_hash char(64) UNIQUE, invited_by FK users RESTRICT, expires_at, accepted_at NULL, revoked_at NULL, created_at, updated_at)`. Only a hash of the one-time token is stored. A pending invitation can be revoked; acceptance creates the user and installation-scoped role assignment atomically. Routine installation-wide invitations are limited to Content & Media Editor, Care Staff, and Finance Staff. Ministry Operations Coordinator and Service & Production Team require a later direct scoped-assignment workflow; Children’s Team remains unavailable until safeguarding rules are accepted. Sessions use the existing `sessions` table with the database session driver, enabling per-session listing and deletion.

`ministry_settings` is a singleton: the setup command creates a fixed UUID `00000000-0000-0000-0000-000000000001`, and model/application APIs never expose arbitrary create operations. `locations` is an internal installation-wide table; there is no cross-ministry foreign key.

| Domain | Tables | Essential relationships and constraints |
| --- | --- | --- |
| Installation | `ministry_settings`, `locations`, `feature_settings`, `email_delivery_settings`, `provider_connections` | One settings row; locations may have parent; provider credentials stored as encrypted server-side values or secret references, never plaintext. |
| Identity | `users`, `sessions`, `roles`, `permissions`, `user_role_assignments`, `role_scopes`, `invitations` | User email unique when present; role assignments carry optional location/group scope. Sessions revocable. |
| People | `people`, `households`, `household_memberships`, `contact_methods`, `consents`, `person_relationships`, `merge_records` | A person may have no login; one user maps to at most one active person. Household memberships allow dated history. |
| Public content | `pages`, `content_items`, `content_series`, `media_assets`, `content_revisions`, `homepage_settings`, `homepage_slides`, `homepage_sections`, `navigation_items`, `navigation_layouts` | Publish state and time; revisions immutable; media assets have visibility class. `pages.blocks` stores an ordered JSON document of validated visual blocks; `body` remains a text compatibility summary for legacy readers and exports. `pages.page_type` distinguishes `standard` pages from the future centralized `homepage` document, while `source_key=homepage` provides a stable installation-local identity during migration. |
| Services | `service_templates`, `service_occurrences`, `service_segments`, `service_staff`, `live_sessions`, `service_prompts` | Occurrence has explicit state and time zone; at most one active primary live session per occurrence. |
| Meetings | `meeting_rooms`, `meeting_occurrences`, `meeting_invites`, `meeting_participations` | Invitation and access policy per occurrence; recording policy explicit. |
| Interaction | `chat_messages`, `chat_moderation_actions`, `polls`, `poll_options`, `poll_responses`, `connection_responses` | Service/meeting context required; moderation history retained; poll anonymity respected. |
| Attendance | `attendance_records`, `attendance_corrections`, `anonymous_viewer_aggregates`, `presence_sessions` | Identified attendance unique per person, occurrence, and channel after reconciliation; anonymous aggregates never linked to people. |
| Care | `prayer_requests`, `care_cases`, `care_notes`, `follow_up_tasks`, `task_activity` | Restricted visibility; assignments and state history; no general profile notes containing pastoral details. |
| Community | `groups`, `group_memberships`, `events`, `event_registrations`, `volunteer_teams`, `volunteer_assignments` | Dates and location; waitlist order; team role and shift uniqueness. |
| Communication | `message_templates`, `campaigns`, `message_deliveries`, `notification_preferences` | Delivery outcome; consent snapshot; provider message ID unique where present. |
| Giving | `funds`, `donations`, `payment_attempts`, `payment_events`, `receipts`, `reconciliation_batches` | Append-only payment events; unique provider transaction ID; donation state derived from verified events. |
| Testimonies | `testimonies` | Member submissions remain private until reviewed; public rows require explicit consent and published status. Author visibility is separate from authorship. |
| Operations | `outbox_events`, `webhook_receipts`, `audit_events`, `export_jobs`, `backup_manifests` | Unique event IDs; immutable audit and webhook receipt rows. |

## 3. Key record definitions

### 2.2 Email delivery

`email_delivery_settings` is a singleton installation record. It stores the active provider choice, enabled state, sender address/name, SMTP connection metadata, and the timestamp/outcome of the latest operator test. SMTP passwords and provider keys are encrypted before storage and are never returned to staff screens or copied into audit payloads. The supported portable choices are the hosting sendmail service, custom SMTP, Resend, Postmark, SendGrid, Mailgun, Brevo, Google Workspace/Gmail, Amazon SES SMTP, and Zoho SMTP. A provider must have a valid sender and complete connection details before activation.

`email_templates` stores one installation-owned template per stable event key. It retains the staff-facing name and description, subject, plain-text body template, approved placeholder catalog, active state, updater, and timestamps. Staff cannot edit the shared HTML shell or introduce arbitrary HTML. Security action URLs are rendered by the platform as labelled buttons rather than interpolated into editable body copy.

### 3.1 Person and identity

`people` owns a stable person ID, names, optional birth date components, preferred language and location, relationship state, and archival status. `users` owns login credentials. Visitors may create a person record through a consented form without a user account. Staff must see an explicit distinction between verified self-service information and staff-entered information. Merge keeps the surviving ID and a permanent redirect/mapping for historical references. The current member self-service screen records birthday month and day in the existing `people.birthday` field using a leap-year placeholder; it never asks for or displays a birth year. Removing birthday permission clears that field and stops birthday-specific preferences.

`consents` records person, purpose, channel, status, source, timestamp, policy version, and actor. A later change creates a new consent event rather than overwriting history. Current effective consent may be materialized for queries.

### 3.2 Service and live session

`service_occurrences` stores template, title, location, starts/ends times, time zone, and lifecycle state. A service has at most one `live_session`, which stores its public playback adapter/reference, separate ingest and playback health, lower-bandwidth and caption URLs, fallback message, and start/end timestamps. `live_stream_events` is the append-only health and configuration history. Stream keys and provider credentials remain in the ministry's provider account or secret store and are never entered into these records or returned in a general service response.

### 3.2a Homepage composition

`homepage_settings` is a second fixed singleton that stores the selected theme family and lead layout. `homepage_slides` has a unique display position, publication state, media type, optional HTTPS media URL, descriptive text, copy, and action. No more than four slides may exist; the application enforces this limit. `homepage_sections` has a unique approved section type, display position, visibility, and validated presentation settings. All homepage changes are audited. Module-linked sections are filtered from the public response when their module is disabled, even if a configuration record remains.

`playback_diagnostic_events` stores short-lived operational media events using a one-way HMAC of a browser-session key. It has no user, person, email, display name, or IP address column. Events are idempotent by client event ID and may record playback type, selected quality, bounded startup time, and bounded rebuffer duration. Staff responses aggregate device sessions and event counts; raw session hashes are not an attendance source or a member activity timeline.

### 3.2b Interactive meetings

`meetings` is the installation-owned meeting definition. It records a Zoom Video SDK session name, encrypted session passcode, lifecycle, access and lobby policies, participant-media/chat/screen-share policies, capacity declaration, and recording notice. The Zoom SDK key and encrypted SDK/webhook secrets are stored separately in the fixed `zoom_video_sdk_settings` record. `meeting_participants` records the application role, lobby/admission decision, provider identity, and join/leave observations. A short-lived Zoom SDK JWT is generated after Laravel admission and is never persisted. These records are operational and attendance evidence is created only under the selected identified-admission policy; a token request, browser page view, or waiting-room entry is not attendance.

The `interactive_meetings` feature setting is disabled by default on new and upgraded installations. Room pages, grant issuance, lobby reads, and lobby decisions are blocked server-side unless an authorized administrator enables the module. Lobby state reuses `meeting_participants.admission_status`; admit/decline decisions are conditional updates so a stale host action cannot overwrite a later decision, and every decision is audited.

AI page drafting adds installation-owned `ai_provider_connections` (one row per provider, with an encrypted credential, enabled state, connection status, last check time, and updater), `ai_model_catalog` (provider/model identifiers, context limit, text input/output and structured-output capabilities, availability, and last-seen time), and `ai_task_routes` (one primary provider/model plus ordered provider/model fallbacks per task; activation state and last successful validation). `ai_configuration_audits` records actor, change type, and redacted before/after values; credential contents and generated page prompts are never recorded. Catalog entries use provider model IDs as external references, not primary application IDs. Catalog refresh marks previously seen but absent entries unavailable and never changes a route automatically. The `ai_page_editor` feature is off by default and is enforced server-side. Draft generation holds the validated proposal in the editor until staff explicitly save it through the existing `pages` and `content_revisions` workflow; no separate AI-content table is needed.

### 3.3 Attendance

`presence_sessions` capture authenticated join/leave heartbeats for possible attendance computation. A missed disconnect creates a bounded inferred end time. `attendance_records` contain `person_id`, occurrence or event context, channel (`in_person`, `online_identified`, `group`, `event`, `serving`), status (`confirmed`, `self_reported`, `corrected`), source, observed interval, and confidence reason. Anonymous viewing uses time-bucket aggregates only. `attendance_corrections` append before/after, reason, actor, and time. Attendance threshold rules are owned by `05-systems.md` and must be configurable, not hardcoded in migrations.

### 3.4 Care and giving

Care cases and notes carry a visibility class and authorized assignment. Search indexes must not leak restricted content. Giving keeps donor linkage separately from general participation reports; access to giving does not imply access to care. Payment attempts and provider events are immutable. Reconciliation may append adjustments rather than rewrite a verified provider event.

## 4. Privacy classes

| Class | Examples | Default access |
| --- | --- | --- |
| Public | Published sermon, public event | Everyone |
| Member | Own profile, group membership | Subject and scoped leaders as permitted |
| Staff operational | Attendance, follow-up status | Authorized staff within scope |
| Restricted care | Prayer details, pastoral notes | Assigned care staff only |
| Restricted child | Child profile, guardian and check-in data | Authorized children's team only |
| Restricted finance | Donations, payout or reconciliation detail | Donor and finance staff as appropriate |
| Security | Sessions, audit, provider configuration | Security administrators and limited operators |

Keep these classes separate in API resources and exports. General exports exclude restricted care, child, finance, and security data unless their specific authority is proven.

## 5. Indexes and uniqueness

- Unique active login email; unique active `users.person_id`; unique provider event and transaction IDs per provider.
- Composite indexes on `(service_occurrence_id, channel, person_id)` for attendance, `(assignee_id, status, due_at)` for tasks, `(location_id, starts_at)` for services/events, and `(visibility, published_at)` for content.
- Partial indexes for open care cases, pending follow-up, live occurrences, and unpublished content.
- Full-text search only on public or authorized operational fields. Restricted care content requires a separate access-aware search path.
- Large event, audit, chat, and delivery tables may be partitioned after measured growth. Partitioning does not weaken retention or audit rules.

## 6. Deletion and retention

Person deletion is a reviewed workflow: revoke access, stop optional communications, anonymize permitted operational records, and retain legally required finance/audit records with minimized identity. Deleting a household or location must not cascade into donation, care, or attendance history. Retention periods are installation policy values subject to local obligations; the product must provide defaults and administrator review, not claim one global legal period.

Recording files may be unpublished independently of their metadata and audit history. Expired raw presence heartbeats and chat logs can be purged according to policy after durable attendance and moderation evidence have been produced. Backups follow their own retention and deletion schedule.

## 7. Migration order and release rules

1. Installation and locations.
2. Identity, roles, permissions, and audit.
3. People, households, contact methods, consent.
4. Content and media metadata.
5. Service schedules, live sessions, meetings.
6. Interaction and attendance.
7. Care and follow-up.
8. Groups, events, volunteering, communications.
9. Giving and reconciliation.
10. Operational outbox, exports, and reporting projections.

Each migration has a deterministic forward path and an explicit foreign-key deletion policy. Production changes use expand → backfill → switch → contract across releases. Avoid destructive rollback assumptions. Seed only roles, permissions, system defaults, and sample data explicitly requested by setup; never seed a real ministry's member information.

## 8. Schema quality gate

Before implementing a module: produce column definitions, nullability, unique/check constraints, indexes, FK actions, privacy class, retention policy, and expected query patterns. Test a clean migration and a realistic upgrade migration. Verify that unauthorized role scopes cannot retrieve restricted records through joins, search, exports, or aggregate reports.
### Service capacity rehearsals

`service_capacity_rehearsals` stores an installation-owned, service-scoped readiness exercise. A plan records expected peak viewers, explicit headroom, resulting required test viewers, audience geography, provider/test method, and thresholds for startup time, rebuffer rate, playback error rate, and chat latency. Completion stores measured values, optional evidence and notes, and a server-calculated `passed` or `failed` result. The creator, completer, timestamps, and audit events preserve accountability. A passed rehearsal supports only the documented installation, provider plan, geography, targets, and tested load; it is not an application-wide capacity guarantee.

`playback_diagnostic_events` remains anonymous operational telemetry. It contains no user, person, name, email, or IP field and is deleted after the configured retention window, seven days by default.

`live_stream_probe_runs` groups one manual or scheduled distribution check for a live session. `live_stream_probe_results` records the configured region key and label, local or remote probe type, health, HTTP status, latency, bounded error code, and timestamp. Probe-agent credentials and target URLs are configuration, not database result data. Probe history is operational evidence and never attendance.


## Public navigation manager

Public navigation is ministry-owned and stored as ordered `navigation_items`. Each link has a label, internal or external URL, optional approved icon, a controlled sidebar section (`Main`, `Connect`, `Resources`, `About`, or `Member area`), parent link for a dropdown, a ministry-controlled `is_enabled` menu-selection flag, device visibility, optional new-tab behavior, and an optional required feature. Unselected links remain configured and can be re-enabled from Staff Navigation; deleting a link remains a separate action. Disabled features and unselected links are omitted from public rendering. The public desktop/mobile sidebar groups top-level links by their selected section; the mobile footer uses the first five enabled top-level links marked mobile-visible, followed by its More control. Existing links receive the neutral `Main` section when the additive section migration is applied, and remain selected when the additive `is_enabled` migration is applied.

`navigation_layouts` stores ordered staff and member sidebar destinations as bounded key/icon/section JSON entries, and mobile-footer destinations as bounded key/icon entries. Sidebar section values must match a section in the server-owned destination registry; destination keys never resolve to arbitrary routes. The staff renderer reapplies permission and module checks for every request; member links are still protected by their route middleware and module gates. Published page destinations are selectable, and navigation changes record an audit event against the installation settings identifier.
# Digital school extension

The digital school uses installation-local records: `school_courses`, `school_course_modules`, `school_lessons`, `school_enrollments`, `school_lesson_progress`, `school_assessments`, `school_assessment_questions`, `school_assessment_attempts`, `school_live_classes`, `school_certificates`, and `school_course_instructors`. A person can have one enrollment per course. A completed enrollment may have a historical certificate chain: a revoked certificate is retained with its reason, actor, and public verification state; a replacement certificate links to the credential it supersedes. Course instructor assignments give staff with `school.instruct` read/instructor access only to explicitly assigned courses. Live classes may reference a platform meeting or an approved external URL.

