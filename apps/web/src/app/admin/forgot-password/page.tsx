"use client";

import { FormEvent, useState } from "react";
import Link from "next/link";
import { api } from "../api";

export default function ForgotPassword() {
  const [email, setEmail] = useState("");
  const [error, setError] = useState("");
  const [sent, setSent] = useState(false);
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError("");
    try {
      await api<void>("/auth/csrf");
      await api("/auth/password/reset-request", { method: "POST", body: JSON.stringify({ email }) });
      setSent(true);
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Could not request a reset."); }
    finally { setBusy(false); }
  }

  return <main className="admin-page"><div className="admin-card login-card">
    <Link href="/admin/login" className="admin-back">← Back to sign in</Link>
    <p className="eyebrow">Staff account</p><h1>Reset your password</h1>
    {sent ? <p>If that address has an account, a reset link has been sent. Check your inbox.</p> : <form className="admin-form" onSubmit={submit}>
      <label>Email address<input type="email" autoComplete="email" value={email} onChange={event => setEmail(event.target.value)} required /></label>
      {error && <p className="form-error" role="alert">{error}</p>}
      <button className="button button-primary" disabled={busy}>{busy ? "Sending…" : "Send reset link"}</button>
    </form>}
  </div></main>;
}
