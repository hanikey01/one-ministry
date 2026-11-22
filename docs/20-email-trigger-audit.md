# 20 — Email Trigger Audit

**Audited:** 2026-11-20  
**Scope:** Actual Laravel trigger wiring in `services/api`; a catalog entry or Blade view alone is not treated as implemented.

All implemented transactional messages use the installation-owned delivery connection. Delivery is synchronous unless the row says otherwise. Provider configuration remains required before any real message can leave the installation.

| Workflow | Trigger and recipient | Actual implementation | Delivery / idempotency / failure handling | Status and missing work |
| --- | --- | --- | --- | --- |
| Staff email verification | Staff requests verification; current staff email | `EmailVerificationService` → `EmailTemplateService::send('staff_email_verification')` → `emails.ministry-transactional` | Synchronous. One active hashed token; token is deleted if delivery throws. | Implemented. |
| Member signup verification | Signup start or resend; prospective member email | `MemberRegistrationVerificationService` → `member_signup_verification` | Synchronous. Verification record/code owns expiry and resend state; failed send removes unusable state. | Implemented; public-form guard now protects start, verify, and resend. |
| Password reset | Reset request; matching account email (generic browser response either way) | `PasswordRecoveryService` → `password_reset` | Synchronous. One hashed, expiring token; delivery failure does not expose account existence. | Implemented. |
| Staff invitation | Authorized administrator creates invitation; invitee email | `StaffInvitationService` → `staff_invitation` | Synchronous. Hashed invitation token and expiry; creation is rolled back/removed on send failure. | Implemented. |
| Birthday reminder | Hourly scheduler evaluates the next ministry-local day; active staff with `birthdays.read` | `birthdays:send-reminders` → `birthday_staff_reminder` | Scheduler-driven synchronous bounded loop. Durable unique person/recipient/year/type delivery row; failed recipients are logged and may retry without stopping others. | Implemented; host cron must run Laravel scheduler. |
| Member welcome | Account creation; new member | Catalog key `member_welcome` exists, but no trigger calls it. | None. | Missing wiring; welcome timing/retry rule must be approved before implementation. |
| Giving receipt | Verified donation; donor | Catalog key `giving_receipt` exists, but no verified-payment trigger calls it. | None. | Missing. Must attach only to verified provider/reconciliation state and deduplicate by donation/receipt identity. |
| Event registration confirmation | Successful registration; registrant | Catalog key `event_confirmation` exists, but registration controllers do not call it. | None. | Missing wiring and waitlist-specific wording. |
| Event reminder/change/cancellation | Scheduled reminder or staff change; registered attendees | No notification class, template key, or trigger. | None. | Missing; reminder timing and change significance rules are unresolved. |
| Department join request | New join request; authorized department leaders | No email trigger. | None. | Missing; recipient scope and digest/immediate choice require a product rule. |
| Department approval/rejection | Staff review; requester | No email trigger. | None. | Missing; rejection-copy policy is unresolved. |
| Prayer acknowledgement | Prayer submission; requester when an email is available | No email trigger. | None. | Missing; acknowledgement must avoid echoing prayer text. |
| Prayer internal alert | Prayer submission; `prayer.read` users | No email trigger; restricted queue is the authoritative destination. | None. | Missing by design pending a safe, non-content alert rule and staff notification preferences. |
| Contact-form delivery | Public contact submission; `ministry_settings.contact_email` | `StaffPeopleController::contact` → `EmailDeliverySettingsService::sendHtml` | Synchronous after durable person/task creation. Failure logs only IDs/class and never rolls back the request. | Implemented. Requires configured ministry contact email and active delivery connection. |
| Follow-up acknowledgement | Next-step/contact request; requester | Catalog key `follow_up_acknowledgement` exists, but no trigger calls it. | None. | Missing wiring. |
| Follow-up assignment | Assignment/reassignment; assigned staff | No template or trigger. | None. | Missing; batching and confidential-summary rules are unresolved. |
| Testimony submission acknowledgement | Submission; submitter | No template or trigger. | None. | Missing. |
| Testimony approval/rejection/publication | Review transition; submitter | Catalog key `testimony_published` exists, but `TestimonyController` does not call it. No rejection/approval keys exist. | None. | Missing wiring; anonymous guest recipient and rejection-copy rules require definition. |
| Bulk email campaigns | Scheduled campaign; consented recipients | `EmailCampaignService`, `ministry:send-email-campaigns` | Scheduler batches. Per-recipient delivery rows are restart-safe; consent is rechecked; sent/failed/skipped outcomes persist. | Implemented. |
| Security-sensitive account changes | Password/email/MFA/session changes; affected account | No notification trigger beyond verification/reset emails. | None. | Missing; event catalog and safe recovery guidance require approval. |

## Operational requirements

- Configure and activate the installation Email workspace with a verified sender and working SMTP transport.
- Set the ministry contact email for contact-form delivery.
- Run `php artisan schedule:run` every minute from host cron. This drives campaigns and the hourly birthday reminder command.
- Monitor application logs and `birthday_reminder_deliveries`/campaign delivery outcomes. Logs must not contain prayer text, contact-message bodies, tokens, credentials, or private notes.
