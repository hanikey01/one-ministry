from docx import Document
from docx.shared import Inches, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_CELL_VERTICAL_ALIGNMENT
from docx.enum.section import WD_SECTION
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.enum.style import WD_STYLE_TYPE
from pathlib import Path

OUT = Path(r"C:\Users\HP\Documents\ChatGPT\christian ministry\docs\proposals\OneMinistry_Project_Overview.docx")
OUT.parent.mkdir(parents=True, exist_ok=True)

NAVY = "17324D"
GREEN = "1F5A47"
GOLD = "D6A84A"
PALE = "EFF5F2"
LIGHT = "F5F7F8"
MID = "65736D"
WHITE = "FFFFFF"
BLACK = "000000"
BORDER = "D9D9D9"

doc = Document()
sec = doc.sections[0]
sec.top_margin = Inches(.72)
sec.bottom_margin = Inches(.72)
sec.left_margin = Inches(.78)
sec.right_margin = Inches(.78)

def font(run, name="Aptos", size=None, bold=None, color=None):
    run.font.name = name
    run._element.get_or_add_rPr().rFonts.set(qn("w:ascii"), name)
    run._element.get_or_add_rPr().rFonts.set(qn("w:hAnsi"), name)
    if size: run.font.size = Pt(size)
    if bold is not None: run.bold = bold
    if color: run.font.color.rgb = RGBColor.from_string(color)

styles = doc.styles
normal = styles["Normal"]
normal.font.name = "Aptos"
normal.font.size = Pt(10.2)
normal.font.color.rgb = RGBColor.from_string("26332E")
normal.paragraph_format.space_after = Pt(7)
normal.paragraph_format.line_spacing = 1.14

for style_name, size, before, after in [("Title", 31, 0, 12), ("Heading 1", 21, 14, 8), ("Heading 2", 13.5, 10, 5), ("Heading 3", 11, 8, 4)]:
    s = styles[style_name]
    s.font.name = "Aptos Display" if style_name != "Heading 3" else "Aptos"
    s.font.size = Pt(size)
    s.font.bold = style_name != "Title"
    s.font.color.rgb = RGBColor.from_string(BLACK)
    s.paragraph_format.space_before = Pt(before)
    s.paragraph_format.space_after = Pt(after)
    s.paragraph_format.keep_with_next = True

if "Feature Name" not in styles:
    s = styles.add_style("Feature Name", WD_STYLE_TYPE.PARAGRAPH)
    s.font.name = "Aptos"
    s.font.size = Pt(10.3)
    s.font.bold = True
    s.font.color.rgb = RGBColor.from_string(BLACK)
    s.paragraph_format.space_after = Pt(2)
    s.paragraph_format.keep_with_next = True

def shade(cell, fill):
    tcPr = cell._tc.get_or_add_tcPr()
    shd = tcPr.find(qn("w:shd"))
    if shd is None:
        shd = OxmlElement("w:shd")
        tcPr.append(shd)
    shd.set(qn("w:fill"), fill)

def set_cell_margins(cell, top=110, start=130, bottom=110, end=130):
    tc = cell._tc
    tcPr = tc.get_or_add_tcPr()
    tcMar = tcPr.first_child_found_in("w:tcMar")
    if tcMar is None:
        tcMar = OxmlElement("w:tcMar")
        tcPr.append(tcMar)
    for m, v in (("top", top), ("start", start), ("bottom", bottom), ("end", end)):
        node = tcMar.find(qn(f"w:{m}"))
        if node is None:
            node = OxmlElement(f"w:{m}")
            tcMar.append(node)
        node.set(qn("w:w"), str(v)); node.set(qn("w:type"), "dxa")

def borders(table):
    tblPr = table._tbl.tblPr
    b = tblPr.find(qn("w:tblBorders"))
    if b is None:
        b = OxmlElement("w:tblBorders"); tblPr.append(b)
    for edge in ("top", "left", "bottom", "right", "insideH", "insideV"):
        el = OxmlElement(f"w:{edge}")
        el.set(qn("w:val"), "single"); el.set(qn("w:sz"), "4"); el.set(qn("w:color"), BORDER)
        b.append(el)

