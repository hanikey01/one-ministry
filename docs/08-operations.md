# 08 — Installation and Operations

**Status:** Operational specification. Commands and exact environment variables will be added alongside runnable code.

## 1. Installation package

Publish a versioned release with source or images, Compose examples, environment template, migration tool, administrator setup flow, health check, sample content, backup and restore tools, and release notes. An installation belongs to one ministry. Each ministry supplies its own domain, TLS, secrets, storage, and provider accounts. The system must be usable without a vendor-run control plane.

## 2. Minimum production prerequisites

- Domain and trusted TLS for web, API, media, and callbacks.
- Host resources sized for web/API/worker/database and a separately assessed media path.
- PostgreSQL and Redis with persistent or appropriate managed configuration.
- Private S3-compatible storage or a documented equivalent, plus a backup destination separate from the application host.
- Email provider for account and operational messages. SMS and payment credentials are required only if enabled.
- A working broadcast/meeting media configuration appropriate to enabled features.
- Named administrator and operator, documented incident contact, and time zone.

Setup validation must report missing prerequisites and prevent a feature from being enabled with an invalid provider. Test mail, object storage, callback URLs, queue, scheduler, live playback, meeting join, and restore before public launch.

Staff password recovery requires a configured delivery mail transport and a public `APP_URL` that reaches the web app. The default `MAIL_MAILER=log` intentionally returns `503` on recovery requests, so tokens are never written to logs. Configure and test outbound mail before relying on recovery. A successful reset revokes the account's database sessions; the operator can review `auth.password_reset` audit events without seeing token or password values.

The staff Email workspace owns the installation’s active email delivery connection and editable message templates. It supports the hosting sendmail service and common SMTP providers; provider credentials are encrypted at rest and cannot be read back from the screen. Set a verified sender address, save and activate the provider, then use **Send test email** and confirm receipt before relying on invitations, recovery, or elevated staff actions. Hosting sendmail must be tested on the actual host because container and shared-host packages do not imply an available local mail transfer agent. An inactive, incomplete, or failed connection prevents delivery-dependent actions from retaining a usable verification or recovery token.

### AI page editor provider setup

**2026-11-13 operational amendment:** A primary passing the representative composition check can operate while the route is `degraded` because a fallback failed. Inspect the returned per-model failure code, repair that provider/model, then rerun compatibility. Catalog metadata records the last probe version, timestamp, and result. Gemini discovery lists candidates without asserting structured-output support; a successful probe supplies that evidence. HTTP failures expose a stable diagnostic code and status without raw provider text. Generation logs retain the trace ID, provider, model, stage, failure code, HTTP status, and allowlisted provider status. `provider_schema_rejected` requires inspecting the adapter/schema contract; `provider_invalid_key` requires credential replacement; rate limits and connection/timeout failures require checking provider quotas or outbound HTTPS. Do not request prompts or credentials in support logs. The older all-models-must-pass instructions below are superseded by D-043.

AI drafting is optional and remains disabled until the installation administrator enables `ai_page_editor` (or exposes it to authorized staff in preview). Configure the ministry-owned Gemini API key, OpenRouter API key, or both from the protected AI settings page. Secrets are encrypted with the installation key, shown only as configured/unconfigured state, and replaced by submitting a new key. Refresh each enabled provider's model catalog, select a structured-output capable primary model and ordered fallbacks for page drafting, then run the compatibility test. The route remains inactive until all selected models pass. The scheduler refreshes enabled model catalogs daily at 04:10 installation time; confirm the Laravel scheduler cron is running. A removed or incompatible selected model invalidates the route and creates an audit entry; staff must choose and test a replacement. Rotate keys at the provider first, replace them in Ministry, refresh the catalog, and rerun validation. Provider requests use bounded timeouts and sanitized errors; logs and audit records must not include keys, prompts, or generated content. Staff review a full-page or selected section/column/element proposal before explicitly applying it. Scoped requests send the complete public draft for context but return and apply only the selected object. The server validates the complete resulting document and strips invented media URLs or destinations. Nothing is saved until staff submit the normal page draft form; publication still requires the usual publisher review.

## 3. Deployment and upgrade

