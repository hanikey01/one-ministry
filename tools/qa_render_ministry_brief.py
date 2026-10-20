from pathlib import Path
from docx import Document
from docx.table import Table
from docx.text.paragraph import Paragraph
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import LETTER
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import inch
from reportlab.platypus import SimpleDocTemplate, Paragraph as RP, Spacer, PageBreak, Table as RT, TableStyle, KeepTogether
from xml.sax.saxutils import escape

SRC = Path(r"C:\Users\HP\Documents\ChatGPT\christian ministry\docs\proposals\OneMinistry_Project_Overview.docx")
OUT = Path(r"C:\Users\HP\Documents\ChatGPT\christian ministry\docs\proposals\rendered\OneMinistry_Project_Overview_QA.pdf")
OUT.parent.mkdir(parents=True, exist_ok=True)

def blocks(parent):
    body = parent.element.body
    for child in body.iterchildren():
        if child.tag.endswith('}p'):
            yield Paragraph(child, parent)
        elif child.tag.endswith('}tbl'):
            yield Table(child, parent)

styles = getSampleStyleSheet()
styles.add(ParagraphStyle(name='DocTitle', parent=styles['Title'], fontName='Helvetica-Bold', fontSize=28, leading=31, textColor=colors.black, spaceAfter=18))
styles.add(ParagraphStyle(name='H1x', parent=styles['Heading1'], fontName='Helvetica-Bold', fontSize=19, leading=22, textColor=colors.black, spaceBefore=12, spaceAfter=8))
styles.add(ParagraphStyle(name='H2x', parent=styles['Heading2'], fontName='Helvetica-Bold', fontSize=13, leading=16, textColor=colors.black, spaceBefore=9, spaceAfter=5))
styles.add(ParagraphStyle(name='H3x', parent=styles['Heading3'], fontName='Helvetica-Bold', fontSize=10.5, leading=13, textColor=colors.black, spaceBefore=6, spaceAfter=3))
styles.add(ParagraphStyle(name='Bodyx', parent=styles['BodyText'], fontName='Helvetica', fontSize=9.2, leading=12, textColor=colors.HexColor('#26332E'), spaceAfter=6))
styles.add(ParagraphStyle(name='Bulletx', parent=styles['BodyText'], fontName='Helvetica', fontSize=9, leading=11.5, leftIndent=13, firstLineIndent=-7, bulletIndent=2, spaceAfter=3))
styles.add(ParagraphStyle(name='Labelx', parent=styles['BodyText'], fontName='Helvetica-Bold', fontSize=8, leading=10, textColor=colors.HexColor('#1F5A47'), spaceAfter=5))
styles.add(ParagraphStyle(name='Cellx', parent=styles['BodyText'], fontName='Helvetica', fontSize=7.5, leading=9.2, textColor=colors.HexColor('#26332E')))
styles.add(ParagraphStyle(name='CellHeadx', parent=styles['BodyText'], fontName='Helvetica-Bold', fontSize=7.8, leading=9.4, textColor=colors.white))

def on_page(canvas, doc):
    canvas.saveState(); canvas.setFont('Helvetica', 7); canvas.setFillColor(colors.HexColor('#65736D'))
    canvas.drawRightString(7.7*inch, 10.55*inch, 'ONEMINISTRY PLATFORM    PROJECT OVERVIEW')
    canvas.drawCentredString(4.25*inch, .42*inch, str(doc.page)); canvas.restoreState()

story=[]
for block in blocks(Document(SRC)):
    if isinstance(block, Paragraph):
        text = block.text.strip()
        if not text: story.append(Spacer(1, 4)); continue
        if block._p.xpath('.//w:br[@w:type="page"]'):
            story.append(PageBreak()); continue
        style = block.style.name if block.style else ''
        if style == 'Title': ps=styles['DocTitle']
        elif style == 'Heading 1': ps=styles['H1x']
        elif style == 'Heading 2': ps=styles['H2x']
        elif style in ('Heading 3','Feature Name'): ps=styles['H3x']
        elif style.startswith('List Bullet'):
            story.append(RP(escape(text), styles['Bulletx'], bulletText='•')); continue
        elif text.isupper() and len(text)<100: ps=styles['Labelx']
        else: ps=styles['Bodyx']
        story.append(RP(escape(text), ps))
    else:
        data=[]
        for ri,row in enumerate(block.rows):
            data.append([RP(escape(cell.text.strip()), styles['CellHeadx'] if ri==0 else styles['Cellx']) for cell in row.cells])
        n=len(data[0])
        widths={2:[1.55*inch,5.25*inch],3:[1.25*inch,2.45*inch,3.1*inch]}.get(n,[6.8*inch/n]*n)
        t=RT(data,colWidths=widths,repeatRows=1,hAlign='CENTER')
        cmds=[('BACKGROUND',(0,0),(-1,0),colors.HexColor('#17324D')),('GRID',(0,0),(-1,-1),.4,colors.HexColor('#D9D9D9')),('VALIGN',(0,0),(-1,-1),'MIDDLE'),('LEFTPADDING',(0,0),(-1,-1),7),('RIGHTPADDING',(0,0),(-1,-1),7),('TOPPADDING',(0,0),(-1,-1),6),('BOTTOMPADDING',(0,0),(-1,-1),6)]
        for ri in range(2,len(data),2): cmds.append(('BACKGROUND',(0,ri),(-1,ri),colors.HexColor('#F5F7F8')))
        t.setStyle(TableStyle(cmds)); story.extend([t,Spacer(1,7)])

pdf=SimpleDocTemplate(str(OUT),pagesize=LETTER,rightMargin=.7*inch,leftMargin=.7*inch,topMargin=.7*inch,bottomMargin=.7*inch,title='OneMinistry Platform Project Overview')
pdf.build(story,onFirstPage=on_page,onLaterPages=on_page)
print(OUT)