def set_repeat_header(row):
    trPr = row._tr.get_or_add_trPr(); el = OxmlElement("w:tblHeader"); el.set(qn("w:val"), "true"); trPr.append(el)

def add_label(text):
    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(5)
    r = p.add_run(text.upper()); font(r, size=8.2, bold=True, color=GREEN); r.font.letter_spacing = Pt(1)
    return p

def add_bullets(items, level=0):
    for item in items:
        p = doc.add_paragraph(style="List Bullet" if level == 0 else "List Bullet 2")
        p.paragraph_format.space_after = Pt(3)
        p.add_run(item)

def add_feature(title, body):
    p = doc.add_paragraph(style="Feature Name")
    p.add_run(title)
    b = doc.add_paragraph(body)
    b.paragraph_format.left_indent = Inches(.12)
    b.paragraph_format.space_after = Pt(8)

def add_table(headers, rows, widths):
    t = doc.add_table(rows=1, cols=len(headers))
    t.alignment = WD_TABLE_ALIGNMENT.CENTER
    t.autofit = False
    borders(t)
    hdr = t.rows[0]
    set_repeat_header(hdr)
    for i, h in enumerate(headers):
        c = hdr.cells[i]; c.width = Inches(widths[i]); shade(c, NAVY); set_cell_margins(c)
        c.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
        p = c.paragraphs[0]; p.paragraph_format.space_after = Pt(0)
        r = p.add_run(h); font(r, size=9, bold=True, color=WHITE)
    for ri, row in enumerate(rows):
        cells = t.add_row().cells
        for i, value in enumerate(row):
            c = cells[i]; c.width = Inches(widths[i]); set_cell_margins(c); c.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
            if ri % 2: shade(c, LIGHT)
            p = c.paragraphs[0]; p.paragraph_format.space_after = Pt(0); p.paragraph_format.line_spacing = 1.05
            r = p.add_run(value); font(r, size=8.7, color="26332E")
    doc.add_paragraph().paragraph_format.space_after = Pt(2)
    return t

def page_break(): doc.add_page_break()

# Header and footer
header = sec.header
hp = header.paragraphs[0]
hp.text = "ONEMINISTRY PLATFORM    PROJECT OVERVIEW"
hp.alignment = WD_ALIGN_PARAGRAPH.RIGHT
font(hp.runs[0], size=7.4, bold=True, color=MID)
footer = sec.footer
fp = footer.paragraphs[0]
fp.alignment = WD_ALIGN_PARAGRAPH.CENTER
field = OxmlElement("w:fldSimple"); field.set(qn("w:instr"), "PAGE")
fp._p.append(field)

# Cover
doc.add_paragraph().paragraph_format.space_after = Pt(44)
add_label("A digital ministry platform for churches and Christian ministries")
p = doc.add_paragraph(style="Title")
p.add_run("OneMinistry Platform")
sub = doc.add_paragraph()
sub.paragraph_format.space_after = Pt(24)
r = sub.add_run("Project overview and ministry capability brief"); font(r, size=16, color=MID)

intro = doc.add_paragraph()
intro.paragraph_format.space_after = Pt(28)
r = intro.add_run("A complete, ministry-owned platform for public communication, digital worship, member care, community life, content, giving, and administration.")
font(r, size=13.2, color=NAVY)

add_table(["Working name", "Audience", "Document purpose"], [["OneMinistry Platform", "Pastors, ministry leaders, administrators and digital teams", "Introduce the vision, capabilities, ownership model and current development position"]], [1.55, 2.35, 3.0])

p = doc.add_paragraph()
p.paragraph_format.space_before = Pt(22)
r = p.add_run("Working name note. "); font(r, bold=True, color=BLACK)
p.add_run("OneMinistry Platform is the recommended project name for discussion. It reflects one independently operated installation serving the whole ministry. Trademark, domain and local registration checks should be completed before public launch.")