Maintain development, staging, and production environments. CI runs migrations against a clean database, tests, static checks, web build, and container build. Deploy to staging with representative configuration, run smoke tests, then perform explicit production promotion. Take a verified backup before migrations. Apply additive schema changes first; deploy code compatible with old and new fields during a transition; backfill; remove old fields only in a later release.

Version all container images and configuration schemas. Release notes state migrations, new secrets, provider changes, breaking API changes, and required operator steps. Rolling back code is allowed only when database compatibility has been checked. A failed migration or media adapter change has a written recovery path.

### PHP security updates

The API, queue worker, and scheduler use the PHP-FPM application image built from `services/api/Dockerfile`. Keep its PHP patch release explicit and update it promptly when the PHP project publishes security fixes; the PHP version constraint in `composer.json` does not update the PHP runtime. For the October 2026 critical PHP fixes, the image uses PHP 8.5.12 on Alpine 3.24. Rebuild with `docker compose build --pull api`, recreate `api`, `worker`, and `scheduler` from the same image, and verify `php -v` in each service before reopening production traffic. Recreating these containers does not remove the database or Redis volumes. Do not treat restarting containers built from an older local image as patching PHP.

## 4. Capacity planning for any audience size

The application imposes no fixed cap on live viewers. An individual installation is bounded by its infrastructure and provider contracts. Before a service, declare expected peak concurrent viewers, geography, acceptable startup delay, latency, and budget. Use an elastic broadcast distribution path for read-mostly viewers; size interactive rooms independently. Confirm origin and CDN/provider quotas and have headroom for spikes.

Load tests must measure playback success, start time, rebuffering, error rate, chat latency, API rate, database pressure, and cost at declared peak plus agreed headroom. Record test date, configuration, provider limits, and observed maximum. Never advertise an untested number as a guarantee. For unusually large events, schedule a rehearsal and on-call operator.

## 5. Monitoring and alerting

Monitor web/API availability, database connectivity, Redis/queue depth, scheduled-job delay, service state, media ingest health, HLS playback probes, meeting connection failures, object storage, webhook verification errors, message failures, and payment reconciliation gaps. Alerts must route to a named operator with severity and runbook. Dashboards show current service occurrence and distinguish source ingest failure from viewer playback failure.

Do not put prayer text, counselling notes, access tokens, payment details, or child data in logs. Operational metrics use aggregate identifiers where possible.

Homepage media belongs to the ministry installation. Before publishing a slide, operators verify that uploaded or externally hosted media has a stable HTTPS delivery URL, usage rights, descriptive alternative text where applicable, and suitable compression for mobile networks. Provider credentials and upload paths are never emitted in the public page.

## 6. Backup, restore, and export

Back up database and media metadata, media objects, and versioned configuration. Encrypt backups; restrict access; keep a copy outside the primary host. Document schedule, retention, recovery point objective, and recovery time objective per installation. Restore into an isolated test environment regularly and verify counts, referential integrity, sample media, administrator login, and provider secrets reconfiguration. A backup that has not been restored is unverified.

User-facing exports are different from disaster-recovery backups. Exports honor permissions and separate restricted care, child, finance, and security classes. Export downloads expire. Record requester, scope, reason, and completion.

### 6.1 Phase 0 local operator commands

`docker compose exec api php artisan ministry:doctor --profile=local` checks application key, database, migrations, ministry setup, Redis, sessions, and writable storage. `--profile=production` additionally fails on non-HTTPS public URL, log mail transport, and missing private object storage configuration. `--json` emits a machine-readable report, and `--strict` exits nonzero for warnings as well as failures. The doctor reads configuration and dependency state; it does not mutate records or send mail.

Set `FILESYSTEM_DISK=s3`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, and `AWS_BUCKET`; `AWS_ENDPOINT` and `AWS_USE_PATH_STYLE_ENDPOINT=true` support a self-hosted S3-compatible provider. The application configures this disk as private. After configuration, run `docker compose exec api php artisan ministry:storage-preflight --json`. The command writes, reads, and deletes one randomly named object under `preflight/`; it fails without a complete configuration or the required private object permissions. Do not run it against an untrusted endpoint.

