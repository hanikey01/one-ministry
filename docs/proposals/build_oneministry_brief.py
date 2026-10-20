from docx import Document
from docx.shared import Inches, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.section import WD_SECTION
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_CELL_VERTICAL_ALIGNMENT
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.enum.style import WD_STYLE_TYPE
from docx.enum.text import WD_BREAK
from pathlib import Path

OUT = Path('docs/proposals/OneMinistry_Project_Brief.docx')

NAVY = '173B2A'
GREEN = '1D6A43'
PALE_GREEN = 'EAF4ED'
LIGHT = 'F4F7F5'
MID = 'D9E2DC'
TEXT = '1C2921'
MUTED = '52645A'
WHITE = 'FFFFFF'


def shade(cell, fill):
    tcPr = cell._tc.get_or_add_tcPr()
    shd = tcPr.find(qn('w:shd'))
    if shd is None:
        shd = OxmlElement('w:shd')
        tcPr.append(shd)
    shd.set(qn('w:fill'), fill)


def borders(cell, color=MID):
    tcPr = cell._tc.get_or_add_tcPr()
    borders_el = tcPr.first_child_found_in('w:tcBorders')
    if borders_el is None:
        borders_el = OxmlElement('w:tcBorders')
        tcPr.append(borders_el)
    for edge in ('top', 'left', 'bottom', 'right', 'insideH', 'insideV'):
        tag = qn(f'w:{edge}')
        el = borders_el.find(tag)
        if el is None:
            el = OxmlElement(f'w:{edge}')
            borders_el.append(el)
        el.set(qn('w:val'), 'single')
        el.set(qn('w:sz'), '6')
        el.set(qn('w:color'), color)


def set_cell_margins(cell, top=100, start=120, bottom=100, end=120):
    tc = cell._tc
    tcPr = tc.get_or_add_tcPr()
    tcMar = tcPr.first_child_found_in('w:tcMar')
    if tcMar is None:
        tcMar = OxmlElement('w:tcMar')
        tcPr.append(tcMar)
    for m, v in [('top', top), ('start', start), ('bottom', bottom), ('end', end)]:
        node = tcMar.find(qn(f'w:{m}'))
        if node is None:
            node = OxmlElement(f'w:{m}')
            tcMar.append(node)
        node.set(qn('w:w'), str(v))
        node.set(qn('w:type'), 'dxa')


def keep_with_next(paragraph):
    pPr = paragraph._p.get_or_add_pPr()
    keep = OxmlElement('w:keepNext')
    pPr.append(keep)


def set_repeat_table_header(row):
    trPr = row._tr.get_or_add_trPr()
    tblHeader = OxmlElement('w:tblHeader')
    tblHeader.set(qn('w:val'), 'true')
    trPr.append(tblHeader)


def add_run(p, text, bold=False, color=None, size=None, italic=False):
    r = p.add_run(text)
    r.bold = bold
    r.italic = italic
    if color: r.font.color.rgb = RGBColor.from_string(color)
    if size: r.font.size = Pt(size)
    return r


def add_bullet(doc, text):
    p = doc.add_paragraph(style='List Bullet')
    p.paragraph_format.space_after = Pt(4)
    p.paragraph_format.line_spacing = 1.15
    p.add_run(text)
    return p


def add_table(doc, headers, rows, widths=None):
    table = doc.add_table(rows=1, cols=len(headers))
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.autofit = False
    hdr = table.rows[0]
    set_repeat_table_header(hdr)
    for i, h in enumerate(headers):
        c = hdr.cells[i]
        shade(c, NAVY)
        borders(c, NAVY)
        set_cell_margins(c)
        c.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
        p = c.paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.LEFT
        r = p.add_run(h)
        r.bold = True; r.font.color.rgb = RGBColor.from_string(WHITE); r.font.size = Pt(9)
        if widths: c.width = Inches(widths[i])
    for idx, row in enumerate(rows):
        cells = table.add_row().cells
        for i, val in enumerate(row):
            c = cells[i]
            shade(c, 'FFFFFF' if idx % 2 == 0 else LIGHT)
            borders(c)
            set_cell_margins(c)
            c.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
            if widths: c.width = Inches(widths[i])
            p = c.paragraphs[0]
            p.paragraph_format.space_after = Pt(0)
            p.paragraph_format.line_spacing = 1.1
            r = p.add_run(val)
            r.font.size = Pt(9)
    doc.add_paragraph().paragraph_format.space_after = Pt(2)
    return table


