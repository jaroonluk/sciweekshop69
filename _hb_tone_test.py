# -*- coding: utf-8 -*-
import uharfbuzz as hb
from pathlib import Path
from PIL import Image, ImageDraw

font_path = Path(r"C:\Users\Administrator\AppData\Local\Microsoft\Windows\Fonts\THSarabunNew Bold.ttf")
data = font_path.read_bytes()
face = hb.Face(data)
font = hb.Font(face)
hb.ot_font_set_funcs(font)
SIZE = 240
font.scale = (SIZE, SIZE)

text = "ก๋วยจั๊บ น้ำยา เกี๊ยว เบเกอร์รี่"

buf = hb.Buffer()
buf.add_str(text)
buf.guess_segment_properties()
hb.shape(font, buf)


class PathCollector:
    def __init__(self, dx, dy):
        self.dx = dx
        self.dy = dy
        self.paths = []
        self.cur = []

    def _map(self, x, y):
        return (self.dx + x, self.dy - y)

    def move_to(self, x, y, *a):
        self.cur = [self._map(x, y)]

    def line_to(self, x, y, *a):
        self.cur.append(self._map(x, y))

    def cubic_to(self, x1, y1, x2, y2, x3, y3, *a):
        p0 = self.cur[-1]
        p1 = self._map(x1, y1)
        p2 = self._map(x2, y2)
        p3 = self._map(x3, y3)
        for i in range(1, 9):
            t = i / 8
            u = 1 - t
            x = u**3 * p0[0] + 3 * u**2 * t * p1[0] + 3 * u * t**2 * p2[0] + t**3 * p3[0]
            y = u**3 * p0[1] + 3 * u**2 * t * p1[1] + 3 * u * t**2 * p2[1] + t**3 * p3[1]
            self.cur.append((x, y))

    def quadratic_to(self, x1, y1, x2, y2, *a):
        p0 = self.cur[-1]
        p1 = self._map(x1, y1)
        p2 = self._map(x2, y2)
        for i in range(1, 7):
            t = i / 6
            u = 1 - t
            x = u * u * p0[0] + 2 * u * t * p1[0] + t * t * p2[0]
            y = u * u * p0[1] + 2 * u * t * p1[1] + t * t * p2[1]
            self.cur.append((x, y))

    def close_path(self, *a):
        if self.cur:
            self.paths.append(self.cur)
            self.cur = []


funcs = hb.DrawFuncs()
pen_x = 0.0
pen_y = 0.0
baseline = 220
all_paths = []
for info, pos in zip(buf.glyph_infos, buf.glyph_positions):
    dx = pen_x + pos.x_offset
    dy = baseline - (pen_y + pos.y_offset)
    coll = PathCollector(dx, dy)

    def make_move(c):
        return lambda x, y, *a: c.move_to(x, y)

    def make_line(c):
        return lambda x, y, *a: c.line_to(x, y)

    def make_cubic(c):
        return lambda x1, y1, x2, y2, x3, y3, *a: c.cubic_to(x1, y1, x2, y2, x3, y3)

    def make_quad(c):
        return lambda x1, y1, x2, y2, *a: c.quadratic_to(x1, y1, x2, y2)

    def make_close(c):
        return lambda *a: c.close_path()

    funcs.set_move_to_func(make_move(coll))
    funcs.set_line_to_func(make_line(coll))
    funcs.set_cubic_to_func(make_cubic(coll))
    funcs.set_quadratic_to_func(make_quad(coll))
    funcs.set_close_path_func(make_close(coll))
    funcs.draw_glyph(info.codepoint, font)
    all_paths.extend(coll.paths)
    pen_x += pos.x_advance
    pen_y += pos.y_advance

xs = []
ys = []
for p in all_paths:
    for x, y in p:
        xs.append(x)
        ys.append(y)
minx, maxx = min(xs), max(xs)
miny, maxy = min(ys), max(ys)
pad = 20
w = int(maxx - minx + pad * 2)
h = int(maxy - miny + pad * 2)
im = Image.new("RGBA", (w, h), (26, 63, 138, 255))
d = ImageDraw.Draw(im)
for p in all_paths:
    pts = [(x - minx + pad, y - miny + pad) for x, y in p]
    if len(pts) >= 3:
        d.polygon(pts, fill=(255, 255, 255, 255))
out = Path(r"c:\xampp\htdocs\sci_shop\_sign_work\tone_hb.png")
im.save(out)
print("saved", out, w, h, "paths", len(all_paths))
