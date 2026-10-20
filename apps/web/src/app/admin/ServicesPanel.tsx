"use client";

import { FormEvent, useEffect, useState } from "react";
import { api, type ServiceOccurrence } from "./api";

const transitions: Record<ServiceOccurrence["state"], Array<{ label: string; state: ServiceOccurrence["state"] }>> = {
  draft: [{ label: "Schedule", state: "scheduled" }],
  scheduled: [{ label: "Mark ready", state: "ready" }],
  ready: [{ label: "Go live", state: "live" }],
  live: [{ label: "End service", state: "ended" }, { label: "Mark interrupted", state: "interrupted" }],
  interrupted: [{ label: "Resume live", state: "live" }, { label: "End service", state: "ended" }],
  ended: [],
  cancelled: [],
};

function localStartValue() {
  const now = new Date();
  now.setMinutes(now.getMinutes() - now.getTimezoneOffset() + 60, 0, 0);
  return now.toISOString().slice(0, 16);
}

export default function ServicesPanel() {
  const [services, setServices] = useState<ServiceOccurrence[]>([]);
  const [title, setTitle] = useState("");
  const [startsAt, setStartsAt] = useState(localStartValue);
  const [timezone, setTimezone] = useState(() => Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC");
  const [manualOverride, setManualOverride] = useState(false);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  useEffect(() => { api<{ data: ServiceOccurrence[] }>("/staff/services").then(response => setServices(response.data)).catch(cause => setError(cause instanceof Error ? cause.message : "Could not load services.")); }, []);

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError("");
    try {
      const response = await api<{ data: ServiceOccurrence }>("/staff/services", { method: "POST", body: JSON.stringify({ title, timezone, starts_at: new Date(startsAt).toISOString(), manual_stream_override: manualOverride }) });
      setServices(current => [response.data, ...current]); setTitle(""); setStartsAt(localStartValue()); setManualOverride(false);
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Could not create the service."); } finally { setBusy(false); }
  }

  async function transition(service: ServiceOccurrence, state: ServiceOccurrence["state"]) {
    setBusy(true); setError("");
    try {
      const response = await api<{ data: ServiceOccurrence }>(`/staff/services/${service.id}/transition`, { method: "POST", body: JSON.stringify({ state }) });
      setServices(current => current.map(item => item.id === service.id ? response.data : item));
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Could not change the service state."); } finally { setBusy(false); }
  }

  return <section className="admin-section services-section"><div className="section-title"><div><p className="eyebrow">Digital church</p><h2>Service control room</h2></div><p>Plan worship, confirm readiness, and move through the live-service lifecycle.</p></div>
    <div className="service-list">{services.length ? services.map(service => <article className="service-row" key={service.id}><div><span className={`service-state ${service.state}`}>{service.state}</span><h3>{service.title}</h3><p>{new Date(service.starts_at).toLocaleString([], { dateStyle: "medium", timeStyle: "short" })} · {service.timezone}</p></div><div className="item-actions">{transitions[service.state].map(next => <button className={next.state === "live" ? "button button-primary service-action" : "text-button"} disabled={busy} key={next.state} onClick={() => transition(service, next.state)}>{next.label}</button>)}</div></article>) : <div className="empty-workspace"><span>✦</span><div><strong>No services yet</strong><p>Create the next service to begin preparing the worship room.</p></div></div>}</div>
    <form className="admin-form service-create-form" onSubmit={create}><h3>Plan a service</h3><div className="form-grid"><label>Service title<input value={title} onChange={event => setTitle(event.target.value)} maxLength={160} placeholder="Sunday worship" required /></label><label>Start time<input type="datetime-local" value={startsAt} onChange={event => setStartsAt(event.target.value)} required /></label></div><label>Time zone<input value={timezone} onChange={event => setTimezone(event.target.value)} required /></label><label className="checkbox-label"><input type="checkbox" checked={manualOverride} onChange={event => setManualOverride(event.target.checked)} /> Stream is manually confirmed for this preview</label><button className="button button-primary" disabled={busy}>{busy ? "Saving…" : "Create service"}</button></form>{error && <p className="form-error" role="alert">{error}</p>}
  </section>;
}
