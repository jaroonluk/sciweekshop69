# -*- coding: utf-8 -*-
"""Shop signs v4: TH Sarabun, proportional type, official Jampaka, event dates."""
from __future__ import annotations

import unicodedata
from pathlib import Path

import freetype as ft
import numpy as np
import uharfbuzz as hb
from PIL import Image, ImageDraw, ImageFont

OUT = Path(
    r"G:\Shared drives\000 - Science Week KKU\Sc Week 2026\รับสมัครร้านค้า_จัดหารายได้\ป้ายร้านค้า"
)
WORK = Path(r"c:\xampp\htdocs\sci_shop\_sign_work")
WORK.mkdir(exist_ok=True)

MASCOT_SRC = Path(
    r"C:\Users\Administrator\.cursor\projects\c-xampp-htdocs-sci-shop\assets\jampaka_official.png"
)

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
LOCK_BROWN = (184, 90, 16)
PILL_CREAM = (255, 246, 217)
CREAM = (255, 242, 205)
NAVY = (20, 48, 110)
ACCENT = (214, 112, 32)
DATE_BG = (255, 250, 235)

BLUE_BOX = (52, 640, 2194, 1230)
PILL_BOX = (860, 300, 1390, 420)

EVENT_TITLE = "สัปดาห์วิทยาศาสตร์แห่งชาติประจำปี พ.ศ.2569"
EVENT_DATE = "17-19 สิงหาคม 2569"

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
    """Shape with HarfBuzz + rasterize with FreeType so Thai marks stay correct."""

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

    def measure(self, text: str, size: int) -> tuple[int, int]:
        im = self.render_line(text, size, shadow=None)
        return im.size


