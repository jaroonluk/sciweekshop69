# -*- coding: utf-8 -*-
"""
เอกสารวิเคราะห์ระบบ SCI Shop รวมเล่มเดียว:
- วิเคราะห์ความต้องการ
- Flow การทำงาน
- ผังฐานข้อมูล / ER Diagram
- ภาพหน้าจอสำคัญ
Font: TH Sarabun New · ขนาดตัวอักษรปกติ 16 pt
"""
from io import BytesIO
from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor
from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parent
APPLY_SHOTS = ROOT / "_present_apply_screens"
ADMIN_SHOTS = ROOT / "_present_admin_screens"
DIAG_DIR = ROOT / "_present_system_docs"
OUT = ROOT / "เอกสารวิเคราะห์ระบบ_SCI_Shop.docx"
OUT_ASCII = ROOT / "sci_shop_system_analysis.docx"
FONT = "TH Sarabun New"
BODY = 16

DIAG_DIR.mkdir(exist_ok=True)


def find_font(size: int):
    candidates = [
        r"C:\Windows\Fonts\THSarabunNew.ttf",
        r"C:\Windows\Fonts\thsarabunnew.ttf",
        r"C:\Windows\Fonts\THSarabun.ttf",
        r"C:\Windows\Fonts\tahoma.ttf",
        r"C:\Windows\Fonts\arial.ttf",
    ]
    for p in candidates:
        if Path(p).exists():
            try:
                return ImageFont.truetype(p, size)
            except OSError:
                continue
    return ImageFont.load_default()


def rounded_rect(draw, xy, fill, outline=None, width=2, radius=14):
    draw.rounded_rectangle(xy, radius=radius, fill=fill, outline=outline, width=width)


def box(draw, x, y, w, h, title, lines, title_font, body_font, fill="#FFFDF8", border="#0d8a7f"):
    rounded_rect(draw, (x, y, x + w, y + h), fill=fill, outline=border, width=2)
    draw.rectangle((x, y, x + w, y + 36), fill=border)
    draw.text((x + 12, y + 8), title, font=title_font, fill="white")
    ty = y + 46
    for line in lines:
        draw.text((x + 12, ty), line, font=body_font, fill="#1c160e")
        ty += 22


def arrow(draw, x1, y1, x2, y2, color="#5c5346"):
    draw.line((x1, y1, x2, y2), fill=color, width=3)
    # simple arrow head
    if abs(x2 - x1) >= abs(y2 - y1):
        # horizontal
        s = 1 if x2 > x1 else -1
        draw.polygon([(x2, y2), (x2 - 10 * s, y2 - 6), (x2 - 10 * s, y2 + 6)], fill=color)
    else:
        s = 1 if y2 > y1 else -1
        draw.polygon([(x2, y2), (x2 - 6, y2 - 10 * s), (x2 + 6, y2 - 10 * s)], fill=color)


