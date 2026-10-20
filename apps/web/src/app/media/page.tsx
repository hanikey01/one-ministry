"use client";

import { useEffect, useState } from "react";
import Link from "next/link";

type Media = { id: string; title: string; description: string | null; kind: string; published_at: string | null; provider: string };

export default function MediaPage() {
  const [items, setItems] = useState<Media[]>([]);
  useEffect(() => { fetch("/api/v1/public/media").then(response => response.json()).then(payload => setItems(payload.data ?? [])).catch(() => setItems([])); }, []);
  return <main className="catalog-page"><header className="catalog-header"><Link className="brand" href="/"><span className="brand-mark">✦</span>Ministry Platform</Link><Link href="/" className="worship-back">← Home</Link></header><section className="catalog-hero"><p className="eyebrow">Watch and listen</p><h1>Media library</h1><p>Messages, teaching, and resources prepared by your ministry.</p></section><section className="catalog-grid">{items.map(item => <article className="catalog-card" key={item.id}><p>{item.kind}</p><h2>{item.title}</h2><span>{item.published_at ? new Date(item.published_at).toLocaleDateString([], { dateStyle: "long" }) : "Recently added"}</span><p>{item.description || "This resource is ready to explore."}</p><span className="media-provider">Available through {item.provider}</span></article>)}{!items.length && <p className="catalog-empty">Published media will appear here.</p>}</section></main>;
}
