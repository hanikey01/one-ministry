"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { api } from "../api";

export default function VerifyEmail() {
  const [message, setMessage] = useState("Verifying your email…");
  useEffect(() => { const token = new URLSearchParams(window.location.hash.slice(1)).get("token") ?? ""; void Promise.resolve().then(async () => { if (!token) throw new Error("This verification link is incomplete."); await api("/auth/email/verify", { method: "POST", body: JSON.stringify({ token }) }); }).then(() => { window.history.replaceState(null, "", "/admin/verify-email"); setMessage("Your email has been verified."); }).catch(cause => setMessage(cause instanceof Error ? cause.message : "Could not verify your email.")); }, []);
  return <main className="admin-page"><div className="admin-card login-card"><p className="eyebrow">Staff account</p><h1>Email verification</h1><p>{message}</p><Link href="/admin/login">Sign in</Link></div></main>;
}