def make_er_diagram() -> Path:
    W, H = 1600, 1180
    img = Image.new("RGB", (W, H), "#FFF8EC")
    draw = ImageDraw.Draw(img)
    title_f = find_font(36)
    h_f = find_font(22)
    b_f = find_font(18)
    s_f = find_font(16)

    draw.text((40, 20), "ER Diagram · ฐานข้อมูล sciweekshop", font=title_f, fill="#0d8a7f")
    draw.text((40, 70), "ความสัมพันธ์หลักของตารางในระบบ SCI Shop", font=s_f, fill="#5c5346")

    # roles
    box(draw, 40, 120, 220, 150, "roles", [
        "PK id",
        "code, name_th",
    ], h_f, b_f, border="#475569")

    # users
    box(draw, 320, 110, 280, 200, "users", [
        "PK id",
        "FK role_id → roles",
        "email, display_name",
        "eoffice_username",
        "google_sub, is_active",
    ], h_f, b_f, border="#9a3412")

    # events
    box(draw, 680, 110, 300, 200, "events", [
        "PK id",
        "code, title, year_be",
        "apply_program",
        "is_active",
    ], h_f, b_f, border="#0d8a7f")

    # event_rounds
    box(draw, 1080, 100, 320, 230, "event_rounds", [
        "PK id",
        "FK event_id → events",
        "round_no, title",
        "apply_open_at / close_at",
        "is_open, ask_high_power",
        "ask_ice_bucket, apply_flow",
    ], h_f, b_f, border="#c45c12")

    # zones
    box(draw, 680, 360, 280, 170, "zones", [
        "PK id",
        "FK event_id → events",
        "code (A/B/…), name_th",
        "sort_order",
    ], h_f, b_f, border="#1f6fbf")

    # slots
    box(draw, 1040, 380, 320, 210, "slots", [
        "PK id",
        "FK event_id → events",
        "FK zone_id → zones",
        "code, category",
        "slot_limit, is_active",
    ], h_f, b_f, border="#7a4bb8")

    # applicants
    box(draw, 320, 420, 320, 300, "applicants", [
        "PK id",
        "FK event_id, round_id",
        "FK user_id (opt)",
        "FK assigned_slot_id",
        "name, phone, zone_code",
        "category, qualifications",
        "doc_status, selection",
        "payment_status, …",
    ], h_f, b_f, border="#187a48")

    # applicant_files
    box(draw, 40, 520, 250, 200, "applicant_files", [
        "PK id",
        "FK applicant_id",
        "file_type",
        "stored_path / mime",
        "drive_url (legacy)",
    ], h_f, b_f, border="#0f766e")

    # alumni
    box(draw, 320, 780, 300, 180, "alumni_vendors", [
        "PK id",
        "year_be, name",
        "slot_code, category",
        "payment_status",
    ], h_f, b_f, border="#b45309")

    # audit
    box(draw, 700, 780, 300, 180, "audit_logs", [
        "PK id",
        "FK user_id",
        "action, entity_type",
        "entity_id, detail_json",
    ], h_f, b_f, border="#475569")

    # settings
    box(draw, 1080, 780, 280, 150, "settings", [
        "PK setting_key",
        "setting_value",
    ], h_f, b_f, border="#64748b")

    # relationship lines
    arrow(draw, 260, 180, 320, 180)
    arrow(draw, 600, 200, 680, 200)
    arrow(draw, 830, 310, 830, 360)
    arrow(draw, 980, 210, 1080, 210)
    arrow(draw, 960, 440, 1040, 460)
    arrow(draw, 830, 530, 640, 530)
    arrow(draw, 1180, 330, 500, 420)
    arrow(draw, 320, 560, 290, 580)
    arrow(draw, 460, 720, 460, 780)
    arrow(draw, 460, 310, 460, 420)
    # users -> audit_logs
    arrow(draw, 460, 310, 700, 820)

    legend = "สัญลักษณ์: PK = Primary Key · FK = Foreign Key · ลูกศรชี้ไปยังตารางที่อ้างอิง"
    draw.text((40, 1120), legend, font=s_f, fill="#5c5346")

    path = DIAG_DIR / "er_diagram.png"
    img.save(path, "PNG", optimize=True)
    return path


def make_flow_vendor() -> Path:
    W, H = 1400, 900
    img = Image.new("RGB", (W, H), "#FFF8EC")
    draw = ImageDraw.Draw(img)
    title_f = find_font(34)
    f = find_font(20)
    sf = find_font(17)

    draw.text((40, 24), "Flow · ผู้สมัครร้านค้า (พอร์ทัลสาธารณะ)", font=title_f, fill="#0d8a7f")

    steps = [
        (80, 120, "1. เปิดลิงก์รับสมัคร", "ดูสถานะเปิด/ปิดรับ"),
        (420, 120, "2. สมัครใหม่ หรือ", "ตรวจสถานะด้วยเบอร์โทร"),
        (760, 120, "3. ขั้นตอน 1 ข้อมูล", "คุณสมบัติ ชื่อ เบอร์"),
        (1100, 120, "4. ขั้นตอน 2 ประเภท", "เลือกประเภท/โซน"),
        (1100, 340, "5. ขั้นตอน 3 เอกสาร", "อัปโหลดไฟล์แนบ"),
        (760, 340, "6. ขั้นตอน 4 ยืนยัน", "ตรวจทาน + captcha"),
        (420, 340, "7. ส่งใบสมัคร", "บันทึกลง applicants"),
        (80, 340, "8. ติดตามผล", "ค้นหาสถานะภายหลัง"),
    ]
    for x, y, t, d in steps:
        rounded_rect(draw, (x, y, x + 260, y + 110), "#fff", "#0d8a7f", 2, 16)
        draw.text((x + 16, y + 22), t, font=f, fill="#1c160e")
        draw.text((x + 16, y + 58), d, font=sf, fill="#5c5346")

    # arrows row1
    for x in (340, 680, 1020):
        arrow(draw, x, 175, x + 80, 175, "#0d8a7f")
    arrow(draw, 1230, 230, 1230, 340, "#0d8a7f")
    for x in (1100, 760, 420):
        arrow(draw, x, 395, x - 80, 395, "#0d8a7f")

    note = "เงื่อนไข: สมัครได้เฉพาะช่วง apply_open_at–apply_close_at และธง is_open = เปิด"
    draw.text((80, 520), note, font=sf, fill="#b42318")

    # side notes
    rounded_rect(draw, (80, 580, 1320, 820), "#fffdf8", "#e6dcc8", 2, 16)
    draw.text((110, 610), "ข้อมูลที่ระบบเก็บเมื่อส่งใบสมัคร", font=f, fill="#0d8a7f")
    bullets = [
        "• applicants: ข้อมูลผู้สมัคร โซน ประเภท สถานะเอกสาร/คัดเลือก",
        "• applicant_files: ไฟล์แนบ (บัตร รูป แผน ฯลฯ) เก็บ MinIO หรือ local",
        "• จับคู่ alumni_vendors เพื่อเตือนเจ้าเดิมที่ค้างชำระปีก่อน (ถ้ามี)",
    ]
    yy = 660
    for b in bullets:
        draw.text((110, yy), b, font=sf, fill="#1c160e")
        yy += 36

    path = DIAG_DIR / "flow_vendor.png"
    img.save(path, "PNG", optimize=True)
    return path


