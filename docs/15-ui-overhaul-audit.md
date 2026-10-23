# 15 — UI Overhaul Audit and Design Direction

**Status:** Historical audit and redesign rationale. The active application-experience rules are owned by [18 — Application Experience and Performance Contract](18-experience-performance-contract.md) and component/accessibility detail by [07 — Experience and Design System](07-design.md).
**Audited:** 2026-10-15

## 1. Result

The current Laravel interface is functionally useful but it does not yet meet the intended premium product standard. It is a collection of page templates sharing a basic shell, rather than one deliberate design system. The redesign must preserve all server-side permissions, feature flags, accessibility requirements, and shared-hosting portability while replacing the visual and interaction layer.

## 2. What is working

- Staff pages use a single authenticated workspace shell and its primary navigation is permission-aware.
- The desktop staff shell already reserves a left sidebar and uses a mobile drawer.
- Public pages, staff pages, member pages, authentication, and worship have separate layout entry points.
- Ministry primary and accent colours are passed into the layouts.
- The current homepage configuration supports live-service priority, a carousel, and configurable sections.

## 3. Audit findings

| Area | Finding | Effect | Redesign action |
| --- | --- | --- | --- |
| Design system | `resources/css/app.css` mixes all visual rules in a single monolithic stylesheet and repeats literal colours, fonts, borders, and spacing. | Pages do not look consistently designed and theme changes are shallow. | Establish semantic tokens, type scale, elevation, radii, responsive rules, dark navigation tokens, and component classes before restyling pages. |
| Staff navigation | The staff navigation is a flat list of text links with no icons, group labels, counts, or clear operational hierarchy. Individual pages still provide old `navigation` slots, while the authenticated layout renders the shared navigation component instead. | Navigation source of truth is unclear; the sidebar lacks the premium reference experience. | Replace with one data-driven sidebar: icon, label, permission, feature requirement, group, optional badge, active state, and profile/action area. Remove obsolete page-level navigation slots. |
| Staff workspace | The top bar has placeholder search and notification controls. Tables dominate the pages, with limited action context, filters, empty-state illustrations, or responsive card alternatives. | Staff work feels like an early administration interface rather than a daily operations tool. | Give each page a command header, context actions, KPI or status strip where useful, filters, responsive data views, and task-oriented empty states. |
| Member area | The member layout uses a horizontal top navigation and an inert Account button. | It does not deliver the requested dashboard experience or a coherent information architecture. | Build a dedicated desktop member sidebar and a mobile drawer/bottom navigation with profile, groups, events, serving, giving, prayer, next steps, and preferences. |
| Public site | The public header, listing pages, and footer are generic. The homepage uses the same compact shell without a stronger editorial rhythm, content cards, or family-specific presentation rules. | The ministry's public identity is not distinctive enough. | Treat the public website as a separate branded surface. Build reusable hero, feature grid, schedule, story, card, CTA, and footer components that vary safely with the selected homepage family. |
| Homepage editor | The editor is a single long form with no visual slide preview, sorting, edit/delete controls, draft/publish overview, or media chooser. | It is hard to curate a polished homepage. | Use compact editable cards, live preview, slide reorder controls, section gallery, context-specific configuration panels, and a clear publish state. |
| Worship room | The worship shell is structurally separate but does not yet share a complete visual language with public pages or give media status sufficient prominence. | A live service does not feel like a refined destination. | Introduce a distraction-minimized worship layout with media-first hierarchy, clear live state, accessible panels, and resilient outage treatment. |
| Authentication | Sign-in uses fixed generic copy and a text mark rather than the ministry's identity and theme. | The first staff/member touchpoint feels disconnected from the rest of the platform. | Use installation branding, a more focused form card, support/help state, and consistent visual tokens. |
| Responsiveness | Most large tables retain a minimum width and scroll horizontally. Member navigation remains horizontal. | Narrow-screen workflows are compromised. | Define mobile patterns per surface: overlay sidebar for staff/member, stacked action bars, summary cards for data tables, and full-width form controls. |
| Accessibility and polish | Focus, loading, disabled, selected, error, success, empty, and hover states are inconsistent. Icon-only controls use glyph characters in several places. | The UI is less clear for keyboard, screen-reader, and touch users. | Create accessible button, icon button, status, toast, skeleton, dialog, and empty-state components; use labelled SVG icons. |

## 4. Target visual system

This direction supersedes earlier visual examples wherever they feel dated or generic. Existing design documentation continues to own accessibility, information architecture, responsive behavior, permissions, and ministry-controlled branding; the new system owns visual quality and interaction polish.

### Staff workspace

- **Desktop:** fixed 272 px sidebar using the ministry's configured light/dark sidebar colors; neutral charcoal defaults; logo/installation identity at the top; grouped navigation; visible active item; profile menu and support/settings at the bottom.
- **Top bar:** page breadcrumb, command/search entry, notification area, profile controls; no placeholder controls presented as finished functionality.
- **Content:** ministry-configurable light/dark neutral or branded canvas and panel colors with a maximum working width, page command header, metric cards, elevated content panels, and accessible brand accents.
- **Navigation groups:** Workspace, Ministry, Content, Operations, Administration. Each item has an outline SVG icon and permission/feature-aware visibility.
- **Mobile:** hamburger opens the same sidebar as a focus-managed overlay. There is no horizontal replacement menu.

### Member workspace

- **Desktop:** a slim left sidebar sharing the staff system's spatial language but using warmer, member-facing copy and fewer tools.
- **Mobile:** a simple five-item bottom bar for Home, Groups, Events, Giving, and More; More opens the same navigation drawer.

### Public and worship

- **Public:** editorial typography, generous rhythm, full-bleed lead, high-quality card/grid patterns, branded media, and module-aware navigation.
- **Worship:** video remains central. Chat, notes, prayer, and responses are secondary panels with clear state and keyboard support.

## 5. Delivery batches

1. **Foundation:** semantic CSS tokens, type scale, Lucide-style inline SVG icon system, layout primitives, buttons, form fields, cards, tables, badges, menus, empty states, and responsive utility rules.
2. **Staff shell:** data-driven sidebar, desktop/mobile behavior, top bar, profile area, page header, and removal of legacy per-page navigation fragments.
3. **Staff pages:** overview, people, services, content/homepage, community, care, giving, reports, settings, and security. Each is converted in logical groups using real data and existing permissions.
4. **Member workspace:** new sidebar/bottom navigation, overview, self-service pages, profile and preferences.
5. **Public site and homepage:** family-aware header/footer, hero, carousel, section components, listing pages, and page builder styles.
6. **Worship and authentication:** live-room visual system, resilient media states, sign-in/recovery/invitation/verification screens.
7. **Quality pass:** keyboard and screen-reader checks, 360 px and 768 px layouts, long-content checks, visual regression references, and browser smoke tests.

## 6. Acceptance criteria

- Every staff and member page uses the same sidebar on desktop and the same drawer on mobile; horizontal menus are not substituted for either dashboard.
- Navigation has one source of truth, with icons, grouping, active state, permissions, and feature gating.
- Every important page has a clear title, context, primary action, usable empty state, and a narrow-screen layout.
- Theme settings use semantic tokens across all surfaces; ministries can choose primary/accent colors and light/dark canvas, panel, and sidebar colors, with readable foregrounds derived automatically.
- Public homepage families look intentionally different while maintaining one trusted design language.
- Existing routes, permissions, feature-disablement behavior, sensitive-data boundaries, and server rendering continue to work.
