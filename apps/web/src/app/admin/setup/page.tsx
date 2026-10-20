"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { api } from "../api";

const defaultTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC";

export default function InitialSetupPage() {
  const router = useRouter();
  const [form, setForm] = useState({ name: "", domain: "", timezone: defaultTimezone, location: "Main Campus", tagline: "", contact_email: "", primary_color: "#17324D", accent_color: "#D9A441", admin_name: "", admin_email: "", password: "", password_confirmation: "" });
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  function update(key: keyof typeof form, value: string) { setForm(current => ({ ...current, [key]: value })); }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (form.password !== form.password_confirmation) { setError("Password confirmation does not match."); return; }
    setBusy(true); setError("");
    try {
      await api<void>("/auth/csrf");
      const { password_confirmation, ...payload } = form;
      await api<{ data: { status: string } }>("/installation/setup", { method: "POST", body: JSON.stringify(payload) });
      router.replace("/admin/login?setup=complete");
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Setup could not be completed."); } finally { setBusy(false); }
  }

  return <main className="admin-page"><div className="admin-card login-card"><Link href="/" className="admin-back">← Return to site</Link><p className="eyebrow">First-run setup</p><h1>Configure your ministry</h1><p>Use your ministry’s real details. This creates the first staff administrator and cannot be run again.</p><form className="admin-form" onSubmit={submit}>
    <label>Ministry name<input value={form.name} onChange={e => update("name", e.target.value)} maxLength={160} required /></label>
    <label>Public domain<input type="text" placeholder="ministry.org" value={form.domain} onChange={e => update("domain", e.target.value)} maxLength={253} required /></label>
    <label>Time zone<input value={form.timezone} onChange={e => update("timezone", e.target.value)} placeholder="Africa/Lagos" required /></label>
    <label>First location<input value={form.location} onChange={e => update("location", e.target.value)} maxLength={160} required /></label>
    <label>Tagline <span>(optional)</span><input value={form.tagline} onChange={e => update("tagline", e.target.value)} maxLength={240} /></label>
    <label>Public contact email <span>(optional)</span><input type="email" value={form.contact_email} onChange={e => update("contact_email", e.target.value)} /></label>
    <div className="color-fields"><label>Primary color<input type="color" value={form.primary_color} onChange={e => update("primary_color", e.target.value)} /></label><label>Accent color<input type="color" value={form.accent_color} onChange={e => update("accent_color", e.target.value)} /></label></div>
    <h2>Initial administrator</h2>
    <label>Your name<input autoComplete="name" value={form.admin_name} onChange={e => update("admin_name", e.target.value)} maxLength={160} required /></label>
    <label>Your staff email<input type="email" autoComplete="email" value={form.admin_email} onChange={e => update("admin_email", e.target.value)} required /></label>
    <label>Create a password<input type="password" autoComplete="new-password" value={form.password} onChange={e => update("password", e.target.value)} minLength={12} required /></label>
    <label>Confirm password<input type="password" autoComplete="new-password" value={form.password_confirmation} onChange={e => update("password_confirmation", e.target.value)} minLength={12} required /></label>
    {error && <p className="form-error" role="alert">{error}</p>}<button className="button button-primary" disabled={busy}>{busy ? "Configuring…" : "Complete setup"}</button>
  </form></div></main>;
}
