# -*- coding: utf-8 -*-
"""Rebuild shop signs: tall category text in blue bar, mascot above, no food icons."""
from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

OUT = Path(r"G:\Shared drives\000 - Science Week KKU\Sc Week 2026\รับสมัครร้านค้า_จัดหารายได้\ป้ายร้านค้า")
WORK = Path(r"c:\xampp\htdocs\sci_shop\_sign_work")
WORK.mkdir(exist_ok=True)

ASSET_MAGENTA = Path(
    r"C:\Users\Administrator\.cursor\projects\c-xampp-htdocs-sci-shop\assets\champaka_mascot_magenta.png"
)
ASSET_FALLBACK = Path(
    r"C:\Users\Administrator\.cursor\projects\c-xampp-htdocs-sci-shop\assets\champaka_mascot.png"
)

FONT_BOLD = Path(r"C:\Windows\Fonts\leelawdb.ttf")
if not FONT_BOLD.exists():
    FONT_BOLD = Path(r"C:\Windows\Fonts\LeelaUIb.ttf")

BLUE = (26, 63, 138)
WHITE = (255, 255, 255)
LOCK_BROWN = (184, 90, 16)
PILL_CREAM = (255, 246, 217)
CREAM = (255, 242, 205)

BLUE_BOX = (52, 608, 2194, 1204)
PILL_BOX = (860, 378, 1390, 512)

# Keep ~2% padding inside blue so glyphs never clip
PAD_X = 28
PAD_Y = 10
FILL_RATIO = 0.985

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


def ink_size(text: str, fnt) -> tuple[int, int]:
    # Measure actual glyph ink, not font ascent/descent boxes
    canvas = Image.new("L", (3200, 1400), 0)
    d = ImageDraw.Draw(canvas)
    d.text((80, 80), text, font=fnt, fill=255)
    bbox = canvas.getbbox()
    if not bbox:
        return 0, 0
    return bbox[2] - bbox[0], bbox[3] - bbox[1]


def wrap_candidates(category: str) -> list[list[str]]:
    """Prefer readable wraps; never mid-split short single words."""
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

    # Known readable splits for long labels
    specials = {
        "เครื่องดื่มไม่มีแอลกอฮอล์": ["เครื่องดื่ม", "ไม่มีแอลกอฮอล์"],
        "ข้าวไข่เจียว อาหารตามสั่ง": ["ข้าวไข่เจียว", "อาหารตามสั่ง"],
        "เฉาก๊วยนมสดและเครื่องดื่ม": ["เฉาก๊วยนมสด", "และเครื่องดื่ม"],
        "ขนมจีนน้ำยา (น้ำยาหลากหลาย)/หมี่กะทิ": [
            "ขนมจีนน้ำยา",
            "(น้ำยาหลากหลาย)",
            "หมี่กะทิ",
        ],
        "แจ่วฮ้อน/ก๋วยจั๊บ/หมูกระทะ": ["แจ่วฮ้อน/ก๋วยจั๊บ", "หมูกระทะ"],
        "ลูกชุบ/ขนมเบื้อง/ขนมไทย": ["ลูกชุบ/ขนมเบื้อง", "ขนมไทย"],
        "สุกี้โรล/เกี๊ยวต้ม/ชาบู": ["สุกี้โรล", "เกี๊ยวต้ม/ชาบู"],
        "สื่อเกมการศึกษา/บอร์ดเกม": ["สื่อเกมการศึกษา", "บอร์ดเกม"],
        "หม่าล่าย่าง (เสียบไม้)": ["หม่าล่าย่าง", "(เสียบไม้)"],
        "เบเกอร์รี่": ["เบเกอร์รี่"],
    }
    # Put preferred wraps first so they win ties
    if category in specials:
        cands.insert(0, specials[category])

    # Deduplicate
    uniq: list[list[str]] = []
    seen = set()
    for lines in cands:
        lines = [x.strip() for x in lines if x and x.strip()]
        key = tuple(lines)
        if key not in seen:
            seen.add(key)
            uniq.append(lines)
    return uniq