def make_flow_admin() -> Path:
    W, H = 1500, 980
    img = Image.new("RGB", (W, H), "#FFF8EC")
    draw = ImageDraw.Draw(img)
    title_f = find_font(34)
    f = find_font(20)
    sf = find_font(17)

    draw.text((40, 24), "Flow · เจ้าหน้าที่ / กรรมการ (SCI Shop Admin)", font=title_f, fill="#0d8a7f")

    cards = [
        (70, 110, "#0d8a7f", "1. ตั้งค่า", "ปี · รอบ · โซน · ล็อก\nเปิด/ปิดรับสมัคร"),
        (340, 110, "#c45c12", "2. พิจารณา", "ตรวจเอกสาร\nคัดเลือกเข้าล็อก"),
        (610, 110, "#1f6fbf", "3. สรุป", "ตารางสถานะ\nกรองเจ้าเดิม"),
        (880, 110, "#7a4bb8", "4. จัดล็อก", "รายงานร้าน/ล็อก\nจัดสรรล็อกว่าง"),
        (1150, 110, "#187a48", "5. การเงิน", "ติ๊กชำระเงิน\nซิงก์ alumni"),
        (70, 360, "#b45309", "6. ประกาศ", "ดาวน์โหลด Word\n/ Excel ประกาศ"),
        (340, 360, "#9a3412", "7. ผู้ใช้งาน", "ให้สิทธิ์จาก eoffice\n(admin เท่านั้น)"),
        (610, 360, "#475569", "8. บันทึก", "ดู audit log\n(admin เท่านั้น)"),
        (880, 360, "#0f766e", "9. สรุปใช้งาน", "กราฟประเภท\nช่วงเวลาสมัคร"),
    ]
    for x, y, color, title, desc in cards:
        rounded_rect(draw, (x, y, x + 240, y + 160), "#fff", color, 3, 16)
        draw.rectangle((x, y, x + 240, y + 42), fill=color)
        draw.text((x + 14, y + 10), title, font=f, fill="white")
        for i, line in enumerate(desc.split("\n")):
            draw.text((x + 14, y + 60 + i * 28), line, font=sf, fill="#1c160e")

    # flow arrows top row
    for x in (310, 580, 850, 1120):
        arrow(draw, x, 190, x + 30, 190, "#5c5346")
    arrow(draw, 1270, 270, 190, 360, "#5c5346")

    rounded_rect(draw, (70, 580, 1430, 900), "#fffdf8", "#e6dcc8", 2, 16)
    draw.text((100, 610), "ลำดับธุรกิจโดยสรุป", font=f, fill="#0d8a7f")
    lines = [
        "1) สร้างกิจกรรมและรอบ → กำหนดวันเปิด–ปิดรับสมัคร → คัดลอกลิงก์ประชาสัมพันธ์",
        "2) ผู้สมัครส่งใบสมัครผ่าน apply.php → ข้อมูลเข้า applicants + applicant_files",
        "3) กรรมการตรวจเอกสาร → ตั้ง doc_status / selection → กำหนด assigned_slot",
        "4) การเงินอัปเดต payment_status → ซิงก์ alumni สำหรับปีถัดไป",
        "5) ส่งออกประกาศ Word/Excel ของร้านที่ได้รับการคัดเลือก",
    ]
    yy = 660
    for line in lines:
        draw.text((100, yy), line, font=sf, fill="#1c160e")
        yy += 40

    path = DIAG_DIR / "flow_admin.png"
    img.save(path, "PNG", optimize=True)
    return path


