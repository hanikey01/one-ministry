# 16 — Interactive Meetings Design

**Status:** Proposed implementation design for Phase 4.  
**Owner:** Product Master §5.3; architecture §3.2; media delivery §8.  
**Purpose:** Deliver a ministry-branded, Zoom-style conference experience for small, interactive gatherings without confusing it with a one-to-many service broadcast.

## 1. Product decision and boundary

An interactive meeting is for a bounded group where participants may publish camera, microphone, screen share, chat, and reactions. Examples include small groups, discipleship classes, prayer appointments, counselling intake calls, leadership meetings, and online training.

A broadcast worship service remains a different experience. It distributes one composed program feed to a potentially large audience. It does not permit every viewer to publish video. A meeting must never be used as an unbounded substitute for a broadcast.

The platform supplies the branded meeting interface, access policy, staff permissions, attendance rules, care handoff, audit trail, and records. A configured meeting-media provider carries real-time media. The sole supported in-product provider is **Zoom Video SDK**. Each ministry connects its own Zoom Video SDK account; Laravel issues short-lived SDK grants after its own authorization checks. Google Meet may be linked only as an external meeting and is not a provider for the in-product experience.

## 2. User-facing experience

### 2.1 Meeting journey

```text
Invitation / member event → device check → waiting room → meeting room
                                                     ↘ declined / meeting closed
```

1. A member opens a meeting from an event, group, invitation, or member dashboard.
2. The pre-join screen checks camera, microphone, speaker, and network. It shows the ministry name, meeting name, scheduled start, recording notice, and join policy.
3. The participant selects a display name and chooses camera and microphone state. A participant may join muted or camera-off.
4. Where enabled, the participant waits for a host. They see an accurate waiting-room status but cannot see or hear meeting participants.
5. On admission, the room loads the ministry’s shared stage and available controls.
6. On leaving, the platform records a bounded participation session only for an identified participant and only after a successful room admission. A page view, rejected device check, or waiting-room presence is not attendance.

### 2.2 Participant room

The meeting room has four regions:

| Region | Contents |
| --- | --- |
| Top bar | Meeting name, connection state, recording indicator, participant count, leave button. |
| Shared stage | Host-selected speaker, two-person conversation, screen share with speaker, or gallery. |
| Participant strip | Visible speakers and active participants; local pin remains private to that participant. |
| Control bar | Microphone, camera, device menu, screen share, raise hand, reactions, chat, participants, captions, more, leave. |

The shared stage is authoritative for the default viewing layout. A participant can locally pin another participant for their own screen without changing the stage for everyone else.

### 2.3 Host console

Hosts see the same room with an additional, desktop-first right panel:

| Area | Controls |
| --- | --- |
| Lobby | Admit, decline, admit all, and a visible count of waiting people. |
| Participants | Search, mute, remove, promote/demote co-host or presenter, assign breakout room, see raised hand and device state. |
| Stage | Spotlight one person, select a two-person layout, prioritise a shared screen, return to gallery, or return to automatic active-speaker layout. |
| Safety | Mute all, pause participant chat, disable participant screen sharing, lock the room, and end the meeting. |
| Breakouts | Create rooms, assign people manually or evenly, open/close rooms, broadcast an announcement, and return everyone to the main room. |
| Recording | Start, pause, resume, and stop only if the meeting policy and provider support recording. |

A host can silence an existing audio publication, remove a participant, or prevent additional publishing according to the provider's control model. The application must never silently turn on a participant’s microphone or camera; browser permissions require that person to choose to unmute or enable video.

### 2.4 Prayer and pastoral handoff

A public or group meeting may show a **Request private prayer** action. The request contains minimal contact/consent information and becomes a restricted request. An authorized host can offer a private prayer room to the requester and an eligible prayer-team member.

The private room is a new access-scoped meeting, not a breakout room visible to ordinary hosts. Care details, prayer text, and private-room participant records retain the existing care permission domain and audit requirements.

## 3. Meeting policy

Each meeting stores the following controlled choices.

