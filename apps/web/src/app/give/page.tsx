"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";

type Fund = { id: string; name: string; description: string | null };

export default function GivePage() {
  const [funds, setFunds] = useState<Fund[]>([]);
  const [notice, setNotice] = useState("");
  useEffect(() => { fetch("/api/v1/public/giving/funds").then(response => response.json()).then(payload => setFunds(payload.data ?? [])).catch(() => setNotice("Giving is not available right now.")); }, []);
  async function begin(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    const response = await fetch("/api/v1/public/giving/intents", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ fund_id: data.get("fund_id"), amount_minor: Math.round(Number(data.get("amount")) * 100), currency: "USD", receipt_email: data.get("email") }) });
    setNotice(response.ok ? "Your secure payment provider will be connected here after giving is configured." : "We could not begin this gift.");
  }
  return <main className="catalog-page"><header className="catalog-header"><Link className="brand" href="/"><span className="brand-mark">✦</span>Ministry Platform</Link><Link href="/" className="worship-back">← Home</Link></header><section className="catalog-hero"><p className="eyebrow">Give with purpose</p><h1>Support the work</h1><p>Choose a fund and give securely through your ministry’s payment provider.</p></section><section className="giving-card"><form onSubmit={begin}><label>Fund<select name="fund_id" required>{funds.map(fund => <option key={fund.id} value={fund.id}>{fund.name}</option>)}</select></label><label>Amount (USD)<input name="amount" type="number" min="1" step="0.01" required /></label><label>Receipt email <span>(optional)</span><input name="email" type="email" /></label><button className="button button-primary" disabled={!funds.length}>Continue to secure giving</button></form>{notice && <p className="catalog-notice" role="status">{notice}</p>}</section></main>;
}
