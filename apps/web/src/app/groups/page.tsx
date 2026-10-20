"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";

type Group = { id: string; name: string; description: string | null };

export default function GroupsPage() {
  const [groups, setGroups] = useState<Group[]>([]);
  const [selected, setSelected] = useState<Group | null>(null);
  const [notice, setNotice] = useState("");

  useEffect(() => { fetch("/api/v1/public/groups").then(response => response.json()).then(payload => setGroups(payload.data ?? [])).catch(() => setNotice("Groups are unavailable right now.")); }, []);
  async function request(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!selected) return;
    const form = new FormData(event.currentTarget);
    const response = await fetch(`/api/v1/public/groups/${selected.id}/join-requests`, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ name: form.get("name"), email: form.get("email"), message: form.get("message") }) });
    setNotice(response.ok ? `Your request to join ${selected.name} has been sent.` : "We could not send your request.");
    if (response.ok) setSelected(null);
  }

  return <main className="catalog-page"><header className="catalog-header"><Link className="brand" href="/"><span className="brand-mark">✦</span>Ministry Platform</Link><Link href="/" className="worship-back">← Home</Link></header><section className="catalog-hero"><p className="eyebrow">Find your people</p><h1>Groups and community</h1><p>Find a smaller space to grow, ask questions, and build real relationships.</p></section><section className="catalog-grid">{groups.map(group => <article className="catalog-card" key={group.id}><p>Community group</p><h2>{group.name}</h2><p>{group.description || "Connect with this ministry group."}</p><button className="button button-primary" onClick={() => setSelected(group)}>Request to join</button></article>)}{!groups.length && <p className="catalog-empty">Groups will appear here when they are available.</p>}</section>{selected && <div className="catalog-dialog" role="dialog" aria-modal="true" aria-labelledby="group-request-title"><form onSubmit={request}><button type="button" className="text-button dialog-close" onClick={() => setSelected(null)}>Close</button><p className="eyebrow">Group request</p><h2 id="group-request-title">{selected.name}</h2><label>Your name<input name="name" maxLength={160} required /></label><label>Email <span>(optional)</span><input name="email" type="email" /></label><label>Message <span>(optional)</span><textarea name="message" maxLength={2000} /></label><button className="button button-primary">Send request</button></form></div>}{notice && <p className="catalog-notice" role="status">{notice}</p>}</main>;
}
