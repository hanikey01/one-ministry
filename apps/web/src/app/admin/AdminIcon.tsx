import type { ReactNode } from "react";

export type AdminIconName = "home"|"people"|"heart"|"calendar"|"event"|"library"|"pages"|"message"|"giving"|"chart"|"staff"|"location"|"modules"|"settings"|"security";

export default function AdminIcon({ name }: { name: AdminIconName }) {
  const paths: Record<AdminIconName, ReactNode> = {
    home:<><path d="M3 10.8 12 3l9 7.8"/><path d="M5.5 9.5V21h13V9.5M9 21v-7h6v7"/></>,
    people:<><circle cx="9" cy="8" r="3.2"/><path d="M3 20c.5-4 2.5-6 6-6s5.5 2 6 6"/><circle cx="17.5" cy="9" r="2.5"/><path d="M16 15c3 0 4.5 1.7 5 5"/></>,
    heart:<path d="M20.8 5.7c-2.3-2.4-6.1-1.7-8.8 1.1C9.3 4 5.5 3.3 3.2 5.7.8 8.1 1 12 3.5 14.5L12 22l8.5-7.5c2.5-2.5 2.7-6.4.3-8.8Z"/>,
    calendar:<><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18M8 14h2M14 14h2M8 18h2"/></>,
    event:<><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="m9 16 2 2 4-5"/></>,
    library:<><path d="M4 4h5v16H4zM10 4h5v16h-5zM16 5l4-1 2 15-4 1z"/></>,
    pages:<><path d="M5 3h10l4 4v14H5z"/><path d="M15 3v5h4M8 12h8M8 16h8"/></>,
    message:<><path d="M4 4h16v13H9l-5 4V4Z"/><path d="M8 9h8M8 13h5"/></>,
    giving:<><circle cx="12" cy="12" r="9"/><path d="M15.5 8.5c-.8-.8-2-1.2-3.4-1.2-2 0-3.3 1-3.3 2.5 0 3.8 6.6 1.8 6.6 5.3 0 1.4-1.3 2.5-3.5 2.5-1.5 0-2.9-.5-3.8-1.4M12 5v14"/></>,
    chart:<><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></>,
    staff:<><circle cx="9" cy="8" r="3"/><path d="M3 20c.5-4 2.5-6 6-6s5.5 2 6 6M17 11v6M14 14h6"/></>,
    location:<><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></>,
    modules:<><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></>,
    settings:<><circle cx="12" cy="12" r="3"/><path d="M19 13.5v-3l-2-.7-.7-1.7.9-2-2.2-2.2-2 .9-1.8-.7-.7-2h-3l-.7 2-1.7.7-2-.9L.9 6.1l.9 2-.7 1.7-2 .7v3l2 .7.7 1.7-.9 2L3.1 20l2-.9 1.7.7.7 2h3l.7-2 1.8-.7 2 .9 2.2-2.2-.9-2 .7-1.7 2-.6Z" transform="translate(2) scale(.84)"/></>,
    security:<><path d="M12 3 4.5 6v5c0 5 3 8.5 7.5 10 4.5-1.5 7.5-5 7.5-10V6L12 3Z"/><path d="m9 12 2 2 4-5"/></>,
  };
  return <svg viewBox="0 0 24 24" aria-hidden="true">{paths[name]}</svg>;
}
