"use client";

import { FormEvent, useEffect, useState } from "react";
import { api, type ServiceOccurrence } from "./api";
import ChatModerationPanel from "./ChatModerationPanel";
import PlaybackDiagnosticsPanel from "./PlaybackDiagnosticsPanel";
import CapacityRehearsalPanel from "./CapacityRehearsalPanel";
import RegionalProbePanel from "./RegionalProbePanel";

type Template = { id: string; title: string; timezone: string; default_duration_minutes: number };
type RunItem = { id: string; title: string; status: "pending" | "active" | "complete" | "skipped"; duration_seconds: number | null };
type Note = { id: string; content: string; created_at: string };
type Prompt = { id: string; message: string; offset_seconds: number; fired: boolean };
type Poll = { id: string; question: string; status: "draft" | "open" | "closed"; options: Array<{ id: string; label: string; responses?: number }> };
type StreamSession = { id: string; provider: "youtube" | "vimeo" | "hls"; playback_type: string; provider_stream_id: string | null; playback_url: string | null; lower_bandwidth_url: string | null; captions_url: string | null; fallback_message: string | null; ingest_health: "unknown" | "healthy" | "degraded" | "offline"; playback_health: "unknown" | "healthy" | "degraded" | "offline"; last_health_at: string | null; last_probe_status: string | null; last_probe_http_status: number | null; last_probe_latency_ms: number | null; last_probed_at: string | null };
type StreamEvent = { id: string; event_type: string; changes_json: string; created_at: string };

