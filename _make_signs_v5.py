# -*- coding: utf-8 -*-
"""Shop signs v5: rembg mascot, lock emphasis, event TR, faculty in blue frame."""
from __future__ import annotations

import unicodedata
from pathlib import Path

import freetype as ft
import numpy as np
import uharfbuzz as hb
from PIL import Image, ImageDraw, ImageFilter
from rembg import remove

OUT = Path(
    r"G:\Shared drives\000 - Science Week KKU\Sc Week 2026\รับสมัครร้านค้า_จัดหารายได้\ป้ายร้านค้า"
)
WORK = Path(r"c:\xampp\htdocs\sci_shop\_sign_work")
WORK.mkdir(exist_ok=True)

MASCOT_SRC = Path(
    r"C:\Users\Administrator\.cursor\projects\c-xampp-htdocs-sci-shop\assets\jampaka_official.png"
)
MASCOT_CUT = WORK / "mascot_rembg.png"

FONT_BOLD = Path(
    r"C:\Users\Administrator\AppData\Local\Microsoft\Windows\Fonts\THSarabunNew Bold.ttf"
)
FONT_REG = Path(
    r"C:\Users\Administrator\AppData\Local\Microsoft\Windows\Fonts\THSarabunNew.ttf"
)
if not FONT_BOLD.exists():
    FONT_BOLD = Path(
        r"C:\Users\Administrator\AppData\Local\Microsoft\Windows\Fonts\Sarabun-Bold.ttf"
    )
if not FONT_REG.exists():
    FONT_REG = Path(
        r"C:\Users\Administrator\AppData\Local\Microsoft\Windows\Fonts\Sarabun-Regular.ttf"
    )

BLUE = (26, 63, 138)
WHITE = (255, 255, 255)
LOCK_BROWN = (170, 78, 12)
PILL_CREAM = (255, 248, 220)
PILL_EDGE = (230, 175, 90)
CREAM = (255, 242, 205)
NAVY = (22, 50, 112)
ACCENT = (214, 112, 32)
FACULTY_CREAM = (255, 236, 190)

# Larger blue frame (no mid event banner)
BLUE_BOX = (48, 540, 2198, 1260)
# Prominent lock pill
PILL_BOX = (560, 220, 1686, 480)

EVENT_TITLE = "สัปดาห์วิทยาศาสตร์แห่งชาติประจำปี พ.ศ.2569"
EVENT_DATE = "17-19 สิงหาคม 2569"
FACULTY = "คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น"

SLOTS = [
    ("A1", "เครื่องดื่มไม่มีแอลกอฮอล์"),
    ("A2", "ข้าวไข่เจียว อาหารตามสั่ง"),
    ("A3", "มันฝรั่งทอด"),
    ("A4", "เครื่องดื่มไม่มีแอลกอฮอล์"),
    ("A5", "ข้าวราดแกง"),
    ("A6", "ผัดไทย/หอยทอด"),
    ("A7", "ขนมจีนน้ำยา (น้ำยาหลากหลาย)/หมี่กะทิ"),
    ("A8", "ปอเปี๊ยะ/แหนมคลุก"),
    ("A9", "หม่าล่าย่าง (เสียบไม้)"),
    ("A10", "เครื่องดื่มไม่มีแอลกอฮอล์"),
    ("A11", "ไส้กรอกอีสาน"),
    ("A12", "ไก่ย่าง/ส้มตำ"),
    ("B1", "เครื่องดื่มไม่มีแอลกอฮอล์"),
    ("B2", "ลูกชิ้นทอด/นึ่ง"),
    ("B3", "ซูชิ/อาหารญี่ปุ่น"),
    ("B4", "อาหารทอดทานเล่น"),
    ("B5", "ผลไม้"),
    ("B6", "ยำ"),
    ("C1", "ไอศกรีม"),
    ("C2", "ลูกชุบ/ขนมเบื้อง/ขนมไทย"),
    ("C3", "พิซซ่า"),
    ("C4", "ข้าวเหนียวหมูปิ้ง"),
    ("C5", "ข้าวไข่เจียว อาหารตามสั่ง"),
    ("C6", "แจ่วฮ้อน/ก๋วยจั๊บ"),
    ("C7", "สื่อเกมการศึกษา/บอร์ดเกม"),
    ("C8", "สุกี้โรล/เกี๊ยวต้ม/ชาบู"),
    ("C9", "เฉาก๊วยนมสดและเครื่องดื่ม"),
    ("C10", "วาฟเฟิล"),
    ("C11", "ขนมจีบ/ซาลาเปา"),
    ("C12", "สุกี้โรล/เกี๊ยวต้ม/ชาบู"),
    ("C13", "ยำ"),
    ("C14", "ผลไม้"),
    ("D1", "หม่าล่าย่าง (เสียบไม้)"),
    ("D2", "ลูกชิ้นทอด/นึ่ง"),
    ("D3", "ซูชิ/อาหารญี่ปุ่น"),
    ("D4", "สโมกี้ไบท์"),
    ("D5", "เครื่องดื่มไม่มีแอลกอฮอล์"),
    ("D6", "เบเกอร์รี่"),
    ("D7", "แจ่วฮ้อน/ก๋วยจั๊บ/หมูกระทะ"),
    ("D8", "ไก่ย่าง/ส้มตำ"),
]


