# -*- coding: utf-8 -*-
"""Rebuild lock_D6 from D8 template: clean ghosts, large bakery text."""
from __future__ import annotations

import math
import unicodedata
from pathlib import Path

import freetype as ft
import numpy as np
import uharfbuzz as hb
from PIL import Image, ImageDraw, ImageFilter

OUT = Path(
    r"G:\Shared drives\000 - Science Week KKU\Sc Week 2026\รับสมัครร้านค้า_จัดหารายได้\ป้ายร้าน_update"
)
WORK = Path(r"c:\xampp\htdocs\sci_shop\_sign_work")
WORK.mkdir(exist_ok=True)

# D8 category type is thick loopless; Kanit matches weight but breaks on ร์รี่.
# Use Leelawadee Bold for correct Thai marks, thicken via alpha dilation to match D8 weight.
FONT_CAT = Path(r"C:\Windows\Fonts\leelawdb.ttf")
FONT_LOCK = Path(r"C:\Windows\Fonts\leelawdb.ttf")
if not FONT_CAT.exists():
    FONT_CAT = Path(r"C:\Windows\Fonts\LeelaUIb.ttf")
    FONT_LOCK = FONT_CAT

BLUE = (26, 63, 138)
WHITE = (255, 255, 255)
LOCK_BROWN = (184, 90, 16)
PILL_CREAM = (255, 246, 217)
PILL_EDGE = (220, 170, 90)
CREAM = (255, 251, 240)

BLUE_BOX = (46, 677, 2199, 1135)
# Larger wipe so old D8 lock cannot ghost through
PILL_WIPE = (780, 360, 1470, 580)
PILL_BOX = (840, 410, 1406, 545)

TEXT = unicodedata.normalize("NFC", "เบเกอร์รี่")
LOCK_TEXT = unicodedata.normalize("NFC", "ล็อก D6")


class ThaiRenderer:
    def __init__(self, font_path: Path):
        self.data = Path(font_path).read_bytes()
        self.hb_face = hb.Face(self.data)
        self.ft_face = ft.Face(str(font_path))

    def _alpha(self, text: str, size: int) -> tuple[np.ndarray, int, int]:
        text = unicodedata.normalize("NFC", text)
        hb_font = hb.Font(self.hb_face)
        hb.ot_font_set_funcs(hb_font)
        hb_font.scale = (size, size)
        self.ft_face.set_char_size(size * 64)

        buf = hb.Buffer()
        buf.add_str(text)
        buf.guess_segment_properties()
        hb.shape(hb_font, buf)

        pen_x = 0.0
        minx = miny = 10**9
        maxx = maxy = -10**9
        bitmaps: list[tuple[np.ndarray, int, int]] = []
        for info, pos in zip(buf.glyph_infos, buf.glyph_positions):
            self.ft_face.load_glyph(
                info.codepoint, ft.FT_LOAD_RENDER | ft.FT_LOAD_TARGET_NORMAL
            )
            bmp = self.ft_face.glyph.bitmap
            x0 = int(round(pen_x + pos.x_offset)) + self.ft_face.glyph.bitmap_left
            y0 = int(round(pos.y_offset)) - self.ft_face.glyph.bitmap_top
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

        pad = 8
        w = maxx - minx + pad * 2
        h = maxy - miny + pad * 2
        alpha = np.zeros((h, w), dtype=np.uint8)
        for arr, x0, y0 in bitmaps:
            x = x0 - minx + pad
            y = y0 - miny + pad
            region = alpha[y : y + arr.shape[0], x : x + arr.shape[1]]
            alpha[y : y + arr.shape[0], x : x + arr.shape[1]] = np.maximum(region, arr)
        return alpha, w, h

    def render_line(
        self,
        text: str,
        size: int,
        fill=WHITE,
        thicken: int = 0,
    ) -> Image.Image:
        alpha, w, h = self._alpha(text, size)
        if thicken > 0:
            # Dilate alpha to match D8 heavy stroke weight
            aimg = Image.fromarray(alpha, "L")
            for _ in range(thicken):
                aimg = aimg.filter(ImageFilter.MaxFilter(3))
            alpha = np.array(aimg)
            # trim empty margins after dilate
            ys, xs = np.where(alpha > 0)
            if len(xs):
                alpha = alpha[ys.min() : ys.max() + 1, xs.min() : xs.max() + 1]
                h, w = alpha.shape

        out = Image.new("RGBA", (w, h), (0, 0, 0, 0))
        layer = np.zeros((h, w, 4), dtype=np.uint8)
        layer[:, :, 0] = fill[0]
        layer[:, :, 1] = fill[1]
        layer[:, :, 2] = fill[2]
        layer[:, :, 3] = alpha
        out.alpha_composite(Image.fromarray(layer, "RGBA"))
        return out


