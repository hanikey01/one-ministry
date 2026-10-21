# 07 — Experience and Design System

**Status:** Canonical product-level interface and accessibility rules. The Laravel application is moving toward one shared, neutral light/dark design system with installation-controlled brand inputs. Documented behaviors here apply across staff, member, public, worship, and meeting experiences; the implementation-status document distinguishes shipped source from verified release behavior.

## 1. Design goals

The experience should feel editorial, confident, warm, and exceptionally polished while remaining neutral across denominations. Premium quality comes from typography, hierarchy, space, motion restraint, tactile surfaces, meaningful imagery, and complete interaction states rather than decorative excess. Staff tools must feel focused and operational; member tools should feel personal and welcoming; public pages should feel expressive and ministry-owned; worship should feel calm and media-first.

The default visual direction uses neutral light and dark palettes, restrained elevation, large editorial headings, compact readable interface text, line icons, and ministry-owned colors. Light defaults use white and neutral gray; dark defaults use charcoal and near-black. Branding remains installation-owned: logo, primary and accent colors, light/dark canvas, panel and sidebar colors, typography choices, imagery, and terminology. The selected accent supplies action buttons and derived button text; ministries may configure sidebar colors separately by mode. Theme configuration derives readable foregrounds, preserves contrast and focus visibility, and keeps semantic success, warning, and danger colors recognizable. A ministry may choose branded surfaces, but each text/background pairing must remain accessible.

Staff appearance is configured from the protected settings workspace. Administrators choose primary and accent colours, light/dark canvas, panel, and sidebar colours, curated typography (`modern`, `editorial`, or `humanist`), sidebar treatment (`deep`, `brand`, or `light`), and density (`comfortable` or `compact`). Sidebar treatment defaults to `light` (match the content surface); its separate light/dark colour controls appear only when `deep` custom treatment is selected. These installation-owned choices apply consistently across staff, member, public, worship, and meeting experiences. The settings screen provides a live preview and automatically selects high-contrast text for chosen backgrounds. Arbitrary fonts, remote stylesheets, and custom CSS are excluded so an installation cannot accidentally break accessibility or administrative workflows.

Before an installation exists, the root route opens a focused four-step launch wizard instead of a public-site placeholder. The wizard uses a warm branded introduction, clear numbered progress, one task per step, direct labels and inline validation, and a live preview of the chosen colours and ministry name. On narrow screens the introduction compacts into a short header with the same progress state; fields remain single column and controls meet touch target requirements. Completion creates the installation atomically, starts a fresh staff session, and then the normal public homepage replaces the wizard permanently.

## 2. Information architecture

| Public | Member | Staff |
| --- | --- | --- |
| Home, Watch Live, Sermons/Resources, Events, Groups, About, Locations, Contact | My profile, My groups, Events, Serving, Prayer, Giving, Preferences | Home, Services, People, Follow-up, Care, Content, Groups, Events, Volunteers, Messaging, Giving, Reports, Settings |

Navigation is module-aware; disabled modules are absent from desktop and mobile navigation, quick actions, dashboard shortcuts, and module-driven homepage content. Do not show a link that leads to a permission or module-state error. Direct access remains server-authorized, and disabled modules return 404 at their staff, member, public, and API routes. Staff-preview modules may appear only to staff authorized for preview. The Modules administration page remains visible to its authorized users so they can re-enable a module. On mobile, prioritize Watch Live, Events, Prayer, and Contact when their modules are available.

Ministry terminology overrides are display-only labels for a bounded set of domain concepts. Provide singular and plural forms, clear defaults, and an immediate preview. Keep product routes, permission names, stored values, and machine-facing contracts independent of the chosen wording. Keep distinct concepts such as broadcast services and interactive meetings configurable separately.

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

The product should feel like one responsive application while preserving Laravel's server-rendered baseline. Where an action returns a bounded result, prefer in-place save/update with an explicit pending state and shared viewport notification instead of navigating back through a full page reload. Preserve form values and explain recoverable errors; continue supporting ordinary form posts when JavaScript is unavailable. Adopt this behavior route by route and do not imply application-wide coverage until the status document records it.

