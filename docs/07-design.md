# 07 — Experience and Design System

**Status:** Product-level behavior and accessibility rules. The visual system is being replaced by the premium overhaul defined in `15-ui-overhaul-audit.md`; examples here are constraints and content guidance, not a visual ceiling.

## 1. Design goals

The experience should feel editorial, confident, warm, and exceptionally polished while remaining neutral across denominations. Premium quality comes from typography, hierarchy, space, motion restraint, tactile surfaces, meaningful imagery, and complete interaction states rather than decorative excess. Staff tools must feel focused and operational; member tools should feel personal and welcoming; public pages should feel expressive and ministry-owned; worship should feel calm and media-first.

The default visual direction uses a deep layered sidebar, warm light working canvas, restrained elevation, large editorial headings, compact readable interface text, line icons, and the ministry's colour as a controlled accent. Branding remains ministry-owned: logo, colour tokens, typography choices, imagery, and terminology. Theme configuration must preserve contrast and focus visibility. A ministry colour may tint accents and hero treatments but cannot make every surface the same colour.

Staff appearance is configured from the protected settings workspace. Administrators choose primary and accent colours plus curated typography (`modern`, `editorial`, or `humanist`), sidebar treatment (`deep`, `brand`, or `light`), and density (`comfortable` or `compact`). The settings screen provides a live preview. Arbitrary fonts, remote stylesheets, and custom CSS are excluded so an installation cannot accidentally break accessibility or administrative workflows.

Before an installation exists, the root route opens a focused four-step launch wizard instead of a public-site placeholder. The wizard uses a warm branded introduction, clear numbered progress, one task per step, direct labels and inline validation, and a live preview of the chosen colours and ministry name. On narrow screens the introduction compacts into a short header with the same progress state; fields remain single column and controls meet touch target requirements. Completion creates the installation atomically, starts a fresh staff session, and then the normal public homepage replaces the wizard permanently.

## 2. Information architecture

| Public | Member | Staff |
| --- | --- | --- |
| Home, Watch Live, Sermons/Resources, Events, Groups, About, Locations, Contact | My profile, My groups, Events, Serving, Prayer, Giving, Preferences | Home, Services, People, Follow-up, Care, Content, Groups, Events, Volunteers, Messaging, Giving, Reports, Settings |

Navigation is module-aware; disabled modules are absent. Do not show a link that leads to a permission error. Staff navigation can adapt by permission, while direct access remains server-authorized. On mobile, prioritize Watch Live, Events, Prayer, and Contact.

## 3. Homepage lead behavior

- **Carousel:** editable content cards with clear title, image/video poster, category, and action; avoid automatic motion that cannot be paused.
- **Upcoming:** show local service time and time zone, countdown as supporting information, and a reminder action only if a messaging channel is configured.
- **Live:** prioritize video and Join worship; make the audio state obvious; show a clear path to notes and prayer. Do not autoplay audio unexpectedly.
- **Interrupted:** explain that the stream is temporarily unavailable, retain service information, and offer refresh, a backup stream when configured, and contact/prayer actions.
- **Ended:** remove live styling promptly and show approved replay only when ready. Do not present an unreviewed recording as published content.

The homepage must work before Livewire or Alpine initializes: Blade renders a safe initial state and polling revalidates it where needed. Avoid layout jumps when the lead changes.

### 3.1 Feature-aware homepage composition

The homepage is assembled from enabled, ministry-selected sections rather than a single fixed visual theme. An administrator chooses one preset as a starting point, then selects and orders the sections available to that installation. Presets are presentation defaults only; they never grant access to a disabled module.

| Preset | Intended use | Typical enabled sections |
| --- | --- | --- |
| Digital church | Broadcast-first ministry | Live/upcoming lead, watch action, sermon series, prayer, online groups, next steps |
| Local church | Physical gathering and care | Service times, locations, newcomer welcome, events, groups, children/ministry links when enabled |
| Teaching ministry | Sermons and resources | Featured series, video/audio sermons, books/resources, speaker/about, mailing/contact action |
| Outreach ministry | Connection and campaigns | Mission story, response form, events, testimonies, giving, partner links |
| Simple presence | Small or newly launched ministry | Welcome, service times, location/contact, selected content and one primary action |

