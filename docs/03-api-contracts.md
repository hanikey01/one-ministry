# 03 — HTTP, Integration, and Polling Contracts

**Status:** Canonical HTTP contract. Laravel web routes and Livewire components may call domain services directly; these contracts remain required for public browser polling, webhooks, optional asynchronous interfaces, and third-party integrations. Field-level OpenAPI schemas are required before implementation of each phase.

## 1. Conventions

- Base path `/api/v1`; JSON request and response bodies; `snake_case` keys; RFC 3339 UTC timestamps plus IANA time zone where scheduling matters.
- Resource IDs are UUIDs. Pagination uses stable cursors. Filtering is allowlisted; never accept arbitrary column names or SQL fragments.
- Authentication uses secure, HTTP-only session cookies for the ministry's own web app. Staff actions require server-side permission and scope checks. Public endpoints are rate-limited.
- Mutating payment, webhook, attendance-correction, and media-control requests require idempotency keys or unique event IDs.
- Standard errors: `{ "error": { "code": "...", "message": "...", "fields": {} }, "request_id": "..." }`. Use 400 for malformed input, 401 unauthenticated, 403 forbidden, 404 hidden/unavailable resource, 409 state conflict, 422 validation, 429 rate limit, 503 unavailable dependency.
- Lists return `{ "data": [], "next_cursor": null }`; single resources return `{ "data": {} }`. Public resources never include internal notes or provider secrets.
- OpenAPI examples and authorization cases must be added in the same change as a route. Version breaking changes under `/api/v2` with a migration period.

## 2. Public site and discovery

| Method/path | Purpose | Access |
| --- | --- | --- |
| `GET /public/site` | Branding, selected platform theme preset, resolved light/dark semantic tokens, the shared chrome treatment with derived readable foreground, navigation, enabled public modules, service time zone | Public |
| `GET /public/home` | Resolved homepage sections and lead-state payload | Public |
| `GET /public/pages/{slug}` | Published page | Public |
| `GET /public/content` / `GET /public/content/{slug}` | Search/list and read published content | Public |
| `GET /public/libraries/{library}` | Published books, sermons, or sermon series; unavailable while its module is disabled | Public |
| `GET /public/libraries/{library}/{asset}/download` | Streams a published uploaded asset or downloads a published book; never exposes draft files | Public |
| `GET /public/locations` | Public branches/campuses | Public |
| `GET /public/events` / `GET /public/events/{id}` | Published events | Public |
| `POST /public/contact` | Contact or newcomer form submission | Public, anti-spam |
| `POST /public/prayer-requests` | Prayer intake with visibility and consent | Public, anti-spam |

Public library items expose an explicit `delivery` value (`download`, `youtube`, or `null`) and a `youtube_id` only for YouTube delivery. Uploaded assets are accessed only through the authorized download route. Public payloads never include storage disk names, storage paths, or raw provider keys.

Authenticated staff settings accept controlled `theme_preset`, `chrome_mode`, optional `chrome_color`, `staff_font`, and `staff_density` values. Invalid appearance keys return `422`; settings changes use the existing audited ministry-settings action. The application resolves shared interface tokens from the selected preset; ministries cannot customize page, form, button, or dashboard colors. These presentation choices are never interpreted as permissions and do not alter public module availability.

`GET /public/home` returns `lead_state` (`carousel`, `upcoming`, `live`, `interrupted`), `service_occurrence_id` when applicable, server time, optional countdown target, approved carousel items, and a public playback descriptor only when stream health permits. Clients do not invent the live state from schedule alone.

The staff Blade routes under `/staff/homepage` require `content.manage`. They allow theme/lead selection, up to four carousel slides, and approved section configuration. They are form-backed routes rather than a public API. Each mutation is validated, audited, and returns the public homepage with feature-linked sections filtered server-side.

`GET /public/pages/{slug}` returns only a published page whose publication time has arrived. Its public payload is limited to ID, title, normalized slug, body, validated `blocks`, SEO fields, publication time, and update time. It never exposes author IDs, reviewer notes, or internal workflow fields. Blocks contain plain-text fields and approved block types rather than trusted HTML; `body` remains a compatibility summary for legacy clients.

### 2.1 Phase 0 `GET /public/site` contract

Response when configured:

