# -*- coding: utf-8 -*-
"""Rebuild lock_D5 from A1: wipe full lock-ink box, redraw ล็อก D5."""
from __future__ import annotations

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

FONT_LOCK = Path(r"C:\Windows\Fonts\leelawdb.ttf")
if not FONT_LOCK.exists():
    FONT_LOCK = Path(r"C:\Windows\Fonts\LeelaUIb.ttf")

LOCK_BROWN = (184, 90, 16)
PILL_CREAM = (255, 246, 217)
LOCK_TEXT = unicodedata.normalize("NFC", "ล็อก D5")


class ThaiRenderer:
    def __init__(self, font_path: Path):
        self.data = Path(font_path).read_bytes()
        self.hb_face = hb.Face(self.data)
        self.ft_face = ft.Face(str(font_path))

    def render_line(self, text: str, size: int, fill=LOCK_BROWN) -> Image.Image:
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
                arr = (arr > 80).astype(np.uint8) * 255
                bitmaps.append((arr, x0, y0))
                minx = min(minx, x0)
                miny = min(miny, y0)
                maxx = max(maxx, x0 + bmp.width)
                maxy = max(maxy, y0 + bmp.rows)
            pen_x += pos.x_advance

        pad = 4
        w = maxx - minx + pad * 2
        h = maxy - miny + pad * 2
        alpha = np.zeros((h, w), dtype=np.uint8)
        for arr, x0, y0 in bitmaps:
            x = x0 - minx + pad
            y = y0 - miny + pad
            region = alpha[y : y + arr.shape[0], x : x + arr.shape[1]]
            alpha[y : y + arr.shape[0], x : x + arr.shape[1]] = np.maximum(region, arr)

        out = Image.new("RGBA", (w, h), (0, 0, 0, 0))
        layer = np.zeros((h, w, 4), dtype=np.uint8)
        layer[:, :, 0] = fill[0]
        layer[:, :, 1] = fill[1]
        layer[:, :, 2] = fill[2]
        layer[:, :, 3] = alpha
        out.alpha_composite(Image.fromarray(layer, "RGBA"))
        return out


def extract_flower(arr: np.ndarray) -> tuple[Image.Image, tuple[int, int]]:
    y0, y1, x0, x1 = 500, 620, 780, 920
    crop = arr[y0:y1, x0:x1]
    r, g, b = (
        crop[:, :, 0].astype(int),
        crop[:, :, 1].astype(int),
        crop[:, :, 2].astype(int),
    )
    flower = (
        ((r > 200) & (g > 150) & (b < 130) & (r > b + 50))
        | ((g > 130) & (g > r + 15) & (g > b + 15) & (r < 200))
        | ((r > 170) & (g > 90) & (g < 190) & (b < 90) & (r > g + 20))
    )
    ys, xs = np.where(flower)
    if len(xs) < 40:
        raise RuntimeError("flower not found")
    pad = 6
    left = max(0, int(xs.min()) - pad)
    top = max(0, int(ys.min()) - pad)
    right = min(crop.shape[1], int(xs.max()) + pad + 1)
    bottom = min(crop.shape[0], int(ys.max()) + pad + 1)
    cut = crop[top:bottom, left:right].copy()
    m = flower[top:bottom, left:right]
    m_img = Image.fromarray((m.astype(np.uint8) * 255), "L").filter(ImageFilter.MaxFilter(5))
    m2 = np.array(m_img) > 0
    rgba = np.zeros((cut.shape[0], cut.shape[1], 4), dtype=np.uint8)
    rgba[:, :, :3] = cut
    rgba[:, :, 3] = np.where(m2, 255, 0)
    return Image.fromarray(rgba, "RGBA"), (x0 + left, y0 + top)


def brown_mask(arr: np.ndarray) -> np.ndarray:
    r, g, b = arr[:, :, 0].astype(int), arr[:, :, 1].astype(int), arr[:, :, 2].astype(int)
    core = (
        (r > 130)
        & (r < 220)
        & (g > 40)
        & (g < 160)
        & (b < 100)
        & (r > g + 25)
        & (r > b + 50)
    )
    zone = np.zeros(arr.shape[:2], dtype=bool)
    zone[520:690, 840:1470] = True
    # Exclude flower yellows on the left
    flower = ((r > 200) & (g > 150) & (b < 130) & (r > b + 50)) | (
        (g > 130) & (g > r + 15) & (g > b + 15) & (r < 200)
    )
    m = core & zone & ~flower
    m_img = Image.fromarray((m.astype(np.uint8) * 255), "L").filter(ImageFilter.MaxFilter(9))
    return np.array(m_img) > 0


