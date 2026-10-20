# 14 — Shared Hosting Deployment Guide

This guide deploys one ministry installation of the Laravel application on cPanel, Plesk, DirectAdmin, or a generic PHP host. It does not require Docker, Redis, Node.js, or a permanent worker in production. Build release assets before upload.

## 1. Release package

Package the Laravel application with production Composer dependencies and the compiled Vite `public/build` directory. Exclude `.env`, tests, local databases, logs, `node_modules`, `.git`, Docker volumes, and backup archives. Retain `vendor/` when the host cannot run Composer. Store private uploads under Laravel storage or an installation-owned S3-compatible bucket.

Before packaging, run the complete release gate documented in `docs/06-build-plan.md`. Record the Git revision and schema version in the release notes.

## 2. Database and environment

Create a dedicated MySQL 8.0+ or MariaDB 10.6+ database and a database user with access only to it. Copy `.env.example` to `.env` outside public web access and set production mode, HTTPS `APP_URL`, MySQL credentials, database session/cache/queue drivers, and the private filesystem disk. Run `php artisan key:generate` once for a new installation. Never replace `APP_KEY` during an upgrade. Configure SMTP before password recovery or staff invitations are used.

## 3. Directory layout

Point the domain document root to the application's `public/` directory and keep `.env`, `vendor`, `storage`, backups, and private library files above it.

- **cPanel:** Domains → Manage → Document Root → `/home/account/ministry/public`.
- **Plesk:** Hosting Settings → Document root → `ministry/public`.
- **DirectAdmin:** use domain setup or a private application directory and point `public_html` to Laravel `public/` with the host-supported method.
- **Generic Apache/LiteSpeed:** enable rewrites and allow Laravel's `public/.htaccess`.

If a host forbids changing the document root, use only its documented Laravel deployment method. Do not copy `.env`, `storage`, or the whole application into a public directory.

## 4. Installation commands

Run from the application directory with PHP 8.3+:

```text
php artisan optimize:clear
php artisan migrate --force
php artisan storage:link
php artisan ministry:doctor --profile=shared --strict
php artisan ministry:install
php artisan optimize
```

Set writable permissions on `storage/` and `bootstrap/cache/` for the PHP process without making them world writable.

## 5. Cron and queues

Add the scheduler once per minute:

```text
* * * * * /path/to/php /path/to/ministry/artisan schedule:run >> /dev/null 2>&1
```

For database queues on hosts without a permanent worker:

```text
* * * * * /path/to/php /path/to/ministry/artisan queue:work database --stop-when-empty --max-time=50 --tries=3 >> /dev/null 2>&1
```

Confirm the scheduler heartbeat with `php artisan ministry:doctor --profile=shared --json` and review failed jobs before retrying them.

## 6. Upgrade and rollback

1. Enable maintenance mode with a secret bypass.
2. Back up the database, private media, `.env`, and provider configuration off host.
3. Upload to a new versioned directory and retain the prior release.
4. Install packaged production dependencies if required.
5. Clear caches, migrate with `--force`, run the shared doctor and smoke checks.
6. Switch the document root or release symlink, optimize, and leave maintenance mode.

Rollback code only when release notes confirm schema compatibility. Never run `migrate:rollback` automatically in production. Restore the verified pre-upgrade database when a schema rollback is required.

## 7. Backup and restore verification

Back up the database daily and private media according to change volume. Encrypt backups and copy them off host. A restore drill uses an isolated database and storage prefix, then checks ministry settings, people counts, future services, published library records, administrator login, private file access, and provider reconfiguration. Record the test date, recovery time, record counts, and operator.

Finance, care, security, and future children's exports remain permission-separated. A disaster recovery archive is not a staff data export.

## 8. Launch checklist

- HTTPS redirects and `APP_URL` match the public domain; `APP_DEBUG=false`.
- `.env`, storage, private media, and backups have no public response.
- The shared doctor passes, scheduler heartbeat is current, and failed jobs are resolved.
- Administrator login, email verification, MFA, password recovery, and session revocation work.
- Enabled modules appear publicly; disabled modules return 404 on direct access.
- Live video uses external YouTube, Vimeo, or HLS delivery and interruption fallback works.
- Anonymous video viewing remains separate from identified attendance.
- Database and media restore has been proven in isolation.

## Planned platform update channel

The platform will support an optional self-hosted update channel for installations. A maintainer release tag will produce a versioned application archive, checksum, signed release manifest, upgrade notes, and database migration compatibility metadata. Each ministry installation will use its own scheduled host cron job to check the selected Stable, Preview, or Manual-only channel.

An update must create a recoverable backup, enter maintenance mode, verify the package checksum and signature, switch releases atomically, run compatible migrations, rebuild application caches, run a health check, and record an audit event. Failed updates must restore the former release and report the failure to an authorized administrator. Installation servers must not need Git credentials or direct access to the maintainer GitHub repository.