```json
{
  "data": {
    "name": "Example Ministry",
    "tagline": "A place to belong",
    "public_domain": "example.org",
    "contact_email": "hello@example.org",
    "timezone": "Africa/Lagos",
    "theme": { "preset": "neutral", "chrome_mode": "preset", "chrome_color": "#24262A", "chrome_foreground": "#FFFFFF", "light": { "canvas": "#F7F7F8", "surface": "#FFFFFF", "ink": "#202124", "muted": "#656A73", "line": "#DFE1E5" }, "dark": { "canvas": "#121315", "surface": "#1B1C1F", "ink": "#F4F4F5", "muted": "#B1B4BC", "line": "#37393E" } },
    "locations": [
      { "id": "uuid", "name": "Main Campus", "slug": "main-campus", "timezone": "Africa/Lagos", "address": null, "is_primary": true }
    ],
    "features": { "digital_church": "disabled" }
  }
}
```

Before installation, return `503` with `error.code = "installation_required"`; do not publish a fictional ministry identity. Response contains no setup token, password, provider secret, staff email, or private location. Public site data may be cached briefly and is invalidated after a settings change.

### 2.2 First-run installation

`POST /installation/setup` is available only while the installation has no ministry settings record. It uses the same validation and transaction as `ministry:install`, requires same-origin CSRF protection, and is rate-limited to three attempts per ten minutes. The request contains the ministry identity, first public location, and first administrator credentials; successful setup returns `201` with `{ "data": { "status": "configured", "admin_email": "..." } }`. A later request returns `422`; credentials are never returned or logged.

The first-party browser route `GET /setup` presents the same installation transaction as a four-step guided wizard: ministry identity, primary location and time zone, starting colours, and the first administrator. It is available only before setup is complete; after completion it redirects to the public homepage. `POST /setup` is same-origin CSRF protected and rate-limited to three attempts per ten minutes, signs the new administrator into a regenerated session only after the transaction succeeds, and redirects to the staff workspace. It never stores credentials in browser storage or a partial server-side setup record.

## 3. Identity and member

### 3.1 Phase 0 first-party staff session

Staff password recovery uses `POST /auth/password/reset-request` with `{ "email": "..." }` and always returns `200 { "data": { "status": "requested" } }` for a valid address when a delivery transport is configured, whether or not an account exists. A missing safe mail transport returns `503` before a token is generated. `POST /auth/password/reset` accepts `{ "email", "token", "password", "password_confirmation" }`; success returns `200 { "data": { "status": "reset" } }`, while an invalid, expired, or reused token returns `422`. Both routes are throttled and require CSRF. The reset token is single use, expires in 30 minutes, is stored only as a password hash, and is delivered in a URL fragment. Successful reset revokes that user's database sessions and is audited without token or password data. The default `log` mail transport cannot send recovery links.

The first staff client uses same-origin, HTTP-only Laravel session cookies. The web client calls `GET /auth/csrf` before a mutating request and sends the `X-XSRF-TOKEN` header from the readable XSRF cookie. The server rotates the session on login and invalidates it on logout. These endpoints are served under the `web` middleware at the same `/api/v1` prefix as the other API routes; the reverse proxy keeps them on one browser origin.

`GET /auth/mfa` returns the authenticated account's enrollment and current-session verification state. `POST /auth/mfa/setup` returns a one-time Base32 authenticator secret and standard `otpauth` URI; it creates no enabled credential. `POST /auth/mfa/enable` accepts a current six-digit TOTP code and returns eight recovery codes once. `POST /auth/mfa/verify` accepts a TOTP or a one-time recovery code and records a 12-hour verification window in the server session. Enrolled secrets are encrypted at rest; recovery codes are hashed. Protected security actions return `403 mfa_required` unless enrollment and recent verification are present. MFA secrets, TOTP codes, and recovery codes are never logged or audited.

`POST /auth/email/verification-request` requires an authenticated account and returns `200 { "data": { "status": "requested" } }`; an inactive or incomplete installation-owned email delivery connection returns `503` before it creates a token. `POST /auth/email/verify` accepts a one-time 64-character token and returns `200 { "data": { "status": "verified" } }`. Tokens expire after 30 minutes, are stored only as SHA-256 hashes, appear only in the emailed verification URL, and are never logged. The first-party `GET`/`POST /verify-email` page completes the same token flow for a browser user. Staff invitation management returns `403 email_verification_required` until the actor's staff email is verified.

Email delivery is configured through the first-party staff Settings screen and is never returned by `GET /staff/settings`. The browser mutation `PUT /staff/settings/email-delivery` requires `settings.update`, accepts a supported provider, sender details, SMTP metadata, and an optional replacement credential. Credentials are encrypted at rest, omitted from responses and audit events, and must be complete before the provider can be activated. The available provider choices include hosting sendmail, custom SMTP, Resend, Postmark, SendGrid, Mailgun, Brevo, Google Workspace/Gmail, Amazon SES SMTP, and Zoho SMTP.

