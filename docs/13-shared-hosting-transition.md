# 13 — Laravel Shared-Hosting Transition

**Status:** Confirmed product direction, approved 2026-10-14. This document owns the migration from the separate Next.js interface to a Laravel-first application that can run on ordinary shared PHP hosting.

## 1. Outcome

The finished product is one Laravel application that renders the public website, member area, staff workspace, and worship room. It must run without a Node.js runtime, Docker, Redis, a persistent queue worker, or a self-hosted realtime server. Those services remain optional enhancement profiles.

Laravel Blade renders pages. Livewire owns server-authorized interactive screens and form workflows. Alpine.js supplies lightweight browser behavior such as menus, drawers, modals, tabs, media controls, and progressive enhancement. Tailwind CSS remains the shared design system.

The existing Laravel domain rules, migrations, services, permissions, audits, feature flags, and API contracts are transition assets. They are retained and progressively consumed by web routes and Livewire components. The separate Next.js deployment is retired only after route-by-route replacement and verification.

## 2. Hosting baseline

| Requirement | Baseline shared-hosting profile | Optional enhancement |
| --- | --- | --- |
| PHP | PHP 8.4.1+ with the listed required extensions | Managed VPS/container profile |
| Database | MySQL or MariaDB; PostgreSQL remains supported | Dedicated managed database |
| Web process | PHP-FPM/Apache/LiteSpeed serving Laravel `public/` | Docker/Caddy or Nginx profile |
| Scheduling | Host cron executes `php artisan schedule:run` every minute | Persistent scheduler process |
| Jobs | Database queue or bounded synchronous fallback | Redis queue worker |
| Realtime | Short polling and authoritative HTTP refresh | Reverb or compatible broadcaster |
| Storage | Safe local private storage where host permits, or S3-compatible storage | Dedicated object store/CDN |
| Broadcast video | YouTube, Vimeo, or external HLS/CDN | Dedicated media origin/distribution |
| Meetings | Configured external meeting provider | Installation-owned media deployment |

Video delivery never passes through the shared PHP host. A broadcast remains a provider or CDN-delivered experience; a small interactive meeting remains an external configured media service. No product-wide viewer capacity claim is made.

### 2.1 Required host capabilities

| Capability | Required baseline | Verification |
| --- | --- | --- |
| PHP runtime | PHP 8.4.1 or later | `php -v`; `php artisan ministry:doctor --profile=shared --json` |
| PHP extensions | `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, `xml` | `php -m`; shared doctor `runtime` check |
| Database | MySQL 8.0+ or MariaDB 10.6+; a dedicated database/user with migration privileges | Fresh migration and focused MariaDB test profile |
| Scheduler | Cron available every minute | Scheduler heartbeat is fresh in the shared doctor profile |
| Storage | Writable `storage/` and `bootstrap/cache/`; private files outside the public web root | Shared doctor `storage` check |
| Uploads | Host limits sized for the ministry's intended document and media uploads | Confirm `upload_max_filesize`, `post_max_size`, request timeout, and disk quota before launch |
| HTTPS and URL | HTTPS domain with document root pointing to Laravel `public/` | Shared doctor `public_url` check; browser test |
| Mail | Installation-owned SMTP or API transport | Test a delivery before account-email workflows launch |

The shared doctor fails when PHP or a required extension is unavailable. It accepts database-backed or synchronous queue fallbacks, but a ministry should use database queues whenever host cron can execute queued work predictably. Redis, a long-running worker, Node.js, Docker, and self-hosted realtime services are optional enhancements.

## 3. Transition batches

### Batch SH-0 — Compatibility and baseline

Define PHP, MySQL/MariaDB, extension, cron, upload, disk, and mail requirements. Add MySQL/MariaDB migration and test coverage beside PostgreSQL. Define database-queue and synchronous fallbacks. Preserve all existing Laravel behavior before interface replacement begins.

**Exit:** a documented shared-hosting profile exists and domain tests pass against supported database engines.

### Batch SH-1 — Laravel interface foundation

Install and configure Blade, Livewire, Alpine.js, and the Laravel asset pipeline. Build shared public, staff, member, and worship layouts; port theme tokens, the premium sidebar, mobile drawer, accessibility primitives, forms, cards, tables, alerts, and empty states.

**Exit:** authenticated and public layout shells render from Laravel without a Node server; ministry theme settings apply to each shell.

### Batch SH-2 — Setup, identity, and administration

Port setup, login, recovery, email verification, MFA, sessions, invitations, branding, locations, and feature settings. Preserve every server-side authorization, throttling, CSRF, MFA, verification, and audit rule.

**Exit:** a fresh ministry can install and administer the application entirely through Laravel-rendered pages.

### Batch SH-3 — Staff operations

Port people, attendance, follow-up, departments, events, volunteers, care, communications, media review, giving, reports, and service operations to Livewire workspaces. Build reusable search, filtering, pagination, table, and inline-action patterns.

**Exit:** staff can complete current daily operations through Laravel pages while permission domains remain enforced.

### Batch SH-4 — Member and public experience

Port member self-service, public content, departments, events, giving, libraries, custom pages, and feature-aware navigation. Preserve direct server enforcement for disabled modules and content publication rules.

**Exit:** current member and public routes work without Next.js and retain ownership, consent, and privacy checks.

### Batch SH-5 — Digital Church baseline

Port producer controls and the worship room. Use polling as the required baseline for chat, polling, service state, and response actions. Realtime providers remain optional. Preserve anonymous diagnostic rules, moderation, media fallbacks, capacity rehearsals, and regional probe evidence.

**Exit:** a ministry runs a public service from Laravel pages with external video delivery, mobile support, and usable outage behavior.

### Batch SH-6 — Portable deployment and retirement

Deliver cPanel, Plesk, DirectAdmin, and generic PHP-hosting guides; cron instructions; update and rollback procedures; database and media backup/restore verification; shared-host health checks; and an enhanced Docker/VPS profile. Remove the Next.js production deployment only after parity, upgrade, accessibility, permission, and browser validation pass.

The enhanced Docker profile now compiles the Laravel Vite bundle inside the PHP image build and routes all browser traffic through Laravel. `apps/web` remains temporarily as transition reference source and is excluded from production Compose.

**Exit:** a ministry deploys, upgrades, backs up, and restores one PHP application using documented shared-hosting instructions.

## 4. Non-negotiable rules during migration

- A page or Livewire component never becomes the only authorization boundary; existing policies and service rules remain authoritative.
- Anonymous playback and page views do not become identified attendance.
- Giving, pastoral care, and future children data remain separate permission domains.
- Disabled modules are rejected on the server as well as hidden from navigation.
- New data behavior requires migrations, tests, contracts, operations instructions, and an upgrade path.
- The existing Next.js interface remains source-only transition reference until its Laravel replacement is verified; it is not a target production deployment after the transition.

## 5. Deferred choices

The project does not yet select a specific shared host, mail provider, realtime provider, meeting provider, or video provider. Each installation configures providers it owns. Provider-native media control and externally operated probe agents remain optional until their individual validation gates are met.
