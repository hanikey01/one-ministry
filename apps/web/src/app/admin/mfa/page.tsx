"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { api } from "../api";

export default function MfaPage() {
  const router = useRouter();
  const [setup, setSetup] = useState<{ secret: string; otpauth_url: string } | null>(null);
  const [code, setCode] = useState(""); const [error, setError] = useState(""); const [codes, setCodes] = useState<string[]>([]);
  useEffect(() => { void api<{ data: { enabled: boolean } }>("/auth/mfa").then(r => { if (!r.data.enabled) return api<{ data: { secret: string; otpauth_url: string } }>("/auth/mfa/setup").then(s => setSetup(s.data)); }).catch(() => setError("Sign in to manage multifactor authentication.")); }, []);
  async function enable(event: FormEvent) { event.preventDefault(); setError(""); try { const response = await api<{ data: { recovery_codes: string[] } }>("/auth/mfa/enable", { method: "POST", body: JSON.stringify({ code }) }); setCodes(response.data.recovery_codes); } catch (cause) { setError(cause instanceof Error ? cause.message : "Could not enable multifactor authentication."); } }
  async function verify(event: FormEvent) { event.preventDefault(); setError(""); try { await api("/auth/mfa/verify", { method: "POST", body: JSON.stringify({ code }) }); router.replace("/admin"); } catch (cause) { setError(cause instanceof Error ? cause.message : "Verification failed."); } }
  return <main className="admin-page"><div className="admin-card login-card"><Link href="/admin" className="admin-back">← Administration</Link><p className="eyebrow">Account security</p><h1>Multifactor authentication</h1>{codes.length ? <><p>Save these recovery codes somewhere safe. Each code works once.</p><pre>{codes.join("\n")}</pre><Link href="/admin">Continue to administration</Link></> : setup ? <><p>Add this secret to an authenticator app, then enter its current six-digit code.</p><code>{setup.secret}</code><form className="admin-form" onSubmit={enable}><label>Authenticator code<input inputMode="numeric" autoComplete="one-time-code" value={code} onChange={e => setCode(e.target.value)} required /></label><button className="button button-primary">Enable multifactor authentication</button></form></> : <form className="admin-form" onSubmit={verify}><p>Enter your authenticator or recovery code to continue with protected actions.</p><label>Verification code<input autoComplete="one-time-code" value={code} onChange={e => setCode(e.target.value)} required /></label><button className="button button-primary">Verify</button></form>}{error && <p className="form-error" role="alert">{error}</p>}</div></main>;
}
