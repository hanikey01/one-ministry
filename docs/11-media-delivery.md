# 11 — Live Media Delivery and Capacity

**Status:** Detailed media design to validate in Phase 1. `01-architecture.md` owns component boundaries; `08-operations.md` owns deployment procedures.

## 1. Two media modes

| Mode | Typical interaction | Delivery design | Attendance signal |
| --- | --- | --- | --- |
| Broadcast service | Few presenters, any number of viewers | Ingest → encode/package → HLS origin → CDN or elastic distribution | Anonymous aggregate viewing; identified presence only for signed-in viewers meeting attendance rule |
| Interactive meeting | Participants can speak and show video | Authorized WebRTC room with host controls | Room join/leave for identified invitees, reconciled with actual meeting policy |

No application-wide viewer cap is imposed. The same web experience can run over distributions of different sizes. Each deployment has measurable limits and must be tested against its declared audience. Interactive room capacity is not inferred from broadcast capacity.

## 2. Broadcast control plane

Core API creates a `service_occurrence` and assigns a media adapter reference. A producer starts a rehearsal or public session. Media adapter reports ingest and playback health through verified callbacks and active probes. Core API owns the final public state and emits versioned events for the homepage and watch room. A stream key alone does not make a service public. Only the primary selected live service occupies the homepage lead; other live services remain discoverable.

Broadcast playback descriptors contain only public playback URL or signed viewing token, service ID, protocol, optional rendition hints, and expiry. Ingest credentials are producer-only and rotate on compromise. Private broadcasts use short-lived playback grants; caching rules must not accidentally make them public.

## 3. Stream timing and interactions

HLS viewers may be behind the production room. Store `produced_at`, `available_at`, and, when possible, a media program timestamp for prompts. If the playback position can be observed reliably, show a prompt when its program time is reached. If it cannot, a host chooses a prompt mode appropriate to the distribution; the interface must not pretend every viewer is synchronized. Chat is live relative to message delivery and should explain if video has meaningful delay. Poll open/close windows can remain time-based but must be long enough for expected playback latency.

## 4. Failure and recovery

An ingest health check, an origin playlist check, and a viewer-side playback probe are distinct. A healthy encoder connection does not prove that viewers can play the stream. After a configurable grace period of failed playback, mark the session interrupted and show a fallback. Retrying playback should not create duplicate attendance records. When a stream resumes, preserve the same occurrence and audit interruption intervals. If recovery is impossible, producer ends the service and can publish a status update.

## 5. Recording and replay

Recording may come from the ingest/packaging path or a meeting egress path. Keep source recording, processed asset, captions/transcript, review state, and published content reference separate. A failed or unreviewed recording is never public. Redaction or trimming produces a new reviewed version. Retention and deletion follow the ministry's policy, with an audit of publication and withdrawal.

## 6. Capacity model

For an event, record expected concurrent viewers, geographic distribution, average bitrate or adaptive ladder, desired start time, maximum acceptable rebuffering, target chat throughput, and acceptable cost. Estimate outbound bandwidth as concurrent viewers × delivered average bitrate, then validate against origin/CDN behavior; this is a planning estimate, not a guarantee. Confirm provider quotas, egress cost, cache hit rate, and regional performance.

| Test | Pass condition to set per deployment |
| --- | --- |
| Playback start | Agreed percentile starts within target time |
| Playback availability | Agreed percentage of synthetic viewers can play throughout event |
| Rebuffering | Time spent stalled stays below agreed budget |
| API/realtime | Join, chat, prayer, and prompt actions stay within agreed latency/error budget |
| Recovery | Ingest loss and restoration produce correct homepage and watch states |
| Cost | Estimated event cost remains inside ministry's approved budget |

Publish a reference benchmark for the project's recommended configuration at several audience sizes, including test method, date, hardware, region, media settings, and observed limits. Do not translate that benchmark into an unconditional capacity promise for a different ministry's host.

## 7. Browser and accessibility validation

Test supported desktop and mobile browsers, landscape/portrait, autoplay restrictions, audio controls, captions, full screen, keyboard navigation, screen reader labels, and low bandwidth. Provide a poster/fallback and clear player error. Do not let video cover prayer or contact actions. A participant can leave a meeting and revoke camera/microphone access without losing their account session.

## 8. Adapter conformance

A broadcast adapter must create/identify a session, rotate ingest credentials, expose playback descriptor, report ingest and playback health, end a session, and verify callbacks. A meeting adapter must issue room-scoped grants, expose room health, apply host permissions, and report join/leave and recording events. Capabilities are declared; an unsupported capability disables the associated UI and fails preflight rather than silently degrading.

The current broadcast provider registry normalizes public YouTube, Vimeo, and HTTPS HLS playback descriptors and declares their implemented capabilities. YouTube and Vimeo use privacy-aware embedded playback; HLS supports direct manifest validation. All three accept the installation's signed normalized health callback. Ingest credential creation or rotation is not implemented by the platform and is explicitly reported as unavailable. This registry is an application boundary, not proof of a provider-native control API. Provider-native lifecycle integrations remain subject to the adapter proof and provider configuration decision.

Regional probe agents implement an installation-configured HTTP contract. The platform sends `target_url` and `playback_type` with the configured bearer token. A successful agent response is JSON containing `status`, optional `http_status`, and `latency_ms`. Results from the origin and every configured agent are persisted separately. Mixed regional results degrade playback health; universal failure marks it offline. A remote agent must apply its own public-target and response-size restrictions because it performs the network request from its region.
