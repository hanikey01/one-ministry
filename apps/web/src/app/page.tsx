import type { CSSProperties } from "react";
import type { Metadata } from "next";
import Link from "next/link";

type Site = {
  name: string;
  tagline: string | null;
  contact_email: string | null;
  colors: { primary: string; accent: string };
  locations: Array<{ id: string; name: string; address: string | null; is_primary: boolean }>;
};
type HomeLead = { lead_state: "carousel" | "upcoming" | "live" | "interrupted"; service_occurrence_id: string | null; service: { title: string; starts_at: string; timezone: string } | null };

async function getSite(): Promise<Site | null> {
  const apiUrl = process.env.API_INTERNAL_URL ?? "http://127.0.0.1:8000";
  try {
    const response = await fetch(`${apiUrl}/api/v1/public/site`, { cache: "no-store" });
    if (!response.ok) return null;
    const payload: { data: Site } = await response.json();
    return payload.data;
  } catch {
    return null;
  }
}
async function getLead(): Promise<HomeLead | null> { const apiUrl = process.env.API_INTERNAL_URL ?? "http://127.0.0.1:8000"; try { const response = await fetch(`${apiUrl}/api/v1/public/home`, { cache: "no-store" }); if (!response.ok) return null; return (await response.json()).data; } catch { return null; } }

export async function generateMetadata(): Promise<Metadata> {
  const site = await getSite();
  return site
    ? { title: site.name, description: site.tagline ?? `Welcome to ${site.name}.` }
    : { title: "Ministry Platform", description: "This ministry is getting ready." };
}

export default async function Home() {
  const site = await getSite();
  const lead = await getLead();
  if (!site) {
    return <main className="setup-state"><div className="setup-orbit setup-orbit-one" /><div className="setup-orbit setup-orbit-two" /><div className="setup-card"><span className="brand-mark" aria-hidden="true">✦</span><p className="eyebrow">Ministry Platform</p><h1>Your ministry, ready to begin.</h1><p>Set up your public website, staff workspace, and digital church from one private installation.</p><a className="button button-primary" href="/admin/setup">Set up this ministry <span aria-hidden="true">→</span></a><p className="setup-note">Already configured? <Link href="/admin/login">Staff sign in</Link></p></div></main>;
  }

  const theme = { "--brand-primary": site.colors.primary, "--brand-accent": site.colors.accent } as CSSProperties;

  return <div className="site-shell" style={theme}>
    <header className="site-header">
      <Link className="brand" href="/" aria-label={`${site.name} home`}><span className="brand-mark" aria-hidden="true">✦</span>{site.name}</Link>
      <nav className="site-nav" aria-label="Main navigation"><a href="#welcome">Home</a><a href="#locations">Visit</a><a href="#about">About</a>{site.contact_email && <a className="nav-cta" href="#connect">Connect</a>}</nav>
    </header>
    <main>
      <section id="welcome" className="hero" aria-labelledby="hero-title">
        <div className="hero-inner"><div className="hero-content"><p className="eyebrow"><span className={lead?.lead_state === "live" ? "live-dot" : ""} />{lead?.lead_state === "live" ? "Live now" : lead?.lead_state === "interrupted" ? "Service update" : "Welcome home"}</p><h1 id="hero-title">{lead?.lead_state === "live" ? lead.service?.title : lead?.lead_state === "upcoming" ? `Coming up: ${lead.service?.title}` : lead?.lead_state === "interrupted" ? "We’ll be right back." : `There’s a place for you here.`}</h1><p className="hero-copy">{lead?.lead_state === "interrupted" ? "Our stream is temporarily unavailable. Please refresh shortly, or get in touch with the ministry." : site.tagline ?? `Join ${site.name} for worship, community, and a life of growing faith.`}</p><div className="hero-actions"><a className="button button-primary" href={lead?.service_occurrence_id && ["live", "interrupted"].includes(lead.lead_state) ? `/worship/${lead.service_occurrence_id}` : "#locations"}>{lead?.lead_state === "live" ? "Join worship" : "Plan your visit"} <span aria-hidden="true">→</span></a>{site.contact_email && <a className="text-link" href="#connect">Get in touch <span aria-hidden="true">↗</span></a>}</div></div><aside className="hero-panel" aria-label="Service information"><div className="hero-panel-icon">✦</div><p>{lead?.lead_state === "live" ? "Worship is happening now" : "You belong here"}</p><strong>{lead?.service?.title ?? "Gather. Grow. Go."}</strong><span>{lead?.lead_state === "upcoming" ? "Next service coming soon" : "A community centered on Jesus"}</span></aside></div>
      </section>
      <section id="about" className="welcome-band"><p className="eyebrow">More than a Sunday</p><div><h2>Faith is better<br />together.</h2><p>Find a welcoming community, meaningful worship, and practical ways to take your next step.</p></div><div className="welcome-stat"><strong>01</strong><span>Community<br />for every season</span></div></section>
      <section id="locations" className="content-section"><div className="section-heading"><div><p className="eyebrow">Gather with us</p><h2>Find your place.</h2></div><p>Come as you are. We are saving a seat for you.</p></div><div className="location-grid">{site.locations.map(location => <article className="location-card" key={location.id}><div className="location-card-top">{location.is_primary && <span className="location-label">Main location</span>}<span aria-hidden="true">↗</span></div><h3>{location.name}</h3><p>{location.address ?? "Contact the ministry for directions and gathering times."}</p><a href="#connect">Plan your visit <span aria-hidden="true">→</span></a></article>)}</div></section>
      {site.contact_email && <section id="connect" className="connect-section"><div><p className="eyebrow">Stay connected</p><h2>We would love to hear from you.</h2></div><a className="button button-light" href={`mailto:${site.contact_email}`}>Email {site.name}</a></section>}
    </main>
    <footer className="site-footer"><div className="brand"><span className="brand-mark" aria-hidden="true">✦</span>{site.name}</div><span>Faith, community, and care.</span><a href="#welcome">Back to top ↑</a></footer>
  </div>;
}