p = doc.add_paragraph()
p.paragraph_format.space_before = Pt(48)
r = p.add_run("Prepared for ministry discussion"); font(r, size=9, bold=True, color=GREEN)
p2 = doc.add_paragraph("October 2026")
font(p2.runs[0], size=9, color=MID)

page_break()

doc.add_heading("Executive summary", level=1)
doc.add_paragraph("OneMinistry Platform is being developed as a self-hosted Christian ministry web application that a church, fellowship, prayer ministry, outreach organization, or multi-branch ministry can operate under its own name and domain. It brings the public website, digital church, member experience, people care, community operations, media libraries, giving, communications, and administration into one configurable system.")
doc.add_paragraph("The platform is designed around ministry ownership. Each installation belongs to one ministry, while that ministry may manage several branches or campuses. The ministry controls its information, branding, enabled modules, provider accounts, backups, and deployment. The code does not depend on a central cross-ministry member database.")
doc.add_paragraph("Development is active. A substantial product foundation and many feature workflows have been implemented in source, with several already available in the local deployment. Additional provider integration, capacity testing, security hardening, operational validation, and staged release work remain before the platform should be presented as production ready.")

doc.add_heading("Why the name fits", level=2)
add_bullets([
    "One ministry experience: public visitors, members, leaders, care teams, producers and administrators use one connected platform.",
    "One ministry owns each installation: ministry data and provider accounts remain under that ministry's control.",
    "One adaptable foundation: ministries can enable the modules they need and choose language, branding, locations and external providers.",
])

doc.add_heading("The ministry need", level=1)
add_table(["Common challenge", "How OneMinistry responds"], [
    ("Disconnected tools", "Connects the website, livestream, people records, follow-up, groups, events, serving, content and administration."),
    ("Online viewers without a ministry pathway", "Combines live worship with chat, prayer, newcomer responses, polls and assigned follow-up."),
    ("Weak visibility into participation", "Keeps identified attendance, anonymous viewer totals, group participation, events and serving as clearly defined records."),
    ("Sensitive information mixed together", "Separates pastoral care, giving, children and security into distinct permission areas."),
    ("Vendor dependence", "Supports ministry-owned hosting, domains, media providers, payment providers, storage, backups and export paths."),
], [2.05, 4.85])

page_break()
doc.add_heading("The complete ministry experience", level=1)
doc.add_paragraph("The platform is intended to support a person from first contact through meaningful participation, while giving ministry teams practical tools to serve responsibly.")
add_table(["Journey stage", "Experience"], [
    ("Discover", "A visitor learns about the ministry, locations, beliefs, services, events, groups and published resources."),
    ("Join worship", "The visitor enters a dedicated live worship room with video, chat, polls, captions, lower-bandwidth options and next-step actions."),
    ("Connect", "The visitor can identify as new, request prayer, ask to speak with someone, join a group or register for an event."),
    ("Belong", "A member can manage a profile, preferences, groups, registrations, serving commitments, giving history and ministry journeys."),
    ("Receive care", "Authorized teams receive assigned follow-up and prayer workflows without exposing private content to unrelated staff."),
    ("Serve and grow", "People can participate in groups, discipleship, events, volunteer teams, baptism, membership and other ministry-defined pathways."),
], [1.45, 5.45])

doc.add_heading("Feature overview", level=1)
doc.add_heading("Public website and identity", level=2)
add_feature("Ministry branding", "Ministry name, tagline, domain, contact details, primary and accent colours, theme presets, locations, and terminology can be configured for each installation.")
add_feature("Public page builder", "Staff can create pages such as About, Beliefs, Leadership, Departments, Locations and Contact. Draft, editorial review, publishing, SEO metadata and immutable revisions are included.")
add_feature("Feature-aware future homepage", "The planned homepage system will choose approved sections according to the modules a ministry activates. During a service, its lead area can show the live experience; outside service time it can show curated content and media.")
add_feature("Public discovery", "Visitors can browse events, groups, books, sermons, media, locations and giving opportunities according to the modules enabled by the ministry.")