| Policy | Choices | Default |
| --- | --- | --- |
| Access | Open registration, member-only, invitation-only | Invitation-only for care; member-only otherwise |
| Entry | Open room, waiting room, host admits | Waiting room |
| Participant media | Camera/mic allowed, view-only, host/presenter-only | Camera/mic allowed |
| Screen sharing | All participants, presenter/host only, disabled | Presenter/host only |
| Chat | Enabled, host-only, disabled | Enabled |
| Recording | Off, host-start with notice, automatic with notice | Off |
| Breakouts | Enabled/disabled | Enabled for groups/classes only |
| Attendance | Off, self-confirmed, identified room admission | Identified admission for registered/member meetings |
| Capacity | Declared maximum, provider-supported maximum | Installation-selected safe default |
| Joining window | Early-open and late-join minutes | 15 minutes before; 30 minutes after start |

Provider capability discovery controls the available choices. Unsupported settings are disabled with an explanation; the system never shows a control that silently does nothing.

## 4. Roles and authority

| Role | Scope | Core actions |
| --- | --- | --- |
| Participant | Own invitation/registration | Join, manage own devices, chat, raise hand, request prayer, leave. |
| Presenter | Assigned meeting | Participant actions plus screen share and stage eligibility. |
| Moderator | Assigned meeting | Admit lobby, moderate chat, mute/remove participants, manage hands. |
| Host | Assigned meeting | Moderator actions plus shared stage, room lock, breakouts, recording controls. |
| Meeting owner | Assigned meeting | Edit policy, appoint hosts, end/cancel meeting, review attendance. |
| Care host | Assigned care scope | Receive private prayer handoffs only; does not gain general meeting administration. |
| Super/Ministry administrator | Installation | Provider configuration and emergency recovery; restricted care content still requires care permissions. |

A person can hold multiple roles. Assignment is meeting-scoped and expires with the meeting unless the meeting is a recurring template. Provider room grants never replace Laravel authorization.

## 5. Provider contract

The meeting provider adapter must expose these server-side operations:

```text
preflight(configuration) → capabilities + safe diagnostics
createRoom(meeting) → provider room reference
issueGrant(meeting, participant, role, expiry) → short-lived signed join grant
admit / remove / mute / updateParticipantPermissions
setStage(layout, participant identities, screen-share identity)
create / assign / close breakout rooms
start / pause / resume / stop recording
endRoom
handleWebhook(raw signed event) → normalized lifecycle event
```

The browser receives only a short-lived room grant, public provider URL, room name, controlled feature flags, and safe participant metadata. API secrets, provider API keys, webhook secrets, and unrestricted room-admin tokens remain server-side and encrypted per installation.

### 5.1 Supported provider profiles

| Profile | Intended deployment | Notes |
| --- | --- | --- |
| Zoom Video SDK | Sole in-product provider | Ministry supplies its own Video SDK key and secret; the platform encrypts them, creates short-lived participant JWTs, and processes Zoom callbacks. Usage charges remain ministry-owned. |
| External link | Compatibility profile | Stores and displays a Zoom/Google Meet link. It cannot provide native stage, participant, or moderation controls in this application. |

## 6. State models

### 6.1 Meeting lifecycle

```text
Draft → Scheduled → Open → In progress → Ended
                  ↘ Cancelled
Open / In progress → Locked
In progress → Interrupted → In progress | Ended
```

- `Open` permits pre-join/waiting room admission only within the configured window.
- `In progress` permits provider grants and eligible attendance confirmation.
- `Locked` admits nobody new except explicitly authorized hosts.
- `Interrupted` preserves the state and displays recovery guidance; it never fabricates attendance or recording success.
- `Ended` invalidates all active grants and prevents new media connections.

### 6.2 Stage model

```json
{
  "mode": "spotlight | conversation | screen_share | gallery | active_speaker",
  "participant_ids": ["uuid"],
  "screen_share_participant_id": "uuid|null",
  "version": 12,
  "changed_by": "uuid",
  "changed_at": "RFC3339"
}
```

The stage version prevents stale host controls from overwriting a later stage choice. The stage event is delivered through a provider data channel when available and HTTP polling/reconnect refresh as the portable fallback.