Staff and member sidebar links use Livewire's hover-prefetched navigation so moving between workspace routes does not reload the whole document. Page-specific browser behavior must initialize after each navigation and release timers, global listeners, and meeting clients when a route is left. Normal links continue to work when JavaScript is unavailable.

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

Define semantic colour tokens for canvas, surface, raised surface, text, muted text, sidebar surface/text, divider border, component border, interactive border, selected border, action, accent, success, warning, danger, and focus. All action buttons use the ministry-selected accent fill and its derived readable foreground; secondary and quiet action buttons follow the same color rule. Divider borders separate quiet content; component borders define cards and panels; interactive borders give fields, menus, and controls a stronger visible boundary. Selected and focus borders use the ministry colour only when contrast remains sufficient. Derive foreground text from each ministry-selected background using contrast calculations; do not trust browser-only color inputs or a hex-format check as accessibility validation. Use a consistent spacing scale, editorial display scale, compact interface scale, three radii, two restrained elevation levels, and shared breakpoints.

Shared select menus render in a viewport overlay so cards, tables, and scroll containers cannot clip them. Keep the menu within horizontal viewport margins, flip it above its trigger when that provides more room, and preserve keyboard navigation and the active light/dark surface tokens.

Core components include: grouped sidebar, mobile drawer, member bottom navigation, top command bar, page command header, metric card, content panel, site header, lead card, content card, event card, service status badge, video shell, chat panel, prompt card, form field, privacy label, task row, person summary, responsive data table, empty state, toast, confirmation dialog, and incident banner. Icons use one outline SVG family with consistent stroke, sizing, and accessible labels. Unicode glyphs are not used as finished interface icons.

Blade templates, Livewire components, and Alpine interactions must use the same semantic tokens and component patterns. Livewire updates preserve focus, announce meaningful state changes, and do not replace active form fields unnecessarily. Polling indicators must state when data was last refreshed and provide a manual refresh where timing matters.

Forms that opt into in-place submission show a pending state on the submitted action, prevent duplicate submission, and announce the server result without a full-page reload. Validation errors appear beside their fields and are announced accessibly; network and server failures retain the user's current form state. Keep ordinary form submission as a server-rendered fallback when scripting is unavailable.

Success, error, warning, and pending notifications use the shared viewport notification stack. Place it within the current safe viewport, keep messages readable above page content, provide a dismiss action, and auto-dismiss only completed informational messages. Pending work stays visible until it completes; errors and warnings remain long enough to read. Keep inline field errors beside their fields.

## 10. Design acceptance

Staff and member dashboards share the same semantic light/dark surface structure across ministries. Ministry colors are deliberate brand inputs (accent, primary, and sidebar), while canvas, panel, text, border, focus, and status roles must resolve through the shared theme tokens rather than page-specific palette literals.

The appearance settings group sidebar choices by their actual effect: custom light/dark sidebar colors, ministry primary color, or the mode's content surface. Default to the content surface. Show the separate sidebar pickers directly under the custom sidebar choice only when that choice is selected, with a short slide-down reveal that matches the settings panel and form controls. Keep the canvas and panel controls in the interface-colours section.

Each core state has a design reference and interaction specification: carousel/upcoming/live/interrupted/ended homepage; public/identified/private worship; meeting prejoin/waiting/active/disconnected; empty/loading/error staff queues; privacy-restricted sections. Test real content lengths and ministry branding variants before signing off.


## Visual page editor

Public pages use an ordered block document. The staff editor offers a block library, selectable canvas, inspector, page patterns, desktop/tablet/mobile previews, undo/redo, and draft-first publishing. Dynamic blocks must remain unavailable and unrendered when their module is disabled.

