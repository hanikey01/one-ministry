# Professional Build Standard

**Applies to:** All implementation in this repository. Product-specific rules in documents `00`–`09` take precedence when explicit; otherwise follow this standard. Record any unresolved conflict in `09-decisions.md`.

## 1. Build for an independently operated product

Every production feature must be installable, configurable, observable, supportable, and upgradeable by a ministry that does not have the original developer present. Supply safe defaults and clear error messages. Avoid hidden dependencies on a developer's account, workstation, domain, or provider. Keep configuration in versioned examples and secrets outside source control.

## 2. Code boundaries

- Keep domain rules in testable application services or actions, separate from controllers and UI components.
- Controllers validate, authorize, orchestrate, and return contract resources. They do not contain substantial business logic.
- Blade and Livewire screens call application services or internal contracts and never duplicate server authorization rules as the sole enforcement. Typed HTTP contracts remain required for public, polling, webhook, and third-party integration consumers.
- Background jobs are idempotent, bounded, retryable, and report failure. No job silently drops an important request.
- Provider APIs are called through adapters with explicit timeout, retry, idempotency, webhook verification, and failure mapping.
- Database queries against scoped records always derive scope from authenticated server context, never only from a client-submitted identifier.
- Prefer the smallest architecture that meets the requirement. Add a new service only when its independent scaling, security boundary, or ownership is demonstrated.

## 3. Data and migrations

- Write schema definitions from `02-database.md` before implementing endpoints. Include nullability, uniqueness, check constraints, indexes, and explicit foreign-key deletion behavior.
- Use integer minor units for money, UTC timestamps plus IANA time zone for schedules, and stable internal IDs independent of providers.
- Production migrations use an expand/backfill/switch/contract approach. Avoid renames, drops, or long table locks in the same release as dependent code.
- Preserve immutable event history for payments, corrections, audit, and webhook receipts. Never replace an original observation with a corrected value without an append-only record.
- Test migrations on both an empty database and a copy of a representative older schema.

## 4. API and events

- Define request/response schema, authorization, privacy class, error codes, pagination, rate limit, and idempotency for each route before implementation.
- Validate all input at the boundary. Use stable error codes; do not leak stack traces or restricted resource existence.
- Make state transitions explicit and reject invalid transitions with current-state information.
- Version public contracts and event payloads. Consumers must tolerate additive fields and refetch authoritative state on reconnect.
- Process external webhooks only after signature verification and deduplication; acknowledge promptly, then handle durable work asynchronously.

## 5. Security and privacy

- Default deny. Check role/action, object relationship, and assigned scope for every sensitive request.
- Separate pastoral, child, finance, and security permissions. A broad staff role is not sufficient for private records.
- Use TLS, secure sessions, CSRF protection where applicable, strong password handling, multifactor authentication for elevated roles, and bounded login/recovery attempts.
- Use least-privilege service credentials, rotation procedures, encrypted backups, and no secrets in client bundles or logs.
- Collect minimal personal data. Define purpose, retention, export, and deletion behavior before storing a new sensitive field.
- Audit restricted reads, writes, exports, role changes, provider settings, attendance corrections, recording controls, and finance actions.
- Do not put private form bodies, prayer text, access tokens, card data, or child details in analytics or diagnostic logs.

## 6. Interface quality

- Follow `07-design.md` and target WCAG 2.2 AA. Support keyboard navigation, visible focus, semantic labels, reduced motion, captions where available, and understandable errors.
- Handle loading, empty, failure, forbidden, and stale-state cases. Do not show a success message before durable confirmation.
- Keep public pages fast on mobile and useful during media outages. Avoid blocking the whole page on video or chat initialization.
- Display definitions with attendance and participation metrics. Never imply that anonymous devices are identified people.

## 7. Testing standard

Test behavior at the boundary where it can fail: policy, consent, state transitions, idempotency, race conditions, migration upgrades, provider callbacks, and failure fallback. Test representative end-to-end journeys and operational recovery. Avoid tests that merely assert a function calls another function without verifying a product rule.

Each change must pass relevant lint, static analysis, unit and integration tests, and a production asset build. Media changes require browser/device validation and a targeted capacity or reconnect test. Security-sensitive changes require a negative authorization test. Shared-hosting changes also require supported database, cron, queue-fallback, writable-storage, and no-Node deployment checks.

## 8. Observability and operations

Every critical workflow has a traceable request/event ID and an operator-visible outcome. Emit structured metrics for failures, latency, queue age, and media health without sensitive payloads. Add runbook steps for new failure modes. Keep backup and restore scripts executable in CI or staging, and periodically restore them.

## 9. Definition of done

A feature is done when: its owning specification and API contract are current; code covers all affected layers; authorization and privacy rules are enforced; meaningful tests pass; installation and upgrade paths work; failure and recovery are visible; accessibility is checked; and a real user can complete the documented workflow end to end. A merged change or passing build alone is not completion.
