"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useState, type CSSProperties, type ReactNode } from "react";
import { api, type Identity, type Settings } from "./api";
import AdminIcon, { type AdminIconName } from "./AdminIcon";

const navigation: ReadonlyArray<readonly [string,string,AdminIconName]> = [["Overview", "/admin", "home"], ["People", "/admin/people", "people"], ["Care & follow-up", "/admin/follow-up", "heart"], ["Services", "/admin/services", "calendar"], ["Groups", "/admin/groups", "groups"], ["Events", "/admin/events", "event"], ["Volunteers", "/admin/volunteers", "volunteer"], ["Pages", "/admin/pages", "pages"], ["Content library", "/admin/libraries", "library"], ["Communications", "/admin/campaigns", "message"], ["Giving", "/admin/giving", "giving"], ["Reports", "/admin/reports", "chart"]];
const management: ReadonlyArray<readonly [string,string,AdminIconName]> = [["Staff access", "/admin/staff", "staff"], ["Locations", "/admin/locations", "location"], ["Modules", "/admin/modules", "modules"], ["Settings", "/admin/settings", "settings"], ["Security", "/admin/security", "security"]];
const fallback: Settings = { name: "Ministry Platform", tagline: null, contact_email: null, primary_color: "#1F6B55", accent_color: "#D8AB5D" };

export default function AdminShell({ children, title, eyebrow }: { children: (identity: Identity) => ReactNode; title: string; eyebrow: string }) {
  const pathname = usePathname(); const router = useRouter();
  const [identity, setIdentity] = useState<Identity | null>(null); const [settings, setSettings] = useState(fallback); const [menuOpen, setMenuOpen] = useState(false);
  useEffect(() => {
    api<{ data: Identity }>("/auth/me").then(response => setIdentity(response.data)).catch(() => router.replace("/admin/login"));
    api<{ data: Settings }>("/staff/settings").then(response => setSettings(response.data)).catch(() => undefined);
    const update = (event: Event) => setSettings((event as CustomEvent<Settings>).detail);
    window.addEventListener("ministry-theme-updated", update); return () => window.removeEventListener("ministry-theme-updated", update);
  }, [router]);
  function item([label, href, icon]: readonly [string, string, AdminIconName]) { const active = href === "/admin" ? pathname === "/admin" : pathname.startsWith(href); return <Link onClick={() => setMenuOpen(false)} className={active ? "workspace-nav-link active" : "workspace-nav-link"} href={href} key={href}><span><AdminIcon name={icon}/></span>{label}</Link>; }
  async function logout() { await api<void>("/auth/logout", { method: "POST" }).catch(() => undefined); router.replace("/admin/login"); }
  const theme = { "--workspace-primary": settings.primary_color, "--workspace-accent": settings.accent_color } as CSSProperties;
  return <main className="workspace-shell premium-workspace" style={theme}>
    <button className={menuOpen ? "workspace-backdrop open" : "workspace-backdrop"} aria-label="Close navigation" onClick={() => setMenuOpen(false)} />
    <aside className={menuOpen ? "workspace-sidebar open" : "workspace-sidebar"}>
      <Link href="/admin" className="workspace-logo"><span className="workspace-logo-mark">{settings.name.slice(0, 1).toUpperCase()}</span><span>{settings.name}<small>Ministry workspace</small></span></Link>
      <button className="workspace-close" aria-label="Close navigation" onClick={() => setMenuOpen(false)}>×</button>
      <nav className="workspace-nav" aria-label="Primary staff navigation"><p>Ministry</p>{navigation.map(item)}<p className="workspace-nav-caption">Administration</p>{management.map(item)}</nav>
      <div className="workspace-sidebar-footer"><Link href="/" target="_blank">View public site ↗</Link><button onClick={logout}>Sign out</button></div>
    </aside>
    <section className="workspace-main">
      <header className="workspace-topbar"><button className="workspace-menu" aria-label="Open navigation" aria-expanded={menuOpen} onClick={() => setMenuOpen(true)}><i/><i/><i/></button><label><span>⌕</span><input aria-label="Search ministry workspace" placeholder="Search people, groups, services…"/><kbd>Ctrl K</kbd></label><div><button className="workspace-notification" aria-label="Notifications">♢<i/></button>{identity && <div className="workspace-profile"><span>{identity.name.slice(0, 1).toUpperCase()}</span><div><strong>{identity.name}</strong><small>{identity.email}</small></div></div>}</div></header>
      <div className="workspace-content"><header className="workspace-header"><div><p className="workspace-eyebrow">{eyebrow}</p><h1>{title}</h1></div></header>{identity ? children(identity) : <div className="workspace-loading">Opening your workspace…</div>}</div>
    </section>
  </main>;
}
