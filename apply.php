<?php
/**
 * Public vendor registration portal.
 */
require_once __DIR__ . '/auth_lib.php';
$base = '';
try {
  $base = rtrim(sci_auth_base_url(), '/');
} catch (Throwable $e) {
  $base = '';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>รับสมัครร้านค้า · คณะวิทยาศาสตร์ มข.</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@600;700&family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    :root {
      --ink: #1c160e;
      --muted: #5c5346;
      --line: #e6dcc8;
      --sun: #ffd84d;
      --teal: #0d8a7f;
      --ok: #187a48;
      --danger: #b42318;
      --card: #fffdf8;
      --bg1: #fff3c4;
      --bg2: #ffe8d2;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "Sarabun", sans-serif;
      color: var(--ink);
      background:
        radial-gradient(900px 480px at 0% -10%, #ffe27a 0%, transparent 55%),
        radial-gradient(800px 420px at 100% 0%, #ffb347 0%, transparent 50%),
        linear-gradient(165deg, var(--bg1), #fffaf0 45%, var(--bg2));
      min-height: 100vh;
      line-height: 1.55;
    }
    .wrap { max-width: 820px; margin: 0 auto; padding: 1.25rem 1rem 3rem; }
    header.hero {
      text-align: center;
      padding: 1.1rem 0 1.15rem;
    }
    .hero-logo-wrap {
      display: flex;
      justify-content: center;
      margin: 0 0 .95rem;
    }
    .hero-logo {
      width: min(168px, 42vw);
      height: auto;
      display: block;
      filter: drop-shadow(0 10px 18px rgba(80, 50, 0, .14));
    }
    header.hero .brand {
      font-family: "Chakra Petch", sans-serif;
      font-size: clamp(1.55rem, 4.5vw, 2.15rem);
      font-weight: 700;
      margin: 0;
      letter-spacing: .01em;
      line-height: 1.25;
    }
    header.hero .sub {
      margin: .45rem 0 0;
      font-size: 1.05rem;
      color: var(--muted);
      line-height: 1.45;
    }
    .banner {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 16px;
      padding: 1rem 1.15rem;
      margin-bottom: 1rem;
      font-size: 1.05rem;
      display: flex;
      gap: .75rem;
      align-items: flex-start;
    }
    .banner.closed { border-color: #f0c2c2; background: #fff5f5; color: #7a1f1a; }
    .banner.open { border-color: #b7e0c8; background: #f3fbf6; }
    .banner .ban-ico {
      width: 2.4rem;
      height: 2.4rem;
      border-radius: 12px;
      display: grid;
      place-items: center;
      flex: 0 0 auto;
      background: #f3eee3;
      color: var(--muted);
    }
    .banner.open .ban-ico { background: #e5f6ec; color: var(--ok); }
    .banner.closed .ban-ico { background: #ffe8e6; color: var(--danger); }
    .banner .ban-ico svg { width: 1.25rem; height: 1.25rem; }
    .banner .ban-body { flex: 1; min-width: 0; }
    .card {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 18px;
      padding: 1.25rem 1.2rem 1.5rem;
      box-shadow: 0 10px 30px rgba(40,28,8,.06);
    }
    .steps {
      display: flex;
      gap: .4rem;
      flex-wrap: wrap;
      margin-bottom: 1.1rem;
    }
    .steps span {
      flex: 1;
      min-width: 5.5rem;
      text-align: center;
      padding: .55rem .4rem;
      border-radius: 999px;
      background: #f3eee3;
      color: var(--muted);
      font-weight: 700;
      font-size: .95rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .35rem;
    }
    .steps span svg {
      width: 1rem;
      height: 1rem;
      flex: 0 0 auto;
    }
    .steps span.on { background: var(--ink); color: var(--sun); }
    .steps span.done { background: #d8f0e4; color: var(--ok); }
    h2 {
      font-family: "Chakra Petch", sans-serif;
      font-size: 1.45rem;
      margin: 0 0 .35rem;
    }
    h2.section-title, h2.panel-title { margin: 0 0 .35rem; }
    .hint { color: var(--muted); margin: 0 0 1rem; font-size: 1.05rem; }
    label.field {
      display: block;
      margin-bottom: 1rem;
    }
    label.field > span {
      display: block;
      font-weight: 700;
      font-size: 1.12rem;
      margin-bottom: .4rem;
    }
    label.field .req { color: var(--danger); }
    input[type=text], input[type=tel], select, textarea {
      width: 100%;
      font: inherit;
      font-size: 1.15rem;
      padding: .85rem 1rem;
      border: 2px solid var(--line);
      border-radius: 12px;
      background: #fff;
      color: var(--ink);
    }
    textarea { min-height: 6rem; resize: vertical; }
    input:focus, select:focus, textarea:focus {
      outline: 3px solid #c9e8e3;
      border-color: var(--teal);
    }
    .zone-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: .65rem;
    }
    .zone-grid label {
      display: flex;
      align-items: center;
      gap: .55rem;
      padding: .9rem .85rem;
      border: 2px solid var(--line);
      border-radius: 12px;
      background: #fff;
      cursor: pointer;
      font-size: 1.1rem;
      font-weight: 700;
    }
    .zone-grid input { width: auto; transform: scale(1.25); }
    .zone-grid label:has(input:checked) {
      border-color: var(--teal);
      background: #eef8f6;
    }
    .upload-box {
      border: 2px dashed #d2c6ae;
      border-radius: 14px;
      padding: 1rem;
      background: #fff;
      margin-bottom: 1rem;
    }
    .upload-box strong { display: block; font-size: 1.12rem; margin-bottom: .25rem; }
    .upload-box p { margin: 0 0 .65rem; color: var(--muted); }
    .upload-box input[type=file] { font-size: 1rem; width: 100%; }
    .preview {
      display: flex;
      flex-wrap: wrap;
      gap: .5rem;
      margin-top: .65rem;
    }
    .preview img, .preview .filechip {
      width: 88px;
      height: 88px;
      object-fit: cover;
      border-radius: 10px;
      border: 1px solid var(--line);
      background: #f7f3ea;
    }
    .preview .filechip {
      display: grid;
      place-items: center;
      font-size: .8rem;
      text-align: center;
      padding: .35rem;
      font-weight: 700;
    }
    .nav {
      display: flex;
      flex-wrap: wrap;
      gap: .65rem;
      margin-top: 1.25rem;
    }
    .btn {
      font: inherit;
      font-size: 1.15rem;
      font-weight: 700;
      border: 0;
      border-radius: 999px;
      padding: .85rem 1.4rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .45rem;
    }
    .btn svg { width: 1.15rem; height: 1.15rem; flex: 0 0 auto; }
    .btn.primary { background: var(--ink); color: var(--sun); }
    .btn.secondary { background: #fff; border: 2px solid var(--line); color: var(--ink); }
    .btn.teal { background: var(--teal); color: #fff; }
    .btn:disabled { opacity: .55; cursor: not-allowed; }
    .err {
      background: #fff1f0;
      border: 1px solid #f0c2c2;
      color: #7a1f1a;
      border-radius: 12px;
      padding: .85rem 1rem;
      margin-bottom: 1rem;
      font-size: 1.05rem;
    }
    .success {
      text-align: center;
      padding: 1rem 0;
    }
    .success .ref {
      font-family: "Chakra Petch", sans-serif;
      font-size: 2rem;
      font-weight: 700;
      color: var(--ok);
      margin: .5rem 0;
    }
    .hidden { display: none !important; }
    .gate-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: .9rem;
      margin-top: .25rem;
    }
    .gate-card {
      display: flex;
      flex-direction: column;
      gap: .45rem;
      text-align: left;
      padding: 1.15rem 1.2rem;
      border-radius: 16px;
      border: 1px solid var(--line);
      background: #fffdf8;
      cursor: pointer;
      font: inherit;
      color: inherit;
      box-shadow: 0 8px 20px rgba(80,50,0,.06);
      transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
      min-height: 8.5rem;
    }
    .gate-card:hover {
      transform: translateY(-2px);
      border-color: rgba(13,138,127,.4);
      box-shadow: 0 12px 26px rgba(13,138,127,.12);
    }
    .gate-card.apply { border-left: 5px solid var(--teal); }
    .gate-card.status { border-left: 5px solid #c45c12; }
    .gate-card .gate-top {
      display: flex;
      align-items: flex-start;
      gap: .75rem;
    }
    .gate-ico {
      width: 3rem;
      height: 3rem;
      border-radius: 14px;
      display: grid;
      place-items: center;
      flex: 0 0 auto;
      color: #fff;
      box-shadow: 0 8px 16px rgba(0,0,0,.12);
    }
    .gate-card.apply .gate-ico { background: linear-gradient(145deg, #14a896, #0d8a7f); }
    .gate-card.status .gate-ico { background: linear-gradient(145deg, #e07a2f, #c45c12); }
    .gate-ico svg { width: 1.45rem; height: 1.45rem; }
    .section-title, .panel-title {
      display: flex;
      align-items: center;
      gap: .55rem;
      margin: 0 0 .35rem;
      font-family: "Chakra Petch", sans-serif;
      font-size: 1.45rem;
    }
    .panel-title { margin: 0; }
    .sec-ico {
      width: 2.15rem;
      height: 2.15rem;
      border-radius: 10px;
      display: grid;
      place-items: center;
      background: #fff1d0;
      color: #9a5b00;
      flex: 0 0 auto;
    }
    .sec-ico.teal { background: #e7f7f4; color: var(--teal); }
    .sec-ico.ok { background: #e5f6ec; color: var(--ok); }
    .sec-ico.warn { background: #fff1e4; color: #c45c12; }
    .sec-ico svg { width: 1.15rem; height: 1.15rem; }
    .success .ok-ico {
      width: 4.2rem;
      height: 4.2rem;
      margin: 0 auto .75rem;
      border-radius: 50%;
      display: grid;
      place-items: center;
      background: linear-gradient(145deg, #2fad6a, #187a48);
      color: #fff;
      box-shadow: 0 12px 24px rgba(24,122,72,.28);
    }
    .success .ok-ico svg { width: 2.1rem; height: 2.1rem; }
    .gate-card .tag {
      align-self: flex-start;
      font-size: .72rem;
      font-weight: 700;
      padding: .15rem .5rem;
      border-radius: 999px;
      background: #eef8f6;
      color: var(--teal);
    }
    .gate-card.status .tag { background: #fff1e4; color: #c45c12; }
    .gate-card h3 {
      margin: 0;
      font-family: "Chakra Petch", sans-serif;
      font-size: 1.2rem;
    }
    .gate-card p {
      margin: 0;
      color: var(--muted);
      font-size: .95rem;
      line-height: 1.45;
      flex: 1;
    }
    .gate-card .go {
      font-family: "Chakra Petch", sans-serif;
      font-weight: 700;
      color: var(--teal);
    }
    .gate-card.status .go { color: #c45c12; }
    .status-panel {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 18px;
      padding: 1.25rem 1.2rem 1.4rem;
      box-shadow: 0 10px 30px rgba(40,28,8,.06);
    }
    .status-panel .panel-head {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      justify-content: space-between;
      gap: .75rem;
      margin-bottom: .85rem;
    }
    .status-panel .panel-head h2 { margin: 0; }
    .status-result-card {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: .9rem 1rem;
      margin-bottom: .55rem;
    }
    .status-result-card .ref {
      font-family: "Chakra Petch", sans-serif;
      font-weight: 700;
      font-size: 1.15rem;
      color: var(--teal);
    }
    .status-result-card .meta {
      color: var(--muted);
      font-size: .95rem;
      margin-top: .25rem;
    }
    .status-result-card .chips {
      display: flex;
      flex-wrap: wrap;
      gap: .35rem;
      margin-top: .55rem;
    }
    .status-chip {
      display: inline-block;
      padding: .2rem .55rem;
      border-radius: 999px;
      font-size: .82rem;
      font-weight: 700;
      background: #f3eee3;
      color: var(--ink);
    }
    .status-chip.ok { background: #e5f6ec; color: var(--ok); }
    .status-chip.warn { background: #fff4e0; color: #9a5b00; }
    .status-chip.danger { background: #ffe8e6; color: var(--danger); }
    .review-card {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 16px;
      overflow: hidden;
      margin: 0 0 1rem;
      box-shadow: 0 6px 18px rgba(80,50,0,.05);
    }
    .review-card .review-head {
      padding: .85rem 1.1rem;
      background: linear-gradient(120deg, #fff6df, #eef8f6);
      border-bottom: 1px solid var(--line);
      font-family: "Chakra Petch", sans-serif;
      font-weight: 700;
      font-size: 1.1rem;
    }
    .review-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0;
    }
    .review-item {
      padding: .85rem 1.1rem;
      border-bottom: 1px solid #f0e8d8;
      border-right: 1px solid #f0e8d8;
      min-width: 0;
    }
    .review-item:nth-child(2n) { border-right: 0; }
    .review-item.full {
      grid-column: 1 / -1;
      border-right: 0;
    }
    .review-item .k {
      display: block;
      font-size: .82rem;
      font-weight: 700;
      color: var(--muted);
      margin-bottom: .2rem;
    }
    .review-item .v {
      font-size: 1.08rem;
      font-weight: 600;
      color: var(--ink);
      line-height: 1.4;
      word-break: break-word;
    }
    .review-docs {
      display: flex;
      flex-wrap: wrap;
      gap: .4rem;
      margin-top: .2rem;
    }
    .review-docs span {
      display: inline-flex;
      align-items: center;
      gap: .25rem;
      padding: .28rem .6rem;
      border-radius: 999px;
      background: #eef8f6;
      color: var(--teal);
      font-size: .88rem;
      font-weight: 700;
    }
    .phone-hint {
      margin: .35rem 0 0;
      font-size: .92rem;
      color: var(--muted);
    }
    .phone-hint.bad { color: var(--danger); font-weight: 700; }
    .phone-hint.ok { color: var(--ok); font-weight: 600; }
    @media (max-width: 560px) {
      .review-grid { grid-template-columns: 1fr; }
      .review-item { border-right: 0; }
    }
    .modal-backdrop {
      position: fixed;
      inset: 0;
      z-index: 80;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      background: rgba(28, 22, 14, .48);
      backdrop-filter: blur(4px);
    }
    .modal-backdrop.show { display: flex; }
    .warn-modal {
      width: min(420px, 100%);
      background: linear-gradient(165deg, #fffdf8, #fff6e8);
      border: 1px solid #efd7a8;
      border-radius: 22px;
      padding: 1.35rem 1.3rem 1.2rem;
      box-shadow: 0 22px 48px rgba(80, 40, 0, .22);
      text-align: center;
      animation: warnPop .22s ease-out;
    }
    @keyframes warnPop {
      from { transform: translateY(10px) scale(.96); opacity: 0; }
      to { transform: none; opacity: 1; }
    }
    .warn-modal .ico {
      width: 4.2rem;
      height: 4.2rem;
      margin: 0 auto .85rem;
      border-radius: 50%;
      display: grid;
      place-items: center;
      background: linear-gradient(145deg, #ffb347, #ff7a45);
      color: #fff;
      box-shadow: 0 10px 20px rgba(255, 122, 69, .35);
    }
    .warn-modal .ico svg { width: 2.1rem; height: 2.1rem; }
    .warn-modal h3 {
      margin: 0 0 .4rem;
      font-family: "Chakra Petch", sans-serif;
      font-size: 1.4rem;
      color: #8a3410;
    }
    .warn-modal p {
      margin: 0;
      color: var(--muted);
      font-size: 1.05rem;
      line-height: 1.5;
    }
    .warn-modal .modal-actions {
      display: flex;
      flex-wrap: wrap;
      gap: .55rem;
      justify-content: center;
      margin-top: 1.15rem;
    }
    .warn-modal .modal-actions .btn { min-width: 8.5rem; }
    .name-row {
      display: grid;
      grid-template-columns: minmax(7.5rem, 9.5rem) 1fr;
      gap: .65rem;
      align-items: end;
    }
    .name-row label.field { margin-bottom: 0; }
    .hp-field {
      position: absolute;
      left: -10000px;
      top: auto;
      width: 1px;
      height: 1px;
      overflow: hidden;
      opacity: 0;
    }
    .captcha-box {
      margin-top: 1rem;
      padding: 1rem 1.05rem;
      border-radius: 14px;
      border: 1px dashed #d2c4a4;
      background: #fffaf0;
    }
    .captcha-box .q {
      font-family: "Chakra Petch", sans-serif;
      font-weight: 700;
      font-size: 1.2rem;
      margin: 0 0 .55rem;
    }
    .captcha-row {
      display: flex;
      flex-wrap: wrap;
      gap: .55rem;
      align-items: center;
    }
    .captcha-row input[type=text] {
      width: 7.5rem;
      text-align: center;
      font-weight: 700;
      letter-spacing: .04em;
    }
    header.hero .org {
      margin: 0 0 .35rem;
      color: var(--teal);
      font-weight: 700;
      font-size: 1.05rem;
    }
    @media (max-width: 560px) {
      .btn { width: 100%; text-align: center; }
      .name-row { grid-template-columns: 1fr; }
      .hero-logo { width: min(132px, 46vw); }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <header class="hero">
      <div class="hero-logo-wrap">
        <img class="hero-logo" src="kkupng.png" alt="ตรามหาวิทยาลัยขอนแก่น" width="168" height="168" decoding="async" />
      </div>
      <p class="org" id="brandOrg">คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น</p>
      <h1 class="brand" id="brandHeadline">รับสมัครร้านค้า</h1>
      <p class="sub" id="eventTitle">กำลังโหลดแบบฟอร์มสมัคร...</p>
    </header>

    <div id="statusBanner" class="banner">
      <span class="ban-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
      <div class="ban-body">กำลังตรวจสอบสถานะการรับสมัคร...</div>
    </div>

    <div class="gate-grid" id="gateView">
      <button type="button" class="gate-card apply" id="btnStartApply">
        <div class="gate-top">
          <span class="gate-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/></svg></span>
          <div>
            <span class="tag">สมัครใหม่</span>
            <h3 style="margin-top:.35rem">กรอกใบสมัครร้านค้า</h3>
          </div>
        </div>
        <p>เริ่มกรอกข้อมูล เลือกโซน อัปโหลดเอกสาร และส่งใบสมัคร</p>
        <span class="go">เริ่มสมัคร →</span>
      </button>
      <button type="button" class="gate-card status" id="btnOpenStatus">
        <div class="gate-top">
          <span class="gate-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span>
          <div>
            <span class="tag">ติดตามผล</span>
            <h3 style="margin-top:.35rem">ตรวจสอบสถานะใบสมัคร</h3>
          </div>
        </div>
        <p>ค้นหาด้วยเบอร์โทรที่ใช้สมัคร เพื่อดูผลตรวจเอกสารและการคัดเลือก</p>
        <span class="go">ตรวจสถานะ →</span>
      </button>
    </div>

    <div class="status-panel hidden" id="statusPanel">
      <div class="panel-head">
        <div>
          <h2 class="panel-title"><span class="sec-ico warn" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span>ตรวจสอบสถานะใบสมัคร</h2>
          <p class="hint" style="margin:.35rem 0 0">กรอกเบอร์โทรที่ใช้สมัคร · ชื่อใช้ช่วยกรองได้ถ้าต้องการ</p>
        </div>
        <button type="button" class="btn secondary" id="btnBackGate" style="padding:.5rem .9rem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7"/><path d="M5 10v10h14V10"/></svg> กลับหน้าหลัก</button>
      </div>
      <label class="field">
        <span>เบอร์โทรที่ใช้สมัคร <span class="req">*</span></span>
        <input type="tel" id="statusPhone" placeholder="08x-xxx-xxxx" autocomplete="tel" />
      </label>
      <label class="field">
        <span>ชื่อ–นามสกุล (ไม่บังคับ)</span>
        <input type="text" id="statusName" placeholder="เช่น สมชาย ใจดี" />
      </label>
      <div class="captcha-box">
        <p class="q" id="statusCaptchaQuestion">กำลังโหลดรหัสป้องกันสแปม...</p>
        <div class="captcha-row">
          <input type="hidden" id="statusCaptchaToken" value="" />
          <input type="text" id="statusCaptchaAnswer" inputmode="numeric" autocomplete="off" placeholder="คำตอบ" />
          <button type="button" class="btn secondary" id="btnRefreshStatusCaptcha" style="padding:.55rem .9rem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 3v6h-6"/></svg> เปลี่ยนข้อ</button>
        </div>
        <p class="hint" style="margin:.55rem 0 0">ตอบคำถามเพื่อยืนยันก่อนค้นหาสถานะ</p>
      </div>
      <div class="nav" style="margin-top:.85rem">
        <button type="button" class="btn teal" id="btnStatus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg> ค้นหาสถานะ</button>
      </div>
      <div id="statusResult" style="margin-top:1rem"></div>
    </div>

    <div class="card hidden" id="applyCard">
      <div class="nav" style="margin:0 0 .85rem;justify-content:flex-start">
        <button type="button" class="btn secondary" id="btnExitApply" style="padding:.45rem .85rem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg> ออกจากแบบฟอร์ม</button>
      </div>
      <div class="steps" id="stepper">
        <span data-step-ind="1" class="on"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> 1. ข้อมูล</span>
        <span data-step-ind="2"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l-6 3V6l6-3 6 3 6-3v15l-6 3-6-3z"/><path d="M9 3v15M15 6v15"/></svg> 2. โซน</span>
        <span data-step-ind="3"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg> 3. เอกสาร</span>
        <span data-step-ind="4"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> 4. ยืนยัน</span>
      </div>
      <div id="formError" class="err hidden"></div>

      <form id="applyForm" novalidate>
        <section class="step" data-step="1">
          <h2 class="section-title"><span class="sec-ico teal" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>ข้อมูลผู้สมัคร</h2>
          <p class="hint">กรอกทีละช่องให้ครบ · ใช้อักษรใหญ่ อ่านง่าย</p>
          <label class="field">
            <span>รอบที่เปิดรับ <span class="req">*</span></span>
            <select id="roundId" name="round_id" required></select>
          </label>
          <label class="field">
            <span>คุณสมบัติของท่าน <span class="req">*</span></span>
            <select id="qualify" name="qualifications" required></select>
          </label>
          <label class="field hidden" id="qualifyOtherWrap">
            <span>ระบุคุณสมบัติอื่นๆ <span class="req">*</span></span>
            <input type="text" id="qualifyOther" name="qualifications_other" placeholder="เช่น..." />
          </label>
          <div class="field">
            <span style="display:block;font-weight:700;font-size:1.12rem;margin-bottom:.4rem">ชื่อ–นามสกุล ผู้สมัคร <span class="req">*</span></span>
            <div class="name-row">
              <label class="field" style="margin:0">
                <span style="font-size:1rem">คำนำหน้า <span class="req">*</span></span>
                <select id="nameTitle" name="name_title" required>
                  <option value="">— เลือก —</option>
                  <option value="นาย">นาย</option>
                  <option value="นาง">นาง</option>
                  <option value="นางสาว">นางสาว</option>
                </select>
              </label>
              <label class="field" style="margin:0">
                <span style="font-size:1rem">ชื่อ–นามสกุล <span class="req">*</span></span>
                <input type="text" id="name" name="name" autocomplete="name" required placeholder="เช่น สมชาย ใจดี" />
              </label>
            </div>
          </div>
          <label class="field">
            <span>เบอร์ติดต่อ <span class="req">*</span></span>
            <input type="tel" id="phone" name="phone" autocomplete="tel" required placeholder="08x-xxx-xxxx" />
            <p class="phone-hint" id="phoneHint">เบอร์นี้ใช้สมัครได้ 1 ครั้งต่อรอบเท่านั้น</p>
          </label>
          <label class="field">
            <span>รายละเอียดเพิ่มเติม / จุดเด่นร้าน</span>
            <textarea id="detail" name="detail" placeholder="เช่น เมนูเด่น วัตถุดิบ ความพิเศษของร้าน"></textarea>
          </label>
          <!-- honeypot: leave empty -->
          <div class="hp-field" aria-hidden="true">
            <label>เว็บไซต์บริษัท
              <input type="text" name="company_url" id="companyUrl" tabindex="-1" autocomplete="off" />
            </label>
          </div>
        </section>

        <section class="step hidden" data-step="2">
          <h2 class="section-title"><span class="sec-ico warn" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l-6 3V6l6-3 6 3 6-3v15l-6 3-6-3z"/><path d="M9 3v15M15 6v15"/></svg></span>เลือกโซนและประเภทร้าน</h2>
          <p class="hint">เลือกโซนก่อน แล้วระบบจะแสดงประเภทร้านในโซนนั้น</p>
          <div class="field">
            <span style="display:block;font-weight:700;font-size:1.12rem;margin-bottom:.5rem">โซนร้านค้า <span class="req">*</span></span>
            <div class="zone-grid" id="zoneGrid"></div>
          </div>
          <label class="field">
            <span>ประเภทร้านค้า <span class="req">*</span></span>
            <select id="category" name="category" required>
              <option value="">— เลือกโซนก่อน —</option>
            </select>
          </label>
        </section>

        <section class="step hidden" data-step="3">
          <h2 class="section-title"><span class="sec-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></span>อัปโหลดเอกสาร</h2>
          <p class="hint">รองรับเฉพาะไฟล์ภาพ JPG / PNG / WEBP · ไฟล์ละไม่เกิน <b id="maxMb">10</b> MB · เก็บบน MinIO</p>
          <div class="upload-box">
            <strong>สำเนาบัตรประชาชน <span class="req">*</span></strong>
            <p>ถ่ายให้ชัด อ่านตัวอักษรได้</p>
            <input type="file" id="idCard" name="id_card" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required />
            <div class="preview" id="prevId"></div>
          </div>
          <div class="upload-box">
            <strong>สำเนาทะเบียนบ้าน <span class="req">*</span></strong>
            <p>หน้าที่มีชื่อผู้สมัคร</p>
            <input type="file" id="houseReg" name="house_reg" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required />
            <div class="preview" id="prevHouse"></div>
          </div>
          <div class="upload-box">
            <strong>รูปถ่ายหน้าตรง <span class="req">*</span></strong>
            <p>เห็นใบหน้าชัดเจน</p>
            <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required />
            <div class="preview" id="prevPhoto"></div>
          </div>
          <div class="upload-box">
            <strong>ภาพอาหาร / สินค้า <span class="req">*</span></strong>
            <p>เลือกได้หลายรูป (สูงสุด <span id="foodMax">5</span> รูป)</p>
            <input type="file" id="food" name="food[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple required />
            <div class="preview" id="prevFood"></div>
          </div>
        </section>

        <section class="step hidden" data-step="4">
          <h2 class="section-title"><span class="sec-ico ok" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>ตรวจสอบก่อนส่ง</h2>
          <p class="hint">ตรวจสอบรายละเอียดด้านล่างให้ครบ แล้วตอบคำถามป้องกันสแปมก่อนส่ง</p>
          <div id="reviewBox" class="review-card"></div>
          <div class="captcha-box">
            <p class="q" id="captchaQuestion">กำลังโหลดรหัสป้องกันสแปม...</p>
            <div class="captcha-row">
              <input type="hidden" id="captchaToken" name="captcha_token" value="" />
              <input type="text" id="captchaAnswer" name="captcha_answer" inputmode="numeric" autocomplete="off" required placeholder="คำตอบ" />
              <button type="button" class="btn secondary" id="btnRefreshCaptcha" style="padding:.55rem .9rem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 3v6h-6"/></svg> เปลี่ยนข้อ</button>
            </div>
            <p class="hint" style="margin:.55rem 0 0">กรุณาตอบคำถามด้านบนเพื่อยืนยันว่าไม่ใช่โปรแกรมอัตโนมัติ</p>
          </div>
        </section>

        <div class="nav">
          <button type="button" class="btn secondary hidden" id="btnBack"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg> ย้อนกลับ</button>
          <button type="button" class="btn primary" id="btnNext">ถัดไป <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>
          <button type="submit" class="btn teal hidden" id="btnSubmit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4 20-7z"/></svg> ส่งใบสมัคร</button>
        </div>
      </form>

      <div id="successBox" class="success hidden">
        <div class="ok-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></div>
        <h2>ส่งใบสมัครเรียบร้อย</h2>
        <p>รหัสอ้างอิงของท่าน</p>
        <div class="ref" id="successRef">—</div>
        <p id="successDetail"></p>
        <p class="hint">เจ้าหน้าที่จะตรวจสอบเอกสารและแจ้งผลการคัดเลือกต่อไป</p>
        <div class="nav" style="justify-content:center">
          <button type="button" class="btn teal" id="btnGotoStatus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg> ตรวจสอบสถานะใบสมัคร</button>
          <button type="button" class="btn secondary" id="btnAgain"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/></svg> สมัครรายการใหม่</button>
          <button type="button" class="btn secondary" id="btnHomeGate"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7"/><path d="M5 10v10h14V10"/></svg> กลับหน้าหลัก</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal-backdrop" id="exitModal" role="dialog" aria-modal="true" aria-labelledby="exitModalTitle">
    <div class="warn-modal">
      <div class="ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          <path d="M12 9v4"/><path d="M12 17h.01"/>
        </svg>
      </div>
      <h3 id="exitModalTitle">ออกจากแบบฟอร์ม?</h3>
      <p>ข้อมูลที่กรอกยัง<strong>ไม่ถูกส่ง</strong> และจะหายไปหากออกตอนนี้<br>ต้องการกลับหน้าหลักหรือไม่?</p>
      <div class="modal-actions">
        <button type="button" class="btn secondary" id="btnExitCancel">อยู่ต่อ</button>
        <button type="button" class="btn" id="btnExitConfirm" style="background:#c45c12;color:#fff;border-color:#c45c12">ออกจากแบบฟอร์ม</button>
      </div>
    </div>
  </div>

  <script>
    const state = {
      step: 1,
      meta: null,
      maxStep: 4,
      mode: "gate", // gate | apply | status | success
      accepting: false,
    };

    const $ = (id) => document.getElementById(id);

    function setMode(mode) {
      state.mode = mode;
      const gate = $("gateView");
      const apply = $("applyCard");
      const status = $("statusPanel");
      if (gate) gate.classList.toggle("hidden", mode !== "gate");
      if (apply) apply.classList.toggle("hidden", mode !== "apply" && mode !== "success");
      if (status) status.classList.toggle("hidden", mode !== "status");

      if (mode === "apply") {
        $("applyForm")?.classList.remove("hidden");
        $("stepper")?.classList.remove("hidden");
        $("successBox")?.classList.add("hidden");
        $("btnExitApply")?.classList.remove("hidden");
        state.step = 1;
        paintStep();
      }
      if (mode === "success") {
        $("applyForm")?.classList.add("hidden");
        $("stepper")?.classList.add("hidden");
        $("btnExitApply")?.classList.add("hidden");
        $("successBox")?.classList.remove("hidden");
      }
      if (mode === "status") {
        $("statusResult").innerHTML = "";
        refreshStatusCaptcha();
        $("statusPhone")?.focus();
      }
      if (mode === "gate") {
        updateGateCards();
      }
      window.scrollTo({ top: 0, behavior: "smooth" });
    }

    function updateGateCards() {
      const start = $("btnStartApply");
      if (!start) return;
      if (state.accepting) {
        start.classList.remove("hidden");
        start.disabled = false;
      } else {
        start.classList.add("hidden");
      }
    }

    function showErr(msg) {
      const el = $("formError");
      if (!msg) {
        el.classList.add("hidden");
        el.textContent = "";
        return;
      }
      el.textContent = msg;
      el.classList.remove("hidden");
      el.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }

    function fmtWhen(v) {
      if (!v) return "—";
      const raw = String(v).trim().replace(" ", "T");
      const d = new Date(raw.length === 16 ? raw + ":00" : raw);
      if (Number.isNaN(d.getTime())) {
        return String(v).replace("T", " ").slice(0, 16);
      }
      const months = [
        "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
        "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม",
      ];
      const day = d.getDate();
      const month = months[d.getMonth()];
      const yearBe = d.getFullYear() + 543;
      const hh = String(d.getHours()).padStart(2, "0");
      const mm = String(d.getMinutes()).padStart(2, "0");
      return `${day} ${month} ${yearBe} เวลา ${hh}:${mm} น.`;
    }

    async function loadMeta() {
      const res = await fetch("apply_api.php?action=meta&_=" + Date.now(), { credentials: "same-origin" });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || "โหลดแบบฟอร์มไม่สำเร็จ");
      state.meta = json;
      const brand = json.branding || {};
      if ($("brandOrg")) $("brandOrg").textContent = brand.org || "คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น";
      if ($("brandHeadline")) $("brandHeadline").textContent = brand.headline || "รับสมัครร้านค้า · คณะวิทยาศาสตร์ มข.";
      $("eventTitle").textContent = brand.subline || json.event?.title || "รับสมัครร้านค้า";
      document.title = brand.page_title || ("รับสมัครร้านค้า · " + (json.event?.title || "คณะวิทยาศาสตร์ มข."));
      $("maxMb").textContent = json.upload?.max_mb || 10;
      $("foodMax").textContent = json.upload?.food_max || 5;
      paintCaptcha(json.captcha);
      paintStatusCaptcha(json.status_captcha);

      const titles = json.name_titles || ["นาย", "นาง", "นางสาว"];
      const titleSel = $("nameTitle");
      if (titleSel && titles.length) {
        const cur = titleSel.value;
        titleSel.innerHTML = `<option value="">— เลือก —</option>` + titles.map(t =>
          `<option value="${esc(t)}" ${cur === t ? "selected" : ""}>${esc(t)}</option>`
        ).join("");
      }

      const banner = $("statusBanner");
      state.accepting = !!json.accepting;
      if (json.accepting) {
        const openRounds = (json.rounds || []).filter(r => r.accepting);
        const r = openRounds[0];
        banner.className = "banner open";
        banner.innerHTML = `<span class="ban-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg></span>`
          + `<div class="ban-body"><b>เปิดรับสมัครอยู่</b> · ${esc(r?.title || "")}`
          + (r?.apply_close_at ? ` · ปิดรับ ${esc(fmtWhen(r.apply_close_at))}` : "")
          + `<br><span style="color:#5c5346">${esc(brand.org || "คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น")}</span></div>`;
      } else {
        banner.className = "banner closed";
        const reasons = (json.rounds || []).map(r => r.status_reason).filter(Boolean);
        banner.innerHTML = `<span class="ban-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg></span>`
          + `<div class="ban-body"><b>ยังไม่เปิดรับสมัคร หรือปิดรับแล้ว</b><br>${esc(reasons[0] || "กรุณาติดตามประกาศอีกครั้ง")}`
          + `<br><span style="color:#5c5346">ท่านยังสามารถตรวจสอบสถานะใบสมัครเดิมได้</span></div>`;
      }

      const roundSel = $("roundId");
      roundSel.innerHTML = (json.rounds || [])
        .filter(r => r.accepting)
        .map(r => `<option value="${r.id}">${esc(r.title)}${r.apply_close_at ? " (ปิด " + esc(fmtWhen(r.apply_close_at)) + ")" : ""}</option>`)
        .join("") || `<option value="">— ไม่มีรอบเปิดรับ —</option>`;

      $("qualify").innerHTML = (json.qualify_options || [])
        .map(q => `<option value="${esc(q)}">${esc(q)}</option>`).join("");

      $("zoneGrid").innerHTML = (json.zones || []).map(z => {
        const codeLabel = "โซน " + z.code;
        const nameTh = String(z.name_th || "").trim();
        const sub = (nameTh && nameTh !== codeLabel && nameTh !== z.code)
          ? `<br><small style="font-weight:500;color:#5c5346">${esc(nameTh)}</small>`
          : "";
        return `<label><input type="radio" name="zone" value="${esc(z.code)}" /> ${esc(codeLabel)}${sub}</label>`;
      }).join("");

      document.querySelectorAll('input[name="zone"]').forEach(r => {
        r.onchange = () => fillCategories(r.value);
      });

      setMode("gate");
    }

    function paintCaptcha(captcha) {
      if (!captcha) return;
      if ($("captchaQuestion")) $("captchaQuestion").textContent = "คำถามป้องกันสแปม: " + (captcha.question || "");
      if ($("captchaToken")) $("captchaToken").value = captcha.token || "";
      if ($("captchaAnswer")) $("captchaAnswer").value = "";
    }

    function paintStatusCaptcha(captcha) {
      if (!captcha) return;
      if ($("statusCaptchaQuestion")) $("statusCaptchaQuestion").textContent = "คำถามป้องกันสแปม: " + (captcha.question || "");
      if ($("statusCaptchaToken")) $("statusCaptchaToken").value = captcha.token || "";
      if ($("statusCaptchaAnswer")) $("statusCaptchaAnswer").value = "";
    }

    async function refreshCaptcha() {
      try {
        const res = await fetch("apply_api.php?action=captcha&purpose=apply&_=" + Date.now(), { credentials: "same-origin" });
        const json = await res.json();
        if (!json.ok) throw new Error(json.error || "โหลดรหัสไม่สำเร็จ");
        if (json.captcha) {
          state.meta = { ...(state.meta || {}), captcha: json.captcha };
          paintCaptcha(json.captcha);
        }
      } catch (e) {
        showErr(e.message || String(e));
      }
    }

    async function refreshStatusCaptcha() {
      try {
        const res = await fetch("apply_api.php?action=captcha&purpose=status&_=" + Date.now(), { credentials: "same-origin" });
        const json = await res.json();
        if (!json.ok) throw new Error(json.error || "โหลดรหัสไม่สำเร็จ");
        if (json.captcha) {
          state.meta = { ...(state.meta || {}), status_captcha: json.captcha };
          paintStatusCaptcha(json.captcha);
        }
      } catch (e) {
        const box = $("statusResult");
        if (box) box.innerHTML = `<div class="err" style="margin:0">${esc(e.message || e)}</div>`;
      }
    }

    function openExitModal() {
      $("exitModal")?.classList.add("show");
    }
    function closeExitModal() {
      $("exitModal")?.classList.remove("show");
    }

    function fullApplicantName() {
      const title = ($("nameTitle")?.value || "").trim();
      const name = ($("name")?.value || "").trim().replace(/\s+/g, " ");
      return title && name ? (title + " " + name) : name;
    }

    function fillCategories(zone) {
      const cats = state.meta?.categories_by_zone?.[zone] || [];
      $("category").innerHTML = cats.length
        ? `<option value="">— เลือกประเภท —</option>` + cats.map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join("")
        : `<option value="">— ไม่พบประเภทในโซนนี้ —</option>`;
    }

    function esc(s) {
      return String(s ?? "").replace(/[&<>"']/g, c => ({
        "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"
      }[c]));
    }

    function paintStep() {
      document.querySelectorAll(".step").forEach(sec => {
        sec.classList.toggle("hidden", Number(sec.dataset.step) !== state.step);
      });
      document.querySelectorAll("[data-step-ind]").forEach(el => {
        const n = Number(el.dataset.stepInd);
        el.classList.toggle("on", n === state.step);
        el.classList.toggle("done", n < state.step);
      });
      $("btnBack").classList.toggle("hidden", state.step <= 1);
      $("btnNext").classList.toggle("hidden", state.step >= state.maxStep);
      $("btnSubmit").classList.toggle("hidden", state.step !== state.maxStep);
      if (state.step === 4) paintReview();
      showErr("");
      window.scrollTo({ top: 0, behavior: "smooth" });
    }

    function validateStep(step) {
      if (step === 1) {
        if (!$("roundId").value) return "กรุณาเลือกรอบที่เปิดรับ";
        if (!$("qualify").value) return "กรุณาเลือกคุณสมบัติ";
        if ($("qualify").value === "อื่นๆ" && !$("qualifyOther").value.trim()) {
          return "กรุณาระบุคุณสมบัติอื่นๆ";
        }
        if (!$("nameTitle").value) return "กรุณาเลือกคำนำหน้าชื่อ";
        if ($("name").value.trim().length < 2) return "กรุณากรอกชื่อ–นามสกุล";
        if (!/^[0-9+\-]{8,20}$/.test($("phone").value.trim().replace(/\s+/g, ""))) {
          return "กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง";
        }
      }
      if (step === 2) {
        const zone = document.querySelector('input[name="zone"]:checked')?.value;
        if (!zone) return "กรุณาเลือกโซน";
        if (!$("category").value) return "กรุณาเลือกประเภทร้านค้า";
      }
      if (step === 3) {
        if (!$("idCard").files?.length) return "กรุณาอัปโหลดสำเนาบัตรประชาชน";
        if (!$("houseReg").files?.length) return "กรุณาอัปโหลดสำเนาทะเบียนบ้าน";
        if (!$("photo").files?.length) return "กรุณาอัปโหลดรูปถ่ายหน้าตรง";
        if (!$("food").files?.length) return "กรุณาอัปโหลดภาพอาหาร/สินค้าอย่างน้อย 1 รูป";
        const maxFood = Number(state.meta?.upload?.food_max || 5);
        if ($("food").files.length > maxFood) return "ภาพอาหาร/สินค้าได้สูงสุด " + maxFood + " รูป";
      }
      if (step === 4) {
        if (!$("captchaToken").value) return "รหัสป้องกันสแปมยังไม่พร้อม กรุณากดเปลี่ยนข้อ";
        if (!$("captchaAnswer").value.trim()) return "กรุณาตอบคำถามป้องกันสแปม";
      }
      return "";
    }

    function paintReview() {
      const zone = document.querySelector('input[name="zone"]:checked')?.value || "-";
      const zoneMeta = (state.meta?.zones || []).find(z => String(z.code) === String(zone));
      let zoneName = "โซน " + zone;
      if (zoneMeta) {
        const nameTh = String(zoneMeta.name_th || "").trim();
        const codeLabel = "โซน " + zoneMeta.code;
        // Avoid "โซน A โซน A" when name_th repeats the code label
        if (nameTh && nameTh !== codeLabel && nameTh !== zoneMeta.code) {
          zoneName = codeLabel + " · " + nameTh;
        } else {
          zoneName = codeLabel;
        }
      }
      const roundText = $("roundId").selectedOptions[0]?.textContent || "-";
      const qualify = $("qualify").value + ($("qualify").value === "อื่นๆ" ? " — " + $("qualifyOther").value : "");
      const docs = [
        ["บัตรประชาชน", $("idCard").files.length],
        ["ทะเบียนบ้าน", $("houseReg").files.length],
        ["รูปหน้าตรง", $("photo").files.length],
        ["อาหาร/สินค้า", $("food").files.length],
      ];
      $("reviewBox").innerHTML = `
        <div class="review-head">สรุปใบสมัครร้านค้า</div>
        <div class="review-grid">
          <div class="review-item"><span class="k">รอบที่เปิดรับ</span><span class="v">${esc(roundText)}</span></div>
          <div class="review-item"><span class="k">เบอร์ติดต่อ</span><span class="v">${esc($("phone").value.trim())}</span></div>
          <div class="review-item full"><span class="k">ชื่อ–นามสกุล</span><span class="v">${esc(fullApplicantName())}</span></div>
          <div class="review-item full"><span class="k">คุณสมบัติ</span><span class="v">${esc(qualify)}</span></div>
          <div class="review-item"><span class="k">โซนร้านค้า</span><span class="v">${esc(zoneName)}</span></div>
          <div class="review-item"><span class="k">ประเภทร้าน</span><span class="v">${esc($("category").value)}</span></div>
          <div class="review-item full"><span class="k">จุดเด่น / รายละเอียด</span><span class="v">${esc($("detail").value || "—")}</span></div>
          <div class="review-item full">
            <span class="k">เอกสารที่แนบ</span>
            <div class="review-docs">${docs.map(([label, n]) => `<span>${esc(label)} · ${n} ไฟล์</span>`).join("")}</div>
          </div>
        </div>`;
    }

    async function checkPhoneDuplicate() {
      const hint = $("phoneHint");
      const phone = ($("phone").value || "").trim().replace(/[\s\-]/g, "");
      const roundId = $("roundId").value;
      if (!hint) return "";
      if (!phone || phone.length < 8) {
        hint.className = "phone-hint";
        hint.textContent = "เบอร์นี้ใช้สมัครได้ 1 ครั้งต่อรอบเท่านั้น";
        return "";
      }
      if (!roundId) {
        hint.className = "phone-hint";
        hint.textContent = "กรุณาเลือกรอบก่อนตรวจสอบเบอร์";
        return "กรุณาเลือกรอบที่เปิดรับ";
      }
      if (!/^[0-9+]{8,20}$/.test(phone)) {
        hint.className = "phone-hint bad";
        hint.textContent = "รูปแบบเบอร์โทรไม่ถูกต้อง";
        return "กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง";
      }
      hint.className = "phone-hint";
      hint.textContent = "กำลังตรวจสอบเบอร์โทร...";
      try {
        const url = "apply_api.php?action=check_phone&round_id=" + encodeURIComponent(roundId)
          + "&phone=" + encodeURIComponent(phone) + "&_=" + Date.now();
        const res = await fetch(url, { credentials: "same-origin" });
        const json = await res.json();
        if (!json.ok) throw new Error(json.error || "ตรวจสอบเบอร์ไม่สำเร็จ");
        if (json.taken) {
          hint.className = "phone-hint bad";
          hint.textContent = json.message || "เบอร์โทรศัพท์นี้มีการใช้งานสมัครไปแล้ว";
          return json.message || "เบอร์โทรศัพท์นี้มีการใช้งานสมัครไปแล้ว";
        }
        hint.className = "phone-hint ok";
        hint.textContent = "เบอร์นี้ยังไม่ถูกใช้ในรอบนี้ สามารถสมัครได้";
        return "";
      } catch (e) {
        hint.className = "phone-hint bad";
        hint.textContent = e.message || String(e);
        return e.message || String(e);
      }
    }

    function bindPreview(input, box) {
      input.addEventListener("change", () => {
        box.innerHTML = "";
        [...(input.files || [])].forEach(f => {
          if (f.type.startsWith("image/")) {
            const img = document.createElement("img");
            img.src = URL.createObjectURL(f);
            img.alt = f.name;
            box.appendChild(img);
          } else {
            const chip = document.createElement("div");
            chip.className = "filechip";
            chip.textContent = f.name.split(".").pop()?.toUpperCase() || "FILE";
            box.appendChild(chip);
          }
        });
      });
    }

    $("qualify").onchange = () => {
      $("qualifyOtherWrap").classList.toggle("hidden", $("qualify").value !== "อื่นๆ");
    };

    let phoneCheckTimer;
    $("phone").oninput = () => {
      const hint = $("phoneHint");
      if (hint) {
        hint.className = "phone-hint";
        hint.textContent = "เบอร์นี้ใช้สมัครได้ 1 ครั้งต่อรอบเท่านั้น";
      }
      clearTimeout(phoneCheckTimer);
      phoneCheckTimer = setTimeout(() => { checkPhoneDuplicate(); }, 450);
    };
    $("phone").onblur = () => checkPhoneDuplicate();
    $("roundId").onchange = () => checkPhoneDuplicate();

    $("btnNext").onclick = async () => {
      const err = validateStep(state.step);
      if (err) return showErr(err);
      if (state.step === 1) {
        const phoneErr = await checkPhoneDuplicate();
        if (phoneErr) return showErr(phoneErr);
      }
      state.step = Math.min(state.maxStep, state.step + 1);
      paintStep();
    };
    $("btnBack").onclick = () => {
      state.step = Math.max(1, state.step - 1);
      paintStep();
    };

    $("applyForm").onsubmit = async (e) => {
      e.preventDefault();
      for (let s = 1; s <= 4; s++) {
        const err = validateStep(s);
        if (err) {
          state.step = s;
          paintStep();
          return showErr(err);
        }
      }
      const fd = new FormData($("applyForm"));
      fd.set("action", "submit");
      fd.set("phone", $("phone").value.trim().replace(/[\s\-]/g, ""));
      fd.set("name_title", $("nameTitle").value);
      fd.set("name", $("name").value.trim().replace(/\s+/g, " "));
      fd.set("captcha_token", $("captchaToken").value);
      fd.set("captcha_answer", $("captchaAnswer").value.trim());
      const zone = document.querySelector('input[name="zone"]:checked')?.value;
      if (zone) fd.set("zone", zone);

      $("btnSubmit").disabled = true;
      $("btnSubmit").textContent = "กำลังส่ง...";
      showErr("");
      try {
        const res = await fetch("apply_api.php?action=submit", { method: "POST", body: fd, credentials: "same-origin" });
        const json = await res.json();
        if (!json.ok) throw new Error(json.error || "ส่งไม่สำเร็จ");
        setMode("success");
        $("successRef").textContent = json.application?.ref || ("A" + json.application?.applicant_id);
        $("successDetail").textContent =
          (json.application?.name || "") + " · โซน " + (json.application?.zone || "") +
          " · " + (json.application?.category || "");
        $("statusBanner").className = "banner open";
        $("statusBanner").innerHTML = `<span class="ban-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg></span><div class="ban-body"><b>ส่งใบสมัครสำเร็จ</b> · เก็บรหัสอ้างอิงไว้สำหรับสอบถามสถานะ</div>`;
        if (json.application?.phone) $("statusPhone").value = json.application.phone;
      } catch (err) {
        showErr(err.message || String(err));
        await refreshCaptcha();
      } finally {
        $("btnSubmit").disabled = false;
        $("btnSubmit").innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4 20-7z"/></svg> ส่งใบสมัคร`;
      }
    };

    $("btnAgain").onclick = () => {
      if (!state.accepting) {
        alert("ขณะนี้ยังไม่เปิดรับสมัคร");
        return setMode("gate");
      }
      setMode("apply");
    };
    $("btnRefreshCaptcha").onclick = () => refreshCaptcha();
    $("btnStartApply").onclick = () => {
      if (!state.accepting) return alert("ขณะนี้ยังไม่เปิดรับสมัคร");
      setMode("apply");
    };
    $("btnOpenStatus").onclick = () => setMode("status");
    $("btnBackGate").onclick = () => setMode("gate");
    $("btnExitApply").onclick = () => openExitModal();
    $("btnExitCancel").onclick = () => closeExitModal();
    $("btnExitConfirm").onclick = () => {
      closeExitModal();
      setMode("gate");
    };
    $("exitModal").onclick = (e) => {
      if (e.target.id === "exitModal") closeExitModal();
    };
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && $("exitModal")?.classList.contains("show")) closeExitModal();
    });
    $("btnGotoStatus").onclick = () => setMode("status");
    $("btnHomeGate").onclick = () => setMode("gate");
    $("btnRefreshStatusCaptcha").onclick = () => refreshStatusCaptcha();

    $("btnStatus").onclick = async () => {
      const phone = $("statusPhone").value.trim();
      const name = $("statusName").value.trim();
      const box = $("statusResult");
      const captchaToken = $("statusCaptchaToken")?.value || "";
      const captchaAnswer = ($("statusCaptchaAnswer")?.value || "").trim();
      if (!phone) {
        box.innerHTML = `<div class="err" style="margin:0">กรุณากรอกเบอร์โทรที่ใช้สมัคร</div>`;
        return;
      }
      if (!captchaToken || !captchaAnswer) {
        box.innerHTML = `<div class="err" style="margin:0">กรุณาตอบคำถามป้องกันสแปม</div>`;
        return;
      }
      box.textContent = "กำลังค้นหา...";
      $("btnStatus").disabled = true;
      try {
        const fd = new FormData();
        fd.set("action", "status");
        fd.set("phone", phone.replace(/\s+/g, ""));
        fd.set("name", name);
        fd.set("captcha_token", captchaToken);
        fd.set("captcha_answer", captchaAnswer);
        const res = await fetch("apply_api.php?action=status", {
          method: "POST",
          body: fd,
          credentials: "same-origin",
        });
        const json = await res.json();
        if (json.status_captcha) paintStatusCaptcha(json.status_captcha);
        else await refreshStatusCaptcha();
        if (!json.ok) throw new Error(json.error || "ค้นหาไม่สำเร็จ");
        if (!json.items?.length) {
          box.innerHTML = `<div class="err" style="margin:0">ไม่พบใบสมัครที่ตรงกับเบอร์นี้</div>`;
          return;
        }
        box.innerHTML = json.items.map(it => {
          const sel = it.selection || "";
          const selClass = sel === "ได้รับการคัดเลือก" ? "ok" : (sel === "ไม่ได้รับการคัดเลือก" ? "danger" : "warn");
          const payClass = it.payment_status === "paid" ? "ok" : "warn";
          return `
          <div class="status-result-card">
            <div class="ref">${esc(it.ref)}</div>
            <div class="meta">${esc(it.name)} · รอบ ${esc(it.round_no)} · โซน ${esc(it.zone)} · ${esc(it.category)}</div>
            <div class="chips">
              <span class="status-chip">เอกสาร: ${esc(it.doc_status || "—")}</span>
              <span class="status-chip ${selClass}">ผลคัดเลือก: ${esc(sel || "—")}</span>
              <span class="status-chip">ล็อก: ${esc(it.assigned_slot || "—")}</span>
              <span class="status-chip ${payClass}">${it.payment_status === "paid" ? "ชำระแล้ว" : "ยังไม่ชำระ"}</span>
            </div>
          </div>`;
        }).join("");
      } catch (e) {
        box.innerHTML = `<div class="err" style="margin:0">${esc(e.message || e)}</div>`;
        await refreshStatusCaptcha();
      } finally {
        $("btnStatus").disabled = false;
      }
    };

    bindPreview($("idCard"), $("prevId"));
    bindPreview($("houseReg"), $("prevHouse"));
    bindPreview($("photo"), $("prevPhoto"));
    bindPreview($("food"), $("prevFood"));

    loadMeta().catch(err => {
      $("statusBanner").className = "banner closed";
      $("statusBanner").textContent = "โหลดแบบฟอร์มไม่สำเร็จ: " + (err.message || err);
      $("gateView")?.classList.add("hidden");
      $("applyCard")?.classList.add("hidden");
    });
  </script>
</body>
</html>
