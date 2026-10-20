"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { api, type Identity } from "../api";

export default function AdminLogin() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true); setError("");
    try {
      await api<void>("/auth/csrf");
      await api<{ data: Identity }>("/auth/login", { method: "POST", body: JSON.stringify({ email, password }) });
      router.replace("/admin");
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Sign in failed.");
    } finally { setBusy(false); }
  }

  return <main className="admin-page"><div className="admin-card login-card">
    <Link href="/" className="admin-back">← Return to site</Link>
    <p className="eyebrow">Ministry staff</p><h1>Sign in</h1>
    <p>Use your staff account to manage this ministry.</p>
    <form onSubmit={submit} className="admin-form">
      <label>Email address<input type="email" autoComplete="username" value={email} onChange={event => setEmail(event.target.value)} required /></label>
      <label>Password<input type="password" autoComplete="current-password" value={password} onChange={event => setPassword(event.target.value)} required /></label>
      {error && <p className="form-error" role="alert">{error}</p>}
      <button className="button button-primary" disabled={busy}>{busy ? "Signing in…" : "Sign in"}</button>
    </form>
    <p><Link href="/admin/forgot-password">Forgot your password?</Link></p>
  </div></main>;
}