# ---------------- Word helpers ----------------

def set_run_font(run, size=BODY, bold=False, color=None):
    run.font.name = FONT
    run._element.rPr.rFonts.set(qn("w:eastAsia"), FONT)
    run.font.size = Pt(size)
    run.bold = bold
    if color is not None:
        run.font.color.rgb = color


def add_para(doc, text, *, size=BODY, bold=False, align=WD_ALIGN_PARAGRAPH.LEFT,
             space_after=6, space_before=0, color=None):
    p = doc.add_paragraph()
    p.alignment = align
    pf = p.paragraph_format
    pf.space_before = Pt(space_before)
    pf.space_after = Pt(space_after)
    pf.line_spacing = 1.15
    run = p.add_run(text)
    set_run_font(run, size=size, bold=bold, color=color)
    return p


def add_bullet(doc, text, *, size=BODY):
    p = doc.add_paragraph(style="List Bullet")
    p.paragraph_format.space_after = Pt(3)
    p.paragraph_format.line_spacing = 1.15
    if p.runs:
        p.runs[0].text = text
        set_run_font(p.runs[0], size=size)
    else:
        set_run_font(p.add_run(text), size=size)
    return p


def add_h(doc, text, level=1):
    sizes = {1: 20, 2: 18, 3: 16}
    colors = {
        1: RGBColor(0x0D, 0x8A, 0x7F),
        2: RGBColor(0x1C, 0x16, 0x0E),
        3: RGBColor(0x5C, 0x53, 0x46),
    }
    return add_para(doc, text, size=sizes.get(level, BODY), bold=True,
                    space_before=12 if level == 1 else 8, space_after=6,
                    color=colors.get(level))


def prepare_image(path: Path, max_width=1400) -> BytesIO:
    img = Image.open(path)
    if img.mode in ("RGBA", "P"):
        img = img.convert("RGB")
    w, h = img.size
    if w > max_width:
        ratio = max_width / w
        img = img.resize((max_width, int(h * ratio)), Image.Resampling.LANCZOS)
    buf = BytesIO()
    img.save(buf, format="JPEG", quality=82, optimize=True)
    buf.seek(0)
    return buf


def add_img(doc, path: Path, caption: str, width_cm=15.2):
    if not path.exists():
        add_para(doc, f"[ไม่พบภาพ: {path.name}]", size=14, color=RGBColor(0xB4, 0x23, 0x18))
        return
    buf = prepare_image(path)
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(6)
    p.paragraph_format.space_after = Pt(2)
    p.add_run().add_picture(buf, width=Cm(width_cm))
    cap = add_para(doc, caption, size=14, align=WD_ALIGN_PARAGRAPH.CENTER,
                   space_after=10, color=RGBColor(0x5C, 0x53, 0x46))
    for r in cap.runs:
        r.italic = True


def page_break(doc):
    p = doc.add_paragraph()
    run = p.add_run()
    run.add_break(WD_BREAK.PAGE)


