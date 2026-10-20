"use client";

import { useState } from "react";
import styles from "./preview.module.css";

const nav = [
  ["home", "Overview"], ["people", "People"], ["heart", "Care & follow-up", "8"], ["calendar", "Services"],
  ["groups", "Groups"], ["event", "Events"], ["volunteer", "Volunteers"], ["library", "Content library"],
  ["message", "Communications"], ["giving", "Giving"], ["chart", "Reports"],
];

function Icon({ name }: { name: string }) {
  const paths: Record<string, React.ReactNode> = {
    home: <><path d="M3 10.8 12 3l9 7.8"/><path d="M5.5 9.5V21h13V9.5M9 21v-7h6v7"/></>, people: <><circle cx="9" cy="8" r="3.2"/><path d="M3 20c.5-4 2.5-6 6-6s5.5 2 6 6"/><circle cx="17.5" cy="9" r="2.5"/><path d="M16 15c3 0 4.5 1.7 5 5"/></>, heart: <path d="M20.8 5.7c-2.3-2.4-6.1-1.7-8.8 1.1C9.3 4 5.5 3.3 3.2 5.7.8 8.1 1 12 3.5 14.5L12 22l8.5-7.5c2.5-2.5 2.7-6.4.3-8.8Z"/>, calendar: <><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18M8 14h2M14 14h2M8 18h2"/></>, groups: <><circle cx="8" cy="8" r="3"/><circle cx="17" cy="8" r="3"/><path d="M2 20c.5-4 2.5-6 6-6 2 0 3.5.7 4.5 2M12 20c.5-4 2-6 5-6s4.5 2 5 6"/></>, event: <><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="m9 16 2 2 4-5"/></>, volunteer: <><path d="M12 21s-8-4.4-8-11a4 4 0 0 1 7-2.7L12 9l1-1.7A4 4 0 0 1 20 10c0 6.6-8 11-8 11Z"/><path d="M8 12h8"/></>, library: <><path d="M4 4h5v16H4zM10 4h5v16h-5zM16 5l4-1 2 15-4 1z"/></>, message: <><path d="M4 4h16v13H9l-5 4V4Z"/><path d="M8 9h8M8 13h5"/></>, giving: <><circle cx="12" cy="12" r="9"/><path d="M15.5 8.5c-.8-.8-2-1.2-3.4-1.2-2 0-3.3 1-3.3 2.5 0 3.8 6.6 1.8 6.6 5.3 0 1.4-1.3 2.5-3.5 2.5-1.5 0-2.9-.5-3.8-1.4M12 5v14"/></>, chart: <><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></>, search: <><circle cx="11" cy="11" r="7"/><path d="m16.5 16.5 4.5 4.5"/></>, bell: <><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></>, plus: <path d="M12 5v14M5 12h14"/>, more: <><circle cx="5" cy="12" r="1" fill="currentColor"/><circle cx="12" cy="12" r="1" fill="currentColor"/><circle cx="19" cy="12" r="1" fill="currentColor"/></>, arrow: <><path d="M5 12h14M14 7l5 5-5 5"/></>, check: <path d="m5 12 4 4L19 6"/>, live: <><circle cx="12" cy="12" r="2" fill="currentColor"/><path d="M8.5 8.5a5 5 0 0 0 0 7M15.5 8.5a5 5 0 0 1 0 7M5.5 5.5a9 9 0 0 0 0 13M18.5 5.5a9 9 0 0 1 0 13"/></>, settings: <><circle cx="12" cy="12" r="3"/><path d="M19 13.5v-3l-2-.7-.7-1.7.9-2-2.2-2.2-2 .9-1.8-.7-.7-2h-3l-.7 2-1.7.7-2-.9L.9 6.1l.9 2-.7 1.7-2 .7v3l2 .7.7 1.7-.9 2L3.1 20l2-.9 1.7.7.7 2h3l.7-2 1.8-.7 2 .9 2.2-2.2-.9-2 .7-1.7 2-.6Z" transform="translate(2) scale(.84)"/></>,
  };
  return <svg viewBox="0 0 24 24" aria-hidden="true">{paths[name]}</svg>;
}