def th(s: str) -> str:
    return unicodedata.normalize("NFC", s.strip())


class ThaiRenderer:
    def __init__(self, font_path: Path):
        self.font_path = Path(font_path)
        self.data = self.font_path.read_bytes()
        self.hb_face = hb.Face(self.data)
        self.ft_face = ft.Face(str(self.font_path))

    def _hb_font(self, size: int) -> hb.Font:
        font = hb.Font(self.hb_face)
        hb.ot_font_set_funcs(font)
        font.scale = (size, size)
        return font

    def render_line(
        self,
        text: str,
        size: int,
        fill: tuple[int, int, int] = WHITE,
        shadow: tuple[int, int, int] | None = (12, 28, 70),
        shadow_off: tuple[int, int] = (3, 3),
    ) -> Image.Image:
        text = th(text)
        if not text:
            return Image.new("RGBA", (1, 1), (0, 0, 0, 0))

        hb_font = self._hb_font(size)
        self.ft_face.set_char_size(size * 64)

        buf = hb.Buffer()
        buf.add_str(text)
        buf.guess_segment_properties()
        hb.shape(hb_font, buf)
        glyphs = list(zip(buf.glyph_infos, buf.glyph_positions))

        pen_x = 0.0
        minx = miny = 10**9
        maxx = maxy = -10**9
        bitmaps: list[tuple[np.ndarray, int, int]] = []

        for info, pos in glyphs:
            self.ft_face.load_glyph(
                info.codepoint, ft.FT_LOAD_RENDER | ft.FT_LOAD_TARGET_NORMAL
            )
            bmp = self.ft_face.glyph.bitmap
            left = self.ft_face.glyph.bitmap_left
            top = self.ft_face.glyph.bitmap_top
            x0 = int(round(pen_x + pos.x_offset)) + left
            y0 = int(round(pos.y_offset)) - top
            if bmp.width and bmp.rows:
                arr = np.frombuffer(bytes(bmp.buffer), dtype=np.uint8).reshape(
                    bmp.rows, bmp.width
                )
                bitmaps.append((arr, x0, y0))
                minx = min(minx, x0)
                miny = min(miny, y0)
                maxx = max(maxx, x0 + bmp.width)
                maxy = max(maxy, y0 + bmp.rows)
            pen_x += pos.x_advance

        if not bitmaps:
            return Image.new("RGBA", (1, 1), (0, 0, 0, 0))

        pad = 6
        w = maxx - minx + pad * 2
        h = maxy - miny + pad * 2
        alpha = np.zeros((h, w), dtype=np.uint8)
        for arr, x0, y0 in bitmaps:
            x = x0 - minx + pad
            y = y0 - miny + pad
            region = alpha[y : y + arr.shape[0], x : x + arr.shape[1]]
            alpha[y : y + arr.shape[0], x : x + arr.shape[1]] = np.maximum(region, arr)

        out_w = w + (abs(shadow_off[0]) if shadow else 0) + 2
        out_h = h + (abs(shadow_off[1]) if shadow else 0) + 2
        out = Image.new("RGBA", (out_w, out_h), (0, 0, 0, 0))

        def paste_color(color: tuple[int, int, int], ox: int, oy: int) -> None:
            layer = np.zeros((out_h, out_w, 4), dtype=np.uint8)
            layer[oy : oy + h, ox : ox + w, 0] = color[0]
            layer[oy : oy + h, ox : ox + w, 1] = color[1]
            layer[oy : oy + h, ox : ox + w, 2] = color[2]
            layer[oy : oy + h, ox : ox + w, 3] = alpha
            out.alpha_composite(Image.fromarray(layer, "RGBA"))

        base_x = max(0, -min(0, shadow_off[0])) + 1
        base_y = max(0, -min(0, shadow_off[1])) + 1
        if shadow:
            paste_color(shadow, base_x + shadow_off[0], base_y + shadow_off[1])
        paste_color(fill, base_x, base_y)
        return out


