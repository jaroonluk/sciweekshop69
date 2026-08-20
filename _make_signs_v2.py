# -*- coding: utf-8 -*-
"""Rebuild shop signs: full-height category text, mascot above blue bar, no food icons."""
from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageFont, ImageFilter

OUT = Path(r"G:\Shared drives\000 - Science Week KKU\Sc Week 2026\รับสมัครร้านค้า_จัดหารายได้\ป้ายร้านค้า")
WORK = Path(r"c:\xampp\htdocs\sci_shop\_sign_work")
WORK.mkdir(exist_ok=True)

FONT_BOLD = Path(r"C:\Windows\Fonts\leelawdb.ttf")
if not FONT_BOLD.exists():
    FONT_BOLD = Path(r"C:\Windows\Fonts\LeelaUIb.ttf")

BLUE = (26, 63, 138)
WHITE = (255, 255, 255)
LOCK_BROWN = (184, 90, 16)
PILL_CREAM = (255, 246, 217)
CREAM = (255, 241, 198)

# Blue banner geometry (from measured template)
BLUE_BOX = (52, 608, 2194, 1204)  # left, top, right, bottom
PILL_BOX = (860, 378, 1390, 512)

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


def font(size: int) -> ImageFont.FreeTypeFont:
    return ImageFont.truetype(str(FONT_BOLD), size)


def text_size(draw: ImageDraw.ImageDraw, text: str, fnt) -> tuple[int, int]:
    box = draw.textbbox((0, 0), text, font=fnt)
    return box[2] - box[0], box[3] - box[1]


def wrap_category(text: str) -> list[str]:
    if "/" in text and len(text) >= 16:
        parts = [p.strip() for p in text.split("/") if p.strip()]
        if len(parts) == 2:
            return parts
        if len(parts) == 3:
            # prefer 2 lines: first two joined, last alone — or split evenly
            if len(parts[0]) + len(parts[1]) <= 18:
                return [parts[0] + "/" + parts[1], parts[2]]
            return [parts[0], parts[1] + "/" + parts[2]]
    return [text]


def is_bg_like(rgb) -> bool:
    r, g, b = rgb[:3]
    # cream / pale yellow flower bg
    if r > 220 and g > 190 and b > 130 and b < 230:
        return True
    if r > 240 and g > 230 and b > 200:
        return True
    return False


def extract_mascot(src: Image.Image) -> Image.Image:
    """Cut mascot from top-right / bottom-right of current artwork as RGBA."""
    # Prefer the denser mascot region found around right side above/on blue bar
    crop = src.crop((1760, 250, 2230, 640)).convert("RGBA")
    px = crop.load()
    w, h = crop.size
    for y in range(h):
        for x in range(w):
            r, g, b, a = px[x, y]
            if is_bg_like((r, g, b)):
                px[x, y] = (r, g, b, 0)
            # also clear near-white card panels
            elif r > 245 and g > 235 and b > 210:
                px[x, y] = (r, g, b, 0)
            # clear soft yellow card fill
            elif r > 250 and g > 230 and b > 170 and abs(r - g) < 40:
                px[x, y] = (r, g, b, 0)
    # trim transparent borders
    bbox = crop.getbbox()
    if bbox:
        crop = crop.crop(bbox)
    # soft edge
    alpha = crop.split()[-1].filter(ImageFilter.MinFilter(3))
    crop.putalpha(alpha)
    return crop


def cover_circle(draw: ImageDraw.ImageDraw, cx: int, cy: int, radius: int, fill) -> None:
    draw.ellipse((cx - radius, cy - radius, cx + radius, cy + radius), fill=fill)


def sample_bg(im: Image.Image, x: int, y: int) -> tuple[int, int, int]:
    px = im.load()
    w, h = im.size
    for r in range(5, 80, 5):
        for dx, dy in ((-r, 0), (r, 0), (0, -r), (0, r), (-r, -r), (r, -r)):
            xx, yy = x + dx, y + dy
            if 0 <= xx < w and 0 <= yy < h:
                rgb = px[xx, yy]
                if is_bg_like(rgb):
                    return rgb[:3]
    return CREAM


def build_clean_base(src: Image.Image, mascot: Image.Image) -> Image.Image:
    im = src.copy().convert("RGB")
    draw = ImageDraw.Draw(im)
    w, h = im.size

    # 1) Cover food-symbol clusters near blue-bar corners with cream
    # Left cluster ~ (50-280, 520-720), Right cluster ~ (1900-2200, 520-720)
    for cx, cy, rad in (
        (150, 620, 130),
        (230, 680, 110),
        (2090, 620, 130),
        (2000, 680, 110),
        (1850, 600, 90),
    ):
        fill = sample_bg(im, cx, cy - rad - 10)
        cover_circle(draw, cx, cy, rad, fill)

    # Also scrub a rectangle strip above blue bar corners
    draw.rectangle((40, 540, 320, 640), fill=sample_bg(im, 100, 500))
    draw.rectangle((1880, 540, 2210, 640), fill=sample_bg(im, 1800, 500))

    # 2) Cover original mascot / cream-card stack on the right (above blue)
    # Keep event title text on far top; cover card+mascot area
    card_fill = sample_bg(im, 1700, 120)
    draw.rounded_rectangle((1680, 180, 2220, 600), radius=28, fill=card_fill)
    # paint a few more bg patches in case
    draw.rectangle((1680, 180, 2220, 600), fill=card_fill)

    # 3) Solid blue banner (wipes leftover icons + old text completely)
    l, t, r, b = BLUE_BOX
    draw.rounded_rectangle((l, t, r, b), radius=16, fill=BLUE)

    # 4) Restore lock pill cleanly
    draw.rounded_rectangle(PILL_BOX, radius=40, fill=PILL_CREAM)

    # 5) Place mascot ABOVE the blue bar (right side)
    mw, mh = mascot.size
    target_h = 280
    scale = target_h / mh
    mw2, mh2 = int(mw * scale), int(mh * scale)
    m2 = mascot.resize((mw2, mh2), Image.Resampling.LANCZOS)
    # sit just above blue bar, right side
    mx = r - mw2 - 40
    my = t - mh2 + 18  # slight overlap onto blue top edge looks natural
    if my < 160:
        my = 160
    im_rgba = im.convert("RGBA")
    im_rgba.alpha_composite(m2, (mx, my))
    return im_rgba.convert("RGB")