def measure_layout(lines: list[str], size: int) -> tuple[bool, int, list[int], int]:
    fnt = font(size)
    heights: list[int] = []
    max_w_used = 0
    for line in lines:
        tw, th = ink_size(line, fnt)
        heights.append(th)
        max_w_used = max(max_w_used, tw)
    gap = max(2, size // 28) if len(lines) > 1 else 0
    total_h = sum(heights) + gap * (len(lines) - 1)
    return True, gap, heights, total_h, max_w_used  # type: ignore[return-value]


def fit_layout(lines: list[str], max_w: int, max_h: int):
    target_h = int(max_h * FILL_RATIO)
    lo, hi = 40, 1100
    best = None
    while lo <= hi:
        mid = (lo + hi) // 2
        fnt = font(mid)
        heights = []
        too_wide = False
        for line in lines:
            tw, th = ink_size(line, fnt)
            if tw > max_w:
                too_wide = True
                break
            heights.append(th)
        if too_wide:
            hi = mid - 1
            continue
        gap = max(2, mid // 28) if len(lines) > 1 else 0
        total_h = sum(heights) + gap * (len(lines) - 1)
        if total_h > target_h:
            hi = mid - 1
            continue
        best = (fnt, gap, heights, total_h, mid)
        lo = mid + 1
    if best is None:
        fnt = font(72)
        heights = [ink_size(line, fnt)[1] for line in lines]
        gap = 6 if len(lines) > 1 else 0
        return fnt, gap, heights, sum(heights) + gap * (len(lines) - 1)
    return best[0], best[1], best[2], best[3]


def choose_lines(category: str, max_w: int, max_h: int) -> list[str]:
    best_lines = [category]
    best_score = -1.0
    for lines in wrap_candidates(category):
        _fnt, _gap, _heights, total = fit_layout(lines, max_w, max_h)
        fill = total / max_h
        # Maximize fill height; tiny penalty for extra lines
        score = fill * 1000 - (len(lines) - 1) * 3
        if fill < 0.55 and len(lines) > 1:
            continue
        if score > best_score:
            best_score = score
            best_lines = lines
    return best_lines


def key_magenta(im: Image.Image, tol: int = 55) -> Image.Image:
    im = im.convert("RGBA")
    px = im.load()
    w, h = im.size
    for y in range(h):
        for x in range(w):
            r, g, b, a = px[x, y]
            # Magenta chroma key
            if r > 180 and b > 180 and g < 120 and abs(r - b) < 80:
                px[x, y] = (0, 0, 0, 0)
                continue
            # Near-white leftovers
            if r > 245 and g > 245 and b > 245:
                px[x, y] = (0, 0, 0, 0)
                continue
            if r > 235 and g > 235 and b > 235 and abs(r - g) < 10 and abs(g - b) < 10:
                px[x, y] = (0, 0, 0, 0)
    bbox = im.getbbox()
    return im.crop(bbox) if bbox else im


def key_white(im: Image.Image) -> Image.Image:
    im = im.convert("RGBA")
    px = im.load()
    w, h = im.size
    # Flood from edges: mark near-white connected to border as transparent
    from collections import deque

    visited = [[False] * w for _ in range(h)]
    q: deque[tuple[int, int]] = deque()

    def is_bg(x: int, y: int) -> bool:
        r, g, b, a = px[x, y]
        if a < 8:
            return True
        return r > 232 and g > 232 and b > 232 and abs(r - g) < 18 and abs(g - b) < 18

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

    bbox = im.getbbox()
    return im.crop(bbox) if bbox else im


def load_mascot() -> Image.Image:
    if ASSET_MAGENTA.exists():
        return key_magenta(Image.open(ASSET_MAGENTA))
    if ASSET_FALLBACK.exists():
        return key_white(Image.open(ASSET_FALLBACK))
    raise SystemExit("missing mascot asset")


def build_clean_base(src: Image.Image, mascot: Image.Image) -> Image.Image:
    im = src.convert("RGB")
    draw = ImageDraw.Draw(im)
    w, _h = im.size

    # Wipe mid decorative band + right food-icon stack
    draw.rectangle((30, 300, w - 30, 1300), fill=CREAM)
    draw.rectangle((1500, 120, w - 20, 640), fill=CREAM)

    l, t, r, b = BLUE_BOX
    draw.rounded_rectangle((l, t, r, b), radius=18, fill=BLUE)

    # Fit mascot fully above blue bar
    mw, mh = mascot.size
    avail_h = t - 130 - 20  # leave room under logos / pill zone
    target_h = min(300, max(160, avail_h))
    scale = target_h / mh
    m2 = mascot.resize((max(1, int(mw * scale)), max(1, int(mh * scale))), Image.Resampling.LANCZOS)
    mx = r - m2.size[0] - 20
    my = t - m2.size[1] - 18  # fully above blue

    out = im.convert("RGBA")
    out.alpha_composite(m2, (mx, my))
    # Ensure blue bar covers any accidental foot overlap
    d2 = ImageDraw.Draw(out)
    d2.rounded_rectangle((l, t, r, b), radius=18, fill=BLUE + (255,))
    return out.convert("RGB")


def render_category_layer(
    lines: list[str],
    fnt,
    gap: int,
    heights: list[int],
    box_w: int,
    box_h: int,
) -> Image.Image:
    """Draw category into transparent layer, then stretch height to fill blue box if needed."""
    total_h = sum(heights) + gap * (len(lines) - 1)
    # Extra canvas for shadows / ink overflow
    layer = Image.new("RGBA", (box_w + 40, box_h + 40), (0, 0, 0, 0))
    draw = ImageDraw.Draw(layer)
    cx = (box_w + 40) // 2
    y_ink = ((box_h + 40) - total_h) // 2
    for i, line in enumerate(lines):
        bb = draw.textbbox((0, 0), line, font=fnt)
        tw = bb[2] - bb[0]
        x = cx - tw // 2 - bb[0]
        y = y_ink - bb[1]
        draw.text((x + 3, y + 3), line, font=fnt, fill=(12, 28, 70, 255))
        draw.text((x, y), line, font=fnt, fill=WHITE + (255,))
        y_ink += heights[i] + gap

    ink = layer.getbbox()
    if not ink:
        return layer
    cropped = layer.crop(ink)
    cw, ch = cropped.size
    target_h = int(box_h * FILL_RATIO)
    target_w = int(box_w * 0.98)
    # Grow to fill height; if that exceeds width, fit width then stretch height only
    scale_h = target_h / ch
    new_w = int(cw * scale_h)
    new_h = int(ch * scale_h)
    if new_w > target_w:
        scale_w = target_w / cw
        new_w = int(cw * scale_w)
        # Prefer height fill: allow mild vertical stretch after width fit
        new_h = min(target_h, max(int(ch * scale_w), int(target_h * 0.92)))
    fitted = cropped.resize((max(1, new_w), max(1, new_h)), Image.Resampling.LANCZOS)

    out = Image.new("RGBA", (box_w, box_h), (0, 0, 0, 0))
    ox = (box_w - fitted.size[0]) // 2
    oy = (box_h - fitted.size[1]) // 2
    out.alpha_composite(fitted, (ox, oy))
    return out


def make_sign(base: Image.Image, slot_id: str, category: str) -> Image.Image:
    im = base.copy()
    draw = ImageDraw.Draw(im)
    l, t, r, b = BLUE_BOX

    draw.rounded_rectangle(PILL_BOX, radius=40, fill=PILL_CREAM)
    lock_text = f"ล็อก {slot_id}"
    lf = font(92)
    bb = draw.textbbox((0, 0), lock_text, font=lf)
    pcx = (PILL_BOX[0] + PILL_BOX[2]) // 2
    pcy = (PILL_BOX[1] + PILL_BOX[3]) // 2
    tw, th = bb[2] - bb[0], bb[3] - bb[1]
    draw.text((pcx - tw // 2 - bb[0], pcy - th // 2 - bb[1]), lock_text, font=lf, fill=LOCK_BROWN)

    draw.rounded_rectangle((l, t, r, b), radius=18, fill=BLUE)
    max_w = (r - l) - PAD_X * 2
    max_h = (b - t) - PAD_Y * 2
    lines = choose_lines(category, max_w, max_h)
    fnt, gap, heights, _total = fit_layout(lines, max_w, max_h)
    layer = render_category_layer(lines, fnt, gap, heights, max_w, max_h)
    out = im.convert("RGBA")
    out.alpha_composite(layer, (l + PAD_X, t + PAD_Y))
    im = out.convert("RGB")
    draw = ImageDraw.Draw(im)
    draw.rounded_rectangle((l, t, r, b), radius=18, outline=BLUE, width=6)
    return im


def main() -> None:
    src = Image.open(OUT / "lock_A1.png").convert("RGB")
    # Prefer original header from a known good source if A1 was already wiped
    # (lock files already regenerated; base wipe recreates cream mid-band)
    mascot = load_mascot()
    mascot.save(WORK / "mascot_used.png")
    print("mascot", mascot.mode, mascot.size)

    base = build_clean_base(src, mascot)
    base.save(WORK / "base_v3.png")

    samples = (
        ("A1", "เครื่องดื่มไม่มีแอลกอฮอล์"),
        ("D6", "เบเกอร์รี่"),
        ("A7", "ขนมจีนน้ำยา (น้ำยาหลากหลาย)/หมี่กะทิ"),
        ("D7", "แจ่วฮ้อน/ก๋วยจั๊บ/หมูกระทะ"),
        ("B5", "ผลไม้"),
        ("B6", "ยำ"),
    )
    for sid, cat in samples:
        img = make_sign(base, sid, cat)
        img.save(WORK / f"preview3_{sid}.png")
        l, t, r, b = BLUE_BOX
        lines = choose_lines(cat, (r - l) - PAD_X * 2, (b - t) - PAD_Y * 2)
        _f, _g, _h, total = fit_layout(lines, (r - l) - PAD_X * 2, (b - t) - PAD_Y * 2)
        print(f"preview {sid} lines={lines} fill={total / ((b - t) - PAD_Y * 2):.2f}")

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
        "- ตัวอักษรหมวดสูงเกือบเต็มกรอบน้ำเงิน (ไม่ล้น)\n"
        "- น้องจำปาฟ้าอยู่เหนือกรอบน้ำเงิน\n"
        "- เอาสัญลักษณ์รายการอาหารออกแล้ว\n\n"
        + "\n".join(f"{s}\t{c}" for s, c in SLOTS)
        + "\n",
        encoding="utf-8",
    )
    print("done")


if __name__ == "__main__":
    main()
