"use client";

import { FormEvent, useState } from "react";
import Link from "next/link";
import { api } from "../api";

export default function AcceptInvitation() {
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [error, setError] = useState("");
  const [complete, setComplete] = useState(false);
  const [busy, setBusy] = useState(false);

  async function accept(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError("");
    try {
      const token = new URLSearchParams(window.location.hash.slice(1)).get("token") ?? "";
      if (!token) throw new Error("This invitation link is incomplete.");
      await api<void>("/auth/csrf");
      await api<{ data: { status: string } }>("/auth/invitations/accept", {
        method: "POST", body: JSON.stringify({ token, password, password_confirmation: confirmation }),
      });
      window.history.replaceState(null, "", "/admin/accept-invitation");
      setComplete(true);
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Could not accept invitation."); }
    finally { setBusy(false); }
  }

  return <main className="admin-page"><div className="admin-card login-card">
    <p className="eyebrow">Staff invitation</p><h1>{complete ? "Account ready" : "Accept your invitation"}</h1>
    {complete ? <p>Your account is ready. <Link href="/admin/login">Sign in</Link> to continue.</p> : <form className="admin-form" onSubmit={accept}>
      <label>Password<input type="password" autoComplete="new-password" minLength={12} value={password} onChange={event => setPassword(event.target.value)} required /></label>
      <label>Confirm password<input type="password" autoComplete="new-password" value={confirmation} onChange={event => setConfirmation(event.target.value)} required /></label>
      {error && <p className="form-error" role="alert">{error}</p>}
      <button className="button button-primary" disabled={busy}>{busy ? "Creating account…" : "Create account"}</button>
    </form>}
  </div></main>;
}