| Method/path | Request | Response / errors |
| --- | --- | --- |
| `GET /auth/csrf` | None | `204`, establishes CSRF cookie |
| `POST /auth/login` | `{ "email": "...", "password": "..." }` | `200` with minimal user identity; `422` invalid input/credentials; `429` throttled |
| `POST /auth/logout` | CSRF header, active session | `204`; subsequent protected requests return `401` |
| `GET /auth/me` | Active session | `200` with ID, name, email, and effective nonrestricted permissions; `401` otherwise |
| `GET /staff/settings` | Active session with `settings.read` | Public branding and palette fields, including primary/accent and light/dark canvas, surface, and sidebar colors; `403` otherwise |
| `PATCH /staff/settings` | Allowed branding, palette, typography, sidebar treatment, and density fields; palette colors are six-digit hex values; CSRF header | `200` updated settings; `422` invalid fields; `403` without `settings.update`; audited |
| `GET /staff/terminology` | Active session with `settings.update` | Server-rendered terminology editor with defaults and current ministry overrides; `403` otherwise |
| `PUT /staff/terminology` | `terminology` object containing bounded singular/plural display labels for supported concepts; CSRF token | Browser forms redirect with success or validation errors by default. Same-origin requests with `Accept: application/json` return `200 { message, data: { terminology } }`; requires `settings.update`; update is audited; omitted/default labels resolve to product defaults |
| `GET /staff/locations` | Active staff session | `{ "data": [{ "id": "uuid", "name": "...", "slug": "...", "timezone": "IANA", "address": null, "is_primary": true, "is_public": true }] }`; requires `locations.read` |
| `POST /staff/locations` | `name`, `timezone`, optional `address`, `is_public`, `is_primary` | `201` location; requires `locations.manage`; slug generated by server; audited |
| `PATCH /staff/locations/{id}` | Any editable creation fields except slug | `200` location; requires `locations.manage`; `404` unknown ID; `409` when removing the only primary without choosing a replacement; audited |
| `GET /staff/features` | Active staff session | `{ "data": [{ "key": "digital_church", "state": "disabled" }] }`; requires `features.read` |
| `GET /staff/ai-settings` | Installation staff with `ai.manage` | Provider connection status (never credentials), currently selected models, and page-editor route; credentials and route configuration are installation-scoped. The model catalog is searched on demand by the chooser endpoint. |
| `GET /staff/ai-settings/models?q=...` | Optional model/provider search text, up to 120 characters | At most 50 available text models that support structured output from enabled providers, plus a `has_more` indicator; requires an authenticated staff session with `ai.manage`; returns no credentials and makes no provider request |
| `PUT /staff/ai-settings/providers/{provider}` | `api_key` (optional replacement), `clear_api_key`, `enabled`; provider is `gemini` or `openrouter` | Staff form redirects with a status/error message; requires `ai.manage` and verified email; audited; secrets encrypted, never returned, logged, or included in audit data |
| `POST /staff/ai-settings/catalog/{provider}/refresh` | Provider path | Staff form redirects with import summary; requires `ai.manage` and verified email; imports current model metadata; provider failure leaves the last successful catalog unchanged; disappeared entries become unavailable |
| `PUT /staff/ai-settings/page-editor-route` | Primary provider/model and one or two ordered fallback provider/model pairs | Staff form redirects; requires `ai.manage` and verified email; route is saved unverified and inactive until explicit compatibility validation succeeds |
| `POST /staff/ai-settings/page-editor-route/test` | None; uses the saved candidate route | Staff form redirects with test result; requires `ai.manage` and verified email; performs bounded structured-output calls against each route; test failure leaves route inactive; success records verified status |
| `POST /staff/pages/ai-draft` | `brief` (20–3,000 characters), optional `title` (200 characters), optional staff-entered `public_details` (5,000 characters) | JSON page proposal with a suggested slug and ordered approved block composition; sections may include story, split image/text, image placeholder with search hint and alt text, quote, list, and FAQ; media URLs and button destinations remain empty for staff to choose; requires authenticated staff with `content.manage`, an available `ai_page_editor` feature, CSRF, and a rate limit of 5 requests/minute; uses only the verified route and its saved fallbacks; validates the returned fields against the page block contract; records provider/model/latency audit metadata without prompt or output; does not persist until staff explicitly apply the proposal and use the normal page draft form |
| `POST /staff/pages/ai-section-revision` | `title` (200 characters), `instruction` (10–1,500 characters), optional `page_purpose` (3,000 characters), selected block index (0–59), JSON `blocks` (up to 100 KB) | JSON proposal containing only the selected block's existing data fields; provider receives the page purpose when supplied and the complete ordered public page draft as continuity context; URL fields are preserved; requires authenticated staff with `content.manage`, an available `ai_page_editor` feature, CSRF, and a rate limit of 10 requests/minute; requires the same verified route and saved fallbacks; audits provider/model/latency and selected block type without prompt or output; does not persist until staff explicitly applies the proposal and saves the page draft |
| `POST /staff/pages/ai-edit-section` | `section_index`, a validated target section, page title/purpose, complete current page blocks, and a plain-text edit instruction; combined context is bounded to 30,000 characters | Structured replacement for the selected section only, using an approved block type; page context is sent to the installation's configured provider so the result fits the surrounding content; requires authenticated staff with `content.manage`, enabled `ai_page_editor`, CSRF, and rate limit of 5 requests/minute; validates that the result preserves the target section contract; response does not persist, and the staff member must review and accept or reject it |
| `PATCH /staff/features/{key}` | `{ "state": "disabled" | "staff_preview" }` | `200` feature; requires `features.manage`; `404` unknown key; `409` for `enabled` until that module's release gate passes; audited |
| `GET /staff/services` | Active session with `services.manage` | Recent service occurrences; requires `services.manage` |
| `POST /staff/services` | `title`, `timezone`, `starts_at`, optional `location_id`, `manual_stream_override` | `201` service occurrence; requires `services.manage` |
| `PATCH /staff/services/schedules/{id}` | Weekly timetable fields: title, experience type, timezone, location, day, start time, duration, and active state | Updates the recurring schedule and regenerates future scheduled occurrences; requires `services.manage` |
| `DELETE /staff/services/schedules/{id}` | None | Removes the timetable entry and its future planned occurrences while retaining historical occurrences; requires `services.manage` |
| `POST /staff/services/{id}/transition` | `{ "state": "scheduled" | "ready" | "live" | "interrupted" | "ended" | "cancelled" }` | `200` updated occurrence; rejects an invalid lifecycle transition or a live transition without stream confirmation; audited |
| `GET /staff/invitations` | Active session | Pending invitations without tokens; requires `roles.manage` |
| `POST /staff/invitations` | `{ "name": "...", "email": "..." }` | `201` with invitation and one-time acceptance URL; requires `roles.manage`; grants only `site_editor`; 24-hour expiry; audited |
| `DELETE /staff/invitations/{id}` | Active session | `204` revokes a pending invitation; requires `roles.manage`; audited |
| `POST /auth/invitations/accept` | `{ "token": "...", "password": "..." }` | `201` account created; `422` invalid/expired/reused token or password; throttled. Does not sign in automatically. |
| `GET /auth/sessions` | Active session | Own sessions with keyed opaque handle, last activity, IP and current marker; no payload or cookie value |
| `DELETE /auth/sessions/{id}` | Active session | `204` removes one of the caller's sessions; `404` for another user's/unknown session; current session removal also logs out |
| `DELETE /auth/sessions` | Active session | `204` revokes all caller sessions including the current one; audited; other users unaffected |
| `GET /staff/pages` | Active session with `content.manage` | All page drafts and publication states for the editorial workspace |
| `POST /staff/pages` | Title, address, validated validated ordered visual block document, with unsupported or disabled module block types rejected, optional SEO fields | Browser form redirects to the new editor by default. Same-origin requests with `Accept: application/json` return `201 { message, data: { redirect, page_id } }`; address is normalized and reserved application routes are rejected; revision 1 and audit event are created |
| `PATCH /staff/pages/{id}` | Editable page fields and validated validated ordered visual block document, with unsupported or disabled module block types rejected | Browser form redirects with a status message by default. Same-origin requests with `Accept: application/json` return `200 { message, data: { page_id, revision, status } }`; creates the next immutable revision and withdraws any published version until reviewed again |
| `GET /staff/pages/{id}/revisions` | Active session with `content.manage` | Revision number, author ID, and creation time; newest first |
| `PATCH /staff/pages/{id}/review` | `status` (`published` or `rejected`), optional `review_notes` | Requires `content.publish`, verified email, and recent MFA; only a draft may be reviewed; audited |

