# -*- coding: utf-8 -*-
"""
Extract Thai name title (คำนำหน้า) from an ID-card file on Google Drive.
Stdout: JSON only — never dumps full OCR text or personal names.
"""
from __future__ import annotations

import json
import re
import sys
import tempfile
from pathlib import Path

TITLE_PATTERNS = [
    ("จ.ส.ต.หญิง", r"จ\.?\s*ส\.?\s*ต\.?\s*หญิง"),
    ("จ.ส.ต.", r"จ\.?\s*ส\.?\s*ต\.?(?!\s*หญิง)"),
    ("นางสาว", r"นางสาว"),
    ("น.ส.", r"น\.?\s*ส\.?"),
    ("นาย", r"นาย"),
    ("นาง", r"นาง(?!สาว)"),
    ("ด.ช.", r"(?:ด\.?\s*ช\.?|เด็กชาย)"),
    ("ด.ญ.", r"(?:ด\.?\s*ญ\.?|เด็กหญิง)"),
]


def fold(s: str) -> str:
    s = re.sub(r"\s+", " ", (s or "").strip())
    s = s.replace("เเ", "แ")
    return s


def has_title(name: str) -> bool:
    n = fold(name)
    for _, pat in TITLE_PATTERNS:
        if re.match(rf"^{pat}\s*", n):
            return True
    return False


def extract_title_from_text(ocr_text: str, core_name: str = "") -> str | None:
    text = fold(ocr_text)
    if not text:
        return None

    core = fold(core_name)
    # Prefer title that appears immediately before the person's core name
    if core:
        core_compact = re.sub(r"\s+", "", core)
        text_compact = re.sub(r"\s+", "", text)
        for title, pat in TITLE_PATTERNS:
            # title + optional space + core (allow OCR spacing noise)
            for m in re.finditer(pat, text):
                after = fold(text[m.end() :])[:80]
                after_c = re.sub(r"\s+", "", after)
                if after_c.startswith(core_compact[: max(4, min(8, len(core_compact)))]):
                    return title

        # looser: title within 30 chars before a core token
        parts = core.split()
        needle = parts[0] if parts else core
        if len(needle) >= 3:
            idx = text.find(needle)
            if idx > 0:
                window = text[max(0, idx - 24) : idx]
                for title, pat in TITLE_PATTERNS:
                    if re.search(pat + r"\s*$", window):
                        return title

    # Fallback: first title-like token on card (common on Thai ID)
    for title, pat in TITLE_PATTERNS:
        if re.search(pat, text):
            # Avoid false positive from English "Name" area — require Thai context
            if re.search(pat + r"\s*[\u0E00-\u0E7F]{2,}", text):
                return title
    return None


def download_drive(file_id: str, dest: Path) -> bool:
    try:
        import gdown
    except ImportError:
        return False
    url = f"https://drive.google.com/uc?id={file_id}"
    try:
        out = gdown.download(url=url, output=str(dest), quiet=True, fuzzy=True)
        return bool(out) and dest.is_file() and dest.stat().st_size > 100
    except Exception:
        return False


def file_to_images(path: Path) -> list:
    from PIL import Image
    import io

    data = path.read_bytes()
    magic = data[:5]
    images = []
    if magic.startswith(b"%PDF") or path.suffix.lower() == ".pdf":
        import pymupdf

        doc = pymupdf.open(stream=data, filetype="pdf")
        try:
            page = doc[0]
            pix = page.get_pixmap(matrix=pymupdf.Matrix(2, 2), alpha=False)
            images.append(Image.open(io.BytesIO(pix.tobytes("png"))))
        finally:
            doc.close()
    else:
        images.append(Image.open(io.BytesIO(data)).convert("RGB"))
    return images


def ocr_images(images) -> str:
    from rapidocr_onnxruntime import RapidOCR

    engine = RapidOCR()
    chunks = []
    for im in images:
        result, _ = engine(im)
        if not result:
            continue
        for line in result:
            # line: [box, text, score]
            if len(line) >= 2 and line[1]:
                chunks.append(str(line[1]))
    return "\n".join(chunks)


def resolve_one(file_id: str, core_name: str = "") -> dict:
    if not file_id:
        return {"ok": False, "title": None, "error": "no_file_id"}
    with tempfile.TemporaryDirectory(prefix="sci_id_") as td:
        dest = Path(td) / "idcard.bin"
        if not download_drive(file_id, dest):
            return {"ok": False, "title": None, "error": "download_failed"}
        try:
            images = file_to_images(dest)
            text = ocr_images(images)
            title = extract_title_from_text(text, core_name)
            if not title:
                return {"ok": False, "title": None, "error": "title_not_found"}
            return {"ok": True, "title": title, "error": None}
        except Exception as e:
            return {"ok": False, "title": None, "error": type(e).__name__}


def main():
    # stdin JSON: {"items":[{"key":"...","file_id":"...","core_name":"..."}]}
    raw = sys.stdin.read()
    try:
        payload = json.loads(raw or "{}")
    except json.JSONDecodeError:
        print(json.dumps({"ok": False, "error": "invalid_json"}, ensure_ascii=False))
        return 1

    items = payload.get("items") or []
    out = {"ok": True, "results": {}}
    for it in items:
        key = str(it.get("key") or it.get("file_id") or "")
        file_id = str(it.get("file_id") or "")
        core = str(it.get("core_name") or "")
        out["results"][key] = resolve_one(file_id, core)
    print(json.dumps(out, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
