"use client";

import { FormEvent, useState } from "react";
import Link from "next/link";
import { api } from "../api";

export default function ResetPassword() {
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [error, setError] = useState("");
  const [complete, setComplete] = useState(false);
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError("");
    try {
      const params = new URLSearchParams(window.location.hash.slice(1));
      const token = params.get("token") ?? "";
      const email = params.get("email") ?? "";
      if (!token || !email) throw new Error("This reset link is incomplete.");
      await api<void>("/auth/csrf");
      await api("/auth/password/reset", { method: "POST", body: JSON.stringify({ email, token, password, password_confirmation: confirmation }) });
      window.history.replaceState(null, "", "/admin/reset-password");
      setComplete(true);
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Could not reset password."); }
    finally { setBusy(false); }
  }

  return <main className="admin-page"><div className="admin-card login-card">
    <p className="eyebrow">Staff account</p><h1>{complete ? "Password updated" : "Choose a new password"}</h1>
    {complete ? <p>Your existing sessions have been signed out. <Link href="/admin/login">Sign in</Link> with your new password.</p> : <form className="admin-form" onSubmit={submit}>
      <label>New password<input type="password" autoComplete="new-password" minLength={12} value={password} onChange={event => setPassword(event.target.value)} required /></label>
      <label>Confirm password<input type="password" autoComplete="new-password" value={confirmation} onChange={event => setConfirmation(event.target.value)} required /></label>
      {error && <p className="form-error" role="alert">{error}</p>}
      <button className="button button-primary" disabled={busy}>{busy ? "Updating…" : "Reset password"}</button>
    </form>}
  </div></main>;
}
