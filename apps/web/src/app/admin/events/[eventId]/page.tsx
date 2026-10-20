"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { api } from "../../api";
import AdminShell from "../../AdminShell";

type EventRecord = { id: string; title: string; starts_at: string; location: string | null; capacity: number | null; registration_open: boolean; cancelled_at: string | null };
type Registration = { id: string; name: string; email: string | null; status: string; checked_in_at: string | null };
type Operations = { event: EventRecord; registrations: Registration[] };

export default function EventOperationsPage({ params }: { params: Promise<{ eventId: string }> }) {
  const [eventId, setEventId] = useState("");
  const [data, setData] = useState<Operations | null>(null);
  const [notice, setNotice] = useState("");
  const [busy, setBusy] = useState(true);

  async function load(id: string) {
    setBusy(true); setNotice("");
    try { setData((await api<{ data: Operations }>(`/staff/events/${id}/operations`)).data); }
    catch (cause) { setNotice(cause instanceof Error ? cause.message : "Could not load event operations."); }
    finally { setBusy(false); }
  }
  useEffect(() => { void params.then(({ eventId: id }) => { setEventId(id); void load(id); }); }, [params]);

  async function action(registration: string, operation: "check-in" | "cancel") {
    setNotice("");
    try { await api(`/staff/events/${eventId}/registrations/${registration}/${operation}`, { method: "POST" }); await load(eventId); }
    catch (cause) { setNotice(cause instanceof Error ? cause.message : "Could not update registration."); }
  }
  async function cancelEvent(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault(); const form = new FormData(formEvent.currentTarget);
    try { await api(`/staff/events/${eventId}/cancel`, { method: "POST", body: JSON.stringify({ reason: form.get("reason") }) }); setNotice("Event cancelled and registration closed."); await load(eventId); }
    catch (cause) { setNotice(cause instanceof Error ? cause.message : "Could not cancel event."); }
  }

  return <AdminShell title={data?.event.title || "Event operations"} eyebrow="Gatherings">{() => <main className="workspace-page detail-workspace">
    {!data && busy ? <div className="workspace-loading">Loading event operations…</div> : <>
    <Link href="/admin/events" className="worship-back">← Events</Link>
    <section className="workspace-panel"><p className="eyebrow">Event operations</p><h1>{data?.event.title || "Event"}</h1>{data && <p>{new Date(data.event.starts_at).toLocaleString()} · {data.event.location || "Location to be confirmed"} · {data.registrations.filter(item => item.status === "registered" || item.status === "checked_in").length}{data.event.capacity ? ` / ${data.event.capacity}` : ""} attending</p>}</section>
    <div className="form-grid"><section className="workspace-panel"><h2>Registrations</h2><div className="operations-list">{data?.registrations.map(registration => <article key={registration.id}><strong>{registration.name}</strong><span>{registration.email || "No email"} · {registration.status.replaceAll("_", " ")}</span><span>{registration.status === "registered" && <button className="workspace-primary-button" disabled={busy} onClick={() => void action(registration.id, "check-in")}>Check in</button>}{["registered", "waitlisted"].includes(registration.status) && <button className="workspace-secondary-button" disabled={busy} onClick={() => void action(registration.id, "cancel")}>Cancel registration</button>}</span></article>)}{!data?.registrations.length && <p>No registrations yet.</p>}</div></section>
      <section className="workspace-panel"><h2>Cancel event</h2><p>Closing an event stops new registrations and records the reason for staff reference.</p><form className="quick-add-form" onSubmit={cancelEvent}><label>Cancellation reason<textarea name="reason" required maxLength={2000} /></label><button className="workspace-secondary-button" disabled={busy || Boolean(data?.event.cancelled_at)}>Cancel event</button></form></section></div>
    {notice && <p className="catalog-notice" role="status">{notice}</p>}</>}
  </main>}</AdminShell>;
}