doc = Document()
sec = doc.sections[0]
sec.top_margin = Inches(.72); sec.bottom_margin = Inches(.68)
sec.left_margin = Inches(.72); sec.right_margin = Inches(.72)

styles = doc.styles
styles['Normal'].font.name = 'Aptos'; styles['Normal']._element.rPr.rFonts.set(qn('w:eastAsia'), 'Aptos')
styles['Normal'].font.size = Pt(10.5); styles['Normal'].font.color.rgb = RGBColor.from_string(TEXT)
styles['Normal'].paragraph_format.space_after = Pt(7); styles['Normal'].paragraph_format.line_spacing = 1.18
for name, size, color, space_before, space_after in [
    ('Title', 27, '000000', 0, 8), ('Heading 1', 16, '000000', 17, 7), ('Heading 2', 12, '000000', 12, 5), ('Subtitle', 11, MUTED, 0, 14)]:
    st = styles[name]; st.font.name='Aptos Display' if name in ('Title','Heading 1') else 'Aptos'; st._element.rPr.rFonts.set(qn('w:eastAsia'), st.font.name)
    st.font.size=Pt(size); st.font.color.rgb=RGBColor.from_string(color); st.font.bold=name.startswith('Heading') or name=='Title'
    st.paragraph_format.space_before=Pt(space_before); st.paragraph_format.space_after=Pt(space_after)
    st.paragraph_format.keep_with_next=True
styles['List Bullet'].font.name='Aptos'; styles['List Bullet'].font.size=Pt(10.5)

# header/footer
header = sec.header.paragraphs[0]
header.alignment = WD_ALIGN_PARAGRAPH.RIGHT
add_run(header, 'ONEMINISTRY  |  PROJECT BRIEF', True, GREEN, 8)
footer = sec.footer.paragraphs[0]
footer.alignment = WD_ALIGN_PARAGRAPH.CENTER
add_run(footer, 'OneMinistry Project Brief  •  October 2026', False, MUTED, 8)

# cover
p = doc.add_paragraph()
p.paragraph_format.space_before = Pt(52); p.paragraph_format.space_after = Pt(7)
add_run(p, 'ONEMINISTRY', True, GREEN, 12)
t = doc.add_paragraph('OneMinistry Project Brief', style='Title')
t.paragraph_format.space_after = Pt(8)
sub = doc.add_paragraph('A self hosted digital ministry platform for public presence, pastoral connection, community operations, and live worship.', style='Subtitle')
sub.paragraph_format.space_after = Pt(25)

cover = doc.add_table(rows=1, cols=1); cover.alignment=WD_TABLE_ALIGNMENT.CENTER
cell=cover.cell(0,0); shade(cell, PALE_GREEN); borders(cell, 'BFD7C5'); set_cell_margins(cell, 220, 250, 220, 250)
p=cell.paragraphs[0]; p.paragraph_format.space_after=Pt(5)
add_run(p,'Purpose',True,NAVY,11)
p=cell.add_paragraph(); p.paragraph_format.space_after=Pt(0); p.paragraph_format.line_spacing=1.15
add_run(p,'OneMinistry gives a church, fellowship, outreach, or prayer ministry one configurable web application it can host under its own identity. It brings the public website, member experience, staff operations, media, live services, and follow up into one ministry owned installation.',False,TEXT,11)

doc.add_paragraph('Prepared for ministry conversations', style='Heading 2')
meta = add_table(doc, ['Audience', 'Use'], [
    ['Ministry leaders, administrators, digital teams, and implementation partners', 'Explain the platform vision, the value it offers, what is already in place, and the next delivery priorities.']
], [2.65, 4.15])

doc.add_paragraph().add_run().add_break(WD_BREAK.PAGE)

# 1
h=doc.add_paragraph('What OneMinistry is', style='Heading 1')
keep_with_next(h)
p=doc.add_paragraph('OneMinistry is a ministry owned digital operating platform. It is designed for organisations that need more than a brochure website but do not want their people, ministry data, communication, and live worship experience split across unrelated tools. Each ministry runs its own installation, connects its own providers, uses its own domain and brand, and controls its own records.')

h=doc.add_paragraph('The gap it solves', style='Heading 2'); keep_with_next(h)
add_table(doc, ['Common ministry challenge', 'OneMinistry response'], [
    ['Public website becomes outdated or disconnected from ministry life', 'A configurable public site and homepage editor draw from real services, events, groups, locations, content, prayer, giving, and next steps.'],
    ['Online services are only a video link with no pathway for people', 'A dedicated worship room connects broadcast media with moderated chat, polls, prayer, newcomer and connection actions, and staff follow up.'],
    ['Member records and participation live in separate spreadsheets or products', 'People, attendance, events, groups, serving, follow up, member self service, and reporting are designed as connected workflows.'],
    ['Teams need access without seeing sensitive pastoral or giving information', 'Scoped roles, team memberships, verified email, MFA gates, audit trails, and separate restricted data domains keep access purposeful.'],
    ['Technology choices lock a ministry into a vendor or central data store', 'The installation belongs to one ministry. External email, payment, storage, media, and video providers are configured by that ministry.']
], [2.45, 4.35])

