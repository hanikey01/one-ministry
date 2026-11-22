# 07 — Experience and Design System

**Status:** Canonical product-level interface and accessibility rules. The Laravel application is moving toward one shared, neutral light/dark design system with installation-controlled brand inputs. Documented behaviors here apply across staff, member, public, worship, and meeting experiences; the implementation-status document distinguishes shipped source from verified release behavior. The cross-cutting application-experience and performance rules are owned by [18 — Application Experience and Performance Contract](18-experience-performance-contract.md).

## 1. Design goals

The experience should feel editorial, confident, warm, and exceptionally polished while remaining neutral across denominations. Premium quality comes from typography, hierarchy, space, motion restraint, tactile surfaces, meaningful imagery, and complete interaction states rather than decorative excess. Staff tools must feel focused and operational; member tools should feel personal and welcoming; public pages should feel expressive and ministry-owned; worship should feel calm and media-first.

The default visual direction uses the Tech Blue light and dark palette, restrained elevation, large editorial headings, compact readable interface text, and line icons. The product provides Tech Blue, Neutral, and Warm presets. Tech Blue uses a vivid accessible blue for actions and a deep navy navigation chrome. Each preset fixes light/dark canvas, panels, text, borders, focus, status, and action colors across the application. Branding remains installation-owned for logo, one optional custom palette seed, typography choices, imagery, and terminology. A custom seed colour remains the action and active-state accent and generates the shared light/dark surfaces, readable foregrounds, borders, focus, and selection automatically. Navigation chrome uses a darker generated variation of that same seed hue; success, warning, and danger retain their standard semantic meanings.

Staff appearance is configured from the protected settings workspace. Administrators choose one platform theme preset or one custom shared palette seed, curated typography (`modern`, `editorial`, or `humanist`), and density (`comfortable` or `compact`). The custom seed derives the complete shared colour system, including the header, sidebar, sticky mobile footer, actions, and light/dark application surfaces. These choices apply consistently across staff, member, public, worship, and meeting experiences. Arbitrary fonts, remote stylesheets, custom CSS, and individual page-surface colour controls are excluded so an installation cannot accidentally break accessibility or administrative workflows.

Before an installation exists, the root route opens a focused five-step launch journey instead of a public-site placeholder. Database connection is step one, followed by the four-step Ministry, Home base, Theme, and Administrator wizard. Home base captures a named primary location, street-address lines, city or locality, optional region and postal code, country, and time zone; the installation stores a complete formatted address rather than treating the location name as its address. Both screens use the same Tech Blue setup shell, progress treatment, type scale, fields, validation, and responsive behavior. On narrow screens the introduction compacts into a short header with the same progress state; fields remain single column and controls meet touch target requirements. Completion creates the installation atomically, starts a fresh staff session, and then the normal public homepage replaces the wizard permanently.

## 2. Information architecture

| Public | Member | Staff |
| --- | --- | --- |
| Home, Watch Live, Sermons/Resources, Events, Departments, About, Locations, Contact | Profile, My departments, Events, Next Steps, Prayer, Giving | Home, Services, People, Follow-up, Prayer requests, Care, Content, Departments, Events, Messaging, Giving, Reports, Settings |

Navigation is module-aware; disabled modules are absent from desktop and mobile navigation, quick actions, dashboard shortcuts, and module-driven homepage content. Every rendered navigation surface marks the current destination, including configured public links, dropdown children, member and staff sidebars, and mobile footers. Detail routes keep the owning archive destination active. Do not show a link that leads to a permission or module-state error. Direct access remains server-authorized, and disabled modules return 404 at their staff, member, public, and API routes. Staff-preview modules may appear only to staff authorized for preview. The Modules administration page remains visible to its authorized users so they can re-enable a module. On mobile, prioritize Watch Live, Events, Prayer, and Contact when their modules are available.

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
| Digital church | Broadcast-first ministry | Live/upcoming lead, watch action, sermon series, prayer, online departments, next steps |
| Local church | Physical gathering and care | Service times, locations, newcomer welcome, events, departments, children/ministry links when enabled |
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