export default function DashboardPreview() {
  const [active, setActive] = useState("Overview");
  const [period, setPeriod] = useState("This week");
  const [done, setDone] = useState<string[]>([]);
  const [menuOpen, setMenuOpen] = useState(false);
  const tasks = [
    ["Welcome first-time guests", "6 people", "Due today", "#c66a43"],
    ["Review prayer requests", "3 private requests", "Pastoral care", "#8b6bbb"],
    ["Confirm Sunday volunteers", "4 responses pending", "Sunday service", "#4a8a78"],
  ];
  return <main className={styles.shell}>
    <button className={`${styles.backdrop} ${menuOpen ? styles.backdropOpen : ""}`} aria-label="Close navigation" onClick={() => setMenuOpen(false)} />
    <aside className={`${styles.sidebar} ${menuOpen ? styles.sidebarOpen : ""}`}>
      <div className={styles.brand}><span className={styles.brandMark}>L</span><div><strong>Living Hope</strong><small>Ministry workspace</small></div></div>
      <button className={styles.closeMenu} aria-label="Close navigation" onClick={() => setMenuOpen(false)}>×</button>
      <nav aria-label="Dashboard preview navigation"><p>Ministry</p>{nav.map(([icon, label, count]) => <button key={label} className={active === label ? styles.active : ""} onClick={() => { setActive(label); setMenuOpen(false); }}><Icon name={icon}/><span>{label}</span>{count && <b>{count}</b>}</button>)}</nav>
      <div className={styles.sidebarBottom}><button><Icon name="settings"/><span>Settings</span></button><div className={styles.profile}><span>AA</span><div><strong>Amara Adams</strong><small>Lead administrator</small></div><button aria-label="Profile menu"><Icon name="more"/></button></div></div>
    </aside>

    <section className={styles.main}>
      <header className={styles.topbar}><button className={styles.menuButton} aria-label="Open navigation" aria-expanded={menuOpen} onClick={() => setMenuOpen(true)}><span/><span/><span/></button><label><Icon name="search"/><input aria-label="Search ministry records" placeholder="Search people, groups, services…"/><kbd>⌘ K</kbd></label><div><button aria-label="Notifications" className={styles.iconButton}><Icon name="bell"/><i/></button><button className={styles.addButton}><Icon name="plus"/>Quick add</button></div></header>
      <div className={styles.content}>
        <div className={styles.heading}><div><p>Saturday, October 3</p><h1>Good morning, Amara.</h1><span>Here’s what needs your attention across the ministry today.</span></div><select value={period} onChange={event => setPeriod(event.target.value)} aria-label="Dashboard reporting period"><option>This week</option><option>This month</option><option>This quarter</option></select></div>

        <section className={styles.stats}>
          <article><div><span className={styles.green}><Icon name="people"/></span><small>Total people</small></div><strong>2,847</strong><p><b>↑ 4.2%</b> from last month</p></article>
          <article><div><span className={styles.gold}><Icon name="calendar"/></span><small>Sunday attendance</small></div><strong>1,284</strong><p><b>↑ 6.8%</b> from last Sunday</p></article>
          <article><div><span className={styles.lilac}><Icon name="heart"/></span><small>Open follow-ups</small></div><strong>18</strong><p><em>6 due today</em></p></article>
          <article><div><span className={styles.blue}><Icon name="groups"/></span><small>Active groups</small></div><strong>32</strong><p><b>74%</b> participation rate</p></article>
        </section>

        <section className={styles.liveCard}><div className={styles.liveVisual}><span><Icon name="live"/> LIVE PREPARATION</span><div><small>NEXT SERVICE</small><strong>Sunday Celebration</strong><p>Tomorrow · 9:00 AM · Main Auditorium</p></div></div><div className={styles.liveDetails}><div><span className={styles.readiness}>82%</span><div><strong>Service readiness</strong><p>Run sheet, team and broadcast check</p></div></div><div className={styles.progress}><i style={{width:"82%"}}/></div><ul><li><Icon name="check"/>Run sheet ready</li><li><Icon name="check"/>Stream configured</li><li className={styles.pending}>4 volunteer responses pending</li></ul><button>Open service desk <Icon name="arrow"/></button></div></section>

        <div className={styles.dashboardGrid}>
          <section className={styles.panel}><div className={styles.panelHead}><div><p>CARE WORKFLOW</p><h2>Needs attention</h2></div><button>View all <Icon name="arrow"/></button></div><div className={styles.taskList}>{tasks.map(([title, detail, meta, color]) => <article className={done.includes(title) ? styles.taskDone : ""} key={title}><button className={styles.checkButton} onClick={() => setDone(current => current.includes(title) ? current.filter(item => item !== title) : [...current, title])}><Icon name="check"/></button><i style={{background:color}}/><div><strong>{title}</strong><span>{detail} · {meta}</span></div><button aria-label={`More options for ${title}`}><Icon name="more"/></button></article>)}</div></section>

          <section className={`${styles.panel} ${styles.activity}`}><div className={styles.panelHead}><div><p>MINISTRY PULSE</p><h2>Recent activity</h2></div><button><Icon name="more"/></button></div><div className={styles.timeline}><article><span className={styles.avatarRose}>JN</span><div><p><strong>James N.</strong> completed newcomer follow-up</p><small>12 minutes ago</small></div></article><article><span className={styles.avatarGreen}><Icon name="giving"/></span><div><p>A new <strong>giving batch</strong> was reconciled</p><small>48 minutes ago</small></div></article><article><span className={styles.avatarBlue}>MK</span><div><p><strong>Mary K.</strong> joined Young Adults group</p><small>1 hour ago</small></div></article><article><span className={styles.avatarGold}><Icon name="library"/></span><div><p><strong>Walking in Grace</strong> was published</p><small>2 hours ago</small></div></article></div></section>

          <section className={`${styles.panel} ${styles.growth}`}><div className={styles.panelHead}><div><p>PARTICIPATION</p><h2>Weekly attendance</h2></div><span className={styles.legend}><i/> In person <i/> Online</span></div><div className={styles.chart}><div className={styles.axis}><span>1,500</span><span>1,000</span><span>500</span><span>0</span></div><div className={styles.bars}>{[[54,24],[65,31],[59,27],[72,35],[68,32],[83,41]].map(([a,b],i)=><div key={i}><span style={{height:`${a}%`}}/><i style={{height:`${b}%`}}/><small>{["Aug 30","Sep 6","Sep 13","Sep 20","Sep 27","Oct 4"][i]}</small></div>)}</div></div></section>
        </div>
      </div>
    </section>
  </main>;
}
