"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";

type EventItem = { id: string; title: string; description: string | null; starts_at: string; location: string | null; capacity: number | null };

export default function EventsPage() {
  const [events, setEvents] = useState<EventItem[]>([]);
  const [selected, setSelected] = useState<EventItem | null>(null);
  const [message, setMessage] = useState("");

  useEffect(() => { fetch("/api/v1/public/events").then(response => response.json()).then(payload => setEvents(payload.data ?? [])).catch(() => setMessage("Events are unavailable right now.")); }, []);

  async function register(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!selected) return;
    const form = new FormData(event.currentTarget);
    const response = await fetch(`/api/v1/public/events/${selected.id}/registrations`, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ name: form.get("name"), email: form.get("email") }) });
    const payload = await response.json().catch(() => ({}));
    setMessage(response.ok ? payload.data?.status === "waitlisted" ? `The event is full. You joined the waitlist for ${selected.title}.` : `You are registered for ${selected.title}.` : response.status === 409 ? "This event is full and its waitlist is closed." : "We could not complete your registration.");
    if (response.ok) setSelected(null);
  }

  return <main className="catalog-page"><header className="catalog-header"><Link className="brand" href="/"><span className="brand-mark">✦</span>Ministry Platform</Link><Link href="/" className="worship-back">← Home</Link></header><section className="catalog-hero"><p className="eyebrow">Gather together</p><h1>Upcoming events</h1><p>Find a gathering, reserve your place, and make a meaningful connection.</p></section><section className="catalog-grid">{events.map(item => <article className="catalog-card" key={item.id}><p>{new Date(item.starts_at).toLocaleString([], { dateStyle: "full", timeStyle: "short" })}</p><h2>{item.title}</h2><span>{item.location || "Location to be announced"}</span><p>{item.description || "More details will be shared by the ministry."}</p><button className="button button-primary" onClick={() => setSelected(item)}>Register</button></article>)}{!events.length && <p className="catalog-empty">No upcoming events are published yet.</p>}</section>{selected && <div className="catalog-dialog" role="dialog" aria-modal="true" aria-labelledby="registration-title"><form onSubmit={register}><button type="button" className="text-button dialog-close" onClick={() => setSelected(null)}>Close</button><p className="eyebrow">Event registration</p><h2 id="registration-title">{selected.title}</h2><label>Your name<input name="name" required maxLength={160} /></label><label>Email <span>(optional)</span><input name="email" type="email" /></label><button className="button button-primary">Complete registration</button></form></div>}{message && <p className="catalog-notice" role="status">{message}</p>}</main>;
}
