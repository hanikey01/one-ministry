# 10 — Permissions Matrix

**Status:** Baseline permission design. `05-systems.md` owns authorization rules; this document enumerates the required grants and default role bundles. A default role never bypasses object and scope checks.

## 1. Default roles

| Role | Intended scope |
| --- | --- |
| Visitor | Public pages and permitted public service actions |
| Member | Own profile, consent, registrations, groups, and donations |
| Group leader | Assigned groups and their attendance/tasks |
| Volunteer coordinator | Assigned teams, shifts, and volunteers |
| Online host | Assigned services' chat, prayer handoff, and prompts |
| Service producer | Assigned service planning, live controls, media health |
| Content editor | Draft and edit public content, not publish unless granted |
| Content publisher | Approve public content and recordings |
| People staff | Scoped people directory and routine follow-up |
| Care staff | Assigned private prayer/care cases |
| Finance staff | Giving, receipts, reconciliation, and finance reports |
| Ministry administrator | Settings, locations, nonrestricted staff roles, module configuration |
| Super administrator | Highest installation role: all installed permission bundles, configuration, and role oversight; requires MFA and verified email for elevated actions |
| Security administrator | Security settings, elevated role grants, audit and export controls |
| Site editor | Public branding and location configuration; no module activation, invitations, audit, care, or finance access |
| System operator | Infrastructure health and deployment; no default access to member records |
| Department member | Team roster membership; no staff-console access from membership alone |
| Department coordinator | Add/remove ordinary members within an assigned department |
| Department executive | Department lead; manages ordinary members within an assigned department |

A user can hold several roles. High-risk roles require multifactor authentication and may require dual approval for bulk export or finance changes. Default roles are templates; an installation can add narrower bundles, but cannot disable core server-side object checks.

Department coordinator and executive are scoped assignments rather than installation-wide role bundles. Only a ministry administrator can create a department or grant/revoke coordinator and executive assignments. Coordinators and executives can only add or remove ordinary members from their own department. A person can hold several department assignments at once.

## 2. Permission map

Legend: **O** own record; **S** assigned scope; **A** all nonrestricted ministry records; **R** restricted assignment; **—** no default grant.

| Capability | Member | Group leader | Host | Producer | People staff | Care staff | Finance | Admin | Security admin |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| View/edit own profile | O | O | O | O | O | O | O | O | O |
| View people directory | — | S | S (minimal) | S (minimal) | A or S | S (minimal) | — | A (minimal) | — |
| Edit person record | — | — | — | — | S | — | — | A | — |
| View/correct attendance | O (view) | S | S (view service) | S (view service) | S | — | — | A (aggregate) | — |
| Run service / see media health | — | — | S (host actions) | S | — | — | — | — | — |
| Moderate service chat | — | — | S | S | — | — | — | — | — |
| View private prayer/care | O (own request) | — | R (active handoff only) | — | — | R | — | — | — |
| Write care notes | — | — | — | — | — | R | — | — | — |
| Manage follow-up | O (own response status) | S | S (assigned handoff) | — | S | R | — | A (status only) | — |
| Manage content drafts | — | — | — | — | — | — | — | S if granted | — |
| Publish content/recordings | — | — | — | — | — | — | — | S if granted | — |
| View giving | O | — | — | — | — | — | A | — | — |
| Reconcile giving | — | — | — | — | — | — | A | — | — |
| Manage roles/settings | — | — | — | — | — | — | — | A (bounded) | A |
| View security audit | — | — | — | — | — | — | — | — | A |
| Export restricted data | — | — | — | — | — | R by separate grant | R by separate grant | — | Grant/oversight only |

The matrix grants are intentionally narrow. For example, a host may see the name and contact channel necessary to complete an active prayer handoff, not the person's historic care file. A ministry administrator may view task counts but cannot open the private request content solely because of the administrator role.

## 3. Scope representation

An assignment records role, grantee, scope type (`installation`, `location`, `group`, `service`, `team`, `care_case`), scope ID, grantor, start, and expiry/revocation. `installation` scope is disallowed for restricted care and child records unless an explicit emergency-access process is defined. Every policy checks current assignment and object membership on each request. Removing an assignment invalidates cached authorization promptly.

## 4. Sensitive actions

| Action | Extra control |
| --- | --- |
| View restricted care note | Assigned case plus MFA; audit view |
| Access child information | Dedicated child-team assignment; guardian relationship as applicable; audit |
| Bulk people export | Specific export grant, reason, short-lived file, audit |
| Care or finance export | Domain-specific grant and review; never included in general people export |
| Change payment provider | Finance plus security approval, credential verification, audit |
| Start/stop recording | Authorized host/producer, visible notice, audit |
| Change role or scope | Granting authority separate from ordinary module administration; audit |
| Correct attendance | Assigned scope, reason, appended correction |
| Configure Zoom Video SDK credentials | Super or ministry administrator, MFA, audit; secret values never displayed after save |
| Configure AI provider credentials and page-editor model routes | Ministry or super administrator with `ai.manage` and verified email; audited; MFA not required; secret values never displayed after save |
| Issue meeting host grant | Assigned meeting host/owner and verified email + MFA; role is derived server-side |
| Admit or remove meeting participant | Assigned moderator/host; audit each action |

Emergency access, if later approved, must be time-limited, reasoned, prominently audited, and reviewed afterward. It is not enabled in the baseline.

## 5. Authorization test matrix

For each resource, test: unauthenticated access, correct role and scope, wrong location, wrong group, former assignment, direct ID access, list filtering, search, export, and disabled module. For restricted data, additionally test broad administrator, finance, host, and system-operator denial. Do not infer access from frontend navigation visibility.
# Digital school permissions

| Permission | Default role | Scope |
|---|---|---|
| `school.manage` | School manager, ministry administrator, super administrator | Create and operate courses, lessons, assessments, live classes, and enrollments |
| `school.instruct` | School manager, ministry administrator, super administrator | Teach courses and review learner progress |