CATEGORY_RENDERER = ThaiRenderer(FONT_BOLD)
UI_RENDERER = ThaiRenderer(FONT_BOLD)
UI_REG = ThaiRenderer(FONT_REG)


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

    specials = {
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
    if category in specials:
        cands.insert(0, specials[category])

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
    """Pick proportional size: fill height ~80–88%, never stretch glyphs."""
    target_h = int(max_h * 0.86)
    lo, hi = 48, 560
    best = None
    while lo <= hi:
        mid = (lo + hi) // 2
        # Extra leading so ไม้เอก/ไม้โท never collide across lines
        gap = max(18, int(mid * 0.22))
        imgs = [CATEGORY_RENDERER.render_line(line, mid) for line in lines]
        widths = [im.size[0] for im in imgs]
        heights = [im.size[1] for im in imgs]
        total_h = sum(heights) + gap * (len(lines) - 1)
        if max(widths) > max_w or total_h > target_h:
            hi = mid - 1
            continue
        best = (mid, gap, imgs, total_h)
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


def key_white_bg(im: Image.Image) -> Image.Image:
    im = im.convert("RGBA")
    from collections import deque

    px = im.load()
    w, h = im.size

    def is_bg(x: int, y: int) -> bool:
        r, g, b, a = px[x, y]
        if a < 10:
            return True
        # near-white / light gray backdrop
        return r > 235 and g > 235 and b > 235 and abs(r - g) < 12 and abs(g - b) < 12

    visited = [[False] * w for _ in range(h)]
    q: deque[tuple[int, int]] = deque()
    for x in range(w):
        q.append((x, 0))
        q.append((x, h - 1))
    for y in range(h):
        q.append((0, y))
        q.append((w - 1, y))
    while q:
        x, y = q.popleft()
        if x < 0 or y < 0 or x >= w or y >= h or visited[y][x]:
            continue
        visited[y][x] = True
        if not is_bg(x, y):
            continue
        px[x, y] = (0, 0, 0, 0)
        q.extend(((x + 1, y), (x - 1, y), (x, y + 1), (x, y - 1)))

    # soften leftover fringe
    for y in range(h):
        for x in range(w):
            r, g, b, a = px[x, y]
            if a and r > 248 and g > 248 and b > 248:
                px[x, y] = (0, 0, 0, 0)

    bbox = im.getbbox()
    return im.crop(bbox) if bbox else im


def load_mascot() -> Image.Image:
    if not MASCOT_SRC.exists():
        raise SystemExit(f"missing mascot: {MASCOT_SRC}")
    return key_white_bg(Image.open(MASCOT_SRC))


def pil_font(path: Path, size: int) -> ImageFont.FreeTypeFont:
    return ImageFont.truetype(str(path), size)


def build_clean_base(src: Image.Image, mascot: Image.Image) -> Image.Image:
    im = src.convert("RGB")
    draw = ImageDraw.Draw(im)
    w, h = im.size

    # Clear mid/right decorative area (old icons / previous overlays)
    draw.rectangle((28, 250, w - 28, 1320), fill=CREAM)
    draw.rectangle((1480, 90, w - 18, 660), fill=CREAM)

    l, t, r, b = BLUE_BOX
    draw.rounded_rectangle((l, t, r, b), radius=20, fill=BLUE)

    # Mascot above blue bar (right)
    mw, mh = mascot.size
    target_h = min(320, max(170, t - 170))
    scale = target_h / mh
    m2 = mascot.resize(
        (max(1, int(mw * scale)), max(1, int(mh * scale))), Image.Resampling.LANCZOS
    )
    mx = r - m2.size[0] - 28
    my = t - m2.size[1] - 20

    out = im.convert("RGBA")
    out.alpha_composite(m2, (mx, my))

    # Soft cream under mascot feet so it sits cleanly on the bar edge
    d2 = ImageDraw.Draw(out)
    d2.rounded_rectangle((l, t, r, b), radius=20, fill=BLUE + (255,))
    return out.convert("RGB")


def draw_event_banner(im: Image.Image) -> Image.Image:
    """Title + date between lock pill and blue bar."""
    out = im.convert("RGBA")
    draw = ImageDraw.Draw(out)
    l, t, r, _b = BLUE_BOX

    band_top = t - 126
    band_bot = t - 14
    band_left = l + 36
    band_right = r - 380  # leave room for mascot

    # Soft gold frame + cream fill
    draw.rounded_rectangle(
        (band_left, band_top, band_right, band_bot),
        radius=20,
        fill=DATE_BG + (250,),
    )
    draw.rounded_rectangle(
        (band_left, band_top, band_right, band_bot),
        radius=20,
        outline=(210, 160, 70, 255),
        width=3,
    )
    # Accent bar on left
    draw.rounded_rectangle(
        (band_left + 8, band_top + 14, band_left + 18, band_bot - 14),
        radius=4,
        fill=ACCENT + (255,),
    )

    title = UI_RENDERER.render_line(EVENT_TITLE, 44, fill=NAVY, shadow=None)
    date = UI_RENDERER.render_line(EVENT_DATE, 52, fill=ACCENT, shadow=None)

    max_w = (band_right - band_left) - 56
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

    cx = (band_left + band_right) // 2 + 6
    gap = 0
    block_h = title.size[1] + gap + date.size[1]
    y0 = band_top + ((band_bot - band_top) - block_h) // 2
    out.alpha_composite(title, (cx - title.size[0] // 2, y0))
    out.alpha_composite(date, (cx - date.size[0] // 2, y0 + title.size[1] + gap))
    return out.convert("RGB")


def make_sign(base: Image.Image, slot_id: str, category: str) -> Image.Image:
    im = base.copy()
    draw = ImageDraw.Draw(im)
    l, t, r, b = BLUE_BOX

    # Lock pill
    draw.rounded_rectangle(PILL_BOX, radius=40, fill=PILL_CREAM)
    lock_img = UI_RENDERER.render_line(
        f"ล็อก {slot_id}", 78, fill=LOCK_BROWN, shadow=None
    )
    pcx = (PILL_BOX[0] + PILL_BOX[2]) // 2
    pcy = (PILL_BOX[1] + PILL_BOX[3]) // 2
    im = im.convert("RGBA")
    im.alpha_composite(
        lock_img, (pcx - lock_img.size[0] // 2, pcy - lock_img.size[1] // 2)
    )
    im = im.convert("RGB")
    im = draw_event_banner(im)

    draw = ImageDraw.Draw(im)
    draw.rounded_rectangle((l, t, r, b), radius=20, fill=BLUE)

    pad_x, pad_y = 40, 28
    max_w = (r - l) - pad_x * 2
    max_h = (b - t) - pad_y * 2
    lines = choose_lines(category, max_w, max_h)
    _size, gap, imgs = fit_lines(lines, max_w, max_h)

    total_h = sum(imx.size[1] for imx in imgs) + gap * (len(imgs) - 1)
    y = t + pad_y + (max_h - total_h) // 2
    cx = (l + r) // 2
    out = im.convert("RGBA")
    for layer in imgs:
        out.alpha_composite(layer, (cx - layer.size[0] // 2, y))
        y += layer.size[1] + gap

    im = out.convert("RGB")
    draw = ImageDraw.Draw(im)
    draw.rounded_rectangle((l, t, r, b), radius=20, outline=BLUE, width=5)
    return im


def main() -> None:
    src = Image.open(OUT / "lock_A1.png").convert("RGB")
    mascot = load_mascot()
    mascot.save(WORK / "mascot_official_cut.png")
    print("mascot", mascot.mode, mascot.size, "font", FONT_BOLD.name)

    base = build_clean_base(src, mascot)
    base.save(WORK / "base_v4.png")

    samples = [
        ("A1", "เครื่องดื่มไม่มีแอลกอฮอล์"),
        ("D6", "เบเกอร์รี่"),
        ("A7", "ขนมจีนน้ำยา (น้ำยาหลากหลาย)/หมี่กะทิ"),
        ("D7", "แจ่วฮ้อน/ก๋วยจั๊บ/หมูกระทะ"),
        ("C6", "แจ่วฮ้อน/ก๋วยจั๊บ"),
        ("C8", "สุกี้โรล/เกี๊ยวต้ม/ชาบู"),
        ("B6", "ยำ"),
    ]
    for sid, cat in samples:
        img = make_sign(base, sid, cat)
        img.save(WORK / f"preview4_{sid}.png")
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
        f"- {EVENT_TITLE}\n"
        f"- {EVENT_DATE}\n"
        "- ฟอนต์ TH Sarabun New (จัดรูปด้วย HarfBuzz ไม่ให้ไม้เอก/ไม้โทซ้อน)\n"
        "- น้องจำปาก้าตกแต่งด้านบนกรอบน้ำเงิน\n"
        "- ไม่มีสัญลักษณ์รายการอาหาร\n\n"
        + "\n".join(f"{s}\t{c}" for s, c in SLOTS)
        + "\n",
        encoding="utf-8",
    )
    print("done")


if __name__ == "__main__":
    main()