def fit_category_text(draw: ImageDraw.ImageDraw, lines: list[str], max_w: int, max_h: int):
    """Grow font until text height nearly equals blue bar height (with tiny padding)."""
    pad = 18  # keep inside frame
    target_h = max_h - pad * 2
    best = None
    # start large and shrink until fits width; prefer max height usage
    for size in range(320, 60, -2):
        fnt = font(size)
        widths, heights = [], []
        for line in lines:
            tw, th = text_size(draw, line, fnt)
            widths.append(tw)
            heights.append(th)
        gap = max(4, size // 14) if len(lines) > 1 else 0
        total_h = sum(heights) + gap * (len(lines) - 1)
        if max(widths) <= max_w and total_h <= target_h:
            best = (lines, fnt, gap, total_h, size)
            break
    if best is None:
        fnt = font(64)
        return lines, fnt, 8
    # try to grow a bit more if there is leftover vertical space
    lines, fnt, gap, total_h, size = best
    return lines, fnt, gap


def draw_centered_lines(draw, lines, fnt, gap, cx, cy, fill):
    heights, widths = [], []
    for line in lines:
        tw, th = text_size(draw, line, fnt)
        widths.append(tw)
        heights.append(th)
    total_h = sum(heights) + gap * (len(lines) - 1)
    y = cy - total_h // 2
    for i, line in enumerate(lines):
        tw, th = widths[i], heights[i]
        # slight shadow for readability
        draw.text((cx - tw // 2 + 2, y + 2), line, font=fnt, fill=(10, 30, 80))
        draw.text((cx - tw // 2, y), line, font=fnt, fill=fill)
        y += th + gap


def make_sign(base: Image.Image, slot_id: str, category: str) -> Image.Image:
    im = base.copy()
    draw = ImageDraw.Draw(im)

    # lock label
    lock_text = f"ล็อก {slot_id}"
    lock_fnt = font(92)
    tw, th = text_size(draw, lock_text, lock_fnt)
    pill_cx = (PILL_BOX[0] + PILL_BOX[2]) // 2
    pill_cy = (PILL_BOX[1] + PILL_BOX[3]) // 2
    # refresh pill each time
    draw.rounded_rectangle(PILL_BOX, radius=40, fill=PILL_CREAM)
    draw.text((pill_cx - tw // 2, pill_cy - th // 2 - 6), lock_text, font=lock_fnt, fill=LOCK_BROWN)

    # category text — fill blue height
    l, t, r, b = BLUE_BOX
    # repaint blue to be safe
    draw.rounded_rectangle((l, t, r, b), radius=16, fill=BLUE)
    lines = wrap_category(category)
    max_w = (r - l) - 80
    max_h = b - t
    lines, cat_fnt, gap = fit_category_text(draw, lines, max_w, max_h)
    cx = (l + r) // 2
    cy = (t + b) // 2
    draw_centered_lines(draw, lines, cat_fnt, gap, cx, cy, WHITE)
    return im


def main() -> None:
    src_path = OUT / "lock_A1.png"
    if not src_path.exists():
        raise SystemExit("missing source sign")
    src = Image.open(src_path).convert("RGB")
    mascot = extract_mascot(src)
    mascot.save(WORK / "mascot.png")
    print("mascot", mascot.size)

    base = build_clean_base(src, mascot)
    base.save(WORK / "base.png")
    print("base ready")

    # preview a few
    for sid, cat in (("D6", "เบเกอร์รี่"), ("A7", "ขนมจีนน้ำยา (น้ำยาหลากหลาย)/หมี่กะทิ"), ("A1", "เครื่องดื่มไม่มีแอลกอฮอล์")):
        preview = make_sign(base, sid, cat)
        preview.save(WORK / f"preview_{sid}.png")
        print("preview", sid)

    # write all
    for sid, cat in SLOTS:
        img = make_sign(base, sid, cat)
        img.save(OUT / f"lock_{sid}.png", "PNG", optimize=True)
        print("wrote", sid, cat)

    # remove extras
    official = {s for s, _ in SLOTS}
    for p in OUT.glob("lock_*.png"):
        sid = p.stem.replace("lock_", "")
        if sid not in official:
            p.unlink()
            print("removed", p.name)

    (OUT / "รายการป้ายร้านค้า.txt").write_text(
        "ป้ายร้านค้า SCI Week 2569\n"
        "- ตัวอักษรหมวดขยายสูงเกือบเต็มกรอบน้ำเงิน (ไม่ล้น)\n"
        "- น้องจำปาฟ้าอยู่เหนือกรอบน้ำเงิน\n"
        "- เอาสัญลักษณ์รายการอาหารออกแล้ว\n\n"
        + "\n".join(f"{s}\t{c}" for s, c in SLOTS)
        + "\n",
        encoding="utf-8",
    )
    print("done")


if __name__ == "__main__":
    main()