CAT = ThaiRenderer(FONT_CAT)
LOCK = ThaiRenderer(FONT_LOCK)


def fit_text(text: str, max_w: int, max_h: int, fill_ratio: float = 0.82, thicken: int = 2):
    target = int(max_h * fill_ratio)
    lo, hi = 40, 900
    best = None
    while lo <= hi:
        mid = (lo + hi) // 2
        im = CAT.render_line(text, mid, thicken=thicken)
        if im.size[0] > max_w or im.size[1] > target:
            hi = mid - 1
            continue
        best = (mid, im)
        lo = mid + 1
    if best is None:
        return 80, CAT.render_line(text, 80, thicken=thicken)
    return best


def main() -> None:
    base = Image.open(OUT / "lock_D8.png").convert("RGB")
    arr = np.array(base)

    # Wipe old lock pill area completely (prevents ghost D8 text)
    wipe = Image.fromarray(arr)
    d = ImageDraw.Draw(wipe)
    d.rounded_rectangle(PILL_WIPE, radius=60, fill=CREAM)
    arr = np.array(wipe)

    l, t, r, b = BLUE_BOX
    region = arr[t : b + 1, l : r + 1].copy()
    rr, gg, bb = region[:, :, 0], region[:, :, 1], region[:, :, 2]
    keep = ((rr > 200) & (gg > 160) & (bb < 120) & (rr > bb + 60)) | (
        (gg > 140) & (gg > rr + 20) & (gg > bb + 20) & (rr < 180)
    )
    region[:] = BLUE
    region[keep] = arr[t : b + 1, l : r + 1][keep]
    arr[t : b + 1, l : r + 1] = region
    im = Image.fromarray(arr)

    pad_x, pad_y = 48, 24
    max_w = (r - l + 1) - pad_x * 2
    max_h = (b - t + 1) - pad_y * 2
    size, layer = fit_text(TEXT, max_w, max_h, fill_ratio=0.90, thicken=0)
    print("category size", size, layer.size)

    cx = (l + r) // 2
    cy = t + pad_y + (max_h - layer.size[1]) // 2
    out = im.convert("RGBA")
    out.alpha_composite(layer, (cx - layer.size[0] // 2, cy))

    final = np.array(out.convert("RGB"))
    reg = final[t : b + 1, l : r + 1]
    reg[keep] = arr[t : b + 1, l : r + 1][keep]
    final[t : b + 1, l : r + 1] = reg
    im = Image.fromarray(final)

    # Fresh lock pill
    draw = ImageDraw.Draw(im)
    pl, pt, pr, pb = PILL_BOX
    draw.rounded_rectangle((pl - 5, pt - 5, pr + 5, pb + 5), radius=50, fill=PILL_EDGE)
    draw.rounded_rectangle((pl, pt, pr, pb), radius=46, fill=PILL_CREAM)
    draw.rounded_rectangle(
        (pl + 10, pt + 10, pr - 10, pb - 10), radius=40, outline=(255, 255, 255), width=3
    )

    lock_img = LOCK.render_line(LOCK_TEXT, 120, fill=LOCK_BROWN, thicken=0)
    max_lw, max_lh = (pr - pl) - 50, (pb - pt) - 36
    if lock_img.size[0] > max_lw or lock_img.size[1] > max_lh:
        ratio = min(max_lw / lock_img.size[0], max_lh / lock_img.size[1])
        lock_img = lock_img.resize(
            (max(1, int(lock_img.size[0] * ratio)), max(1, int(lock_img.size[1] * ratio))),
            Image.Resampling.LANCZOS,
        )
    pcx, pcy = (pl + pr) // 2, (pt + pb) // 2
    out = im.convert("RGBA")
    out.alpha_composite(
        lock_img, (pcx - lock_img.size[0] // 2, pcy - lock_img.size[1] // 2)
    )

    # Single flower accent (like D8)
    flower = Image.new("RGBA", (64, 64), (0, 0, 0, 0))
    fd = ImageDraw.Draw(flower)
    for ang in range(0, 360, 72):
        rad = math.radians(ang)
        cx0 = 32 + int(14 * math.cos(rad))
        cy0 = 32 + int(14 * math.sin(rad))
        fd.ellipse((cx0 - 11, cy0 - 11, cx0 + 11, cy0 + 11), fill=(255, 210, 60, 255))
    fd.ellipse((22, 22, 42, 42), fill=(190, 110, 35, 255))
    out.alpha_composite(flower, (pl - 8, pt - 22))

    result = out.convert("RGB")
    result.save(OUT / "lock_D6.png", "PNG", optimize=True)
    result.crop((l, t, r + 1, b + 1)).save(WORK / "fixed_D6_blue.png")
    result.save(WORK / "fixed_D6_full.png")
    print("saved")


if __name__ == "__main__":
    main()