The page editor offers an optional AI page-planning panel when `ai_page_editor` is available to the staff member. Staff enter a brief and optional public details. AI returns an ordered page composition using approved blocks, including story, split image/text, image placeholder with search hint and alt text, quote, list, FAQ, and optional call to action. It never invents media URLs, destinations, or ministry facts. Staff review the outline and explicitly apply it before the existing canvas changes; they then choose approved media and link destinations, edit the result, and save it as a draft. Staff may also select one page section and ask AI to revise that section. The request includes the page purpose and complete current page content for context, but its result can change only the selected section. Staff review and accept or reject the proposed replacement. The interface explains that the draft page content will be sent to the installation's configured provider. Sections can also be inserted and edited manually. A clear route or provider error and fallback-model use are shown. AI output never publishes directly and cannot insert arbitrary HTML, scripts, links, media, or unapproved block types. The provider/model used for a successful proposal is visible to staff.

The protected AI model settings page groups provider connections, current catalog health, and the page-editor route. API-key fields are always blank after saving and explain that a new value replaces the encrypted credential. Display the route state as unconfigured, unverified, verified, or unavailable; place catalog refresh and compatibility testing next to the corresponding provider or route. Reuse the staff settings panels, fields, and accent action button styles.

## Editor interaction baseline

- The visual page editor uses selectable blocks, direct canvas editing for text, inspector fields for complete block settings, a responsive canvas preview, media selection from published external assets, undo/redo, ordered patterns, and draft-first publication.


## Public navigation manager

Public navigation is ministry-owned and stored as ordered `navigation_items`. Each link has a label, internal or external URL, optional approved icon, a controlled sidebar section (`Main`, `Connect`, `Resources`, `About`, or `Member area`), parent link for a dropdown, device visibility, optional new-tab behavior, and an optional required feature. Disabled features hide associated links in public rendering. The public sidebar groups top-level links under their selected section. Navigation changes are audited.

The staff Navigation page also configures the staff and member sidebars and mobile footers. Each audience has independent destination selection, ordering, icon, and registered sidebar-section choices; mobile footers allow up to five destinations. Group sidebar entries under their selected section and show the section selector on each chosen destination. Use a searchable visual icon picker with a broad set of bundled, approved SVGs; icon strokes inherit the active foreground color for light and dark themes. Keep picker surfaces neutral, theme-token based, compact, and readable; keep the icon glyph smaller than its tile and use restrained selected and focus treatments. Keep fixed mobile navigation surfaces neutral in light and dark modes, full-width, and square-cornered; use the ministry-configured accent only for the active destination. Published public pages can be selected for either workspace. The public mobile footer uses up to five selected top-level public navigation links in their configured order, then provides More to open the full public navigation. Quick-add choices use visible bundled icons and add through the current page without a full navigation reload; destinations already configured in the public menu are omitted. Public destination rows use the same compact checkbox, label, reorder, section, and icon arrangement as the Staff and Member rows. Unchecking a row removes it from the rendered public menu while retaining the configured link so staff can select it again; permanent deletion stays in Manage link details. Public menu order, selection, section, and icon changes save together. Keep the mobile controls touch-friendly and retain the shared shell's existing theme and focus states. A staff destination is displayed only when its permission and module are available to the current user; menu settings never grant access. When the mobile drawer is open, keep its ministry identity and close control visible above the page header.

- Visual sections support one, two, or three content columns, curated spacing choices, and a validated background colour.

## 11. Visual page editor media policy

The public page editor stores ordered, validated blocks and renders only its approved block types. Its essential block set includes text, image, gallery, responsive YouTube/Vimeo video embed, audio, file download, media-and-text, list, accordion, spacer, service times, events, groups, hero, call to action, and configurable columns/sections. It does not accept arbitrary HTML, scripts, or iframe markup. Video is limited to YouTube and Vimeo URLs and uses privacy-conscious YouTube `youtube-nocookie.com` embeds where applicable. Images, galleries, audio, and files require HTTP or HTTPS URLs; staff should use the ministry media library when it is available. Galleries allow no more than twelve media links.
# Digital school experience

The staff workspace provides a compact course portfolio, course settings, curriculum builder, assessment authoring, live-class scheduling, and enrollment management. The member workspace provides a responsive catalog, clear enrollment states, progress, media-aware lessons, assessments, upcoming live classes, and printable certificates. Both use the shared dashboard shell, theme tokens, responsive navigation, styled controls, and dark-mode surfaces.

