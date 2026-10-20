"use client";

import { FormEvent, useEffect, useState } from "react";
import { api } from "./api";

type Invitation = { id: string; name: string; email: string; role_key: string; expires_at: string };
type CreatedInvitation = Invitation & { accept_url: string };

export default function InvitationsPanel() {
  const [invitations, setInvitations] = useState<Invitation[]>([]);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [link, setLink] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api<{ data: Invitation[] }>("/staff/invitations").then(response => setInvitations(response.data))
      .catch(cause => setError(cause instanceof Error ? cause.message : "Could not load invitations."));
  }, []);

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError(""); setLink("");
    try {
      const response = await api<{ data: CreatedInvitation }>("/staff/invitations", {
        method: "POST", body: JSON.stringify({ name, email }),
      });
      setInvitations([response.data, ...invitations]);
      setLink(window.location.origin + response.data.accept_url);
      setName(""); setEmail("");
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Could not create invitation."); }
    finally { setBusy(false); }
  }

  async function revoke(invitation: Invitation) {
    setBusy(true); setError(""); setLink("");
    try {
      await api<void>(`/staff/invitations/${invitation.id}`, { method: "DELETE" });
      setInvitations(invitations.filter(item => item.id !== invitation.id));
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Could not revoke invitation."); }
    finally { setBusy(false); }
  }

  return <section className="admin-section"><h2>Staff invitations</h2><p>Invite a site editor to manage public branding and locations. Share the one-time link privately; it expires in 24 hours.</p>
    <form className="admin-form" onSubmit={create}>
      <label>Name<input value={name} onChange={event => setName(event.target.value)} required maxLength={160} /></label>
      <label>Email<input type="email" value={email} onChange={event => setEmail(event.target.value)} required /></label>
      <button className="button button-primary" disabled={busy}>{busy ? "Creating…" : "Create invitation"}</button>
    </form>
    {link && <div className="private-link" role="status"><strong>Copy this link now</strong><p>It will not be shown again.</p><input readOnly aria-label="One-time invitation link" value={link} onFocus={event => event.target.select()} /></div>}
    <div className="admin-list">{invitations.map(invitation => <div className="admin-list-item" key={invitation.id}>
      <div><strong>{invitation.name}</strong><span>{invitation.email} · Site editor · Expires {new Date(invitation.expires_at).toLocaleString()}</span></div>
      <button className="text-button" disabled={busy} onClick={() => revoke(invitation)}>Revoke</button>
    </div>)}</div>
    {error && <p className="form-error" role="alert">{error}</p>}
  </section>;
}