The member home presents a personal welcome, next registered event, compact activity summary, and direct routes into focused member workspaces. Events, departments, participation, next steps, prayer, giving, and profile/preferences each have a separate member URL and page heading, while retaining the shared sidebar and mobile navigation. Department pages show active membership details and pending join requests without providing a department chat surface. Empty states explain what will appear without pressuring participation. Prayer visibility and contact consent remain explicit. Anonymous stream activity is never shown as member attendance.

Staff and member dashboards use persistent left navigation on desktop. On narrow screens the same navigation opens as an accessible drawer; the member area may also expose a five-item bottom navigation for frequent destinations. Neither dashboard substitutes a horizontal scrolling menu. Staff navigation is grouped by work area and each item has a consistent line icon, text label, permission check, feature check, and active state.

Staff, member, and public navigation share one application chrome: the same logo mark treatment, top-bar height and translucent surface, divider, rounded icon controls, active navigation treatment, drawer transition, focus treatment, and colour tokens. Their information architecture remains audience-specific: staff and members use persistent desktop sidebars, while public pages use a desktop header and the same drawer pattern on small screens. A route may change links and labels, but it does not introduce an unrelated visual navigation system.

Standard staff, member, and public destination pages share one page-heading hierarchy: compact uppercase context, a balanced primary heading, and restrained supporting text. Feature heroes may add imagery or supporting actions, but ordinary archive and account pages do not invent a separate title scale. The public top bar keeps branding and theme controls quiet; service-time access belongs in navigation and page content rather than as a permanent header icon.

The public giving page leads with a calm generosity message, clearly separates gift details, optional donor details, and provider selection, and explains provider confirmation before checkout. Security and receipt explanations remain adjacent to the form without implying that an unconfirmed provider response is a completed donation. Published offline methods remain a distinct section with reconciliation language.

The product should feel like one responsive application while preserving Laravel's server-rendered baseline. Where an action returns a bounded result, prefer in-place save/update with an explicit pending state and shared viewport notification instead of navigating back through a full page reload. Preserve form values and explain recoverable errors; continue supporting ordinary form posts when JavaScript is unavailable. Adopt this behavior route by route and do not imply application-wide coverage until the status document records it.

Staff and member sidebar links use Livewire's hover-prefetched navigation so moving between workspace routes does not reload the whole document. Page-specific browser behavior must initialize after each navigation and release timers, global listeners, and meeting clients when a route is left. Normal links continue to work when JavaScript is unavailable.

The publishing workspace includes a dedicated Pages area alongside Homepage and the content library. Its native structured starter pack provides Homepage, About, Plan a visit, Beliefs, Leadership, Contact, and Blank canvas layouts. Starters are complete version-2 section/column/element documents with responsive settings, coordinated typography, surfaces, spacing, cards, and calls to action; no legacy flat starter is converted at creation time. The Homepage starter is a fully composed public design rather than an unstyled section skeleton, while keeping every native setting and scoped style editable in the inspector. Pages use a visual block editor with a block library, editable structure canvas, exact public-renderer preview, selected-object inspector, and approved elements. The editor clearly distinguishes a private draft from a public page, and saving changes to a published page returns it to draft until an authorized publisher reviews it again.

Department screens use compact team cards and a roster table. The roster clearly distinguishes a linked member account from a roster-only person, shows the department-specific role, and explains the management boundary before an assignment is made. Role choices that a coordinator cannot grant are not presented in the form.

Person profile divides general information, attendance, departments, care, and giving into permission-controlled sections. Do not use hidden tabs as the only security measure. Care and finance interfaces use clear sensitivity labels and audit notices.

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

Time-zone controls use searchable, grouped IANA city identifiers with readable city/region labels and the current UTC offset. Keep `UTC` as the only fixed universal choice; do not promote manual fixed offsets because they do not follow daylight-saving changes. Store the IANA identifier, not the display label. Email workspace tabs, selectable template rows, placeholder-copy chips, primary actions, and secondary actions each use their semantic surface/foreground tokens in both light and dark modes.