h=doc.add_paragraph('Product principles', style='Heading 2'); keep_with_next(h)
for text in [
    'One ministry per installation, with optional campuses, branches, or locations.',
    'A large public service is a broadcast experience; a smaller interactive gathering is a meeting with different controls and capacity rules.',
    'Participation data supports pastoral care and follow up; it does not label people by an automated spiritual score.',
    'Ministries can activate modules, choose terminology, set branding, and configure providers without changing source code.',
    'Sensitive care, finance, and children related information remain in separately protected permission domains.',
    'The target deployment model supports ordinary PHP shared hosting, with Docker and other infrastructure remaining optional.'
]: add_bullet(doc,text)

# 2
h=doc.add_paragraph('What makes the platform different', style='Heading 1'); keep_with_next(h)
p=doc.add_paragraph('The innovation in OneMinistry is not one isolated feature. It is the connection between a ministry’s public presence and the work that happens after someone responds. A visitor can find an event, join worship, request prayer, join a group, become known with consent, and receive appropriate follow up without staff re entering the same information in multiple systems.')
add_table(doc, ['Innovation', 'Why it matters'], [
    ['Feature aware website', 'Homepage layouts and public sections respond to the modules a ministry chooses to activate. A ministry with groups, events, giving, libraries, and digital church can present all of them without a custom rebuild.'],
    ['Live worship state model', 'The homepage shows curated media when no service is live, a live invitation only when playable media is healthy, and a clear fallback if a provider has an outage.'],
    ['Responsible engagement records', 'Anonymous viewers are counted only as aggregates. Identified attendance requires a meaningful action or check in. Staff can correct records with a reason and audit trail.'],
    ['One experience across surfaces', 'Public visitors, members, staff, hosts, producers, coordinators, and finance users have purpose built areas with shared ministry identity and controlled access.'],
    ['Portable provider approach', 'A ministry can connect its own email sender, object storage, payment gateway, video source, and Zoom Video SDK account. The application does not require a vendor controlled central service.']
], [1.75, 5.05])

# 3 built
h=doc.add_paragraph('Capabilities in the current platform', style='Heading 1'); keep_with_next(h)
p=doc.add_paragraph('The following capabilities have been implemented in the Laravel application or are present as connected source level workflows. Production release of each module still requires its specified verification and provider configuration.')

add_table(doc, ['Area', 'Current capabilities'], [
    ['Foundation and administration', 'First run setup wizard; ministry identity, logo, favicon, colours, typography, interface style, locations, modules, navigation, settings, roles, staff access, security, audit history, backups, and shared hosting guidance.'],
    ['Public website and publishing', 'Premium homepage with editable visual themes, carousel slides, module aware sections, public pages, navigation builder, content editor, locations, events, groups, libraries, footer, and responsive mobile navigation.'],
    ['People and pastoral operations', 'People directory, consent aware visitor intake, attendance records, follow up queue, assigned care cases, encrypted pastoral notes, birthdays and communication preferences, and reporting foundations.'],
    ['Community operations', 'Groups, group discovery and join requests, gatherings and attendance, events, registration, waitlists, staff check in, volunteers, teams, rosters, communications, and announcements.'],
    ['Member dashboard', 'Individual profile and preferences, groups, event registrations, serving commitments, identified attendance, prayer requests, next steps, giving history, and a responsive member navigation experience.'],
    ['Media and teaching', 'Feature controlled book, audio sermon, video sermon, sermon series, categories, tags, content status, protected downloads, safe media delivery, and YouTube sermon links.'],
    ['Digital church', 'Service schedules, recurring and one time services, templates, run sheets, host notes, prompts, service lifecycle, broadcast sources, worship room, chat moderation, polls, prayer and newcomer responses, playback health, outage fallback, regional probes, and capacity rehearsal records.'],
    ['Giving and communications', 'Giving funds, donation intents, signed webhooks, receipts and reconciliation foundations; consent filtered email and SMS campaigns, templates, preferences, and retry state.']
], [1.75, 5.05])

