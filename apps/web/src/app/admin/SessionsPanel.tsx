"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { api } from "./api";

type Session = { id: string; ip_address: string | null; user_agent: string; last_activity: string; current: boolean };

export default function SessionsPanel() {
  const router = useRouter();
  const [sessions, setSessions] = useState<Session[]>([]);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api<{ data: Session[] }>("/auth/sessions").then(response => setSessions(response.data))
      .catch(cause => setError(cause instanceof Error ? cause.message : "Could not load sessions."));
  }, []);

  async function revoke(session: Session) {
    setBusy(true); setError("");
    try {
      await api<void>(`/auth/sessions/${session.id}`, { method: "DELETE" });
      setSessions(sessions.filter(item => item.id !== session.id));
      if (session.current) router.replace("/admin/login");
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Could not revoke session."); }
    finally { setBusy(false); }
  }

  async function revokeAll() {
    setBusy(true); setError("");
    try { await api<void>("/auth/sessions", { method: "DELETE" }); router.replace("/admin/login"); }
    catch (cause) { setError(cause instanceof Error ? cause.message : "Could not revoke sessions."); }
    finally { setBusy(false); }
  }

  return <section className="admin-section"><h2>Your sessions</h2><p>Remove a session you no longer recognize. Removing this session signs you out.</p>
    <div className="admin-list">{sessions.map(session => <div className="admin-list-item" key={session.id}>
      <div><strong>{session.current ? "This session" : "Other session"}</strong><span>{session.ip_address ?? "Unknown address"} · Last active {new Date(session.last_activity).toLocaleString()}</span></div>
      <button className="text-button" disabled={busy} onClick={() => revoke(session)}>Revoke</button>
    </div>)}</div>
    {sessions.length > 0 && <button className="text-button" disabled={busy} onClick={revokeAll}>Sign out everywhere</button>}
    {error && <p className="form-error" role="alert">{error}</p>}
  </section>;
}