`docker compose --profile ops run --rm backup backup` writes an encrypted PostgreSQL custom-format dump and SHA-256 sidecar under the local `backups/` directory. Create a strong passphrase in the Git-ignored `secrets/backup_passphrase` file before invoking it; protect this file and keep a separate recovery copy. `docker compose --profile ops run --rm backup restore-verify <backup filename>` validates the checksum, restores into a new isolated `ministry_restore_*` database, checks core table counts, and leaves that database for operator inspection. It never restores over `ministry` or a pre-existing database. The backup container reads the application database but has no API or browser access.

This Phase 0 archive covers PostgreSQL only. No media object store is configured yet; once media storage exists, its objects and manifest must be included and verified before the backup tool can satisfy the complete release gate. Copy encrypted archives and the sidecar to an off-host destination, keep the passphrase separately, and test a restore regularly. The local `backups/` directory is ignored by Git and is not a safe sole backup destination.

### 6.2 Shared-hosting baseline

The shared-hosting release runs one Laravel application from its `public/` directory. The host must provide PHP 8.4.1 or later with the extensions reported by `composer check-platform-reqs`, a supported MySQL/MariaDB or PostgreSQL database, HTTPS, writable Laravel storage/cache directories, and a cron facility. Node.js, Docker, Redis, a persistent worker, and a self-hosted realtime service are not baseline requirements.

Install dependencies during packaging or through the host's supported Composer workflow, set environment values outside the web root, point the domain document root to `public/`, run migrations, set writable storage permissions, and complete first-run setup. This document is the canonical owner for cPanel, Plesk, DirectAdmin, generic PHP host, cron, queue, upgrade, rollback, and restore procedures.

Schedule this cron job once per minute using the PHP binary selected by the host:

```text
* * * * * /path/to/php /path/to/application/artisan schedule:run >> /dev/null 2>&1
```

The baseline queue driver is database-backed. Optional queued work may run through a host cron command on hosts that cannot keep a worker alive; short, non-sensitive work may use documented synchronous fallback. The system-health page must show stale scheduler activity, failed jobs, mail configuration, writable storage, and missing optional providers. Do not place `.env`, backups, or private uploaded files in a public web directory.

Run `php artisan ministry:doctor --profile=shared --json` after configuration. This profile accepts database or synchronous queue/cache fallbacks and does not require Redis. It verifies a recent scheduler heartbeat written by `ministry:record-scheduler-heartbeat`, which is scheduled every minute. Use `--strict` before release to turn unresolved shared-host warnings into failures.

### 6.3 Source-mounted local development

Use `compose.dev.yaml` together with `compose.yaml` for rapid local work. The override mounts `services/api` into the PHP container, starts a separate Node/Vite watcher, and exposes Vite on port 5173. Blade templates, PHP controllers, routes, and migrations use the mounted source immediately; refresh the browser after a change. CSS and JavaScript changes hot-reload through Vite.

Start the local development profile without rebuilding the API image:

```text
docker compose -f compose.yaml -f compose.dev.yaml up -d --force-recreate api proxy vite
```

For a phone or another device on the same Wi-Fi network, set `DEV_HOST` in the
Git-ignored `.env` file to the computer's current LAN IPv4 address and recreate
only the Vite service. This makes the Vite CSS, JavaScript, and HMR URLs point
to the reachable host instead of the phone's own `localhost`:

```text
DEV_HOST=192.168.x.x
DEV_PORT=8080
```

```text
docker compose -f compose.yaml -f compose.dev.yaml up -d --force-recreate vite
```

Use the matching `http://192.168.x.x:8080` address on the phone. This is a
development-preview setting only. A normal production asset build does not use
the Vite development server or a `localhost` asset URL.

Run `docker compose -f compose.yaml -f compose.dev.yaml exec api php artisan optimize:clear` after changing routes, configuration, cached views, or when a local page appears stale. Rebuild the API image only after changing the Dockerfile, Composer dependencies, PHP extensions, or the production asset build. The development override is local-only and must not be used for a production deployment.

### 6.4 Docker storage maintenance

Build cache is operational storage, not application data. Each installation should cap BuildKit cache and inspect Docker disk use on a routine schedule. The supplied Windows operator script runs `docker buildx prune --max-used-space 2GB` and removes only dangling images; it does not remove running containers, named volumes, database data, backup archives, or source files.