CATEGORY_RENDERER = ThaiRenderer(FONT_BOLD)
UI_RENDERER = ThaiRenderer(FONT_BOLD)


def wrap_candidates(category: str) -> list[list[str]]:
    category = th(category)
    cands: list[list[str]] = [[category]]
    if "/" in category:
        parts = [p.strip() for p in category.split("/") if p.strip()]
        if len(parts) == 2:
            cands.append(parts)
        elif len(parts) == 3:
            cands.append([parts[0] + "/" + parts[1], parts[2]])
            cands.append([parts[0], parts[1] + "/" + parts[2]])
            cands.append(parts)
    if " " in category:
        parts = category.split()
        for i in range(1, len(parts)):
            cands.append([" ".join(parts[:i]), " ".join(parts[i:])])
    uniq: list[list[str]] = []
    seen = set()
    for lines in cands:
        lines = [th(x) for x in lines if th(x)]
        key = tuple(lines)
        if key not in seen:
            seen.add(key)
            uniq.append(lines)
    return uniq


def fit_lines(lines: list[str], max_w: int, max_h: int) -> tuple[int, int, list[Image.Image]]:
    target_h = int(max_h * 0.92)
    lo, hi = 48, 600
    best = None
    while lo <= hi:
        mid = (lo + hi) // 2
        gap = max(16, int(mid * 0.20))
        imgs = [CATEGORY_RENDERER.render_line(line, mid) for line in lines]
        widths = [im.size[0] for im in imgs]
        heights = [im.size[1] for im in imgs]
        total_h = sum(heights) + gap * (len(lines) - 1)
        if max(widths) > max_w or total_h > target_h:
            hi = mid - 1
            continue
        best = (mid, gap, imgs)
        lo = mid + 1
    if best is None:
        size = 64
        gap = 18
        imgs = [CATEGORY_RENDERER.render_line(line, size) for line in lines]
        return size, gap, imgs
    return best[0], best[1], best[2]