Country and state/province/region controls in setup and location editing use one searchable standards-based catalog. Region choices update after country selection. Persist stable codes and resolved labels; keep city/locality as text unless an installation explicitly configures a geocoding provider, because there is no bounded authoritative worldwide city selector.

Success, error, warning, and pending notifications use the shared viewport notification stack. Place it within the current safe viewport, keep messages readable above page content, provide a dismiss action, and auto-dismiss only completed informational messages. Pending work stays visible until it completes; errors and warnings remain long enough to read. Keep inline field errors beside their fields.

## 10. Design acceptance

Staff and member dashboards share the same semantic light/dark surface structure across ministries. The selected platform preset or one ministry palette seed supplies action, canvas, panel, text, border, focus, and status roles. The generated palette colours apply across the header, sidebar, and sticky mobile footer; page-specific palette literals are prohibited.

The appearance settings present three previewable platform presets and one optional custom palette control. The custom control is revealed only when selected and derives all shared light/dark tokens from its one seed colour, including automatically readable foregrounds. The settings screen never exposes independent canvas, panel, action, or per-mode sidebar colour pickers.

Each core state has a design reference and interaction specification: carousel/upcoming/live/interrupted/ended homepage; public/identified/private worship; meeting prejoin/waiting/active/disconnected; empty/loading/error staff queues; privacy-restricted sections. Test real content lengths and ministry branding variants before signing off.


## Visual page editor

Public pages use a versioned structured document. The staff editor offers a section/container library, selectable sections, columns, and child elements; an inspector; page patterns; desktop/tablet/mobile previews; undo/redo; and draft-first publishing. The canvas view switch keeps the editable structure view separate from an exact public-renderer view. The exact renderer loads only on demand and refreshes explicitly so it cannot delay the initial editor load. Existing flat block documents remain readable through a compatibility converter during migration. Dynamic elements must remain unavailable and unrendered when their module is disabled.

The exact preview uses fixed 1180px desktop, 768px tablet, and 390px mobile iframe viewports inside an overflow-contained editor surface, so the public renderer's real media queries run at the selected width rather than simulating the result by scaling a summary canvas. Section controls include bounded custom content width, minimum height, vertical alignment, row and column spacing, complete background-media placement, border, radius, shadow, per-side spacing, responsive layout, identity, and scoped code. Column controls include responsive preset or custom width, minimum height, vertical and horizontal alignment, element gap, order, surface styling, per-side spacing, responsive visibility, identity, and scoped code.

The page editor presents two tabs at the top of one shared draft workspace: **Manual editor** and **AI-assisted editor**. Manual mode exposes the block library, canvas, inspector, responsive preview, ordering, undo/redo, and direct section editing. AI-assisted mode exposes page planning and scoped section revision. Switching tabs never discards unsaved changes because both modes operate on the same in-memory draft and preview. Applying an AI proposal creates an undo point; applying and saving remain separate actions.

The page editor offers optional AI page design when `ai_page_editor` is available. Staff enter a brief and optional public details. AI returns a complete version-2 composition using approved native elements, their validated content and visual settings, and bounded sandboxed custom code only when native elements cannot express the design. It never invents media URLs, destinations, or ministry facts. Staff review and explicitly apply the staged design before the canvas changes, then choose approved media and destinations and save normally. Staff may select a section, column, or element and request a complete redesign of only that object; full public draft context is sent for coherence, while the server inserts and validates only the selected replacement. Application creates one undo point. Manual editing remains complete. Route/provider errors and fallback use are visible, provider/model attribution is shown, and AI output never saves or publishes directly.

The protected AI model settings page departments provider connections, current catalog health, and the page-editor route. API-key fields are always blank after saving and explain that a new value replaces the encrypted credential. Display the route state as unconfigured, unverified, verified, or unavailable; place catalog refresh and compatibility testing next to the corresponding provider or route. Reuse the staff settings panels, fields, and accent action button styles.

## Editor interaction baseline