Login and authorization never return password hashes, session tokens, or restricted care/finance data. Session cookie is HTTP-only; XSRF cookie is not a session credential. The public configuration endpoint remains accessible without a session.

All new staff mutations require the same CSRF header and an installation-scoped grant. Location names are unique through generated slugs; a location retains its slug after a rename. Switching primary locations is atomic and leaves exactly one primary. A private location never appears in `/public/site`. Feature preview stays staff-only. Disabled modules return 404 at their staff, member, public, and API route families, and their data is omitted from dashboards and summaries; module administration endpoints remain available to authorized administrators.

Invitation tokens are displayed only once to the inviting administrator for private delivery and are never emailed by the default log transport or written to audit logs. The token appears in the URL fragment, outside ordinary page requests, and is sent only in the acceptance POST. Accepting a link proves possession of the token, but email verification remains a separate Phase 0 control. Invitation and session mutation requests require CSRF protection. Public acceptance is rate-limited. Session records are retained only until expiry and never exposed to another user.

| Method/path | Purpose | Access |
| --- | --- | --- |
| `POST /auth/register`, `POST /auth/login`, `POST /auth/logout` | Self-service identity | Public/authenticated |
| `POST /auth/password/reset-request`, `POST /auth/password/reset` | Recovery | Public with anti-abuse |
| `GET /me`, `PATCH /me` | Own profile, including preferred name, contact permission, and optional birthday month/day | Authenticated |
| `GET /me/consents`, `POST /me/consents` | View/change purpose-specific consents | Authenticated |
| `GET /me/attendance`, `GET /me/tasks` | Own participation and commitments | Authenticated |
| `GET /me/donations` | Own confirmed, refunded, or reversed gifts with fund and receipt status; never exposes another donor | Authenticated donor |
| `GET/POST /me/prayer-requests` | List own requests or submit an encrypted private request with care audience and contact consent | Authenticated member |
| `POST /me/prayer-requests/{id}/removal-request` | Request removal of an own prayer request; another member receives 404 | Request owner |
| `GET/POST /me/journeys` | List own ministry journeys or request membership, baptism, new-member class, discipleship, or serving follow-up with explicit contact consent | Authenticated member |
| `POST /events/{id}/registrations` | Register self/authorized household member | Public or authenticated by event policy |