doc.add_heading("Digital Church and livestream", level=2)
add_feature("Service scheduling", "Weekly and monthly recurring services, one-time gatherings, location and timezone handling, service templates, generated occurrences and rehearsal mode are supported.")
add_feature("Service lifecycle", "Services move through explicit draft, scheduled, ready, live, interrupted, ended and cancelled states. A schedule alone never makes a service appear live.")
add_feature("Broadcast provider flexibility", "A service can use a ministry-owned YouTube Live, Vimeo Live or HTTPS HLS and CDN playback source. The application imposes no fixed viewer cap; real capacity depends on the chosen provider and tested infrastructure.")
add_feature("Worship room", "The viewer experience includes live video, responsive mobile presentation, privacy-enhanced embeds, captions, lower-bandwidth HLS, service status, chat, polls and ministry response actions.")
add_feature("Production control room", "Producers manage run sheets, internal notes, timed prompts, polls, stream configuration, encoder health, viewer playback health and interruption status.")
add_feature("Resilient service experience", "When video fails, the room remains useful. It shows ministry-selected outage information while keeping eligible chat, prayer and connection tools available.")

page_break()
doc.add_heading("Feature overview continued", level=1)
doc.add_heading("People participation and care", level=2)
add_feature("People directory", "Staff can manage people records, visitor intake, contact details, relationship information and duplicate review without creating a shared database across ministries.")
add_feature("Attendance integrity", "The platform separates in-person check-in, identified online presence, group attendance, event attendance, serving, and anonymous viewer totals. An anonymous stream view is never treated as a known person's attendance.")
add_feature("Follow-up workflows", "Newcomers, connection requests, prayer requests, missed gatherings and ministry journeys can create assigned follow-up work with status and accountability.")
add_feature("Private care", "Pastoral cases and encrypted notes live in a restricted permission domain with assignment controls, verified-email and multifactor requirements, and audited access.")
add_feature("Celebrations and consent", "Birthday reminders, communication preferences and public recognition depend on the person's recorded choices.")

doc.add_heading("Member workspace", level=2)
add_feature("Personal dashboard", "Members can see upcoming events, groups, serving commitments, recent identified attendance, open ministry actions and profile information in a responsive workspace.")
add_feature("Prayer and next steps", "Members can submit private prayer requests and request membership, baptism, a new-member class, discipleship or serving follow-up with explicit contact consent.")
add_feature("Giving history", "An authenticated member can see only their own eligible gifts, fund, status and receipt state.")
add_feature("Privacy preferences", "Members can update basic profile details, communication preferences and birthday visibility choices in plain language.")

doc.add_heading("Community and ministry operations", level=2)
add_feature("Groups", "Public group discovery, join requests, staff review, membership roles, attendance and group leader operations are included.")
add_feature("Events", "Event publishing, registration, capacity, waitlists, roster operations, check-in, cancellation and waitlist promotion are supported.")
add_feature("Volunteering", "Volunteer teams, assignments, acceptance, decline, substitution, schedules and service history support ministry serving operations.")
add_feature("Interactive meetings", "A separate meeting foundation supports smaller gatherings that need participant access and recording notice. Broadcast services and interactive meetings remain distinct because their scale and attendance rules differ.")

page_break()
doc.add_heading("Content communication and stewardship", level=1)
doc.add_heading("Libraries and teaching content", level=2)
add_feature("Separate content libraries", "Books, audio sermons, video sermons and sermon series operate as separate libraries. Each can be activated or disabled by the administrator and is blocked server-side when disabled.")
add_feature("Flexible books", "Books can be uploaded as PDF, EPUB, DOC, DOCX, MOBI, AZW, AZW3 or TXT files, with categories, tags and publication status.")
add_feature("Audio and video sermons", "Sermons support speakers, scripture references, duration, categories, tags and series. Video sermons can use a YouTube link, while approved uploaded content uses controlled delivery routes.")
add_feature("Editorial control", "Content permissions, pending review, approval, rejection, audit history and public-only published delivery are part of the content foundation.")

