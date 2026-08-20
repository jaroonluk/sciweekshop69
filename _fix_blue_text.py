# -*- coding: utf-8 -*-
"""Fix blue-bar text only on selected signs in ป้ายร้าน_update."""
from __future__ import annotations

import unicodedata
from pathlib import Path

import freetype as ft
import numpy as np
import uharfbuzz as hb
from PIL import Image

OUT = Path(
    r"G:\Shared drives\000 - Science Week KKU\Sc Week 2026\รับสมัครร้านค้า_จัดหารายได้\ป้ายร้าน_update"
)
WORK = Path(r"c:\xampp\htdocs\sci_shop\_sign_work")
WORK.mkdir(exist_ok=True)

FONT_BOLD = Path(
    r"C:\Users\Administrator\AppData\Local\Microsoft\Windows\Fonts\THSarabunNew Bold.ttf"
)
if not FONT_BOLD.exists():
    FONT_BOLD = Path(
        r"C:\Users\Administrator\AppData\Local\Microsoft\Windows\Fonts\Sarabun-Bold.ttf"
    )

BLUE = (26, 63, 138)
WHITE = (255, 255, 255)
SHADOW = (12, 28, 70)

# Exact labels requested
UPDATES: dict[str, list[str]] = {
    "lock_C2.png": ["วาฟเฟิล"],
    "lock_C10.png": ["ลูกชุบ/ขนมเบื้อง/ขนมไทย"],
}


def th(s: str) -> str:
    return unicodedata.normalize("NFC", s.strip())


class ThaiRenderer:
    def __init__(self, font_path: Path):
        self.data = Path(font_path).read_bytes()
        self.hb_face = hb.Face(self.data)
        self.ft_face = ft.Face(str(font_path))

    def render_line(self, text: str, size: int) -> Image.Image:
        text = th(text)
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

        if not bitmaps:
            return Image.new("RGBA", (1, 1), (0, 0, 0, 0))

        pad = 4
        w = maxx - minx + pad * 2
        h = maxy - miny + pad * 2
        alpha = np.zeros((h, w), dtype=np.uint8)
        for arr, x0, y0 in bitmaps:
            x = x0 - minx + pad
            y = y0 - miny + pad
            region = alpha[y : y + arr.shape[0], x : x + arr.shape[1]]
            alpha[y : y + arr.shape[0], x : x + arr.shape[1]] = np.maximum(region, arr)

        # shadow + white
        out = Image.new("RGBA", (w + 6, h + 6), (0, 0, 0, 0))

        def paste(color: tuple[int, int, int], ox: int, oy: int) -> None:
            layer = np.zeros((out.size[1], out.size[0], 4), dtype=np.uint8)
            layer[oy : oy + h, ox : ox + w, 0] = color[0]
            layer[oy : oy + h, ox : ox + w, 1] = color[1]
            layer[oy : oy + h, ox : ox + w, 2] = color[2]
            layer[oy : oy + h, ox : ox + w, 3] = alpha
            out.alpha_composite(Image.fromarray(layer, "RGBA"))

        paste(SHADOW, 3, 3)
        paste(WHITE, 0, 0)
        return out


RENDERER = ThaiRenderer(FONT_BOLD)


def find_main_blue(im: Image.Image) -> tuple[int, int, int, int]:
    arr = np.array(im.convert("RGB"))
    dist = np.abs(arr.astype(int) - np.array(BLUE)).sum(axis=2)
    mask = dist <= 28
    rows = mask.any(axis=1)
    best = None
    i = 0
    n = len(rows)
    while i < n:
        if not rows[i]:
            i += 1
            continue
        j = i
        while j < n and rows[j]:
            j += 1
        if best is None or (j - i) > best[2]:
            best = (i, j - 1, j - i)
        i = j
    if not best:
        raise RuntimeError("blue bar not found")
    t, b, _ = best
    cols = mask[t : b + 1].any(axis=0)
    xs = np.where(cols)[0]
    return int(xs.min()), int(t), int(xs.max()), int(b)


def fit_lines(lines: list[str], max_w: int, max_h: int) -> tuple[int, int, list[Image.Image]]:
    """Match large style of A1/A3: fill ~90% of blue height."""
    target = int(max_h * 0.90)
    lo, hi = 40, 520
    best = None
    while lo <= hi:
        mid = (lo + hi) // 2
        gap = max(10, int(mid * 0.16)) if len(lines) > 1 else 0
        imgs = [RENDERER.render_line(line, mid) for line in lines]
        tw = max(im.size[0] for im in imgs)
        th = sum(im.size[1] for im in imgs) + gap * (len(lines) - 1)
        if tw > max_w or th > target:
            hi = mid - 1
            continue
        best = (mid, gap, imgs)
        lo = mid + 1
    if best is None:
        imgs = [RENDERER.render_line(line, 72) for line in lines]
        return 72, 12, imgs
    return best


def rewrite_blue(path: Path, lines: list[str]) -> None:
    im = Image.open(path).convert("RGB")
    original = np.array(im)
    l, t, r, b = find_main_blue(im)
    box_w = r - l + 1
    box_h = b - t + 1

    region = original[t : b + 1, l : r + 1].copy()
    rr, gg, bb = region[:, :, 0], region[:, :, 1], region[:, :, 2]
    # Keep only mascot-like yellow pixels peeking into the bar
    is_mascot = (rr > 200) & (gg > 160) & (bb < 120) & (rr > bb + 60)
    # Also keep green stem accents on mascot
    is_stem = (gg > 140) & (gg > rr + 20) & (gg > bb + 20) & (rr < 180)

    filled = region.copy()
    filled[:] = BLUE
    keep = is_mascot | is_stem
    filled[keep] = region[keep]

    out_arr = original.copy()
    out_arr[t : b + 1, l : r + 1] = filled
    im = Image.fromarray(out_arr)

    pad_x, pad_y = 36, 10
    max_w = box_w - pad_x * 2
    max_h = box_h - pad_y * 2
    size, gap, imgs = fit_lines(lines, max_w, max_h)
    total_h = sum(x.size[1] for x in imgs) + gap * (len(imgs) - 1)
    y = t + pad_y + (max_h - total_h) // 2
    cx = (l + r) // 2

    rgba = im.convert("RGBA")
    for layer in imgs:
        rgba.alpha_composite(layer, (cx - layer.size[0] // 2, y))
        y += layer.size[1] + gap

    # Restore mascot on top of any text that overlapped it
    final = np.array(rgba.convert("RGB"))
    reg = final[t : b + 1, l : r + 1]
    reg[keep] = region[keep]
    final[t : b + 1, l : r + 1] = reg
    result = Image.fromarray(final)

    result.save(path, "PNG", optimize=True)
    result.crop((l, t, r + 1, b + 1)).save(WORK / f"fixed_{path.name}")
    print(f"updated {path.name} size={size} lines={lines} box_h={box_h}")


def main() -> None:
    for name, lines in UPDATES.items():
        path = OUT / name
        if not path.exists():
            print("MISSING", name)
            continue
        rewrite_blue(path, lines)
    print("done")


if __name__ == "__main__":
    main()