Self-service account creation never automatically grants ministry membership or staff privileges. A guest form may create a person record without a login. Linking a later account to a pre-existing record requires a verification/review process.

## 4. Services and live worship

| Method/path | Purpose | Access |
| --- | --- | --- |
| `GET /services/upcoming`, `GET /services/{id}` | Public schedule and service description | Public |
| `GET /services/{id}/watch` | Current player, notes, public actions, delayed-state metadata | Public unless private |
| `GET /public/services/{id}` | Current public worship-room state and media fallback descriptor | Public when Digital Church is enabled and the service is live or interrupted |
| `POST /services/{id}/presence` | Identified join/heartbeat/leave, with dedupe token | Authenticated attendee |
| `POST /services/{id}/responses` | Newcomer, prayer, decision, or configurable next action | Public with anti-spam |
| `POST /public/services/{id}/playback-diagnostics` | Idempotent anonymous room, player, stall, recovery, failure, heartbeat, or end signal with bounded timings | Public only while a non-rehearsal service is live or interrupted; throttled |
| `GET /services/{id}/chat`, `POST /services/{id}/chat` | Public chat, cursor list and post | By service chat policy |
| `POST /services/{id}/chat/{message_id}/moderate` | Hide/delete/mute action with reason | Host/moderator |
| `GET /staff/services/{id}/chat/moderation` | Recent messages, chat settings, and restricted moderation incident history | Host/producer for assigned service |
| `PATCH /staff/services/{id}/chat/settings` | Pause/resume chat and set zero to 300 second slow mode with optional incident reason | Host/producer; recorded in moderation history |
| `POST /staff/services/{id}/chat/{message_id}/mute` | Block the signed-in user or hashed service-scoped guest identity behind a message, temporarily or pending review | Host/producer; reason required |
| `GET /services/{id}/polls`, `POST /services/{id}/polls/{poll_id}/responses` | Visible polls and answers | By poll policy |
| `POST /staff/services`, `PATCH /staff/services/{id}` | Plan and edit occurrence | Producer |
| `POST /staff/services/{id}/prepare` | Validate ready state and preflight | Producer |
| `POST /staff/services/{id}/start`, `/interrupt`, `/resume`, `/end`, `/cancel` | Lifecycle transitions | Producer with idempotency key |
| `GET /staff/services/{id}/health` | Ingest, playback, chat, and queue health | Producer/host |
| `POST /staff/services/{id}/prompts/{prompt_id}/fire` | Deliver a prompt in service context | Host/producer |
| `GET /staff/services/{id}/stream` | Playback configuration, current ingest/playback health, and recent stream events | Producer |
| `PUT /staff/services/{id}/stream` | Configure a ministry-owned YouTube, Vimeo, or HTTPS HLS public playback source, optional lower-bandwidth rendition, captions, and outage message | Producer; audited event history |
| `PATCH /staff/services/{id}/stream/health` | Record ingest and viewer-playback health independently with an optional operator reason | Producer; an offline live stream moves the service to interrupted |
| `POST /staff/services/{id}/stream/probe` | Fetch the configured public playback target with bounded timeouts and record status, HTTP result, and latency | Producer; private and reserved network targets are rejected |
| `GET /staff/services/{id}/playback-diagnostics` | Six-hour operational aggregate of recent device sessions, starts, stalls, recoveries, failures, quality choices, and average startup | Producer; never returns raw session hashes or identified attendance |
| `GET /staff/services/{id}/capacity-rehearsals` | Service capacity rehearsal history and criterion results | `services.manage` |
| `POST /staff/services/{id}/capacity-rehearsals` | Expected peak, headroom, geography, provider plan, and performance targets | `201`; required test load is calculated by the server |
| `POST /staff/services/{id}/capacity-rehearsals/{rehearsal}/start` | No body | Planned to running only; `services.manage` |
| `POST /staff/services/{id}/capacity-rehearsals/{rehearsal}/complete` | Actual peak and measured startup, rebuffer, error, and chat latency values; optional evidence URL and notes | Server returns `passed` only when tested load and every threshold pass |
| `GET /staff/services/{id}/stream/regional-probes` | Configured region labels, declared provider capabilities, and twelve recent probe runs | `services.manage`; excludes probe tokens and endpoint URLs |
| `POST /staff/services/{id}/stream/regional-probes` | No body | Runs the origin plus installation-configured regional probes and returns a persisted result; `201` |
| `POST /webhooks/livestream/{provider}` | Receive provider-normalized ingest and playback health | HMAC signed installation webhook; provider event ID is idempotent |

