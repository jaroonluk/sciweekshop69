# -*- coding: utf-8 -*-
"""Capture admin UI screens for the Word manual (localhost + bootstrap session)."""
from pathlib import Path
from playwright.sync_api import sync_playwright

OUT = Path(__file__).resolve().parent
OUT.mkdir(exist_ok=True)
BASE = "http://localhost/sci_shop"
TOKEN = "sci-admin-shot-2026"
BOOT = f"{BASE}/_shot_admin_bootstrap.php?t={TOKEN}&goto=index.php"


def shot(page, name: str) -> None:
    path = OUT / f"{name}.png"
    page.screenshot(path=str(path), full_page=True)
    print(f"saved {path.name} ({path.stat().st_size:,} bytes)")


def wait_main(page, timeout=20000):
    page.wait_for_selector("#main .hub-wrap, #main .work-bar, #main h2, #main .hub-card", timeout=timeout)
    page.wait_for_timeout(800)


def goto_view(page, view: str):
    # Prefer hub card click; fall back to JS state
    btn = page.locator(f'[data-goto-view="{view}"]')
    if btn.count() > 0:
        btn.first.click()
        page.wait_for_timeout(900)
        return
    page.evaluate(
        """(view) => {
          if (typeof setView === 'function') { setView(view); return; }
          if (window.state) {
            window.state.view = view;
            if (typeof render === 'function') render();
          }
        }""",
        view,
    )
    page.wait_for_timeout(900)


def close_modals(page):
    page.evaluate(
        """() => {
          document.querySelectorAll('.modal-backdrop.show').forEach(el => el.classList.remove('show'));
          document.querySelectorAll('.lightbox.show').forEach(el => el.classList.remove('show'));
        }"""
    )
    page.wait_for_timeout(200)


def back_home(page):
    close_modals(page)
    btn = page.locator("#btnBackHome")
    if btn.count() and btn.first.is_visible():
        try:
            btn.first.click(timeout=5000, force=True)
            page.wait_for_timeout(700)
            return
        except Exception:
            pass
    page.evaluate(
        """() => {
          if (typeof setView === 'function') setView('home');
          else if (window.state) { window.state.view = 'home'; if (typeof render === 'function') render(); }
        }"""
    )
    page.wait_for_timeout(700)


def click_events_sub(page, sub: str):
    tab = page.locator(f'[data-evt-sub="{sub}"]')
    if tab.count():
        tab.first.click()
        page.wait_for_timeout(600)


def main():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(viewport={"width": 1440, "height": 900})
        page = context.new_page()
        page.goto(BOOT, wait_until="networkidle", timeout=60000)
        wait_main(page)
        shot(page, "01_home_hub")

        # Events / rounds
        goto_view(page, "events")
        page.wait_for_timeout(1000)
        shot(page, "02_events_overview")
        click_events_sub(page, "rounds")
        shot(page, "03_events_rounds")
        click_events_sub(page, "zones")
        shot(page, "04_events_zones")
        click_events_sub(page, "slots")
        shot(page, "05_events_slots")
        click_events_sub(page, "swap")
        shot(page, "06_events_swap")
        click_events_sub(page, "copy")
        shot(page, "07_events_copy")
        back_home(page)
        wait_main(page)

        # Review
        goto_view(page, "review")
        page.wait_for_timeout(1200)
        close_modals(page)
        shot(page, "08_review")
        check_btn = page.locator("[data-check]").first
        if check_btn.count():
            try:
                check_btn.click(timeout=3000)
                page.wait_for_timeout(700)
                if page.locator("#statusModal.show").count() or page.evaluate("() => document.getElementById('statusModal')?.classList.contains('show')"):
                    shot(page, "08b_review_status_modal")
            except Exception as e:
                print("skip status modal:", e)
        close_modals(page)
        back_home(page)

        # Summary
        goto_view(page, "summary")
        page.wait_for_timeout(1000)
        shot(page, "09_summary")
        back_home(page)

        # Report
        goto_view(page, "report")
        page.wait_for_timeout(1000)
        shot(page, "10_report")
        back_home(page)

        # Finance
        goto_view(page, "finance")
        page.wait_for_timeout(1000)
        shot(page, "11_finance")
        back_home(page)

        # Users
        goto_view(page, "users")
        page.wait_for_timeout(1000)
        shot(page, "12_users")
        back_home(page)

        # Audit
        goto_view(page, "audit")
        page.wait_for_timeout(1000)
        shot(page, "13_audit")
        back_home(page)

        # Usage
        goto_view(page, "usage")
        page.wait_for_timeout(1200)
        shot(page, "14_usage")

        # Home announce area (scroll)
        back_home(page)
        wait_main(page)
        announce = page.locator("#btnAnnounceDocx, .hub-announce, .tone-docx")
        if announce.count():
            announce.first.scroll_into_view_if_needed()
            page.wait_for_timeout(400)
            shot(page, "15_announce_home")

        browser.close()
        print("done ->", OUT)


if __name__ == "__main__":
    main()