def choose_lines(category: str, max_w: int, max_h: int) -> list[str]:
    category = th(category)
    preferred = {
        "เครื่องดื่มไม่มีแอลกอฮอล์": ["เครื่องดื่ม", "ไม่มีแอลกอฮอล์"],
        "ข้าวไข่เจียว อาหารตามสั่ง": ["ข้าวไข่เจียว", "อาหารตามสั่ง"],
        "เฉาก๊วยนมสดและเครื่องดื่ม": ["เฉาก๊วยนมสด", "และเครื่องดื่ม"],
        "ขนมจีนน้ำยา (น้ำยาหลากหลาย)/หมี่กะทิ": [
            "ขนมจีนน้ำยา",
            "(น้ำยาหลากหลาย)",
            "หมี่กะทิ",
        ],
        "แจ่วฮ้อน/ก๋วยจั๊บ/หมูกระทะ": ["แจ่วฮ้อน / ก๋วยจั๊บ", "หมูกระทะ"],
        "แจ่วฮ้อน/ก๋วยจั๊บ": ["แจ่วฮ้อน", "ก๋วยจั๊บ"],
        "ลูกชุบ/ขนมเบื้อง/ขนมไทย": ["ลูกชุบ / ขนมเบื้อง", "ขนมไทย"],
        "สุกี้โรล/เกี๊ยวต้ม/ชาบู": ["สุกี้โรล", "เกี๊ยวต้ม / ชาบู"],
        "สื่อเกมการศึกษา/บอร์ดเกม": ["สื่อเกมการศึกษา", "บอร์ดเกม"],
        "หม่าล่าย่าง (เสียบไม้)": ["หม่าล่าย่าง", "(เสียบไม้)"],
        "ปอเปี๊ยะ/แหนมคลุก": ["ปอเปี๊ยะ", "แหนมคลุก"],
        "ผัดไทย/หอยทอด": ["ผัดไทย", "หอยทอด"],
        "ไก่ย่าง/ส้มตำ": ["ไก่ย่าง", "ส้มตำ"],
        "ลูกชิ้นทอด/นึ่ง": ["ลูกชิ้นทอด", "นึ่ง"],
        "ซูชิ/อาหารญี่ปุ่น": ["ซูชิ", "อาหารญี่ปุ่น"],
        "ขนมจีบ/ซาลาเปา": ["ขนมจีบ", "ซาลาเปา"],
    }
    if category in preferred:
        return preferred[category]

    best_lines = [category]
    best_score = -1.0
    prefer_wrap = ("/" in category) or (len(category) >= 14)
    for lines in wrap_candidates(category):
        if prefer_wrap and len(lines) == 1 and len(wrap_candidates(category)) > 1:
            continue
        _size, gap, imgs = fit_lines(lines, max_w, max_h)
        total_h = sum(im.size[1] for im in imgs) + gap * (len(lines) - 1)
        fill = total_h / max_h
        score = fill * 1000 - (len(lines) - 1) * 6
        if prefer_wrap and len(lines) >= 2:
            score += 40
        if fill < 0.40 and len(lines) > 1:
            continue
        if score > best_score:
            best_score = score
            best_lines = lines
    return best_lines


def load_mascot() -> Image.Image:
    if MASCOT_CUT.exists():
        im = Image.open(MASCOT_CUT).convert("RGBA")
    else:
        src = Image.open(MASCOT_SRC).convert("RGBA")
        im = remove(src).convert("RGBA")
        im.save(MASCOT_CUT)
    bbox = im.getbbox()
    if bbox:
        im = im.crop(bbox)
    # Soft edge: slight alpha cleanup of near-transparent fringe
    arr = np.array(im)
    a = arr[..., 3].astype(np.float32)
    a = np.clip((a - 12) * (255 / (255 - 12)), 0, 255)
    arr[..., 3] = a.astype(np.uint8)
    return Image.fromarray(arr, "RGBA")


