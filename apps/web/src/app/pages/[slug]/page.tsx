import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";

export const dynamic = "force-dynamic";

type PublicPage = { title: string; slug: string; body: string | null; seo_title: string | null; seo_description: string | null; updated_at: string };
type Site = { name?: string; tagline?: string | null; primary_color?: string; accent_color?: string };
type RouteProps = { params: Promise<{ slug: string }> };

const apiUrl = process.env.API_INTERNAL_URL ?? "http://127.0.0.1:8000";

async function pageBySlug(slug: string): Promise<PublicPage | null> {
  const response = await fetch(`${apiUrl}/api/v1/public/pages/${encodeURIComponent(slug)}`, { cache: "no-store" });
  if (response.status === 404) return null;
  if (!response.ok) throw new Error("The page could not be loaded.");
  return (await response.json()).data;
}

async function site(): Promise<Site> {
  try {
    const response = await fetch(`${apiUrl}/api/v1/public/site`, { cache: "no-store" });
    return response.ok ? (await response.json()).data : {};
  } catch { return {}; }
}

export async function generateMetadata({ params }: RouteProps): Promise<Metadata> {
  const { slug } = await params;
  const page = await pageBySlug(slug);
  if (!page) return { title: "Page not found" };
  return { title: page.seo_title || page.title, description: page.seo_description || undefined };
}

export default async function PublicContentPage({ params }: RouteProps) {
  const { slug } = await params;
  const [page, ministry] = await Promise.all([pageBySlug(slug), site()]);
  if (!page) notFound();
  const colors = { "--page-primary": ministry.primary_color || "#173b31", "--page-accent": ministry.accent_color || "#d8ad58" } as React.CSSProperties;
  return <main className="public-page" style={colors}>
    <header className="public-page-header"><Link href="/" className="public-page-brand"><span>✦</span>{ministry.name || "Ministry Platform"}</Link><Link href="/">Home</Link></header>
    <section className="public-page-hero"><p>Our ministry</p><h1>{page.title}</h1>{page.seo_description && <span>{page.seo_description}</span>}</section>
    <article className="public-page-body">{page.body || "Content for this page will be available soon."}</article>
    <footer className="public-page-footer"><strong>{ministry.name || "Ministry Platform"}</strong><span>{ministry.tagline || "Faith, community and service."}</span></footer>
  </main>;
}