## 7. Data model additions

The existing `meetings`, `meeting_participants`, and `meeting_recordings` tables are a proof-of-concept foundation. Before feature implementation, add these expandable records:

| Table | Essential fields |
| --- | --- |
| `meeting_provider_settings` | singleton/provider key, enabled, encrypted credentials, region, tested state, changed_by. |
| `meeting_occurrences` | meeting/template reference, title, starts/ends, timezone, lifecycle state, policy snapshot, provider room reference. |
| `meeting_role_assignments` | occurrence, person/user, role, granted_by, expiry, revoked_at. |
| `meeting_join_requests` | occurrence, user/guest reference, display name, state, requested/admitted/declined times, moderator. |
| `meeting_presence_sessions` | occurrence, participant, joined/left, admission source, provider session reference, no media payload. |
| `meeting_stage_events` | occurrence, immutable stage JSON, version, actor, created time. |
| `meeting_control_events` | occurrence, action, target, bounded reason, actor, timestamp. |
| `meeting_breakout_rooms` | occurrence, provider reference, name, state, opens/closes time. |
| `meeting_breakout_assignments` | room, participant, assigned_by, joined/left. |
| `meeting_webhook_receipts` | provider event ID, signature result, normalized type, raw payload retention reference, processed_at. |

Provider recording IDs and storage references are stored separately from published media. A recording stays `pending_review` until an authorized content reviewer approves it. A failed, interrupted, or rejected recording never appears publicly.

## 8. HTTP and realtime contracts

All browser actions are first-party, same-origin actions. Server mutations authorize the caller, issue provider actions, persist an audit/control event, and return the new authoritative state.

| Method/path | Purpose | Access |
| --- | --- | --- |
| `GET /meetings/{occurrence}` | Safe room metadata and join eligibility | Eligible participant |
| `POST /meetings/{occurrence}/preflight` | Saves non-sensitive device result; returns policy | Eligible participant |
| `POST /meetings/{occurrence}/join-request` | Enters waiting room or requests access | Eligible participant/registered guest |
| `POST /meetings/{occurrence}/join-grant` | Returns a short-lived media grant after admission | Admitted participant |
| `POST /meetings/{occurrence}/leave` | Closes presence session | Joined participant |
| `GET /meetings/{occurrence}/state` | Current lifecycle, stage version, safe participants | Joined participant |
| `POST /staff/meetings/{occurrence}/lobby/{request}/admit` | Admit a lobby request | Moderator/host |
| `POST /staff/meetings/{occurrence}/controls` | Mute, remove, lock, chat/share policy | Host with scope |
| `PUT /staff/meetings/{occurrence}/stage` | Change shared stage with expected version | Host with scope |
| `POST /staff/meetings/{occurrence}/breakouts` | Create/open/close/assign breakouts | Host with scope |
| `POST /staff/meetings/{occurrence}/recording/{action}` | Record lifecycle action | Host; notice required |
| `POST /webhooks/meetings/{provider}` | Signature-verified provider lifecycle callback | Provider only |

All grant and control endpoints are rate limited. Join grants expire quickly, have a unique nonce, and are audience-bound to the meeting occurrence. State-changing host requests require an idempotency key and either an expected stage version or equivalent conflict token.

## 9. Privacy, safeguarding, and audit

- Waiting-room information is visible only to hosts/moderators for that meeting.
- The application stores no raw audio/video, camera frames, or microphone diagnostics unless recording is explicitly enabled and the provider callback creates a recording record.
- A visible, persistent recording indicator and notice appear before and during any recording. A participant must be able to leave without ending their application session.
- Device preflight stores only optional boolean readiness and bounded error categories; it stores no device names or media content.
- Camera/mic status, join/leave, admission, role changes, removal, stage changes, room lock, recording, and breakout assignment are auditable control events.
- Care handoff and private prayer room membership use the care permission domain. General hosts cannot read prayer text merely because they can moderate the main meeting.
- Youth/children meetings remain unavailable until the separate safeguarding specification is approved.

## 10. Cost, capacity, and operational controls