# 4 roadmap
h=doc.add_paragraph('Priority work ahead', style='Heading 1'); keep_with_next(h)
p=doc.add_paragraph('These items are the main remaining delivery areas before OneMinistry can be presented as a fully verified production release. They are grouped by ministry value and delivery dependency rather than treated as promises of immediate availability.')
add_table(doc, ['Priority', 'Delivery area', 'What remains'], [
    ['1', 'Release and operational readiness', 'Complete browser, accessibility, mobile, migration, shared hosting, backup restore, and provider configuration verification. Document upgrade and launch procedures for a real ministry installation.'],
    ['2', 'Email and communications delivery', 'Finish preferred provider configuration, hosting mailer choice, invitation delivery, real end to end verification, and operational failure handling.'],
    ['3', 'Giving gateway completion', 'Add ministry selected payment gateway adapters, hosted donation checkout, webhook validation against provider sandboxes, reconciliation workflow, receipts, and finance release checks.'],
    ['4', 'Zoom Video SDK meetings', 'Complete the branded browser room, pre join checks, waiting room, host and moderator controls, active speaker stage, screen sharing, participant management, breakout support where appropriate, and provider callback verification.'],
    ['5', 'Digital school', 'Design and build course catalogues, enrolment, modules and lessons, progress, assessments, certificates, learning cohorts, instructor tools, and live class integration through the meeting experience.'],
    ['6', 'Advanced data portability and scale', 'Complete export and import flows, media manifests, retention review, provider adapters, production stream capacity exercises, external monitoring, and recovery rehearsal.']
], [.58, 1.65, 4.57])

h=doc.add_paragraph('Digital school opportunity', style='Heading 2'); keep_with_next(h)
p=doc.add_paragraph('A digital school is a strong next module because it turns discipleship, leadership training, new member classes, and ministry training into a structured experience. It should use the same identity, permissions, content library, group, meeting, attendance, and communication foundations already being built. Live classes can use the Zoom Video SDK meeting layer; on demand lessons can combine video, documents, quizzes, assignments, and discussion.')

# 5 architecture and value
h=doc.add_paragraph('How a ministry would use it', style='Heading 1'); keep_with_next(h)
add_table(doc, ['Moment', 'Example OneMinistry flow'], [
    ['A visitor plans a first visit', 'They see the next service, location and event on the homepage, submit a newcomer request, and staff receive a consent aware follow up task.'],
    ['A member wants to grow', 'They sign in, discover a group or course, register for an event, receive communications they have permitted, and view their commitments in one dashboard.'],
    ['A team prepares a service', 'A producer sets the service state, run sheet, broadcast source, prompts, polls, hosts, and response actions from the service desk.'],
    ['A live service is running', 'The website moves to the worship experience. Viewers watch the broadcast, engage through moderated tools, and choose prayer, connection, or next steps without false attendance inference.'],
    ['A ministry administrator launches', 'They complete the setup wizard, upload identity assets, set appearance and schedule, enable modules, connect providers, invite approved staff, and use the guided operational checks.']
], [1.75, 5.05])

h=doc.add_paragraph('Recommended positioning', style='Heading 2'); keep_with_next(h)
p=doc.add_paragraph('OneMinistry can be presented as a ministry owned digital church platform: a configurable website, operations hub, member portal, and worship experience that a ministry hosts under its own brand. It is best suited to ministries that want one connected system while retaining ownership of their records, providers, and public identity.')

h=doc.add_paragraph('Name recommendation', style='Heading 2'); keep_with_next(h)
p=doc.add_paragraph('Keep the name OneMinistry. It is clear, broad enough for churches, fellowships, prayer ministries, and outreach organisations, and matches the product principle that each installation belongs to one ministry. For public materials, use the descriptor “OneMinistry Digital Church Platform” until a final brand identity and domain are selected.')

h=doc.add_paragraph('Next conversation with a ministry', style='Heading 2'); keep_with_next(h)
for text in [
    'Confirm the ministry’s launch goals: public website, member portal, operations, online worship, giving, learning, or a phased combination.',
    'Select the modules to activate for the first launch and identify the responsible ministry teams.',
    'Choose ministry owned providers for email, payments, storage, broadcast media, and interactive meetings.',
    'Agree the initial service capacity target, privacy rules, staff roles, communications consent approach, and backup ownership.',
    'Plan a launch rehearsal using the ministry’s real branding, content, service schedule, and operator workflow.'
]: add_bullet(doc,text)

# core properties
for p in doc.paragraphs:
    p.paragraph_format.widow_control = True

OUT.parent.mkdir(parents=True, exist_ok=True)
doc.save(OUT)
print(OUT)
