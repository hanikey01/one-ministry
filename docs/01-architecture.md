# 01 — Architecture

**Status:** Proposed implementation baseline. The product contract is `00-product-master.md`; capacity and provider choices require validation.

## 1. Deployment model

One installation serves one ministry. It can model many locations, teams, and online campuses. Installations do not share application databases, account identities, secrets, or media buckets. A ministry may operate staging and production instances.

The distributable package contains application code, database migrations, seed defaults, deployment examples, setup checks, backup/restore tools, and administrator documentation. It requires a domain with TLS, a server or hosting environment, database, and configured providers. Optional managed providers do not change ownership of the ministry's application data.

## 2. Logical components

| Component | Proposed technology | Responsibility |
| --- | --- | --- |
| Web and application | Laravel Blade, Livewire, Alpine.js, Tailwind CSS | Public pages, member dashboard, staff console, worship UI, domain rules, and provider adapters |
| Scheduler | Laravel scheduler through host cron | Reminders, birthdays, follow-up routing, cleanup, callbacks, and exports |
| Primary store | MySQL/MariaDB baseline; PostgreSQL supported | Durable ministry and application records |
| Queue/cache | Database queue and cache baseline | Redis queue/cache is optional; never sole storage for canonical attendance |
| App updates | HTTP polling and authoritative refresh baseline | Reverb or compatible broadcaster is optional for chat, service state, and notifications |
| Interactive media | Zoom Video SDK | Small meetings, production rooms, camera/microphone participation |
| Broadcast origin and distribution | Media pipeline plus HLS and optional CDN | Large read-mostly online services |
| Media storage | Protected local storage where safe, or S3-compatible object storage | Images, audio, recordings, exports, restricted documents |
| Reverse proxy | TLS-capable proxy | Routing, headers, rate limiting, upload limits |

Do not insert a general-purpose AI service into the first release. The bounded AI page-editor exception in `09-decisions.md` permits staff to draft public pages through installation-configured Gemini or OpenRouter adapters. Laravel owns the provider boundary, encrypted per-installation credentials, validation, routing, and audit; no separate AI service or central vendor-controlled account is required. Full-page planning receives the staff brief and optional public details. A selected-section revision receives the current public page draft for context and can return copy only for the selected block. The assistant cannot query pastoral-care, giving, attendance, or children's records. It can create drafts only; the existing publisher review remains mandatory. This exception does not authorize other AI features.

## 3. Media architecture

### 3.1 Large online service

Production camera or software encoder → ingest endpoint → transcoding and packaging → HLS origin → CDN or equivalent elastic distribution → public player. Chat, prayer, polls, and next actions travel through the application realtime path, keyed to the same `service_occurrence_id`. The player must tolerate a stream delay: timed prompts can be scheduled against the viewer's playback position or manually fired by a host, not only the wall clock.

The platform does not set a fixed viewer count. **Each deployment still has finite bandwidth, origin capacity, provider quotas, and budget.** The operations guide requires the ministry to declare an expected peak, choose a distribution profile, test at that load, and monitor headroom. A direct peer-to-peer or room-per-viewer conference is not the default for a large service.

### 3.2 Interactive meeting

Browser ↔ media server for participant tracks. Core API issues short-lived, room-scoped credentials after checking authorization. A meeting has host, moderator, speaker, and participant grants. Waiting room, recording permission, and invitation policy are meeting settings. TURN, TLS, UDP reachability, and regional latency are deployment concerns. Each installation configures its own Zoom Video SDK account; Laravel issues scoped, short-lived SDK grants after authorization.

### 3.3 Recordings

Recording is independently controlled per service or meeting. Video platform callback → verify signature or shared secret → durable media asset in processing state → staff review → publication. Recording start/stop and consent notice are visible to participants. Failed processing leaves an actionable state and does not publish partial media automatically.

## 4. Application boundaries

- Blade, Livewire, and Alpine render and collect input. They do not make independent authorization decisions or hold provider secrets.
- Core API owns business rules and the database. No browser client writes database tables directly.
- Workers execute jobs idempotently. They consume durable job metadata and can be retried safely.
- Provider adapters isolate mail, SMS, payment, object storage, and media vendors. Domain modules call adapter contracts, not vendor SDKs directly.
- Realtime channels use server-issued authorization. Private prayer and staff channels are never inferred from a public service room token.
- Integrations receive the least data required. Provider credentials stay server-side.

## 5. Data flow and consistency

Use database transactions for related durable changes. Emit domain events through an outbox or equivalent durable handoff when a change must trigger jobs or realtime notifications. Replayed webhooks and repeated jobs must be idempotent by provider event ID or domain key. Preserve an immutable audit trail for sensitive reads and changes.

Service state has one authoritative record in PostgreSQL. Redis may cache and broadcast it, but restarting Redis cannot cause a scheduled service to become live or lose its ended state. The homepage obtains the current state through the API and subscribes to changes; it rechecks state on reconnect.

## 6. Security boundaries

| Boundary | Requirement |
| --- | --- |
| Public → API | Rate limits, validation, spam controls, request size limits |
| Member → own data | Session authentication and object-level authorization |
| Staff → ministry data | Permission plus assigned location/group/case scope; audit sensitive access |
| API → media | Short-lived signed room/stream grants, verified callbacks |
| API → providers | Per-installation credentials, restricted scopes, secret rotation |
| Database/storage | Encryption in transit, private network access, encrypted backups, restricted buckets |

Role design and privacy classification are owned by `02-database.md` and `05-systems.md`. Do not grant a general administrator implicit access to restricted pastoral notes or giving details.

## 7. Deployment profiles

| Profile | Intended use | Media expectation |
| --- | --- | --- |
| Development | Local feature work | Synthetic or small test meetings and streams |
| Small production | One ministry with modest attendance | Single app stack and separately sized media origin/distribution |
| Growing production | Multiple locations and higher attendance | Separate web/API/worker capacity, shared cache, CDN, monitored media path |
| Large event | Exceptional viewership | Pre-event capacity plan, load test, provider quota confirmation, incident staffing |

These are profiles, not product editions or hard attendance tiers. The same code runs in all profiles.

## 8. Failure behavior

- If broadcast ingest fails, show the last known service information and a retry/fallback message. Do not falsely report a healthy live stream.
- If realtime chat fails, video remains watchable; forms use an HTTP fallback where possible.
- If media fails, the public site, contact forms, and published content remain available.
- If email or SMS fails, store retryable delivery status and alert operators. Do not discard the underlying follow-up task.
- If a payment provider times out, keep the gift pending until verified by webhook or reconciliation. Never infer success from browser redirect alone.
- On database outage, show an appropriate read-only or service-unavailable state; do not fabricate attendance or donation acknowledgements.

## 9. Environment and configuration

Minimum configuration: public URL, database DSN, encryption/session keys, writable application storage, mail provider, host cron, and a media profile. SMS, payment, object storage, Redis, realtime, and meeting providers are optional until their modules are enabled. Secrets are read from the environment or a secret manager, not stored in Git or exposed to the browser. Setup checks validate TLS, storage permissions, cron/scheduler activity, callback reachability where configured, queue health, and backup destination before declaring the installation production-ready.

## 10. Validation before architecture freeze

Proof of concept must demonstrate: a broadcast with dynamic homepage switching; a small interactive meeting; a signed private room join; recording review; authenticated chat moderation; reconnect after Redis or browser interruption; and a load test against a declared audience target. Decide exact media components and provider adapters only after this proof. Record outcomes in `09-decisions.md`.