After rebuilding an application image, run the maintenance script (or its equivalent) before starting another rebuild. This removes superseded image layers and caps BuildKit cache while preserving images referenced by running containers and all named application volumes:

```powershell
docker image prune --all --force
./infra/maintenance/Invoke-DockerMaintenance.ps1
```

Do not delete the current image before its replacement container is running. Do not use `docker system prune --volumes` for routine rebuild cleanup because named database and Redis volumes contain application data.

Run it manually from the repository root:

```powershell
./infra/maintenance/Invoke-DockerMaintenance.ps1
```

Use a different cache budget only when the host has a documented capacity plan:

```powershell
./infra/maintenance/Invoke-DockerMaintenance.ps1 -MaxBuildCache 4GB
```

On Windows development or enhanced self-hosted installations, schedule a weekly task under the account that has Docker access:

```powershell
$project = 'C:\path\to\christian ministry'
$script = Join-Path $project 'infra\maintenance\Invoke-DockerMaintenance.ps1'
$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$script`""
$trigger = New-ScheduledTaskTrigger -Weekly -DaysOfWeek Sunday -At 3:00AM
Register-ScheduledTask -TaskName 'Ministry Platform Docker maintenance' -Action $action -Trigger $trigger -Description 'Caps BuildKit cache and removes dangling Docker images.'
```

The scheduled task requires Docker Desktop or Docker Engine to be running at its scheduled time. Operators review `docker system df` after upgrades or unusually large media-processing work. Do not add `docker system prune --volumes` to routine automation: named volumes contain PostgreSQL and Redis data.

## 7. Incident playbooks

| Incident | Immediate behavior | Operator action |
| --- | --- | --- |
| Broadcast ingest lost | Homepage and watch page show interrupted/fallback state | Check encoder, ingest, origin; resume or end explicitly; publish status |
| Playback distribution degraded | Keep chat/forms available where possible; show retry/backup path | Check CDN/origin, switch approved fallback, monitor playback probes |
| Chat abuse | Moderators can mute, delete, pause chat | Preserve moderation audit, review repeat abuse |
| Database unavailable | Avoid false confirmations | Restore service, reconcile queued/provider events, verify data |
| Payment callback failure | Gifts remain pending | Verify provider event, replay safely, reconcile, avoid duplicate receipt |
| Message provider outage | Tasks remain open, deliveries show failure | Switch configured adapter or retry after recovery |
| Credential compromise | Revoke affected sessions/keys | Rotate secrets, audit access, assess data exposure, follow local notification obligations |

The staff console must expose enough health and event history to follow these playbooks without direct database edits.

### Livestream operator procedure

Before a service, open its producer workspace, connect the ministry-owned YouTube Live, Vimeo Live, or HTTPS HLS playback URL, and verify the optional lower-bandwidth `.m3u8` and captions `.vtt` resources. Stream keys remain in the provider or encoder and must not be pasted into the platform. Run the producer playback check, then record separate ingest and viewer-playback checks; the service can enter live state after healthy ingest and usable playback, or after an authorized explicit manual override. The desk only offers valid next lifecycle states. If ingest or playback is recorded offline while live, the service moves to interrupted and the configured public fallback message appears while chat and response tools remain available according to service policy. Restore both checks, confirm playback from an external viewer connection, and explicitly resume live. The event history records configuration and health changes without provider secrets.

Set a strong, unique `LIVESTREAM_WEBHOOK_SECRET` in the installation environment before connecting a provider adapter. The adapter signs the exact normalized JSON body with HMAC SHA-256 and sends the result as `X-Ministry-Signature: sha256=<hex>`. Rotate the secret through a planned maintenance window because the initial contract accepts one active value. Provider event IDs must remain stable during retries so duplicate deliveries are acknowledged without reapplying state. Use the producer's playback probe before opening the room and after recovery; it uses bounded timeouts, rejects local and reserved network targets, and records the HTTP outcome and latency. A successful HTTP response confirms target reachability, while the producer still verifies actual video and audio from an external viewer connection.

For a chat incident, record the reason in the producer workspace, enable slow mode when message volume is the problem, or pause chat when the room needs immediate control. Hosts can still post an identified update while chat is paused. Remove content that should no longer be public and mute the participant behind a message when repeat posting must stop. Use the shortest appropriate mute duration and review indefinite blocks after the service. The incident history is restricted to authorized staff; guest keys are stored only as hashes and must not be copied into reports or logs.

Before declaring an expected broadcast audience, create a capacity rehearsal in the producer workspace. Record the peak audience, safety headroom, audience geography, provider tier, delivery settings, test regions, and performance thresholds. Run the rehearsal through the same CDN, chat path, and installation configuration intended for the service. Attach an external evidence URL when available and enter measured peak load, p95 startup, rebuffer rate, error rate, and p95 chat latency. The server calculates pass or fail and never treats a manually written note as proof. Repeat the rehearsal after material provider, infrastructure, geography, encoder, or application changes. Capacity remains limited to the tested conditions.

Anonymous playback diagnostics are retained for `PLAYBACK_DIAGNOSTIC_RETENTION_DAYS`, defaulting to seven days. The scheduler runs `php artisan ministry:prune-playback-diagnostics` daily at 03:20 installation time. Operators may run it manually with `--days=7 --json`; valid overrides are 1 through 365 days. Keep the scheduler container running and monitor scheduler delay.

Regional playback monitoring always checks from the installation origin. Add installation-owned or contracted probe agents through `LIVESTREAM_PROBE_REGIONS`, a JSON array of `key`, `label`, HTTPS `endpoint`, and secret `token`. Keys must be unique. The API sends a bearer-authenticated request containing the public playback target and playback type; the agent returns `status` (`healthy` or `offline`), optional HTTP status, and measured latency in milliseconds. Endpoints must resolve to public addresses. Tokens stay in installation environment configuration and are never returned by staff APIs or stored in probe results.

The scheduler runs `php artisan ministry:probe-live-streams` every five minutes for ready, live, and interrupted services. A manual run is available in the producer workspace. All healthy regions produce healthy playback, mixed results produce degraded playback, and all regions failing produce offline playback. Offline health follows the existing interruption rule for a live service. Operate remote agents independently in the claimed region, protect their tokens, restrict outbound targets to public HTTP media resources, monitor their availability, and test them before relying on regional claims. The included application defines the agent contract but does not claim that an unconfigured region was tested.

## 8. Security maintenance

### Release signing

The repository tag workflow signs release manifests with an RSA private key held in the `RELEASE_SIGNING_PRIVATE_KEY_PEM` Actions secret. Its matching `RELEASE_SIGNING_KEY_ID` variable is included in the manifest. Generate and store the private key outside the repository, restrict access to release maintainers, and keep a separately protected recovery copy. Do not paste private keys into issues, logs, workflows, or application settings. Key rotation requires publishing the new public key to the installation trust store before signing with it; the installation-side update client and public-key trust store are not yet implemented.

### Zoom Video SDK operation

Each ministry creates and pays for its own Zoom Video SDK account and enters its SDK key, SDK secret, and optional webhook secret in Staff settings. The application encrypts secrets at rest. Operators must never place a Zoom secret in a homepage field, browser configuration, source file, or support ticket. Keep interactive meetings disabled until a short browser proof of concept has joined a test room from two separate accounts.

The Laravel server signs a short-lived SDK JWT only after it has checked the authenticated user, room lifecycle, invitation policy, and lobby decision. Rotate a compromised SDK secret in Zoom first, update the encrypted setting immediately, and invalidate/re-admit active room participants as needed. Do not treat a Zoom join token or waiting-room event as attendance; use the configured identified-admission workflow. Configure Zoom webhooks only after signature verification and receipt deduplication have been deployed.

Patch dependencies and images on a documented cadence; prioritize security updates. Rotate provider and signing keys with overlap where needed. Restrict production database access, require multifactor authentication for elevated roles, and review staff assignments. Run vulnerability scans and a privacy/security review before a public release. Keep a contact path for vulnerability reports.

## 9. Ministry administrator handbook

Document everyday tasks separately from server operations: branding, service setup, going live, hosts and moderation, adding people, follow-up queues, content review, communication preferences, birthday settings, reports, role assignment, exports, and disabling a feature. Use screenshots or walkthroughs once the interface exists; do not invent them at specification stage.