Only the server can transition service state. Every transition includes actor, previous/new state, time, and reason when interrupted or cancelled. State conflicts return 409 and the current state. `watch` must remain useful if chat is unavailable.

Guest chat posts require a stable random service-scoped key. The server stores only a SHA-512 hash and never returns it. Pause and slow mode do not prevent authorized hosts from posting service updates. Removed messages disappear from the public list; removal and participant mutes require a reason and create restricted moderation events. A changed display name does not bypass a mute because enforcement uses the signed-in account or hashed guest key.

Playback diagnostics use a separate browser-session key hashed with the installation secret. Public clients generate an event UUID so retry delivery is safe. Embedded players report load visibility only; native HLS may additionally report start, stall, recovery, failure, and timing signals. The API stores no IP address or user link for these events. `active_recently` means a session reported an event within 45 seconds and must not be labeled as a person, member, or confirmed attendee.

A service may enter `live` when its configured stream reports healthy ingest and healthy or degraded playback, or when an authorized producer records an explicit manual override. The public playback descriptor exposes only a canonical allowlisted embed URL or HTTPS HLS URL, optional lower-bandwidth URL, and captions URL. It never exposes stream keys, provider credentials, raw webhook data, or internal provider configuration. Anonymous playback does not create identified attendance.

Livestream webhooks use the raw request body and `X-Ministry-Signature: sha256=<hex HMAC>` with the installation's `LIVESTREAM_WEBHOOK_SECRET`. The normalized body contains `event_id`, `service_occurrence_id`, separate `ingest_health` and `playback_health`, and an optional reason. Replayed event IDs return a successful duplicate result without changing health twice. Invalid signatures return `401`; missing installation configuration returns `503`. Provider-specific adapters must translate their native payload to this contract only after validating the provider's own delivery requirements.

## 5. Interactive meetings and media