All presets share the same accessibility, branding tokens, responsive behavior, live-service state model, header, footer, and consent rules. A disabled module is removed from the picker, navigation, public payload, and server routes. An enabled module may still be omitted from a ministry's homepage.

The editor opens with a visual starter-theme gallery: Sanctuary, Community, Editorial, and Modern. Each theme has a clear purpose, preview-safe visual treatment, lead composition, editable sample slides, and a curated section set. Applying a theme explicitly replaces only the current homepage composition; it must state that consequence before the action, retain an audit event, and never enable a disabled module. After adoption, staff use the detailed editor to modify slides, section content, order, and visibility. The public carousel uses explicit previous/next controls and does not advance automatically.

## 4. Worship room

Desktop places the player first, with collapsible notes/chat and persistent response actions. Mobile keeps video visible while switching among Chat, Notes, Prayer, and Next Steps tabs. Low-bandwidth mode reduces decorative assets and favors audio or a lower-quality stream when available. Show connection and playback status in plain language.

Guest chat shows a display-name step and community guidelines. Private prayer opens a clearly separate flow so users understand who can read it. Hosts have visible moderator badges. Prompts are concise, dismissible, and do not cover the video or captions. If prompts are tied to playback position, they should appear when the viewer reaches that point rather than when the live producer originally fired them.

## 5. Meeting experience

Prejoin screen shows camera/microphone preview, chosen name, meeting privacy, and recording status. Controls use text labels or accessible names, not icon-only ambiguity. Host controls are grouped separately from ordinary participant controls. Waiting-room state explains that a host must admit the participant. On disconnect, offer rejoin and a clear error explanation.

## 6. Member and staff experiences

Member area emphasizes upcoming commitments and self-service preferences. Staff dashboard emphasizes work needing action: live service health, due follow-ups, unassigned prayer requests, failed messages, and pending content/recording approval. Do not rank people by a spiritual engagement score. Reports must explain what each count means and allow drill-down only within permission scope.

The member home presents a personal welcome, next registered event, compact activity summary, and direct routes into focused member workspaces. Events, groups, serving, participation, next steps, prayer, giving, and profile/preferences each have a separate member URL and page heading, while retaining the shared sidebar and mobile navigation. Empty states explain what will appear without pressuring participation. Prayer visibility and contact consent remain explicit. Anonymous stream activity is never shown as member attendance.

Staff and member dashboards use persistent left navigation on desktop. On narrow screens the same navigation opens as an accessible drawer; the member area may also expose a five-item bottom navigation for frequent destinations. Neither dashboard substitutes a horizontal scrolling menu. Staff navigation is grouped by work area and each item has a consistent line icon, text label, permission check, feature check, and active state.

Staff, member, and public navigation share one application chrome: the same logo mark treatment, top-bar height and translucent surface, divider, rounded icon controls, active navigation treatment, drawer transition, focus treatment, and colour tokens. Their information architecture remains audience-specific: staff and members use persistent desktop sidebars, while public pages use a desktop header and the same drawer pattern on small screens. A route may change links and labels, but it does not introduce an unrelated visual navigation system.

The publishing workspace includes a dedicated Pages area alongside Homepage and the content library. It offers starting structures for common ministry pages (About, Plan your visit, Beliefs, Leadership, and Contact) plus a blank page. Pages use a Gutenberg-style visual block editor: a block library, live canvas, selected-block inspector, and safe ordered blocks for heading, paragraph, image, quote, divider, hero, call to action, service-times, events, and groups. The editor clearly distinguishes a private draft from a public page, and saving changes to a published page returns it to draft until an authorized publisher reviews it again.

Department screens use compact team cards and a roster table. The roster clearly distinguishes a linked member account from a roster-only person, shows the department-specific role, and explains the management boundary before an assignment is made. Role choices that a coordinator cannot grant are not presented in the form.

Person profile divides general information, attendance, groups, care, and giving into permission-controlled sections. Do not use hidden tabs as the only security measure. Care and finance interfaces use clear sensitivity labels and audit notices.

## 7. Forms and messages

Ask for the minimum data needed. Explain why contact details are requested and who will respond. Prayer visibility choices require plain-language descriptions. Consent must be an unticked choice where optional. Inline errors identify the field and remedy. Successful submissions provide a reference or clear expectation, without falsely promising a reply time that the ministry has not configured.