def main() -> None:
    a1 = Image.open(OUT / "lock_A1.png").convert("RGB")
    src = np.array(a1)
    flower_img, flower_pos = extract_flower(src)

    mask = brown_mask(src)
    ys, xs = np.where(mask)
    print("mask", xs.min(), ys.min(), xs.max(), ys.max(), "n", int(mask.sum()))

    # Solid cream fill of whole text footprint (kills ghosts in counters too)
    im = Image.fromarray(src.copy())
    draw = ImageDraw.Draw(im)
    pad = 12
    wipe_box = (
        int(xs.min()) - pad,
        int(ys.min()) - pad,
        int(xs.max()) + pad,
        int(ys.max()) + pad,
    )
    draw.rounded_rectangle(wipe_box, radius=40, fill=PILL_CREAM)
    # Also force-mask any leftover brown
    arr = np.array(im)
    leftover = brown_mask(arr)
    arr[leftover] = np.array(PILL_CREAM, dtype=np.uint8)
    print("leftover after wipe", int(leftover.sum()))

    # Target text box = original ink (slightly padded)
    ink = brown_mask(src)
    iy, ix = np.where(ink)
    box = (int(ix.min()), int(iy.min()), int(ix.max()), int(iy.max()))
    max_w = box[2] - box[0] + 8
    max_h = box[3] - box[1] + 4

    lock = ThaiRenderer(FONT_LOCK)
    best = None
    for size in range(165, 70, -1):
        layer = lock.render_line(LOCK_TEXT, size)
        if layer.size[0] <= max_w and layer.size[1] <= max_h:
            best = (size, layer)
            break
    if best is None:
        layer = lock.render_line(LOCK_TEXT, 90)
        ratio = min(max_w / layer.size[0], max_h / layer.size[1])
        layer = layer.resize(
            (max(1, int(layer.size[0] * ratio)), max(1, int(layer.size[1] * ratio))),
            Image.Resampling.LANCZOS,
        )
        best = (90, layer)
    size, lock_img = best
    print("font", size, lock_img.size, "target", max_w, max_h)

    cx = (box[0] + box[2]) // 2
    cy = (box[1] + box[3]) // 2
    out = Image.fromarray(arr).convert("RGBA")
    out.alpha_composite(
        lock_img, (cx - lock_img.size[0] // 2, cy - lock_img.size[1] // 2)
    )
    out.alpha_composite(flower_img, flower_pos)

    result = out.convert("RGB")
    result.save(OUT / "lock_D5.png", "PNG", optimize=True)
    result.save(WORK / "fixed_D5_full.png")
    result.crop((700, 480, 1550, 720)).save(WORK / "fixed_D5_pillzone.png")
    result.resize((562, 397)).save(WORK / "fixed_D5_preview.png")

    a1c = Image.open(OUT / "lock_A1.png").crop((700, 480, 1550, 720))
    d5c = result.crop((700, 480, 1550, 720))
    combo = Image.new("RGB", (a1c.size[0] * 2 + 10, a1c.size[1]), (200, 200, 200))
    combo.paste(a1c, (0, 0))
    combo.paste(d5c, (a1c.size[0] + 10, 0))
    combo.save(WORK / "cmp_A1_D5_side.png")

    # Ghost check: A1-only brown that remains identical
    a1b = src
    d5 = np.array(result)
    r, g, b = a1b[:, :, 0].astype(int), a1b[:, :, 1].astype(int), a1b[:, :, 2].astype(int)
    a1_core = (
        (r > 140)
        & (r < 210)
        & (g > 50)
        & (g < 140)
        & (b < 80)
        & (r > g + 35)
        & (r > b + 70)
    )
    zone = np.zeros_like(a1_core)
    zone[520:690, 840:1470] = True
    a1_core &= zone
    identical = (
        a1_core
        & (d5[:, :, 0] == a1b[:, :, 0])
        & (d5[:, :, 1] == a1b[:, :, 1])
        & (d5[:, :, 2] == a1b[:, :, 2])
    )
    print("identical A1-core pixels remaining", int(identical.sum()))

    # outside pill identical?
    pill = np.zeros(a1b.shape[:2], dtype=bool)
    pill[500:700, 760:1520] = True
    diff = np.abs(a1b.astype(int) - d5.astype(int)).sum(axis=2)
    print("outside pill max diff", int(diff[~pill].max()))
    print("saved")


if __name__ == "__main__":
    main()
