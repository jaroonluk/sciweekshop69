# -*- coding: utf-8 -*-
"""Capture apply.php screens for meeting presentation (force-show form if closed)."""
from pathlib import Path
from playwright.sync_api import sync_playwright

OUT = Path(__file__).resolve().parent
URL = "https://sci.kku.ac.th/app/sciweekshop69/apply.php?event=event-2569-aa7ecc&round=12"


def shot(page, name: str) -> None:
    path = OUT / f"{name}.png"
    page.screenshot(path=str(path), full_page=True)
    print(f"saved {path.name} ({path.stat().st_size:,} bytes)")


def open_apply_form(page) -> None:
    """Show apply form even when registration window is closed (demo for meeting)."""
    page.evaluate(
        """() => {
      const gate = document.getElementById('gateView');
      const apply = document.getElementById('applyCard');
      const status = document.getElementById('statusPanel');
      if (gate) gate.classList.add('hidden');
      if (status) status.classList.add('hidden');
      if (apply) apply.classList.remove('hidden');
      document.getElementById('applyForm')?.classList.remove('hidden');
      document.getElementById('stepper')?.classList.remove('hidden');
      document.getElementById('successBox')?.classList.add('hidden');
      document.getElementById('btnExitApply')?.classList.remove('hidden');
      window.scrollTo(0, 0);
    }"""
    )
    page.wait_for_timeout(400)


def open_status(page) -> None:
    page.evaluate(
        """() => {
      const gate = document.getElementById('gateView');
      const apply = document.getElementById('applyCard');
      const status = document.getElementById('statusPanel');
      if (gate) gate.classList.add('hidden');
      if (apply) apply.classList.add('hidden');
      if (status) status.classList.remove('hidden');
      window.scrollTo(0, 0);
    }"""
    )
    page.wait_for_timeout(500)


def show_step(page, n: int) -> None:
    page.evaluate(
        """(n) => {
      document.querySelectorAll('.step').forEach(sec => {
        sec.classList.toggle('hidden', Number(sec.dataset.step) !== n);
      });
      document.querySelectorAll('[data-step-ind]').forEach(el => {
        const i = Number(el.dataset.stepInd);
        el.classList.toggle('on', i === n);
        el.classList.toggle('done', i < n);
      });
      const back = document.getElementById('btnBack');
      const next = document.getElementById('btnNext');
      const submit = document.getElementById('btnSubmit');
      if (back) back.classList.toggle('hidden', n <= 1);
      if (next) next.classList.toggle('hidden', n >= 4);
      if (submit) {
        submit.classList.toggle('hidden', n !== 4);
        submit.disabled = false;
      }
      window.scrollTo(0, 0);
    }""",
        n,
    )
    page.wait_for_timeout(500)


def fill_demo_step1(page) -> None:
    page.evaluate(
        """() => {
      const set = (id, v) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.value = v;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
      };
      const q = document.querySelector('select[name="qualify"], #qualify');
      if (q && q.options.length > 1) {
        q.selectedIndex = 1;
        q.dispatchEvent(new Event('change', { bubbles: true }));
      }
      set('nameTitle', 'นาย');
      set('name', 'ตัวอย่าง ร้านค้าทดสอบ');
      set('phone', '0812345678');
      const power = document.querySelector('input[name="need_high_power"][value="0"]');
      if (power) {
        power.checked = true;
        power.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const ice = document.querySelector('input[name="need_ice"][value="0"]');
      if (ice) {
        ice.checked = true;
        ice.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }"""
    )
    page.wait_for_timeout(400)


def main() -> None:
    with sync_playwright() as p:
        browser = p.chromium.launch(channel="chrome", headless=True)
        context = browser.new_context(
            viewport={"width": 1440, "height": 1100},
            device_scale_factor=2,
            locale="th-TH",
        )
        page = context.new_page()
        page.goto(URL, wait_until="networkidle", timeout=90000)
        page.wait_for_selector("#statusBanner", timeout=60000)
        page.wait_for_timeout(1500)

        shot(page, "01_home_gate")

        open_status(page)
        shot(page, "02_status_check")

        open_apply_form(page)
        show_step(page, 1)
        shot(page, "03_step1_applicant_info_empty")

        fill_demo_step1(page)
        shot(page, "03b_step1_applicant_info_sample")

        show_step(page, 2)
        page.evaluate(
            """() => {
          const z = document.querySelector('input[name="zone"]');
          if (z) {
            z.checked = true;
            z.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }"""
        )
        page.wait_for_timeout(800)
        shot(page, "04_step2_zone_category")

        show_step(page, 3)
        page.wait_for_timeout(700)
        shot(page, "05_step3_upload_docs")

        show_step(page, 4)
        page.wait_for_timeout(700)
        shot(page, "06_step4_review_submit")

        # Mobile
        page.set_viewport_size({"width": 390, "height": 844})
        page.goto(URL, wait_until="networkidle", timeout=90000)
        page.wait_for_selector("#statusBanner", timeout=60000)
        page.wait_for_timeout(1200)
        shot(page, "07_mobile_home")

        open_apply_form(page)
        show_step(page, 1)
        fill_demo_step1(page)
        shot(page, "08_mobile_step1")

        show_step(page, 2)
        page.evaluate(
            """() => {
          const z = document.querySelector('input[name="zone"]');
          if (z) {
            z.checked = true;
            z.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }"""
        )
        page.wait_for_timeout(600)
        shot(page, "09_mobile_step2")

        show_step(page, 3)
        page.wait_for_timeout(500)
        shot(page, "10_mobile_step3")

        browser.close()

    # index for meeting
    files = sorted(OUT.glob("*.png"))
    lines = [
        "# ภาพหน้าจอระบบรับสมัครร้านค้า",
        "",
        f"แหล่ง: `{URL}`",
        "",
        "หมายเหตุ: รอบนี้สถานะจริงคือ **ปิดรับสมัครแล้ว** จึงบังคับเปิดแบบฟอร์มเพื่อถ่ายภาพเสนอที่ประชุม (ไม่ได้ส่งใบสมัครจริง)",
        "",
    ]
    labels = {
        "01_home_gate.png": "หน้าหลัก / สถานะรับสมัคร",
        "02_status_check.png": "ตรวจสอบสถานะใบสมัคร",
        "03_step1_applicant_info_empty.png": "ขั้นตอน 1 · ข้อมูลผู้สมัคร (ว่าง)",
        "03b_step1_applicant_info_sample.png": "ขั้นตอน 1 · ข้อมูลผู้สมัคร (ตัวอย่างกรอก)",
        "04_step2_zone_category.png": "ขั้นตอน 2 · เลือกโซนและประเภท",
        "05_step3_upload_docs.png": "ขั้นตอน 3 · อัปโหลดเอกสาร",
        "06_step4_review_submit.png": "ขั้นตอน 4 · ตรวจสอบก่อนส่ง",
        "07_mobile_home.png": "มือถือ · หน้าหลัก",
        "08_mobile_step1.png": "มือถือ · ขั้นตอน 1",
        "09_mobile_step2.png": "มือถือ · ขั้นตอน 2",
        "10_mobile_step3.png": "มือถือ · ขั้นตอน 3",
    }
    for f in files:
        title = labels.get(f.name, f.stem)
        lines.append(f"## {title}")
        lines.append("")
        lines.append(f"![{title}]({f.name})")
        lines.append("")
    (OUT / "README.md").write_text("\n".join(lines), encoding="utf-8")
    print("DONE ->", OUT)
    for f in files:
        print(" -", f.name)


if __name__ == "__main__":
    main()