| Method/path | Purpose | Access |
| --- | --- | --- |
| `GET /meetings/{id}` | Meeting details visible to caller | Invitation/registration policy |
| `POST /meetings/{id}/join-token` | Short-lived room-scoped media grant | Authorized participant |
| `POST /staff/meetings`, `PATCH /staff/meetings/{id}` | Create/edit meeting policy | Assigned leader |
| `POST /staff/meetings/{id}/recording/start`, `/stop` | Recording control with notice | Authorized host |
| `POST /webhooks/media/{adapter}` | Media lifecycle events | Verified provider only |
| `POST /staff/media/{asset_id}/approve`, `/reject` | Review recording | Content approver |

Join tokens must include room, identity, grants, expiry, and nonce. A token for one room cannot open another. Provider webhook payloads are stored as receipts, signature-checked, deduplicated, and processed asynchronously.

## 6. People, care, community, and giving

| Method/path | Purpose | Access |
| --- | --- | --- |
| `GET /staff/people`, `POST /staff/people`, `GET/PATCH /staff/people/{id}` | Scoped people directory | People staff |
| `GET /staff/departments`, `GET /staff/departments/{id}` | Department workspace and roster | Ministry administrator or assigned department coordinator/executive |
| `POST /staff/departments` | Create a department | Ministry administrator |
| `POST /staff/departments/{id}/members` | Add a person or change their team role | Assigned coordinator/executive for ordinary members; ministry administrator for coordinator/executive appointments |
| `DELETE /staff/departments/{id}/members/{membership}` | Remove a roster membership | Assigned coordinator/executive for ordinary members; ministry administrator for coordinator/executive removal |
| `POST /staff/people/{id}/merge` | Reviewed duplicate merge | Authorized manager |
| `GET /staff/attendance`, `POST /staff/attendance/corrections` | Review and correct attendance | Scoped leader/staff |
| `GET /staff/follow-ups`, `POST /staff/follow-ups`, `PATCH /staff/follow-ups/{id}` | Assign and complete tasks | Assigned team |
| `GET /staff/care-cases/{id}`, `POST /staff/care-cases/{id}/notes` | Restricted care | Assigned care staff |
| `GET /groups`, `POST /groups/{id}/join`, `GET /me/groups` | Group discovery and membership | Per group policy |
| `GET /staff/groups`, `POST /staff/groups`, `POST /staff/groups/{id}/attendance` | Group operations | Scoped group leader |
| `GET /staff/volunteers`, `POST /staff/volunteer-assignments` | Serving schedules | Scoped team leader |
| `POST /donations/intents` | Begin a donation with configured provider | Public/member by provider policy |
| `POST /webhooks/payments/{adapter}` | Provider event intake | Verified provider only |
| `GET /staff/giving`, `POST /staff/giving/reconcile` | Finance reporting/reconciliation | Finance permission |

`GET /staff/people/{id}` does not include care notes or donation amounts by default. Those are separate resources with separate authorization. The attendance correction endpoint appends a correction event; it does not silently rewrite the original observation.

## 7. Administration and operations

| Method/path | Purpose | Access |
| --- | --- | --- |
| `GET/PATCH /staff/settings` | Ministry settings and branding | Administrator |
| `GET/PATCH /staff/features` | Module settings | Administrator |
| `GET/POST/PATCH /staff/roles` | Permission bundles and assignments | Security administrator |
| `GET /staff/audit` | Filtered audit events | Authorized auditor |
| `POST /staff/exports`, `GET /staff/exports/{id}` | Scoped data export | Explicit export permission |
| `GET /staff/system/health` | Dependency and job health | Operator |
| `GET /staff/reports/{report}` | Defined report with filters and measure definitions | Scoped report permission |
| `GET /staff/services/{service}/responses` | Service-scoped connection responses and prayer request count; prayer body withheld | `services.manage` |
| `GET/POST/PATCH/DELETE /staff/services/{service}/run-sheet` | Ordered service segments and host status controls | `services.manage` |
| `GET/POST /staff/services/{service}/notes` | Internal service notes | `services.manage` |
| `GET/POST /staff/services/{service}/prompts`, `POST .../{prompt}/fire` | Timed prompts and explicit firing | `services.manage` |

Changing a sensitive configuration requires audit detail. Provider secret values are write-only in administration views.

## 8. Realtime channels and domain events

| Channel/event | Payload minimum | Audience |
| --- | --- | --- |
| `service.{id}.state_changed` | ID, state, version, server timestamp, public health | Public service room |
| `service.{id}.chat_message` | Message ID, display name, text, host badge, time | Authorized room |
| `service.{id}.chat_moderated` | Message ID, action, time | Authorized room |
| `service.{id}.prompt_fired` | Prompt ID, action descriptor, time, playback marker if present | Authorized room |
| `service.{id}.poll_changed` | Poll ID, state, public totals if allowed | Authorized room |
| `staff.service.{id}.health_changed` | Component status and version | Service team |
| `staff.follow_up.assigned` | Task ID and due time, no confidential body | Assignee |