def build_clean_base(src: Image.Image, mascot: Image.Image) -> Image.Image:
    im = src.convert("RGB")
    draw = ImageDraw.Draw(im)
    w, h = im.size

    # Sample footer gold to erase duplicated faculty text
    gold = im.getpixel((w // 2, h - 40))

    # Clear previous overlays: mid band (keep logos top-left)
    draw.rectangle((28, 210, w - 28, 1340), fill=CREAM)
    # Clear footer text on gold bar, keep gold strip
    draw.rectangle((120, h - 90, w - 120, h - 18), fill=gold)

    l, t, r, b = BLUE_BOX
    draw.rounded_rectangle((l, t, r, b), radius=22, fill=BLUE)

    # Mascot on right, fully above blue bar
    mw, mh = mascot.size
    avail = t - 130
    target_h = min(380, max(220, avail - 10))
    scale = target_h / mh
    m2 = mascot.resize(
        (max(1, int(mw * scale)), max(1, int(mh * scale))), Image.Resampling.LANCZOS
    )
    shadow = Image.new("RGBA", (m2.size[0] + 40, m2.size[1] + 40), (0, 0, 0, 0))
    sh = m2.split()[-1].point(lambda v: int(v * 0.22))
    shadow.paste((50, 35, 20, 255), (14, 18), sh)
    shadow = shadow.filter(ImageFilter.GaussianBlur(12))

    mx = r - m2.size[0] - 36
    my = t - m2.size[1] - 8  # fully above blue

    out = im.convert("RGBA")
    out.alpha_composite(shadow, (mx - 10, my - 2))
    out.alpha_composite(m2, (mx, my))

    d2 = ImageDraw.Draw(out)
    d2.rounded_rectangle((l, t, r, b), radius=22, fill=BLUE + (255,))
    return out.convert("RGB")


def draw_event_top_right(im: Image.Image) -> Image.Image:
    """Small event title + date at top-right."""
    out = im.convert("RGBA")
    title = UI_RENDERER.render_line(EVENT_TITLE, 28, fill=NAVY, shadow=None)
    date = UI_RENDERER.render_line(EVENT_DATE, 32, fill=ACCENT, shadow=None)

    # Right-align under top margin, left of canvas edge
    right = im.size[0] - 48
    top = 56
    max_w = 620
    if title.size[0] > max_w:
        ratio = max_w / title.size[0]
        title = title.resize(
            (max(1, int(title.size[0] * ratio)), max(1, int(title.size[1] * ratio))),
            Image.Resampling.LANCZOS,
        )
    if date.size[0] > max_w:
        ratio = max_w / date.size[0]
        date = date.resize(
            (max(1, int(date.size[0] * ratio)), max(1, int(date.size[1] * ratio))),
            Image.Resampling.LANCZOS,
        )

    # Soft pill behind small text for readability
    pad_x, pad_y = 16, 8
    bw = max(title.size[0], date.size[0]) + pad_x * 2
    bh = title.size[1] + date.size[1] + pad_y * 2 + 2
    bx = right - bw
    by = top
    panel = Image.new("RGBA", (bw, bh), (0, 0, 0, 0))
    pd = ImageDraw.Draw(panel)
    pd.rounded_rectangle((0, 0, bw - 1, bh - 1), radius=14, fill=(255, 250, 235, 210))
    out.alpha_composite(panel, (bx, by))
    out.alpha_composite(title, (right - title.size[0] - pad_x, by + pad_y))
    out.alpha_composite(
        date, (right - date.size[0] - pad_x, by + pad_y + title.size[1] + 1)
    )
    return out.convert("RGB")


def draw_lock_badge(im: Image.Image, slot_id: str) -> Image.Image:
    """Large prominent lock pill."""
    draw = ImageDraw.Draw(im)
    l, t, r, b = PILL_BOX

    # Soft drop shadow
    draw.rounded_rectangle((l + 8, t + 10, r + 8, b + 10), radius=56, fill=(210, 175, 120))
    # Outer gold rim
    draw.rounded_rectangle((l - 8, t - 8, r + 8, b + 8), radius=56, fill=PILL_EDGE)
    draw.rounded_rectangle((l, t, r, b), radius=50, fill=PILL_CREAM)
    draw.rounded_rectangle(
        (l + 12, t + 12, r - 12, b - 12), radius=42, outline=(255, 255, 255), width=4
    )

    lock_img = UI_RENDERER.render_line(
        f"ล็อก {slot_id}", 168, fill=LOCK_BROWN, shadow=(120, 60, 20), shadow_off=(5, 5)
    )
    max_w = (r - l) - 50
    max_h = (b - t) - 36
    if lock_img.size[0] > max_w or lock_img.size[1] > max_h:
        ratio = min(max_w / lock_img.size[0], max_h / lock_img.size[1])
        lock_img = lock_img.resize(
            (max(1, int(lock_img.size[0] * ratio)), max(1, int(lock_img.size[1] * ratio))),
            Image.Resampling.LANCZOS,
        )

    pcx = (l + r) // 2
    pcy = (t + b) // 2
    out = im.convert("RGBA")
    out.alpha_composite(
        lock_img, (pcx - lock_img.size[0] // 2, pcy - lock_img.size[1] // 2)
    )
    return out.convert("RGB")


def make_sign(base: Image.Image, slot_id: str, category: str) -> Image.Image:
    im = base.copy()
    l, t, r, b = BLUE_BOX

    im = draw_lock_badge(im, slot_id)
    im = draw_event_top_right(im)

    draw = ImageDraw.Draw(im)
    draw.rounded_rectangle((l, t, r, b), radius=22, fill=BLUE)

    # Faculty strip reserved at bottom inside blue frame
    faculty_img = UI_RENDERER.render_line(
        FACULTY, 46, fill=FACULTY_CREAM, shadow=None
    )
    faculty_h = faculty_img.size[1] + 24
    # Divider line above faculty
    div_y = b - faculty_h - 8

    pad_x, pad_y = 44, 26
    max_w = (r - l) - pad_x * 2
    max_h = (div_y - t) - pad_y * 2
    lines = choose_lines(category, max_w, max_h)
    _size, gap, imgs = fit_lines(lines, max_w, max_h)

    total_h = sum(imx.size[1] for imx in imgs) + gap * (len(imgs) - 1)
    y = t + pad_y + (max_h - total_h) // 2
    cx = (l + r) // 2
    out = im.convert("RGBA")
    for layer in imgs:
        out.alpha_composite(layer, (cx - layer.size[0] // 2, y))
        y += layer.size[1] + gap

    # Faculty inside frame
    d = ImageDraw.Draw(out)
    d.line((l + 80, div_y, r - 80, div_y), fill=(255, 255, 255, 90), width=2)
    fy = b - faculty_h + 8
    # scale faculty if too wide
    if faculty_img.size[0] > max_w:
        ratio = max_w / faculty_img.size[0]
        faculty_img = faculty_img.resize(
            (
                max(1, int(faculty_img.size[0] * ratio)),
                max(1, int(faculty_img.size[1] * ratio)),
            ),
            Image.Resampling.LANCZOS,
        )
    out.alpha_composite(faculty_img, (cx - faculty_img.size[0] // 2, fy))

    im = out.convert("RGB")
    draw = ImageDraw.Draw(im)
    draw.rounded_rectangle((l, t, r, b), radius=22, outline=(18, 48, 110), width=6)
    return im


def main() -> None:
    # Prefer freshest lock as logo carrier
    src = Image.open(OUT / "lock_A1.png").convert("RGB")

    print("cutting mascot background…")
    if MASCOT_SRC.exists():
        cut = remove(Image.open(MASCOT_SRC).convert("RGBA")).convert("RGBA")
        cut.save(MASCOT_CUT)
    mascot = load_mascot()
    mascot.save(WORK / "mascot_v5.png")
    print("mascot", mascot.mode, mascot.size)

    base = build_clean_base(src, mascot)
    base.save(WORK / "base_v5.png")

    samples = [
        ("A1", "เครื่องดื่มไม่มีแอลกอฮอล์"),
        ("D6", "เบเกอร์รี่"),
        ("A7", "ขนมจีนน้ำยา (น้ำยาหลากหลาย)/หมี่กะทิ"),
        ("D7", "แจ่วฮ้อน/ก๋วยจั๊บ/หมูกระทะ"),
        ("C8", "สุกี้โรล/เกี๊ยวต้ม/ชาบู"),
        ("B6", "ยำ"),
    ]
    for sid, cat in samples:
        img = make_sign(base, sid, cat)
        img.save(WORK / f"preview5_{sid}.png")
        print("preview", sid)

    for sid, cat in SLOTS:
        img = make_sign(base, sid, cat)
        img.save(OUT / f"lock_{sid}.png", "PNG", optimize=True)
        print("wrote", sid)

    official = {s for s, _ in SLOTS}
    for p in OUT.glob("lock_*.png"):
        if p.stem.replace("lock_", "") not in official:
            p.unlink()
            print("removed", p.name)

    (OUT / "รายการป้ายร้านค้า.txt").write_text(
        "ป้ายร้านค้า SCI Week 2569\n"
        f"- {EVENT_TITLE} | {EVENT_DATE} (มุมบนขวา ตัวเล็ก)\n"
        f"- {FACULTY} (ภายในกรอบน้ำเงิน)\n"
        "- ล็อก … เด่นชัด\n"
        "- น้องจำปาก้าตัดพื้นหลังขาวแล้ว\n"
        "- ฟอนต์ TH Sarabun New + HarfBuzz\n\n"
        + "\n".join(f"{s}\t{c}" for s, c in SLOTS)
        + "\n",
        encoding="utf-8",
    )
    print("done")


if __name__ == "__main__":
    main()