## 8. Accessibility and localization

- Target WCAG 2.2 AA for the public site, worship room, member area, and staff console.
- Keyboard navigation, visible focus, semantic headings, labels, error summaries, and screen-reader announcements for live-state changes.
- Captions/subtitles when supplied; transcripts for published sermons where available; no flashing content.
- Honor reduced-motion preference. Give users control over carousel movement and video autoplay.
- All dates and times display with the ministry or viewer time zone clearly identified. Prepare copy for translation and right-to-left layouts even if initial locales are limited.
- Test on narrow phones, low bandwidth, touch input, keyboard-only input, and screen readers.

## 9. Design tokens and components

Define semantic colour tokens for canvas, surface, raised surface, text, muted text, divider border, component border, interactive border, selected border, primary action, accent, success, warning, danger, and focus. Divider borders separate quiet content; component borders define cards and panels; interactive borders give fields, buttons, menus, and controls a stronger visible boundary. Selected and focus borders use the ministry colour only when contrast remains sufficient. Use a consistent spacing scale, editorial display scale, compact interface scale, three radii, two restrained elevation levels, and shared breakpoints. Admin-set colours are validated against contrast requirements and given a safe fallback.

Core components include: grouped sidebar, mobile drawer, member bottom navigation, top command bar, page command header, metric card, content panel, site header, lead card, content card, event card, service status badge, video shell, chat panel, prompt card, form field, privacy label, task row, person summary, responsive data table, empty state, toast, confirmation dialog, and incident banner. Icons use one outline SVG family with consistent stroke, sizing, and accessible labels. Unicode glyphs are not used as finished interface icons.

Blade templates, Livewire components, and Alpine interactions must use the same semantic tokens and component patterns. Livewire updates preserve focus, announce meaningful state changes, and do not replace active form fields unnecessarily. Polling indicators must state when data was last refreshed and provide a manual refresh where timing matters.

## 10. Design acceptance

Each core state has a design reference and interaction specification: carousel/upcoming/live/interrupted/ended homepage; public/identified/private worship; meeting prejoin/waiting/active/disconnected; empty/loading/error staff queues; privacy-restricted sections. Test real content lengths and ministry branding variants before signing off.


## Visual page editor

Public pages use an ordered block document. The staff editor offers a block library, selectable canvas, inspector, page patterns, desktop/tablet/mobile previews, undo/redo, and draft-first publishing. Dynamic blocks must remain unavailable and unrendered when their module is disabled.

## Editor interaction baseline

- The visual page editor uses selectable blocks, direct canvas editing for text, inspector fields for complete block settings, a responsive canvas preview, media selection from published external assets, undo/redo, ordered patterns, and draft-first publication.


## Public navigation manager

Public navigation is ministry-owned and stored as ordered `navigation_items`. Each link has a label, internal or external URL, optional approved icon, parent link for a dropdown, device visibility, optional new-tab behavior, and an optional required feature. Disabled features hide associated links in public rendering. Navigation changes are audited.

- Visual sections support one, two, or three content columns, curated spacing choices, and a validated background colour.

## 11. Visual page editor media policy

The public page editor stores ordered, validated blocks and renders only its approved block types. Its essential block set includes text, image, gallery, responsive YouTube/Vimeo video embed, audio, file download, media-and-text, list, accordion, spacer, service times, events, groups, hero, call to action, and configurable columns/sections. It does not accept arbitrary HTML, scripts, or iframe markup. Video is limited to YouTube and Vimeo URLs and uses privacy-conscious YouTube `youtube-nocookie.com` embeds where applicable. Images, galleries, audio, and files require HTTP or HTTPS URLs; staff should use the ministry media library when it is available. Galleries allow no more than twelve media links.
# Digital school experience

The staff workspace provides a compact course portfolio, course settings, curriculum builder, assessment authoring, live-class scheduling, and enrollment management. The member workspace provides a responsive catalog, clear enrollment states, progress, media-aware lessons, assessments, upcoming live classes, and printable certificates. Both use the shared dashboard shell, theme tokens, responsive navigation, styled controls, and dark-mode surfaces.