All events have `event_id`, `schema_version`, `occurred_at`, and monotonic per-aggregate version where state ordering matters. Clients refetch authoritative state after reconnect. Never broadcast care content, child records, donation details, or provider secrets on public channels.

## 9. Contract verification

For every endpoint, define OpenAPI schema, example success/error bodies, authentication, role/scope policy, idempotency, rate limit, and privacy class. Validate polling or integration clients against the same schema. Test 401/403/404 boundaries, stale state transitions, duplicate webhooks, and module-disabled responses.


## Public navigation manager

## Interactive meeting contract

| Route | Purpose | Access and response |
| --- | --- | --- |
| `POST /meetings/{id}/join-grant` | Request admission to an interactive Zoom Video SDK meeting | Authenticated user only; returns `waiting` while host admission is pending, or a short-lived SDK JWT, session name, and passcode after admission. Never returns an SDK secret. Rate-limited. |
| `GET /meetings/{id}/waiting-status` | Refresh the caller's lobby state | Authenticated meeting participant only; returns meeting and admission states, never other participants. Rate-limited. |
| `GET /staff/meetings/{id}/lobby` | List pending admission requests | Active host/moderator assigned to this meeting, verified email, and recent MFA. Returns only participant ID, display name, and request time. |
| `POST /staff/meetings/{id}/lobby/{participant}` | Admit or decline one waiting participant | Same host checks as lobby; body is `{ "decision": "admit|decline" }`. The waiting state is changed once, audited, and stale decisions fail. |
| `POST /api/v1/staff/meetings` | Create a Zoom Video SDK meeting definition and initial host assignment | `services.manage`, verified email, MFA; lifecycle/policy validation and audit required. |
| `POST /api/v1/staff/meetings/{id}/tokens` | Host requests a Zoom join grant | `services.manage`, verified email, MFA; grant is short-lived and host role is derived server-side. |

Meeting grants return a normal `200` response with `waiting` while a host must first admit the participant. The browser polls only its own waiting state and receives no media grant until admission. Other errors use `422 meeting_not_open` for a closed lifecycle and `403 invitation_required` for an invitation-only room without an assignment. JWTs are generated on the Laravel server with the encrypted Zoom Video SDK secret and are never persisted or logged. Grant issuance and waiting-room presence are not attendance; provider-confirmed admitted participation remains a separate pending integration.

Live-service responses include an `experience` value: `broadcast`, `meeting`, `hybrid`, or `in_person`. `broadcast` is the public player route; `meeting` is the authenticated Zoom room route; `hybrid` keeps broadcast as the default and exposes an explicit interactive-room action when a linked room exists.

Public navigation is ministry-owned and stored as ordered `navigation_items`. Each link has a label, internal or external URL, optional approved icon, controlled sidebar section (`Main`, `Connect`, `Resources`, `About`, or `Member area`), parent link for a dropdown, a ministry-controlled `is_enabled` menu-selection flag, device visibility, optional new-tab behavior, and an optional required feature. Disabled features and unselected links are omitted from public rendering; unselected links remain available in Staff Navigation. The staff navigation reorder contract accepts ordered `items[]`, selected link IDs in `enabled[]`, and optional `sections[id]` and `icons[id]` values; values are validated against the approved link, section, and icon lists and saved atomically with the order. Navigation changes are audited.

`GET /staff/navigation` is available to authenticated staff with `content.manage` and provides the public link editor plus staff/member menu choices. `PUT /staff/navigation/layouts` accepts destination keys and approved icon names for each staff/member sidebar and mobile footer, plus a registered section assignment for each sidebar item. Mobile footer lists are limited to five entries; sidebars are limited to 24. Keys must resolve to built-in destinations or published pages. Saves are audited. At render time staff permissions, installed modules, member route guards, and public page publication state remain authoritative even when a menu item is selected.
# Digital school web contracts

Staff school routes require authentication, the `school.manage` permission, and an enabled `digital_school` module. They cover course configuration, curriculum authoring, assessment authoring, live-class scheduling, learner invitation, and enrollment moderation. Member school routes require authentication and an enabled module. Course access is limited to published courses; learning actions require an active enrollment belonging to the current member. Certificate retrieval requires a non-revoked certificate belonging to that member's completed enrollment.