def build():
    er = make_er_diagram()
    flow_v = make_flow_vendor()
    flow_a = make_flow_admin()

    doc = Document()
    style = doc.styles["Normal"]
    style.font.name = FONT
    style.font.size = Pt(BODY)
    style._element.rPr.rFonts.set(qn("w:eastAsia"), FONT)
    sec = doc.sections[0]
    sec.top_margin = Cm(1.8)
    sec.bottom_margin = Cm(1.8)
    sec.left_margin = Cm(2.0)
    sec.right_margin = Cm(2.0)

    # ===== Cover =====
    add_para(doc, "คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น", size=BODY,
             align=WD_ALIGN_PARAGRAPH.CENTER)
    add_para(doc, "เอกสารวิเคราะห์ระบบ", size=26, bold=True,
             align=WD_ALIGN_PARAGRAPH.CENTER, space_before=28,
             color=RGBColor(0x0D, 0x8A, 0x7F))
    add_para(doc, "SCI Shop — ระบบรับสมัครและคัดเลือกร้านค้า", size=20, bold=True,
             align=WD_ALIGN_PARAGRAPH.CENTER, space_after=10)
    add_para(doc, "รวม: ความต้องการระบบ · Flow การทำงาน · ER Diagram · ภาพหน้าจอ",
             size=BODY, align=WD_ALIGN_PARAGRAPH.CENTER,
             color=RGBColor(0x5C, 0x53, 0x46))
    add_para(doc, "ตัวอักษร: TH Sarabun New ขนาด 16 pt", size=14,
             align=WD_ALIGN_PARAGRAPH.CENTER, space_before=20,
             color=RGBColor(0x5C, 0x53, 0x46))

    page_break(doc)

    # ===== TOC-like =====
    add_h(doc, "สารบัญ", 1)
    for i, t in enumerate([
        "บทที่ 1 ภาพรวมระบบ",
        "บทที่ 2 วิเคราะห์ความต้องการของระบบ",
        "บทที่ 3 Flow การทำงานของระบบ",
        "บทที่ 4 ผังฐานข้อมูล / ER Diagram",
        "บทที่ 5 ภาพหน้าจอระบบ",
        "บทที่ 6 สรุป",
    ], 1):
        add_para(doc, f"{i}. {t}")

    page_break(doc)

    # ===== Ch1 =====
    add_h(doc, "บทที่ 1 ภาพรวมระบบ", 1)
    add_para(doc,
             "SCI Shop เป็นระบบสนับสนุนการรับสมัครคัดเลือกผู้ประกอบการร้านค้า "
             "(เช่น โครงการ SCiSQUARE / SCI Week) ของคณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น "
             "ครอบคลุมทั้งพอร์ทัลสาธารณะสำหรับผู้สมัคร และระบบหลังบ้านสำหรับกรรมการ/"
             "เจ้าหน้าที่")
    add_h(doc, "1.1 ขอบเขตระบบ", 2)
    add_bullet(doc, "พอร์ทัลรับสมัครออนไลน์ (apply.php) — กรอกข้อมูล อัปโหลดเอกสาร ส่งใบสมัคร ตรวจสถานะ")
    add_bullet(doc, "ระบบหลังบ้าน (index.php) — ตั้งค่ารอบ ตรวจเอกสาร คัดเลือก จัดล็อก การเงิน ประกาศ")
    add_bullet(doc, "การยืนยันตัวตนเจ้าหน้าที่ด้วย Google OAuth + สิทธิ์ RBAC จาก eoffice")
    add_bullet(doc, "จัดเก็บไฟล์แนบบน MinIO/S3 หรือ local พร้อมเสิร์ฟผ่าน file_serve.php")
    add_h(doc, "1.2 ผู้มีส่วนได้ส่วนเสีย", 2)
    add_bullet(doc, "ผู้สมัครร้านค้า / ผู้ประกอบการ")
    add_bullet(doc, "กรรมการฝ่ายจัดหารายได้")
    add_bullet(doc, "ผู้ดูแลระบบ")
    add_bullet(doc, "เจ้าหน้าที่การเงิน")

    # ===== Ch2 Requirements =====
    page_break(doc)
    add_h(doc, "บทที่ 2 วิเคราะห์ความต้องการของระบบ", 1)

    add_h(doc, "2.1 ความต้องการเชิงธุรกิจ (Business Requirements)", 2)
    add_bullet(doc, "เปิดรับสมัครร้านค้าเป็นรอบ พร้อมกำหนดวันเปิด–ปิดรับได้")
    add_bullet(doc, "ให้ผู้ประกอบการสมัครออนไลน์และอัปโหลดเอกสารหลักฐานได้ด้วยตนเอง")
    add_bullet(doc, "ให้คณะกรรมการตรวจเอกสารและคัดเลือกเข้าล็อกร้านได้เป็นระบบ")
    add_bullet(doc, "ติดตามสถานะชำระเงิน และเตือนเจ้าเดิมที่ค้างชำระในปีถัดไป")
    add_bullet(doc, "พิมพ์/ส่งออกรายชื่อร้านที่ได้รับการคัดเลือกเป็น Word และ Excel")

    add_h(doc, "2.2 ความต้องการเชิงฟังก์ชัน (Functional Requirements)", 2)
    add_h(doc, "ฝั่งผู้สมัคร", 3)
    add_bullet(doc, "FR-V01 ดูสถานะเปิด/ปิดรับสมัครของรอบ")
    add_bullet(doc, "FR-V02 สมัครใหม่แบบหลายขั้นตอน (ข้อมูล → ประเภท/โซน → เอกสาร → ยืนยัน)")
    add_bullet(doc, "FR-V03 อัปโหลดเอกสารตาม schema ของรอบ (ภาพ/PDF)")
    add_bullet(doc, "FR-V04 ตอบคำถามป้องกันสแปมก่อนส่งใบสมัคร")
    add_bullet(doc, "FR-V05 ตรวจสอบสถานะใบสมัครด้วยเบอร์โทร (+ captcha)")

    add_h(doc, "ฝั่งเจ้าหน้าที่", 3)
    add_bullet(doc, "FR-A01 เข้าสู่ระบบด้วย Google และใช้ตามสิทธิ์ admin / committee / finance")
    add_bullet(doc, "FR-A02 จัดการกิจกรรม ปี รอบรับสมัคร โซน และล็อก")
    add_bullet(doc, "FR-A03 คัดลอกลิงก์ประชาสัมพันธ์และคัดลอกโครงสร้างปีใหม่")
    add_bullet(doc, "FR-A04 พิจารณาผู้สมัคร ตรวจเอกสาร บันทึกสถานะและหมายเหตุ")
    add_bullet(doc, "FR-A05 คัดเลือก/ไม่คัดเลือก และกำหนดล็อกร้าน")
    add_bullet(doc, "FR-A06 ดูสรุปผู้สมัครและรายงานร้าน/ล็อก")
    add_bullet(doc, "FR-A07 บันทึกสถานะชำระเงินและซิงก์ alumni")
    add_bullet(doc, "FR-A08 ส่งออกประกาศ Word/Excel")
    add_bullet(doc, "FR-A09 (admin) จัดการผู้ใช้และดูบันทึกการใช้งาน")
    add_bullet(doc, "FR-A10 ดูสรุปการใช้งาน (กราฟสถิติ)")

    add_h(doc, "2.3 ความต้องการเชิงไม่ใช่ฟังก์ชัน (Non-Functional)", 2)
    add_bullet(doc, "NFR-01 ความปลอดภัย: OAuth, RBAC, captcha, จำกัดชนิดไฟล์อัปโหลด")
    add_bullet(doc, "NFR-02 รองรับภาษาไทย (utf8mb4) และแสดงผลบนมือถือสำหรับพอร์ทัลสมัคร")
    add_bullet(doc, "NFR-03 แยกสิทธิ์ชัดเจน — ผู้ไม่มีสิทธิ์ไม่เข้าหลังบ้านได้")
    add_bullet(doc, "NFR-04 เก็บไฟล์แยกจากรหัส (MinIO/local) และเข้าถึงผ่าน endpoint ที่ต้องล็อกอิน")
    add_bullet(doc, "NFR-05 บันทึก audit ของการกระทำสำคัญ")

    add_h(doc, "2.4 ข้อจำกัดและสมมติฐาน", 2)
    add_bullet(doc, "เจ้าหน้าที่ต้องมีบัญชี Google และได้รับสิทธิ์จาก admin ผ่าน eoffice")
    add_bullet(doc, "สมัครได้เฉพาะช่วงที่รอบถูกตั้งค่าเปิดรับ")
    add_bullet(doc, "ประกาศ Word/Excel แสดงเฉพาะร้านสถานะ “ได้รับการคัดเลือก”")

    # ===== Ch3 Flow =====
    page_break(doc)
    add_h(doc, "บทที่ 3 Flow การทำงานของระบบ", 1)
    add_h(doc, "3.1 Flow ผู้สมัครร้านค้า", 2)
    add_para(doc,
             "ผู้สมัครเปิดลิงก์ประชาสัมพันธ์ → ดูสถานะรับสมัคร → กรอก 4 ขั้นตอน → ส่งใบสมัคร "
             "→ ติดตามผลด้วยเบอร์โทรในภายหลัง")
    add_img(doc, flow_v, "ภาพที่ 3.1 · Flow การทำงานฝั่งผู้สมัคร")

    add_h(doc, "3.2 Flow เจ้าหน้าที่ / กรรมการ", 2)
    add_para(doc,
             "ลำดับงานแนะนำบนหน้าหลัก: ตั้งค่า → พิจารณา → สรุป → จัดล็อก → ชำระเงิน → ประกาศ "
             "ตามด้วยเมนูผู้ใช้/บันทึก (admin) และสรุปการใช้งาน")
    add_img(doc, flow_a, "ภาพที่ 3.2 · Flow การทำงานฝั่งเจ้าหน้าที่")

    add_h(doc, "3.3 Flow สถานะเอกสารและคัดเลือก (ย่อ)", 2)
    add_bullet(doc, "doc_status: รอตรวจสอบ → ครบถ้วน / ไม่ครบถ้วน / ขาดคุณสมบัติ")
    add_bullet(doc, "selection: รอพิจารณา → ได้รับการคัดเลือก / ไม่ได้รับการคัดเลือก")
    add_bullet(doc, "เมื่อคัดเลือก ระบุ assigned_slot — ถ้าล็อกมีผู้เดิมจะถูกตั้งเป็นรอพิจารณา")
    add_bullet(doc, "payment_status: unpaid / paid (ร้านที่อนุมัติแล้ว)")

    # ===== Ch4 ER =====
    page_break(doc)
    add_h(doc, "บทที่ 4 ผังฐานข้อมูล / ER Diagram", 1)
    add_para(doc,
             "ฐานข้อมูลชื่อ sciweekshop (MySQL / MariaDB, utf8mb4, InnoDB) "
             "โครงสร้างหลักอยู่ใน sql/001_schema.sql")
    add_img(doc, er, "ภาพที่ 4.1 · ER Diagram ระบบ SCI Shop")

    add_h(doc, "4.1 สรุปตารางหลัก", 2)
    tables = [
        ("roles / users", "บทบาทและบัญชีเจ้าหน้าที่ เชื่อม eoffice และ Google"),
        ("events", "กิจกรรมรับสมัครตามปี (SCiWEEK / SCiSQUARE)"),
        ("event_rounds", "รอบรับสมัคร วันเปิด–ปิด ธงเปิดรับ และตัวเลือกแบบฟอร์ม"),
        ("zones / slots", "โซนและล็อกร้านพร้อมประเภทสินค้า"),
        ("applicants", "ใบสมัคร สถานะเอกสาร การคัดเลือก การชำระเงิน"),
        ("applicant_files", "ไฟล์แนบของใบสมัคร"),
        ("alumni_vendors", "ร้านค้าเก่าสำหรับเตือนค้างชำระปีถัดไป"),
        ("audit_logs", "บันทึกการใช้งาน"),
        ("settings", "ค่าคอนฟิก เช่น ขนาดไฟล์ที่อนุญาต"),
    ]
    for name, desc in tables:
        add_para(doc, f"{name} — {desc}")

    add_h(doc, "4.2 ความสัมพันธ์สำคัญ", 2)
    add_bullet(doc, "events 1—N event_rounds, zones, slots, applicants")
    add_bullet(doc, "zones 1—N slots")
    add_bullet(doc, "event_rounds 1—N applicants")
    add_bullet(doc, "applicants 1—N applicant_files")
    add_bullet(doc, "applicants N—0..1 slots (assigned_slot_id)")
    add_bullet(doc, "users N—1 roles · audit_logs N—0..1 users")

    # ===== Ch5 Screenshots =====
    page_break(doc)
    add_h(doc, "บทที่ 5 ภาพหน้าจอระบบ", 1)
    add_para(doc,
             "ภาพต่อไปนี้เป็นหน้าจอจริงของระบบ ครอบคลุมหน้ารับสมัคร หน้าตรวจเอกสาร "
             "และหน้าคัดเลือกร้านค้าตามที่ร้องขอ")

    add_h(doc, "5.1 หน้ารับสมัคร (พอร์ทัลสาธารณะ)", 2)
    add_para(doc, "หน้าหลักแสดงสถานะเปิด/ปิดรับ และเมนูตรวจสถานะ")
    add_img(doc, APPLY_SHOTS / "01_home_gate.png", "ภาพที่ 5.1 · หน้ารับสมัคร — หน้าหลัก/สถานะรับสมัคร")
    add_para(doc, "ขั้นตอนกรอกข้อมูลผู้สมัคร")
    add_img(doc, APPLY_SHOTS / "03b_step1_applicant_info_sample.png",
            "ภาพที่ 5.2 · หน้ารับสมัคร — ขั้นตอน 1 ข้อมูลผู้สมัคร")
    add_para(doc, "ขั้นตอนอัปโหลดเอกสารประกอบการสมัคร")
    add_img(doc, APPLY_SHOTS / "05_step3_upload_docs.png",
            "ภาพที่ 5.3 · หน้ารับสมัคร — ขั้นตอน 3 อัปโหลดเอกสาร")
    add_para(doc, "ขั้นตอนตรวจสอบก่อนส่งใบสมัคร")
    add_img(doc, APPLY_SHOTS / "06_step4_review_submit.png",
            "ภาพที่ 5.4 · หน้ารับสมัคร — ขั้นตอน 4 ยืนยันและส่ง")

    add_h(doc, "5.2 หน้าตรวจเอกสาร", 2)
    add_para(doc,
             "ในระบบหลังบ้าน เมนู “พิจารณาผู้สมัคร” ใช้เปิดรายชื่อตามโซน "
             "แล้วตรวจสถานะเอกสาร (Admin) พร้อมผลตรวจอัตโนมัติจากไฟล์แนบ")
    add_img(doc, ADMIN_SHOTS / "08_review.png",
            "ภาพที่ 5.5 · หน้าพิจารณาผู้สมัคร (รายการสำหรับตรวจเอกสาร)")
    add_img(doc, ADMIN_SHOTS / "08b_review_status_modal.png",
            "ภาพที่ 5.6 · หน้าต่างตรวจสถานะเอกสาร (Admin)")

    add_h(doc, "5.3 หน้าคัดเลือกร้านค้า", 2)
    add_para(doc,
             "การคัดเลือกร้านค้าทำได้จากหน้าพิจารณาผู้สมัคร (ตั้งผลการคัดเลือกและล็อก) "
             "และจากหน้ารายงานร้าน/ล็อกเพื่อจัดสรรล็อกว่าง")
    add_img(doc, ADMIN_SHOTS / "10_report.png",
            "ภาพที่ 5.7 · หน้ารายงานร้าน / ล็อก — จัดสรรและคัดเลือกร้านเข้าล็อก")
    add_img(doc, ADMIN_SHOTS / "09_summary.png",
            "ภาพที่ 5.8 · หน้าสรุปผู้สมัคร — ภาพรวมผลการคัดเลือก")
    add_img(doc, ADMIN_SHOTS / "01_home_hub.png",
            "ภาพที่ 5.9 · หน้าหลักหลังบ้าน — จุดเข้าเมนูตรวจเอกสารและคัดเลือก")

    # ===== Ch6 =====
    page_break(doc)
    add_h(doc, "บทที่ 6 สรุป", 1)
    add_para(doc,
             "ระบบ SCI Shop รองรับวงจรงานครบตั้งแต่เปิดรอบรับสมัคร การสมัครออนไลน์ "
             "การตรวจเอกสาร การคัดเลือกเข้าล็อก การติดตามชำระเงิน จนถึงการพิมพ์ประกาศ "
             "โดยแยกสิทธิ์ผู้ใช้ชัดเจนและเก็บข้อมูลในฐาน sciweekshop ตาม ER Diagram ในเอกสารนี้")
    add_para(doc,
             "เอกสารนี้จัดทำเพื่อใช้เป็นเอกสารวิเคราะห์ความต้องการและอ้างอิงโครงสร้างระบบ "
             "ประกอบการพัฒนารักษา และส่งมอบงาน",
             space_before=8)
    add_para(doc, "— จบเอกสาร —", size=14, align=WD_ALIGN_PARAGRAPH.CENTER,
             space_before=24, color=RGBColor(0x5C, 0x53, 0x46))

    doc.save(OUT)
    doc.save(OUT_ASCII)
    print(f"saved: {OUT}")
    print(f"saved: {OUT_ASCII}")
    print(f"size: {OUT.stat().st_size:,} bytes")
    print(f"er: {er}")
    print(f"flow_v: {flow_v}")
    print(f"flow_a: {flow_a}")


if __name__ == "__main__":
    build()