doc.add_heading("Communication", level=2)
add_feature("Campaigns and templates", "Staff can prepare email or SMS campaigns, message templates and recipient queues with delivery history and retry status.")
add_feature("Consent-aware audiences", "Optional ministry communication respects preferences, purpose-specific consent and quiet-hour rules.")
add_feature("Service engagement", "Chat, polls, newcomers, prayer and speak-to-someone actions turn a broadcast into an actionable ministry experience.")

doc.add_heading("Giving and reporting", level=2)
add_feature("Ministry-defined funds", "Ministries can present giving funds and create donation intents through a configured payment provider.")
add_feature("Financial controls", "Verified provider events, idempotency, immutable payment history, reconciliation, receipts and finance-only permissions protect stewardship workflows.")
add_feature("Defined reports", "Reporting foundations cover service participation, follow-up, groups, volunteers and aggregate giving with definitions that help leaders interpret each number responsibly.")

doc.add_heading("Administration and security", level=2)
add_feature("Premium staff workspace", "A responsive dashboard provides a persistent desktop sidebar, accessible mobile drawer, operational overview, shared design system and configurable ministry theme colours.")
add_feature("Staff identity and access", "Session security, staff invitations, password recovery, email verification, authenticator-app multifactor authentication, role assignments and permission checks form the access foundation.")
add_feature("Auditability", "Sensitive changes, restricted reads, service transitions, health changes, publishing actions, attendance corrections and finance actions create traceable records.")

page_break()
doc.add_heading("How a live service works", level=1)
add_table(["Stage", "Producer and host actions", "Public experience"], [
    ("Prepare", "Choose a scheduled service, configure the ministry-owned playback source, prepare the run sheet, notes, prompts and polls.", "The service remains upcoming. No calendar time alone can display it as live."),
    ("Verify", "Check encoder ingest and viewer playback separately. Confirm captions and lower-bandwidth delivery where available.", "The room stays unavailable until the service and stream are ready."),
    ("Go live", "Move the service into live state after healthy playback or an explicit authorized override.", "The worship room displays live video, service details, chat, polls and response actions."),
    ("Respond", "Hosts welcome people, moderate chat, fire prompts, open polls and monitor ministry responses.", "Viewers can participate without being forced to create an account."),
    ("Handle interruption", "Record an offline health state, investigate the provider or encoder, and update the congregation.", "Video changes to the ministry's fallback message while useful nonvideo interactions remain available."),
    ("Resume or end", "Verify restored playback and explicitly resume, or end the service and send recordings to review.", "Live presentation stops. A replay appears only after approval."),
], [1.0, 3.0, 3.0])

doc.add_heading("Privacy and ministry trust", level=1)
add_bullets([
    "One ministry owns one installation. Branches and campuses remain inside that ministry's installation.",
    "Anonymous playback contributes only to aggregate viewing information and never becomes a named attendance record.",
    "Giving, pastoral care, children and security use separate permission boundaries.",
    "Prayer and pastoral content is not exposed to general service hosts or ordinary staff.",
    "Public recognition and optional communication depend on recorded consent.",
    "Feature disablement is enforced by the server as well as hidden from the interface.",
    "Stream keys, provider credentials, payment secrets and storage credentials stay outside public responses and client bundles.",
    "Sensitive reads and changes are designed to be auditable.",
])

doc.add_heading("Ownership and deployment", level=1)
doc.add_paragraph("OneMinistry Platform is designed for independent deployment. A ministry can host the application on infrastructure it controls or with a hosting partner, connect its own domain, and choose its own email, SMS, payment, media, storage and meeting providers. The architecture avoids requiring a vendor-operated central member service.")
add_table(["Area", "Ministry control"], [
    ("Identity", "Name, domain, logo, colours, terminology, locations and public content"),
    ("Modules", "Digital Church, groups, giving, libraries and other capabilities can be activated according to ministry needs"),
    ("Providers", "Ministry-owned media, payment, messaging and storage accounts"),
    ("Data", "Installation-scoped records, documented exports, backups and restore procedures"),
    ("Capacity", "Declared viewer targets and provider limits validated through rehearsal and load testing"),
], [1.55, 5.45])