Interactive provider usage is calculated in participant-minutes: one person connected for one minute equals one participant-minute. The provider settings screen must show the ministry’s configured provider, current quota/usage when the provider supports it, and a ministry-defined monthly warning/stop threshold.

The meeting creation screen requires a declared participant limit. It compares the requested limit with the provider capability and the installation’s tested profile. A host sees a preflight warning when no provider is configured, the room exceeds the safe declared limit, or the provider is unhealthy.

Each installation chooses its own provider and bears its own provider charges. The platform does not route media through a vendor-controlled central ministry account. The shared PHP host never carries WebRTC media packets.

## 11. Failure handling

| Failure | Required user experience | Operator action |
| --- | --- | --- |
| Provider unreachable before join | Explain that the meeting is unavailable; retain event details and offer approved fallback contact method. | Check provider connection/preflight. |
| Participant reconnect | Restore room state, stage, role, and waiting-room result without double-counting attendance. | None unless repeated. |
| Host disconnect | Keep room open while another host exists; otherwise apply configured grace period then lock/end. | Promote a co-host before planned meetings. |
| Provider callback delayed | Do not claim recording or attendance complete until durable callback/reconciliation. | Show pending state. |
| Recording failure | Keep meeting active; mark recording failed and do not publish. | Retry/recover through provider; document outcome. |
| Breakout failure | Return affected participants to main room with a clear notice. | Record bounded provider error. |

## 12. Delivery batches

### M-1 — Provider settings and meeting policy

- Feature gate `interactive_meetings`, disabled by default on fresh and upgraded installations.
- Installation-owned provider configuration and preflight.
- Meeting templates, occurrences, policy, role assignments, and invitations.
- Staff creation/list/detail screens and member meeting list.
- No media join until provider configuration passes preflight.

### M-2 — Secure join and core room

- Pre-join device check, waiting room, short-lived provider grants, participant presence, chat, raise hand, participant list, and stage rendering.
- Host lobby admission and decline, mute/remove, shared stage, room lock/end, audit events.
- Accessibility: keyboard controls, focus order, labels, captions control states, reduced-motion layout.

### M-3 — Collaboration and care

- Screen sharing policy, presenter/co-host roles, breakouts, host announcements, private prayer handoff, and scoped care assignment.
- Reconnect/stale-stage recovery through authoritative HTTP state.

### M-4 — Recording and operations

- Recording notice/control, signed provider callbacks, review queue, replay publication gate.
- Usage/capacity indicators, provider outage state, operational runbook, retention jobs, and cost guardrails.

### M-5 — Zoom production hardening

- Zoom Video SDK provider conformance and callback-recovery validation.
- External Zoom/Google Meet link compatibility profile remains outside the in-product meeting experience.

## 13. Acceptance gates

A batch is complete only when it demonstrates these user journeys on a configured provider:

1. A member with a valid invitation joins from the waiting room after host admission; an expired or unrelated grant fails.
2. A host mutes/removes a participant and stage-switches between a speaker, conversation, and shared screen; all connected clients receive the authoritative stage.
3. A participant reconnects without duplicate attendance or elevated role persistence.
4. Breakout assignment remains restricted to hosts and returns participants to the correct main room.
5. Recording notice is visible before recording; a failed or rejected recording cannot publish.
6. Private prayer handoff does not reveal prayer content to ordinary hosts.
7. A room at its declared capacity satisfies its provider, latency, reconnect, and accessibility targets.
8. Provider credentials, join grants, webhook raw bodies, and private care content never appear in browser logs, audit events, exports, or public API payloads.

## 14. Decisions required before coding M-1

1. Confirm the initial provider adapter: **Zoom Video SDK is selected by product direction**.
2. Set the initial meeting categories and default limits for group, class, prayer, and leadership meetings.
3. Choose the initial data-retention duration for meeting presence and control events.
4. Set which role bundles receive `meetings.manage`, `meetings.host`, `meetings.moderate`, and `meetings.record`.
5. Set an installation-default provider usage warning and hard-stop policy.

Until those choices are confirmed, this document is the implementation plan and does not claim that a configured media provider or meeting experience has been deployed.

