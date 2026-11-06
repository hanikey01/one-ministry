"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import AdminShell from "./AdminShell";
import { api, type Identity } from "./api";
import FeaturesPanel from "./FeaturesPanel";
import InvitationsPanel from "./InvitationsPanel";
import LocationsPanel from "./LocationsPanel";
import OperationsPanel, { type OperationView } from "./OperationsPanel";
import ServiceProductionPanel from "./ServiceProductionPanel";
import ServicesPanel from "./ServicesPanel";
import SessionsPanel from "./SessionsPanel";
import SettingsPanel from "./SettingsPanel";
import ServiceSchedulePanel from "./ServiceSchedulePanel";
import LibraryManagerPanel from "./LibraryManagerPanel";
import PageManagerPanel from "./PageManagerPanel";

const operationAreas: Record<string, { view: OperationView; title: string; eyebrow: string }> = {
  people: { view: "people", title: "People", eyebrow: "Community" }, duplicates: { view: "duplicates", title: "Duplicates", eyebrow: "People care" }, "follow-up": { view: "follow_up", title: "Follow-up", eyebrow: "Care workflow" }, birthdays: { view: "birthdays", title: "Birthdays", eyebrow: "Celebrations" }, events: { view: "events", title: "Events", eyebrow: "Gatherings" }, campaigns: { view: "campaigns", title: "Campaigns", eyebrow: "Communications" }, meetings: { view: "meetings", title: "Meetings", eyebrow: "Digital ministry" }, media: { view: "media", title: "Media library", eyebrow: "Content" }, giving: { view: "giving", title: "Giving", eyebrow: "Finance" }, reports: { view: "reports", title: "Reports", eyebrow: "Insights" },
};
const pages: Record<string, { title: string; eyebrow: string }> = { overview: { title: "Ministry workspace", eyebrow: "Overview" }, services: { title: "Services", eyebrow: "Digital church" }, schedule: { title: "Service timetable", eyebrow: "Digital church" }, pages: { title: "Public pages", eyebrow: "Website content" }, libraries: { title: "Libraries", eyebrow: "Content" }, production: { title: "Production desk", eyebrow: "Digital church" }, staff: { title: "Staff access", eyebrow: "Administration" }, locations: { title: "Locations", eyebrow: "Administration" }, settings: { title: "Settings", eyebrow: "Administration" }, security: { title: "Security", eyebrow: "Administration" }, modules: { title: "Modules", eyebrow: "Administration" } };

function Overview({ identity }: { identity: Identity }) {
  const [counts, setCounts] = useState({ people: 0, followUp: 0, events: 0 });
  useEffect(() => { void Promise.allSettled([api<unknown>("/staff/people"), api<unknown>("/staff/follow-up-tasks"), api<unknown>("/staff/events")]).then(results => { const count = (result: PromiseSettledResult<unknown>) => { if (result.status !== "fulfilled" || !result.value || typeof result.value !== "object") return 0; const data = (result.value as {data?: unknown}).data; if (Array.isArray(data)) return data.length; if (data && typeof data === "object") return Number((data as {total?:number}).total ?? ((data as {data?:unknown[]}).data?.length ?? 0)); return 0; }; setCounts({ people: count(results[0]), followUp: count(results[1]), events: count(results[2]) }); }); }, []);
  const cards = [["People", counts.people, "People in your ministry directory", "/admin/people", "♧", "green"], ["Open follow-ups", counts.followUp, "Care actions needing attention", "/admin/follow-up", "♡", "rose"], ["Upcoming events", counts.events, "Gatherings on the calendar", "/admin/events", "◇", "gold"]] as const;
  return <><section className="premium-overview-heading"><div><p>Today’s ministry overview</p><h2>Good to see you, {identity.name.split(" ")[0]}.</h2><span>Care, services and community activity in one focused workspace.</span></div><Link href="/admin/people" className="workspace-primary-button">＋ Quick add person</Link></section><section className="workspace-card-grid premium-stat-grid">{cards.map(([name, value, copy, href, icon, tone]) => <Link href={href} className="workspace-card premium-stat" key={name}><div><span className={`stat-icon ${tone}`}>{icon}</span><small>{name}</small></div><strong>{value}</strong><p>{copy}</p><b>→</b></Link>)}</section><section className="premium-dashboard-grid"><article className="workspace-panel premium-service-card"><div className="service-visual"><span>◉ SERVICE PREPARATION</span><div><small>NEXT WORKSPACE</small><h2>Sunday service</h2><p>Prepare the timetable, production desk and live experience from one place.</p></div></div><div className="service-actions"><p className="eyebrow">Digital church</p><h3>Everything for the next service</h3><p>Coordinate the timetable, production desk and live experience from one place.</p><Link href="/admin/services" className="workspace-primary-button">Open service desk →</Link></div></article><article className="workspace-panel premium-action-card"><div className="section-title"><div><p className="eyebrow">Care workflow</p><h2>Needs attention</h2></div><Link href="/admin/follow-up">View all →</Link></div><Link href="/admin/follow-up"><i className="rose"/><div><strong>Follow-up queue</strong><span>{counts.followUp} open care actions</span></div><b>→</b></Link><Link href="/admin/events"><i className="gold"/><div><strong>Event operations</strong><span>Registrations and check-in</span></div><b>→</b></Link></article></section><section className="workspace-panel workspace-quick-links"><div className="section-title"><div><p className="eyebrow">Administration</p><h2>Manage your installation</h2></div></div><div><Link href="/admin/staff">Staff access</Link><Link href="/admin/locations">Locations</Link><Link href="/admin/security">Account security</Link><Link href="/admin/settings">Brand and theme</Link></div></section></>;
}

export default function WorkspacePage({ area = "overview" }: { area?: string }) {
  const operation = operationAreas[area]; const page = operation ?? pages[area] ?? pages.overview;
  return <AdminShell title={operation?.title ?? page.title} eyebrow={operation?.eyebrow ?? page.eyebrow}>{identity => {
    if (operation) return <OperationsPanel identity={identity} initialView={operation.view} hideTabs />;
    if (area === "services") return <ServicesPanel />;
    if (area === "schedule") return <ServiceSchedulePanel />;
    if (area === "libraries") return <LibraryManagerPanel />;
    if (area === "pages") return <PageManagerPanel canPublish={identity.permissions.includes("content.publish")} />;
    if (area === "production") return <ServiceProductionPanel />;
    if (area === "staff") return <InvitationsPanel />;
    if (area === "locations") return <LocationsPanel canManage={identity.permissions.includes("locations.manage")} />;
    if (area === "settings") return <SettingsPanel canManage={identity.permissions.includes("settings.update")} />;
    if (area === "security") return <><SessionsPanel /><section className="workspace-panel"><h2>Authenticator app</h2><p>Manage multifactor authentication for sensitive staff actions.</p><Link className="workspace-secondary-button" href="/admin/mfa">Open security controls</Link></section></>;
    if (area === "modules") return <FeaturesPanel canManage={identity.permissions.includes("features.manage")} />;
    return <Overview identity={identity} />;
  }}</AdminShell>;
}