page_break()
doc.add_heading("Current development position", level=1)
doc.add_paragraph("The project has moved beyond a visual concept. Core application scaffolding and many end-to-end feature foundations are implemented. The status below distinguishes working source from the remaining production release work.")
add_table(["Area", "Current position"], [
    ("Foundation", "Application stack, installation, branding, identity, security controls, permissions, audit, backups and storage preflight foundations are implemented."),
    ("Digital Church", "Service schedules, lifecycle, worship room, chat, response actions, polls, production controls and livestream source and health management are implemented in source."),
    ("People and care", "People, attendance, follow-up, member profile, prayer, ministry journeys and restricted care foundations are implemented in source."),
    ("Community", "Groups, join requests, attendance, events, registrations, volunteer operations and meetings foundations are implemented."),
    ("Content", "Media, books, sermon libraries, YouTube delivery, series, tags, categories, editorial review foundation and public pages are implemented in source."),
    ("Giving and communication", "Funds, donation workflow foundations, provider event controls, receipts, reconciliation, campaigns, templates and preferences are implemented."),
    ("Release readiness", "Grouped migrations and deployment, provider-specific end-to-end verification, full role administration, capacity testing, accessibility and device verification, security review, staged operations and module release gates remain."),
], [1.55, 5.45])

doc.add_heading("What the platform does not claim", level=2)
add_bullets([
    "It does not claim unlimited livestream capacity. Capacity depends on the chosen media provider, contract, geography and tested infrastructure.",
    "It does not identify anonymous viewers as individual attendees.",
    "It does not treat software activity as a judgment of a person's spiritual condition.",
    "It is not yet being presented as a finished production release.",
])

doc.add_heading("Recommended ministry pilot", level=1)
doc.add_paragraph("A ministry pilot should begin with a discovery and configuration workshop, followed by a controlled service rehearsal. The goal is to confirm that the platform reflects the ministry's actual language, governance, care practices, locations, volunteer structure and digital audience.")
add_table(["Pilot step", "Decision or evidence"], [
    ("1  Ministry discovery", "Confirm ministry structure, locations, service types, leadership roles, terminology and priority modules."),
    ("2  Governance review", "Approve privacy boundaries, permissions, care handling, communication consent, retention and safeguarding requirements."),
    ("3  Provider selection", "Choose hosting, livestream, storage, email, SMS and payment providers owned by the ministry."),
    ("4  Configuration", "Apply branding, theme colours, schedules, libraries, groups, funds, forms and workflows."),
    ("5  Rehearsal", "Run a private service rehearsal with producers, hosts, moderators, viewers and follow-up teams."),
    ("6  Release gate", "Verify capacity, playback, accessibility, backup restore, security, incident handling and staff training before public activation."),
], [1.55, 5.45])

doc.add_heading("Discussion questions for ministry leaders", level=1)
add_bullets([
    "Which ministry experiences should be available at launch, and which should remain disabled?",
    "How many branches, campuses, online communities and service types must the installation support?",
    "Which staff roles should see people, giving, pastoral care, security and children's information?",
    "What livestream audience, locations, latency, reliability and budget should the provider support?",
    "How should newcomer, prayer, membership, baptism, discipleship and serving requests be assigned and followed up?",
    "Which current systems need data import, integration or replacement?",
    "What legal, safeguarding, retention and financial requirements apply in the ministry's jurisdiction?",
])

doc.add_heading("Proposed next step", level=1)
doc.add_paragraph("Review this overview with pastoral, administrative, media, care, finance and technical leaders. Select one representative service and one ministry journey for a structured pilot. The pilot should produce a signed capability scope, permission matrix, provider plan, capacity target and release checklist before public use.")

doc.core_properties.title = "OneMinistry Platform Project Overview"
doc.core_properties.subject = "Ministry capability and project overview"
doc.core_properties.author = "OneMinistry Platform Project"
doc.core_properties.keywords = "ministry platform, digital church, livestream, member care, church management"
doc.save(OUT)
print(OUT)