export default function ServiceProductionPanel() {
  const [services, setServices] = useState<ServiceOccurrence[]>([]);
  const [templates, setTemplates] = useState<Template[]>([]);
  const [serviceId, setServiceId] = useState("");
  const [runSheet, setRunSheet] = useState<RunItem[]>([]);
  const [notes, setNotes] = useState<Note[]>([]);
  const [prompts, setPrompts] = useState<Prompt[]>([]);
  const [polls, setPolls] = useState<Poll[]>([]);
  const [stream, setStream] = useState<StreamSession | null>(null);
  const [streamEvents, setStreamEvents] = useState<StreamEvent[]>([]);
  const [error, setError] = useState("");
  const [streamNotice, setStreamNotice] = useState("");
  const [busy, setBusy] = useState(false);

  async function loadServices() {
    const [serviceResponse, templateResponse] = await Promise.all([api<{ data: ServiceOccurrence[] }>("/staff/services"), api<{ data: Template[] }>("/staff/service-templates")]);
    setServices(serviceResponse.data); setTemplates(templateResponse.data);
    if (!serviceId && serviceResponse.data[0]) setServiceId(serviceResponse.data[0].id);
  }
  async function loadDetail(id: string) {
    if (!id) return;
    const [run, note, prompt, poll, liveStream] = await Promise.all([
      api<{ data: RunItem[] }>(`/staff/services/${id}/run-sheet`),
      api<{ data: Note[] }>(`/staff/services/${id}/notes`),
      api<{ data: Prompt[] }>(`/staff/services/${id}/prompts`),
      api<{ data: Poll[] }>(`/staff/services/${id}/polls`),
      api<{ data: { session: StreamSession | null; events: StreamEvent[] } }>(`/staff/services/${id}/stream`),
    ]);
    setRunSheet(run.data); setNotes(note.data); setPrompts(prompt.data); setPolls(poll.data); setStream(liveStream.data.session); setStreamEvents(liveStream.data.events);
  }
  useEffect(() => { void loadServices().catch(cause => setError(cause instanceof Error ? cause.message : "Could not load producer tools.")); }, []);
  useEffect(() => { void loadDetail(serviceId).catch(cause => setError(cause instanceof Error ? cause.message : "Could not load this service.")); }, [serviceId]);

  async function submitTemplate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); const data = new FormData(event.currentTarget); setBusy(true); setError("");
    try { const response = await api<{ data: Template }>("/staff/service-templates", { method: "POST", body: JSON.stringify({ title: data.get("title"), timezone: data.get("timezone"), default_duration_minutes: Number(data.get("duration")) }) }); setTemplates(current => [...current, response.data]); event.currentTarget.reset(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : "Could not save template."); } finally { setBusy(false); }
  }
  async function submitRunSheet(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!serviceId) return; const data = new FormData(event.currentTarget); setBusy(true);
    try { await api(`/staff/services/${serviceId}/run-sheet`, { method: "POST", body: JSON.stringify({ title: data.get("title"), duration_seconds: Number(data.get("duration")) || null, position: runSheet.length }) }); event.currentTarget.reset(); await loadDetail(serviceId); }
    catch (cause) { setError(cause instanceof Error ? cause.message : "Could not add the segment."); } finally { setBusy(false); }
  }
  async function setRunStatus(item: RunItem, status: RunItem["status"]) {
    if (!serviceId) return; setBusy(true);
    try { await api(`/staff/services/${serviceId}/run-sheet/${item.id}`, { method: "PATCH", body: JSON.stringify({ status }) }); await loadDetail(serviceId); }
    catch (cause) { setError(cause instanceof Error ? cause.message : "Could not update segment."); } finally { setBusy(false); }
  }
  async function submitNote(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!serviceId) return; const data = new FormData(event.currentTarget); setBusy(true);
    try { await api(`/staff/services/${serviceId}/notes`, { method: "POST", body: JSON.stringify({ content: data.get("content") }) }); event.currentTarget.reset(); await loadDetail(serviceId); }
    catch (cause) { setError(cause instanceof Error ? cause.message : "Could not add the note."); } finally { setBusy(false); }
  }
  async function submitPrompt(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!serviceId) return; const data = new FormData(event.currentTarget); setBusy(true);
    try { await api(`/staff/services/${serviceId}/prompts`, { method: "POST", body: JSON.stringify({ message: data.get("message"), offset_seconds: Number(data.get("offset")) || 0 }) }); event.currentTarget.reset(); await loadDetail(serviceId); }
    catch (cause) { setError(cause instanceof Error ? cause.message : "Could not add the prompt."); } finally { setBusy(false); }
  }
  async function firePrompt(prompt: Prompt) {
    if (!serviceId) return; setBusy(true);
    try { await api(`/staff/services/${serviceId}/prompts/${prompt.id}/fire`, { method: "POST" }); await loadDetail(serviceId); }
    catch (cause) { setError(cause instanceof Error ? cause.message : "Could not fire the prompt."); } finally { setBusy(false); }
  }
  async function submitPoll(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!serviceId) return; const data = new FormData(event.currentTarget); const options = String(data.get("options")).split("\n").map(item => item.trim()).filter(Boolean); setBusy(true);
    try { await api(`/staff/services/${serviceId}/polls`, { method: "POST", body: JSON.stringify({ question: data.get("question"), options }) }); event.currentTarget.reset(); await loadDetail(serviceId); }
    catch (cause) { setError(cause instanceof Error ? cause.message : "Could not create the poll."); } finally { setBusy(false); }
  }
  async function pollStatus(poll: Poll, status: "open" | "closed") {
    if (!serviceId) return; setBusy(true);
    try { await api(`/staff/services/${serviceId}/polls/${poll.id}/transition`, { method: "POST", body: JSON.stringify({ status }) }); await loadDetail(serviceId); }
    catch (cause) { setError(cause instanceof Error ? cause.message : "Could not change poll status."); } finally { setBusy(false); }
  }
  async function configureStream(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!serviceId) return; const data = new FormData(event.currentTarget); setBusy(true); setError("");
    try { await api(`/staff/services/${serviceId}/stream`, { method: "PUT", body: JSON.stringify({ provider: data.get("provider"), playback_url: data.get("playback_url"), lower_bandwidth_url: data.get("lower_bandwidth_url") || null, captions_url: data.get("captions_url") || null, fallback_message: data.get("fallback_message") || null }) }); await loadDetail(serviceId); }
    catch (cause) { setError(cause instanceof Error ? cause.message : "Could not configure the live stream."); } finally { setBusy(false); }
  }
  async function updateStreamHealth(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!serviceId || !stream) return; const data = new FormData(event.currentTarget); setBusy(true); setError("");
    try { await api(`/staff/services/${serviceId}/stream/health`, { method: "PATCH", body: JSON.stringify({ ingest_health: data.get("ingest_health"), playback_health: data.get("playback_health"), reason: data.get("reason") || null }) }); await Promise.all([loadDetail(serviceId), loadServices()]); }
    catch (cause) { setError(cause instanceof Error ? cause.message : "Could not update stream health."); } finally { setBusy(false); }
  }
  async function probeStream() {
    if (!serviceId || !stream) return; setBusy(true); setError(""); setStreamNotice("");
    try { const result = await api<{data:{status:string;http_status:number|null;latency_ms:number}}>(`/staff/services/${serviceId}/stream/probe`, { method: "POST" }); await loadDetail(serviceId); setStreamNotice(`Playback probe: ${result.data.status} · ${result.data.http_status ?? "no response"} · ${result.data.latency_ms} ms`); }
    catch (cause) { setError(cause instanceof Error ? cause.message : "The playback probe could not run."); } finally { setBusy(false); }
  }

  return <section className="admin-section producer-section"><div className="section-title"><div><p className="eyebrow">Producer workspace</p><h2>Service control room</h2></div><p>Prepare each service, then operate its live cues from one place.</p></div>
    <div className="producer-service-select"><label>Service<select value={serviceId} onChange={event => setServiceId(event.target.value)}>{services.map(service => <option key={service.id} value={service.id}>{service.title} · {service.state}{service.rehearsal_mode ? " · rehearsal" : ""}</option>)}</select></label><button className="text-button" onClick={() => void loadServices()} disabled={busy}>Refresh services</button></div>
    <div className="stream-control-grid">
      <article className="stream-configuration"><div className="stream-heading"><div><p className="eyebrow">Broadcast delivery</p><h3>Live video source</h3></div>{stream && <span className={`stream-health ${stream.playback_health}`}>{stream.playback_health}</span>}</div><p>Connect a ministry-owned YouTube, Vimeo or HTTPS HLS broadcast. Stream keys stay with the chosen provider and are never entered here.</p><form onSubmit={configureStream}><label>Provider<select name="provider" defaultValue={stream?.provider || "youtube"}><option value="youtube">YouTube Live</option><option value="vimeo">Vimeo Live</option><option value="hls">HLS / broadcast CDN</option></select></label><label>Public playback URL<input name="playback_url" type="url" key={`${stream?.id}-playback`} defaultValue={stream?.playback_url || (stream?.provider === "youtube" && stream.provider_stream_id ? `https://youtube.com/watch?v=${stream.provider_stream_id}` : stream?.provider === "vimeo" && stream.provider_stream_id ? `https://vimeo.com/${stream.provider_stream_id}` : "")} placeholder="https://youtube.com/watch?v=…" required /></label><div className="form-grid"><label>Lower bandwidth HLS <span>optional</span><input name="lower_bandwidth_url" type="url" defaultValue={stream?.lower_bandwidth_url || ""} placeholder="https://cdn.example/low.m3u8" /></label><label>Captions file <span>optional</span><input name="captions_url" type="url" defaultValue={stream?.captions_url || ""} placeholder="https://cdn.example/captions.vtt" /></label></div><label>Outage message<textarea name="fallback_message" defaultValue={stream?.fallback_message || ""} placeholder="The video is temporarily unavailable. Please stay with us while the team restores it." /></label><button className="workspace-primary-button" disabled={busy}>{busy ? "Saving…" : stream ? "Update stream" : "Connect stream"}</button></form></article>
      <article className="stream-operations"><div className="stream-heading"><div><p className="eyebrow">Operator health</p><h3>Broadcast status</h3></div><span className={`stream-health ${stream?.ingest_health || "unknown"}`}>{stream?.ingest_health || "not configured"}</span></div>{stream ? <><form onSubmit={updateStreamHealth}><div className="form-grid"><label>Encoder / ingest<select name="ingest_health" defaultValue={stream.ingest_health}><option value="unknown">Unknown</option><option value="healthy">Healthy</option><option value="degraded">Degraded</option><option value="offline">Offline</option></select></label><label>Viewer playback<select name="playback_health" defaultValue={stream.playback_health}><option value="unknown">Unknown</option><option value="healthy">Healthy</option><option value="degraded">Degraded</option><option value="offline">Offline</option></select></label></div><label>Operator note<input name="reason" placeholder="Optional incident or verification note" /></label><div className="stream-button-row"><button className="workspace-secondary-button" disabled={busy}>Record health check</button><button type="button" className="workspace-secondary-button" disabled={busy} onClick={()=>void probeStream()}>Run playback probe</button></div></form>{streamNotice&&<p className="form-success">{streamNotice}</p>}{stream.last_probed_at&&<p className="probe-summary">Last probe: <strong>{stream.last_probe_status}</strong> · HTTP {stream.last_probe_http_status??"none"} · {stream.last_probe_latency_ms??0} ms · {new Date(stream.last_probed_at).toLocaleString()}</p>}<div className="stream-event-list"><strong>Recent activity</strong>{streamEvents.slice(0,6).map(item=><p key={item.id}><span>{item.event_type.replaceAll("_", " ")}</span><time>{new Date(item.created_at).toLocaleString([], { dateStyle: "short", timeStyle: "short" })}</time></p>)}</div></> : <div className="empty-workspace"><span>◉</span><div><strong>No video source connected</strong><p>Save the public playback source before running health checks.</p></div></div>}</article>
    </div>
    <ChatModerationPanel serviceId={serviceId}/>
    <PlaybackDiagnosticsPanel serviceId={serviceId}/>
    <RegionalProbePanel serviceId={serviceId} configured={Boolean(stream)}/>
    <CapacityRehearsalPanel serviceId={serviceId}/>
    <div className="producer-grid">
      <article><h3>Service templates</h3><div className="producer-list">{templates.map(template => <p key={template.id}><strong>{template.title}</strong><span>{template.default_duration_minutes} min · {template.timezone}</span></p>)}</div><form onSubmit={submitTemplate}><input name="title" placeholder="Sunday worship template" required /><input name="timezone" placeholder="America/New_York" required /><input name="duration" type="number" defaultValue="90" min="1" /><button className="text-button" disabled={busy}>Add template</button></form></article>
      <article><h3>Run sheet</h3><div className="producer-list">{runSheet.map(item => <p key={item.id}><strong>{item.title}</strong><span>{item.status}{item.duration_seconds ? ` · ${Math.round(item.duration_seconds / 60)} min` : ""}</span><button className="text-button" onClick={() => setRunStatus(item, item.status === "active" ? "complete" : "active")} disabled={busy}>{item.status === "active" ? "Complete" : "Start"}</button></p>)}</div><form onSubmit={submitRunSheet}><input name="title" placeholder="Segment title" required /><input name="duration" type="number" placeholder="Minutes" min="0" /><button className="text-button" disabled={busy}>Add segment</button></form></article>
      <article><h3>Notes</h3><div className="producer-list">{notes.map(note => <p key={note.id}>{note.content}</p>)}</div><form onSubmit={submitNote}><textarea name="content" placeholder="Internal producer note" required /><button className="text-button" disabled={busy}>Add note</button></form></article>
      <article><h3>Timed prompts</h3><div className="producer-list">{prompts.map(prompt => <p key={prompt.id}><strong>{prompt.message}</strong><span>{prompt.offset_seconds}s · {prompt.fired ? "Fired" : "Ready"}</span>{!prompt.fired && <button className="text-button" onClick={() => firePrompt(prompt)} disabled={busy}>Fire</button>}</p>)}</div><form onSubmit={submitPrompt}><input name="message" placeholder="Prompt message" required /><input name="offset" type="number" placeholder="Offset seconds" min="0" /><button className="text-button" disabled={busy}>Add prompt</button></form></article>
      <article className="producer-polls"><h3>Live polls</h3><div className="producer-list">{polls.map(poll => <p key={poll.id}><strong>{poll.question}</strong><span>{poll.status} · {poll.options.map(option => `${option.label}: ${option.responses ?? 0}`).join(" · ")}</span>{poll.status === "draft" && <button className="text-button" onClick={() => pollStatus(poll, "open")} disabled={busy}>Open</button>}{poll.status === "open" && <button className="text-button" onClick={() => pollStatus(poll, "closed")} disabled={busy}>Close</button>}</p>)}</div><form onSubmit={submitPoll}><input name="question" placeholder="Ask a question" required /><textarea name="options" placeholder={"One option per line\nAt least two options"} required /><button className="text-button" disabled={busy}>Create poll</button></form></article>
    </div>{error && <p className="form-error" role="alert">{error}</p>}
  </section>;
}
