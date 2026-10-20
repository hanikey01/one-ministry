"use client";

import { FormEvent, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { api, type Identity } from "./api";

type Entry = Record<string, unknown>;
export type OperationView = "people" | "duplicates" | "follow_up" | "birthdays" | "groups" | "events" | "volunteers" | "campaigns" | "meetings" | "media" | "giving" | "reports";

const views: Array<{ key: OperationView; label: string; permission: string; path: string }> = [
  { key: "people", label: "People", permission: "services.manage", path: "/staff/people" },
  { key: "duplicates", label: "Duplicates", permission: "services.manage", path: "/staff/people/duplicates" },
  { key: "follow_up", label: "Follow-up", permission: "services.manage", path: "/staff/follow-up-tasks" },
  { key: "birthdays", label: "Birthdays", permission: "community.manage", path: "/staff/celebrations/birthdays" },
  { key: "groups", label: "Groups", permission: "services.manage", path: "/staff/groups" },
  { key: "events", label: "Events", permission: "services.manage", path: "/staff/events" },
  { key: "volunteers", label: "Volunteers", permission: "community.manage", path: "/staff/volunteer-assignments" },
  { key: "campaigns", label: "Campaigns", permission: "communications.manage", path: "/staff/campaigns" },
  { key: "meetings", label: "Meetings", permission: "services.manage", path: "/staff/meetings" },
  { key: "media", label: "Media", permission: "services.manage", path: "/staff/media" },
  { key: "giving", label: "Giving", permission: "finance.view", path: "/staff/giving/donations" },
  { key: "reports", label: "Reports", permission: "reports.view", path: "/staff/reports/services" },
];

function listFrom(payload: unknown): Entry[] {
  if (!payload || typeof payload !== "object") return [];
  const data = (payload as { data?: unknown }).data;
  if (Array.isArray(data)) return data as Entry[];
  if (data && typeof data === "object" && Array.isArray((data as { data?: unknown }).data)) return (data as { data: Entry[] }).data;
  return [];
}

function primary(entry: Entry): string {
  const person = [entry.first_name, entry.last_name].filter(Boolean).join(" ");
  return String(person || (entry.name ?? entry.title ?? entry.summary ?? entry.reference ?? entry.id ?? "Untitled record"));
}

function detail(entry: Entry): string {
  return [entry.triage, entry.status, entry.assignee_name, entry.due_at, entry.email, entry.starts_at, entry.created_at].filter(Boolean).map(String).join(" · ");
}

export default function OperationsPanel({ identity, initialView, hideTabs = false }: { identity: Identity; initialView?: OperationView; hideTabs?: boolean }) {
  const available = useMemo(() => views.filter(view => identity.permissions.includes(view.permission)), [identity.permissions]);
  const [view, setView] = useState<OperationView>(initialView && available.some(item => item.key === initialView) ? initialView : available[0]?.key ?? "people");
  const [records, setRecords] = useState<Entry[]>([]);
  const [report, setReport] = useState<Entry | null>(null);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [personName, setPersonName] = useState("");
  const [personEmail, setPersonEmail] = useState("");

  const active = available.find(item => item.key === view) ?? available[0];

  async function load() {
    if (!active) return;
    setBusy(true); setError(""); setReport(null);
    try {
      const response = await api<unknown>(active.path);
      if (view === "reports") setReport((response as { data?: { metrics?: Entry } }).data?.metrics ?? null);
      else setRecords(listFrom(response));
    } catch (cause) {
      setRecords([]);
      setError(cause instanceof Error ? cause.message : "Could not load this workspace.");
    } finally { setBusy(false); }
  }

  useEffect(() => { void load(); }, [view]);

  async function createPerson(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError("");
    try {
      const response = await api<{ data: Entry }>("/staff/people", { method: "POST", body: JSON.stringify({ first_name: personName, email: personEmail || undefined, consent_to_contact: Boolean(personEmail) }) });
      setRecords(current => [response.data, ...current]); setPersonName(""); setPersonEmail("");
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Could not add the person."); }
    finally { setBusy(false); }
  }

  async function updateTask(id: string, status: "in_progress" | "completed") {
    setBusy(true); setError("");
    try { await api(`/staff/follow-up-tasks/${id}`, { method: "PATCH", body: JSON.stringify({ status }) }); await load(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : "Could not update the task."); }
    finally { setBusy(false); }
  }

  async function updateVolunteer(id: string, status: "accepted" | "declined") {
    setBusy(true); setError("");
    try { await api(`/staff/volunteer-assignments/${id}`, { method: "PATCH", body: JSON.stringify({ status }) }); await load(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : "Could not update the assignment."); }
    finally { setBusy(false); }
  }

  async function requestSubstitute(id: string) {
    setBusy(true); setError("");
    try { await api(`/staff/volunteer-assignments/${id}/substitution`, { method: "POST", body: JSON.stringify({}) }); await load(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : "Could not request a substitute."); }
    finally { setBusy(false); }
  }

  if (!available.length) return null;

  return <section id="operations" className="workspace-panel operations-section">
    <div className="section-title"><div><p className="eyebrow">Ministry operations</p><h2>Work queues and records</h2></div><p>Each area only appears when your staff role permits access.</p></div>
    {!hideTabs && <div className="operations-tabs" role="tablist" aria-label="Ministry operations">
      {available.map(item => <button key={item.key} type="button" role="tab" aria-selected={view === item.key} className={view === item.key ? "active" : ""} onClick={() => setView(item.key)}>{item.label}</button>)}
    </div>}
    <div className="operations-toolbar"><div><h3>{active?.label}</h3><p>{view === "reports" ? "Definitions are included with each report metric." : "Recent records available within your current permission scope."}</p></div><button className="text-button" type="button" onClick={() => void load()} disabled={busy}>{busy ? "Refreshing…" : "Refresh"}</button></div>
    {view === "people" && <form className="quick-add-form" onSubmit={createPerson}><label>Name<input value={personName} onChange={event => setPersonName(event.target.value)} required maxLength={100} placeholder="First name" /></label><label>Email <span>(optional)</span><input type="email" value={personEmail} onChange={event => setPersonEmail(event.target.value)} placeholder="person@example.org" /></label><button className="button button-primary" disabled={busy}>Add person</button></form>}
    {report ? <dl className="report-metrics">{Object.entries(report).map(([key, value]) => <div key={key}><dt>{key.replaceAll("_", " ")}</dt><dd>{String(value)}</dd></div>)}</dl> : <div className="operations-list">{records.length ? records.map((record, index) => <article key={String(record.id ?? index)}><strong>{primary(record)}</strong><span>{detail(record) || "Record ready for review"}</span>{view === "people" && <Link className="workspace-secondary-button" href={`/admin/people/${String(record.id)}`}>Open profile</Link>}{view === "groups" && <Link className="workspace-primary-button" href={`/admin/groups/${String(record.id)}`}>Manage group</Link>}{view === "events" && <Link className="workspace-primary-button" href={`/admin/events/${String(record.id)}`}>Manage event</Link>}{view === "follow_up" && <span><button className="workspace-secondary-button" disabled={busy} onClick={() => void updateTask(String(record.id), "in_progress")}>Start</button><button className="workspace-primary-button" disabled={busy} onClick={() => void updateTask(String(record.id), "completed")}>Complete</button></span>}{view === "volunteers" && <span><button className="workspace-primary-button" disabled={busy} onClick={() => void updateVolunteer(String(record.id), "accepted")}>Accept</button><button className="workspace-secondary-button" disabled={busy} onClick={() => void updateVolunteer(String(record.id), "declined")}>Decline</button><button className="workspace-secondary-button" disabled={busy} onClick={() => void requestSubstitute(String(record.id))}>Need substitute</button></span>}</article>) : !busy && <div className="empty-workspace"><span>◌</span><div><strong>No records yet</strong><p>This workspace will show items as your ministry begins using it.</p></div></div>}</div>}
    {error && <p className="form-error" role="alert">{error}</p>}
  </section>;
}