- The visual page editor uses selectable blocks, direct canvas editing for text, inspector fields for complete block settings, a responsive canvas preview, media selection from published external assets, undo/redo, ordered patterns, and draft-first publication.


## Public navigation manager

Public navigation is ministry-owned and stored as ordered `navigation_items`. Each link has a label, internal or external URL, optional approved icon, a controlled sidebar section (`Main`, `Connect`, `Resources`, `About`, or `Member area`), parent link for a dropdown, device visibility, optional new-tab behavior, and an optional required feature. Disabled features hide associated links in public rendering. The public sidebar departments top-level links under their selected section. Navigation changes are audited.

The staff Navigation page also configures the staff and member sidebars and mobile footers. Each audience has independent destination selection, ordering, icon, and registered sidebar-section choices; mobile footers allow up to five destinations. Department sidebar entries under their selected section and show the section selector on each chosen destination. Use a searchable visual icon picker with a broad set of bundled, approved SVGs; icon strokes inherit the active foreground color for light and dark themes. Keep picker surfaces neutral, theme-token based, compact, and readable; keep the icon glyph smaller than its tile and use restrained selected and focus treatments. Keep fixed mobile navigation surfaces neutral in light and dark modes, full-width, and square-cornered; use the ministry-configured accent only for the active destination. Published public pages can be selected for either workspace. The public mobile footer uses up to five selected top-level public navigation links in their configured order. It has no synthetic **More** destination; the accessible drawer remains the complete public-navigation entry point. Quick-add choices use visible bundled icons and add through the current page without a full navigation reload; destinations already configured in the public menu are omitted. Public destination rows use the same compact checkbox, label, reorder, section, and icon arrangement as the Staff and Member rows. Unchecking a row removes it from the rendered public menu while retaining the configured link so staff can select it again; permanent deletion stays in Manage link details. Public menu order, selection, section, and icon changes save together. Keep the mobile controls touch-friendly and retain the shared shell's existing theme and focus states. A staff destination is displayed only when its permission and module are available to the current user; menu settings never grant access. When the mobile drawer is open, keep its ministry identity and close control visible above the page header.

- Visual sections support a parent container with one or more columns, responsive column widths and stacking, curated spacing choices, and a validated background colour. Elements belong to columns rather than sitting directly on the page. The editor may constrain the initial presets for simplicity, but the document contract must support arbitrary approved element counts within each column.

## 11. Visual page editor media policy

The public page editor stores ordered, validated blocks and renders only its approved block types. Its essential block set includes text, image, gallery, responsive video embed, audio, file download, media-and-text, list, accordion, spacer, service times, events, departments, hero, call to action, and configurable columns/sections. Per D-036, raw HTML, CSS, and JavaScript are accepted only by the dedicated advanced-code element, which renders in an opaque-origin sandbox with a restrictive CSP; ordinary document fields never inject markup or scripts into the application document. YouTube uses privacy-conscious `youtube-nocookie.com` embeds, Vimeo uses its player, direct internal and external video resources use the native player, and other HTTP(S) video destinations use the isolated embed fallback. Images, galleries, video, audio, files, backgrounds, and marked AI-canvas media positions accept HTTP(S) URLs or validated installation-local paths; staff should use the ministry media library when it is available. AI-generated output still cannot invent remote media URLs: staff must explicitly select library media or enter the URL. Background-image markers expose image fit, repeat, and nine-position controls and default newly selected media to cover, no-repeat, and center. Galleries allow no more than twelve media links. Media application failures remain visible without closing the picker or claiming a change, and successful media changes participate in editor undo. A successful asynchronous save is shown as durable only after the server returns the authoritative normalized document; edits made while a save is in flight remain visibly unsaved.
# Digital school experience

The staff workspace provides a compact course portfolio, course settings, curriculum builder, assessment authoring, live-class scheduling, and enrollment management. The member workspace provides a responsive catalog, clear enrollment states, progress, media-aware lessons, assessments, upcoming live classes, and printable certificates. Both use the shared dashboard shell, theme tokens, responsive navigation, styled controls, and dark-mode surfaces.

