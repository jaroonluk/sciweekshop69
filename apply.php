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
    .banner.pending { border-color: #f0d4a8; background: #fff8ed; color: #7a4a12; }
    .banner.open { border-color: #b7e0c8; background: #f3fbf6; }
    .banner.pending,
    .banner.closed {
      border-width: 2px;
      border-radius: 18px;
      padding: 1.35rem 1.45rem 1.4rem;
      margin-bottom: 1.35rem;
      box-shadow: 0 16px 40px rgba(80, 50, 0, .14);
      align-items: center;
      gap: 1rem;
    }
    .banner.pending {
      border-color: #e8a84a;
      border-left-width: 7px;
      border-left-color: #c45c12;
      background: linear-gradient(135deg, #fff9f0 0%, #ffefd6 55%, #fff5e8 100%);
    }
    .banner.closed {
      border-color: #e8a0a0;
      border-left-width: 7px;
      border-left-color: #b42318;
      background: linear-gradient(135deg, #fff7f7 0%, #ffe8e8 55%, #fff0f0 100%);
    }
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
    .banner.pending .ban-ico { background: #ffeed6; color: #c45c12; }
    .banner.pending .ban-ico,
    .banner.closed .ban-ico {
      width: 3.6rem;
      height: 3.6rem;
      border-radius: 15px;
      color: #fff;
      box-shadow: 0 10px 22px rgba(0, 0, 0, .16);
    }
    .banner.pending .ban-ico { background: linear-gradient(145deg, #f0a030, #c45c12); }
    .banner.closed .ban-ico { background: linear-gradient(145deg, #e85555, #b42318); }
    .banner .ban-ico svg { width: 1.25rem; height: 1.25rem; }
    .banner.pending .ban-ico svg,
    .banner.closed .ban-ico svg { width: 1.75rem; height: 1.75rem; }
    .banner .ban-body { flex: 1; min-width: 0; }
    .ban-tag {
      display: inline-block;
      font-size: .72rem;
      font-weight: 800;
      letter-spacing: .04em;
      text-transform: uppercase;
      padding: .2rem .6rem;
      border-radius: 999px;
      margin: 0 0 .45rem;
    }
    .banner.pending .ban-tag { background: rgba(196, 92, 18, .14); color: #9a4a08; }
    .banner.closed .ban-tag { background: rgba(180, 35, 24, .12); color: #8a2018; }
    .ban-headline {
      font-family: "Chakra Petch", sans-serif;
      font-size: 1.55rem;
      font-weight: 800;
      line-height: 1.3;
      margin: 0 0 .55rem;
      letter-spacing: -.01em;
    }
    .ban-schedule {
      display: block;
      font-size: 1rem;
      color: #5c5346;
      line-height: 1.5;
      margin: 0;
      padding: .65rem .9rem;
      border-radius: 12px;
      background: rgba(255, 255, 255, .72);
      border: 1px solid rgba(0, 0, 0, .07);
    }
    .banner.closed .ban-schedule { color: #7a3530; border-color: rgba(180, 35, 24, .14); background: rgba(255, 255, 255, .78); }
    .banner.pending .ban-schedule { color: #7a4a12; border-color: rgba(196, 92, 18, .16); background: rgba(255, 255, 255, .78); }
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
    .choice-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: .55rem;
      margin-top: .35rem;
    }
    .choice-grid label {
      display: flex;
      align-items: center;
      gap: .5rem;
      padding: .75rem .8rem;
      border: 2px solid var(--line);
      border-radius: 12px;
      background: #fff;
      cursor: pointer;
      font-weight: 700;
    }
    .choice-grid input { width: auto; transform: scale(1.2); }
    .choice-grid label:has(input:checked) {
      border-color: var(--teal);
      background: #eef8f6;
    }
    .ask-box {
      margin-bottom: 1rem;
      padding: .95rem 1rem;
      border: 1px solid var(--line);
      border-radius: 14px;
      background: #fffdf8;
    }
    .ask-box .ask-title {
      display: block;
      font-weight: 700;
      font-size: 1.12rem;
      margin-bottom: .25rem;
    }
    .ask-box .ask-desc {
      margin: 0 0 .55rem;
      color: var(--muted);
      font-size: 1.02rem;
      line-height: 1.45;
    }
    .ask-note {
      margin: .7rem 0 0;
      padding: .7rem .8rem;
      border-radius: 12px;
      background: #fff7df;
      border: 1px solid #f0c44a;
      color: #5c4a1f;
      font-size: 1.02rem;
      line-height: 1.45;
    }
    .upload-box {
      border: 2px dashed #d2c6ae;
      border-radius: 14px;
      padding: 1rem 1.05rem 1.1rem;
      background: #fff;
      margin-bottom: 1rem;
    }
    .upload-box strong { display: block; font-size: 1.12rem; margin-bottom: .25rem; }
    .upload-box p { margin: 0 0 .65rem; color: var(--muted); line-height: 1.45; }
    .upload-box input[type=file] { font-size: 1rem; width: 100%; }
    .upload-box textarea { width: 100%; font-size: 1rem; }
    .upload-head {
      display: flex;
      align-items: flex-start;
      gap: .75rem;
      margin-bottom: .7rem;
    }
    .upload-head .uh-ico {
      flex: 0 0 auto;
      width: 2.55rem;
      height: 2.55rem;
      border-radius: 12px;
      display: grid;
      place-items: center;
      background: #f3ebe0;
      color: var(--teal);
    }
    .upload-head .uh-ico svg { width: 1.35rem; height: 1.35rem; }
    .upload-head .uh-ico.img { background: #e8f4f1; color: #1f6f63; }
    .upload-head .uh-ico.pdf { background: #fdecec; color: #b42318; }
    .upload-head .uh-ico.text { background: #eef2ff; color: #3b4d9a; }
    .upload-head .uh-body { min-width: 0; flex: 1; }
    .upload-head .uh-body strong { margin-bottom: .2rem; }
    .upload-head .uh-body p { margin: 0; font-size: .98rem; }
    .upload-head .uh-hint { margin: 0; }
    .upload-head .uh-intro {
      margin: 0;
      font-size: .98rem;
      color: var(--muted);
      line-height: 1.45;
    }
    .upload-head .uh-list {
      margin: .3rem 0 0;
      padding: 0 0 0 1.15rem;
      color: var(--muted);
      font-size: .95rem;
      line-height: 1.5;
    }
    .upload-head .uh-list li { margin: .1rem 0; }
    .upload-head .uh-list li.uh-note {
      list-style: none;
      margin-left: -1.15rem;
      padding-left: 0;
    }
    .upload-head .uh-accept {
      margin: .4rem 0 0;
      font-size: .88rem;
      color: var(--muted);
      line-height: 1.4;
    }
    .file-pick {
      position: relative;
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      margin-top: .15rem;
      font: inherit;
      font-size: 1.02rem;
      font-weight: 700;
      border: 2px solid var(--line);
      border-radius: 999px;
      padding: .55rem 1.05rem;
      background: #fffaf2;
      color: var(--ink);
      cursor: pointer;
    }
    .file-pick:hover { border-color: var(--teal); color: var(--teal); }
    .file-pick svg { width: 1.1rem; height: 1.1rem; }
    .file-pick input[type=file] {
      position: absolute;
      inset: 0;
      opacity: 0;
      cursor: pointer;
      width: 100%;
      height: 100%;
      font-size: 0;
    }
    .preview {
      display: flex;
      flex-wrap: wrap;
      gap: .65rem;
      margin-top: .85rem;
    }
    .preview-card {
      width: 108px;
      border-radius: 14px;
      border: 1px solid var(--line);
      background: #fbf7f0;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      cursor: pointer;
      text-align: left;
      padding: 0;
      font: inherit;
      color: inherit;
      transition: transform .15s ease, box-shadow .15s ease;
    }
    .preview-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 18px rgba(40, 30, 10, .12);
    }
    .preview-card .pc-media {
      width: 100%;
      height: 78px;
      display: grid;
      place-items: center;
      background: #efe7da;
      position: relative;
      overflow: hidden;
    }
    .preview-card .pc-media img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
    .preview-card .pc-media.pdf {
      background: linear-gradient(160deg, #fff1f0, #fde2e0);
      color: #b42318;
    }
    .preview-card .pc-media.pdf svg { width: 2.2rem; height: 2.2rem; }
    .preview-card .pc-media .eye {
      position: absolute;
      right: .35rem;
      bottom: .35rem;
      width: 1.55rem;
      height: 1.55rem;
      border-radius: 999px;
      background: rgba(28, 22, 14, .72);
      color: #fff;
      display: grid;
      place-items: center;
    }
    .preview-card .pc-media .eye svg { width: .9rem; height: .9rem; }
    .preview-card .pc-name {
      padding: .4rem .45rem .5rem;
      font-size: .78rem;
      font-weight: 600;
      line-height: 1.25;
      color: #4a4338;
      word-break: break-all;
    }
    .preview-empty {
      font-size: .92rem;
      color: var(--muted);
      margin-top: .55rem;
    }
    .file-type-modal {
      width: min(460px, 100%);
      background: linear-gradient(165deg, #fffdf8, #fff8ee);
      border: 1px solid #efd7a8;
      border-radius: 22px;
      padding: 1.35rem 1.3rem 1.2rem;
      box-shadow: 0 22px 48px rgba(80, 40, 0, .22);
      text-align: center;
      animation: warnPop .22s ease-out;
    }
    .file-type-modal .ico {
      width: 4.2rem;
      height: 4.2rem;
      margin: 0 auto .85rem;
      border-radius: 50%;
      display: grid;
      place-items: center;
      background: linear-gradient(145deg, #3aa89a, #1f6f63);
      color: #fff;
      box-shadow: 0 10px 20px rgba(31, 111, 99, .28);
    }
    .file-type-modal .ico svg { width: 2.1rem; height: 2.1rem; }
    .file-type-modal h3 {
      margin: 0 0 .4rem;
      font-family: "Chakra Petch", sans-serif;
      font-size: 1.35rem;
      color: var(--ink);
    }
    .file-type-modal p {
      margin: 0;
      color: var(--muted);
      font-size: 1.02rem;
      line-height: 1.5;
    }
    .file-type-modal .type-list {
      display: flex;
      flex-wrap: wrap;
      gap: .45rem;
      justify-content: center;
      margin: .95rem 0 0;
    }
    .file-type-modal .type-list span {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .45rem .75rem;
      border-radius: 12px;
      background: #fff;
      border: 1px solid #e4d8c4;
      font-weight: 700;
      font-size: .95rem;
      color: var(--ink);
    }
    .file-type-modal .type-list span svg { width: 1.05rem; height: 1.05rem; }
    .file-type-modal .bad-name {
      margin-top: .75rem;
      padding: .55rem .7rem;
      border-radius: 10px;
      background: #fff1f0;
      border: 1px solid #f0c2c2;
      color: #7a1f1a;
      font-size: .95rem;
      word-break: break-all;
    }
    .file-type-modal .modal-actions {
      display: flex;
      justify-content: center;
      margin-top: 1.15rem;
    }
    .lightbox {
      width: min(920px, 100%);
      max-height: min(90vh, 900px);
      background: #1c160e;
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 24px 60px rgba(0,0,0,.4);
      display: flex;
      flex-direction: column;
    }
    .lightbox .lb-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
      padding: .7rem .9rem;
      background: rgba(255,255,255,.06);
      color: #f5efe4;
    }
    .lightbox .lb-bar strong {
      font-size: .95rem;
      font-weight: 600;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .lightbox .lb-bar button {
      border: 0;
      background: transparent;
      color: #fff;
      font: inherit;
      font-weight: 700;
      cursor: pointer;
      padding: .35rem .55rem;
      border-radius: 8px;
    }
    .lightbox .lb-bar button:hover { background: rgba(255,255,255,.12); }
    .lightbox .lb-body {
      flex: 1;
      min-height: 0;
      display: grid;
      place-items: center;
      padding: .75rem;
      background: #111;
    }
    .lightbox .lb-body img {
      max-width: 100%;
      max-height: min(78vh, 780px);
      object-fit: contain;
      border-radius: 8px;
    }
    .lightbox .lb-body iframe {
      width: min(860px, 100%);
      height: min(78vh, 780px);
      border: 0;
      border-radius: 8px;
      background: #fff;
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
      flex-direction: column;
      gap: .85rem;
      margin-top: .55rem;
    }
    .review-doc-row {
      border: 1px solid #e8dfd0;
      border-radius: 14px;
      background: #fffefb;
      padding: .75rem .85rem;
    }
    .review-doc-row .rd-title {
      font-size: 1.02rem;
      font-weight: 700;
      color: var(--ink);
      margin: 0 0 .45rem;
      display: flex;
      align-items: center;
      gap: .4rem;
      flex-wrap: wrap;
    }
    .review-doc-row .rd-title .rd-status {
      font-size: .78rem;
      font-weight: 700;
      padding: .15rem .5rem;
      border-radius: 999px;
      background: #eef8f6;
      color: var(--teal);
    }
    .review-doc-row .rd-title .rd-status.miss {
      background: #fff1f0;
      color: #9b1c1c;
    }
    .review-doc-row .rd-text {
      margin: 0;
      color: #4a4338;
      font-size: 1rem;
      line-height: 1.45;
      white-space: pre-wrap;
      word-break: break-word;
    }
    .review-doc-row .rd-files {
      display: flex;
      flex-wrap: wrap;
      gap: .55rem;
    }
    .review-doc-row .rd-empty {
      margin: 0;
      color: var(--muted);
      font-size: .95rem;
    }
    .phone-hint {
      margin: .35rem 0 0;
      font-size: .92rem;
      color: var(--muted);
    }
    .phone-hint:empty { display: none; }
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
        <span data-step-ind="2"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l-6 3V6l6-3 6 3 6-3v15l-6 3-6-3z"/><path d="M9 3v15M15 6v15"/></svg> <span id="step2IndLabel">2. โซน</span></span>
        <span data-step-ind="3"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg> 3. เอกสาร</span>
        <span data-step-ind="4"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> 4. ยืนยัน</span>
      </div>
      <div id="formError" class="err hidden"></div>

      <form id="applyForm" novalidate>
        <section class="step" data-step="1">
          <h2 class="section-title"><span class="sec-ico teal" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>ข้อมูลผู้สมัคร</h2>
          <input type="hidden" id="roundId" name="round_id" value="" />
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
            <p class="phone-hint" id="phoneHint"></p>
          </label>
          <div class="ask-box hidden" id="powerAskWrap">
            <span class="ask-title">ความจำเป็นในการใช้ไฟฟ้ากำลังสูง <span class="req">*</span></span>
            <p class="ask-desc">เช่น เตาอบไฟฟ้า ตู้ไอศกรีม หรืออุปกรณ์ที่ใช้ไฟสูง</p>
            <div class="choice-grid">
              <label><input type="radio" name="need_high_power" value="0" /> ไม่ใช้</label>
              <label><input type="radio" name="need_high_power" value="1" /> ต้องการใช้</label>
            </div>
            <p class="ask-note hidden" id="powerNote">หากต้องการใช้ กรุณานำสายไฟเพื่อรองรับอุปกรณ์ <b>อย่างน้อย 25 เมตร</b></p>
          </div>
          <div class="ask-box hidden" id="iceAskWrap">
            <span class="ask-title">ความจำเป็นต้องใช้ถังน้ำแข็ง <span class="req">*</span></span>
            <p class="ask-desc">หากต้องการใช้ กรุณาระบุจำนวนถัง</p>
            <div class="choice-grid">
              <label><input type="radio" name="need_ice" value="0" /> ไม่ใช้</label>
              <label><input type="radio" name="need_ice" value="1" /> ต้องการใช้</label>
            </div>
            <label class="field hidden" id="iceCountWrap" style="margin:.75rem 0 0">
              <span>จำนวนถังน้ำแข็ง <span class="req">*</span></span>
              <input type="number" id="iceBucketCount" name="ice_bucket_count" min="1" max="50" step="1" placeholder="เช่น 2" />
            </label>
          </div>
          <!-- honeypot: leave empty -->
          <div class="hp-field" aria-hidden="true">
            <label>เว็บไซต์บริษัท
              <input type="text" name="company_url" id="companyUrl" tabindex="-1" autocomplete="off" />
            </label>
          </div>
        </section>

        <section class="step hidden" data-step="2">
          <h2 class="section-title" id="step2Title"><span class="sec-ico warn" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l-6 3V6l6-3 6 3 6-3v15l-6 3-6-3z"/><path d="M9 3v15M15 6v15"/></svg></span><span id="step2TitleText">เลือกโซนและประเภทร้าน</span></h2>
          <p class="hint" id="step2Hint">เลือกโซนก่อน แล้วระบบจะแสดงประเภทร้านในโซนนั้น</p>
          <div class="field" id="zonePickWrap">
            <span style="display:block;font-weight:700;font-size:1.12rem;margin-bottom:.5rem">โซนร้านค้า <span class="req">*</span></span>
            <div class="zone-grid" id="zoneGrid"></div>
          </div>
          <label class="field">
            <span>ประเภทร้านค้า <span class="req">*</span></span>
            <select id="category" name="category" required>
              <option value="">— เลือกโซนก่อน —</option>
            </select>
          </label>
          <p class="hint hidden" id="zoneAutoHint"></p>
        </section>

        <section class="step hidden" data-step="3">
          <h2 class="section-title"><span class="sec-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></span>อัปโหลดเอกสาร</h2>
          <div id="docUploadFields"></div>
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

  <div class="modal-backdrop" id="fileTypeModal" role="dialog" aria-modal="true" aria-labelledby="fileTypeModalTitle">
    <div class="file-type-modal">
      <div class="ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="M9 15h6"/>
        </svg>
      </div>
      <h3 id="fileTypeModalTitle">ชนิดไฟล์ไม่รองรับ</h3>
      <p id="fileTypeModalMsg">กรุณาเลือกไฟล์ตามประเภทที่ระบบรองรับสำหรับช่องนี้</p>
      <div class="type-list" id="fileTypeModalList"></div>
      <div class="bad-name hidden" id="fileTypeModalBad"></div>
      <div class="modal-actions">
        <button type="button" class="btn teal" id="btnFileTypeOk">เข้าใจแล้ว</button>
      </div>
    </div>
  </div>

  <div class="modal-backdrop" id="previewLightbox" role="dialog" aria-modal="true" aria-labelledby="previewLightboxTitle">
    <div class="lightbox">
      <div class="lb-bar">
        <strong id="previewLightboxTitle">ดูไฟล์</strong>
        <button type="button" id="btnCloseLightbox">ปิด</button>
      </div>
      <div class="lb-body" id="previewLightboxBody"></div>
    </div>
  </div>

  <script>
    const state = {
      step: 1,
      meta: null,
      maxStep: 4,
      mode: "gate", // gate | apply | status | success
      accepting: false,
      eventCode: (new URLSearchParams(location.search).get("event") || "").trim(),
      wantedRoundId: Number(new URLSearchParams(location.search).get("round") || 0) || 0,
    };

    const $ = (id) => document.getElementById(id);

    /** Build apply_api URL, always preserving ?event= when present. */
    function apiUrl(action, extra = {}) {
      const p = new URLSearchParams({ action: String(action), _: String(Date.now()) });
      if (state.eventCode) p.set("event", state.eventCode);
      Object.entries(extra).forEach(([k, v]) => {
        if (v === undefined || v === null || v === "") return;
        p.set(k, String(v));
      });
      return "apply_api.php?" + p.toString();
    }

    function appendEventField(fd) {
      if (state.eventCode) fd.set("event", state.eventCode);
      return fd;
    }

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

    function fmtWhenDate(v) {
      const t = fmtWhen(v);
      if (!t || t === "—") return "";
      return t.startsWith("วันที่ ") ? t : ("วันที่ " + t);
    }

    function fmtApplyWindow(round) {
      const title = String(round?.title || "").trim();
      const open = round?.apply_open_at ? fmtWhenDate(round.apply_open_at) : "";
      const close = round?.apply_close_at ? fmtWhenDate(round.apply_close_at) : "";
      const program = state.meta?.apply_program || state.meta?.event?.apply_program || "sciweek";
      let text = "เปิดรับสมัคร";
      if (title && program !== "scisquare") text += " " + title;
      if (open && close) text += " " + open + " ถึง " + close;
      else if (open) text += " " + open;
      else if (close) text += " ถึง " + close;
      return text;
    }

    const BAN_ICO_CLOCK = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>`;
    const BAN_ICO_X = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>`;

    function fmtBanSchedule(round) {
      let when = "";
      if (round?.apply_open_at) when += `เปิดรับ ${esc(fmtWhen(round.apply_open_at))}`;
      if (round?.apply_close_at) when += (when ? " · " : "") + `ปิดรับ ${esc(fmtWhen(round.apply_close_at))}`;
      return when;
    }

    function renderClosedBanner(round, fallbackReason) {
      const reason = round?.status_reason || fallbackReason || "กรุณาติดตามประกาศอีกครั้ง";
      const pendingReasons = ["ยังไม่ถึงวันเปิดรับสมัคร", "รอบนี้ยังไม่เปิดรับสมัคร"];
      const isPending = pendingReasons.includes(reason);
      const isClosed = reason === "ปิดรับสมัครแล้ว";
      const headline = isPending
        ? "ยังไม่ถึงวันเปิดรับสมัคร"
        : (isClosed ? "ปิดรับสมัครแล้ว" : reason);
      const bannerClass = isPending ? "banner pending" : "banner closed";
      const icon = isPending ? BAN_ICO_CLOCK : BAN_ICO_X;
      const schedule = fmtBanSchedule(round);
      return {
        className: bannerClass,
        html: `<span class="ban-ico" aria-hidden="true">${icon}</span>`
          + `<div class="ban-body">`
          + `<span class="ban-tag">สถานะการรับสมัคร</span>`
          + `<p class="ban-headline">${esc(headline)}</p>`
          + (schedule ? `<p class="ban-schedule">${schedule}</p>` : "")
          + `</div>`,
      };
    }

    async function loadMeta() {
      const res = await fetch(apiUrl("meta"), { credentials: "same-origin" });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || "โหลดแบบฟอร์มไม่สำเร็จ");
      state.meta = json;
      if (json.event?.code) {
        state.eventCode = String(json.event.code);
        const url = new URL(location.href);
        if (url.searchParams.get("event") !== state.eventCode) {
          url.searchParams.set("event", state.eventCode);
        }
        if (state.wantedRoundId > 0) {
          url.searchParams.set("round", String(state.wantedRoundId));
        }
        history.replaceState(null, "", url.pathname + url.search + url.hash);
      }
      const brand = json.branding || {};
      if ($("brandOrg")) $("brandOrg").textContent = brand.org || "คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น";
      if ($("brandHeadline")) $("brandHeadline").textContent = brand.headline || "รับสมัครร้านค้า · คณะวิทยาศาสตร์ มข.";
      $("eventTitle").textContent = brand.subline || json.event?.title || "รับสมัครร้านค้า";
      document.title = brand.page_title || ("รับสมัครร้านค้า · " + (json.event?.title || "คณะวิทยาศาสตร์ มข."));
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
      const focus = focusRound();
      const canApply = !!json.accepting && (!focus || !!focus.accepting);
      state.accepting = canApply;
      if (canApply) {
        const openRounds = (json.rounds || []).filter(r => r.accepting);
        const r = (focus && focus.accepting) ? focus : openRounds[0];
        banner.className = "banner open";
        banner.innerHTML = `<span class="ban-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg></span>`
          + `<div class="ban-body"><b>${esc(fmtApplyWindow(r))}</b></div>`;
      } else {
        const fallbackReason = (json.rounds || []).map(x => x.status_reason).filter(Boolean)[0] || "";
        const closed = renderClosedBanner(focus, fallbackReason);
        banner.className = closed.className;
        banner.innerHTML = closed.html;
      }

      const roundSel = $("roundId");
      const openRounds = (json.rounds || []).filter(r => r.accepting);
      let picked = openRounds[0] || null;
      if (state.wantedRoundId && openRounds.some(r => Number(r.id) === state.wantedRoundId)) {
        picked = openRounds.find(r => Number(r.id) === state.wantedRoundId) || picked;
      }
      if (roundSel) roundSel.value = picked ? String(picked.id) : "";
      syncExtraQuestions();

      $("qualify").innerHTML = (json.qualify_options || [])
        .map(q => `<option value="${esc(q)}">${esc(q)}</option>`).join("");

      $("zoneGrid").innerHTML = (json.zones || []).map(z => {
        const codeLabel = "โซน " + z.code;
        const nameTh = String(z.name_th || "").trim();
        const sub = (nameTh && !zoneNameIsRedundant(nameTh, z.code))
          ? `<br><small style="font-weight:500;color:#5c5346">${esc(nameTh)}</small>`
          : "";
        return `<label><input type="radio" name="zone" value="${esc(z.code)}" /> ${esc(codeLabel)}${sub}</label>`;
      }).join("");

      syncApplyFlow();
      renderDocFields();

      setMode("gate");
    }

    function currentDocSchema() {
      const program = state.meta?.apply_program || state.meta?.event?.apply_program || "sciweek";
      const schemas = state.meta?.upload?.doc_schemas || {};
      if (program === "scisquare") {
        const q = $("qualify")?.value || "";
        if (q === "นิติบุคคล") return schemas.juristic || state.meta?.upload?.doc_schema || [];
        return schemas.individual || state.meta?.upload?.doc_schema || [];
      }
      return schemas.default || state.meta?.upload?.doc_schema || [];
    }

    function fieldDomId(key) {
      return "doc_" + String(key || "").replace(/[^a-z0-9_]/gi, "_");
    }

    function fieldAllowedExt(f) {
      const fromSchema = (f.accept_ext || []).map(x => String(x).toLowerCase().replace(/^\./, ""));
      if (fromSchema.length) return [...new Set(fromSchema)];
      const accept = String(f.accept || "");
      const found = [];
      if (/\.jpe?g|image\/jpeg/i.test(accept)) found.push("jpg", "jpeg");
      if (/\.png|image\/png/i.test(accept)) found.push("png");
      if (/\.webp|image\/webp/i.test(accept)) found.push("webp");
      if (/\.pdf|application\/pdf/i.test(accept)) found.push("pdf");
      return [...new Set(found)];
    }

    function fileExt(name) {
      const m = String(name || "").toLowerCase().match(/\.([a-z0-9]+)$/);
      return m ? m[1] : "";
    }

    function normalizeExt(ext) {
      return ext === "jpeg" ? "jpg" : ext;
    }

    function isFileAllowed(file, allowedExt) {
      const ext = normalizeExt(fileExt(file.name));
      const allowed = allowedExt.map(normalizeExt);
      if (ext && allowed.includes(ext)) return true;
      const mime = String(file.type || "").toLowerCase();
      if (mime === "image/jpeg" && allowed.includes("jpg")) return true;
      if (mime === "image/png" && allowed.includes("png")) return true;
      if (mime === "image/webp" && allowed.includes("webp")) return true;
      if (mime === "application/pdf" && allowed.includes("pdf")) return true;
      return false;
    }

    function formatAllowedLabels(allowedExt) {
      const set = new Set(allowedExt.map(normalizeExt));
      const labels = [];
      if (set.has("jpg")) labels.push("JPEG");
      if (set.has("png")) labels.push("PNG");
      if (set.has("webp")) labels.push("WEBP");
      if (set.has("pdf")) labels.push("PDF");
      return labels;
    }

    function uploadHeadIcon(f, allowedExt) {
      const kind = f.kind || "file";
      if (kind === "text") {
        return `<span class="uh-ico text" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h6"/></svg></span>`;
      }
      const set = new Set(allowedExt.map(normalizeExt));
      const onlyPdf = set.has("pdf") && !set.has("jpg") && !set.has("png") && !set.has("webp");
      if (onlyPdf) {
        return `<span class="uh-ico pdf" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M10 12h4v8h-4z"/><path d="M8 12h2"/><path d="M14 18h2"/></svg></span>`;
      }
      return `<span class="uh-ico img" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></span>`;
    }

    function openFileTypeModal(fieldLabel, allowedExt, badNames) {
      const labels = formatAllowedLabels(allowedExt);
      $("fileTypeModalTitle").textContent = "ชนิดไฟล์ไม่รองรับ";
      $("fileTypeModalMsg").textContent = fieldLabel
        ? `ช่อง “${fieldLabel}” รองรับเฉพาะไฟล์ตามรายการด้านล่าง กรุณาเลือกไฟล์ใหม่`
        : "ระบบรองรับเฉพาะไฟล์ตามรายการด้านล่าง กรุณาเลือกไฟล์ใหม่";
      $("fileTypeModalList").innerHTML = labels.map(l => {
        if (l === "PDF") {
          return `<span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg> PDF</span>`;
        }
        return `<span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg> ${esc(l)}</span>`;
      }).join("");
      const bad = $("fileTypeModalBad");
      if (badNames?.length) {
        bad.classList.remove("hidden");
        bad.textContent = "ไฟล์ที่เลือก: " + badNames.join(", ");
      } else {
        bad.classList.add("hidden");
        bad.textContent = "";
      }
      $("fileTypeModal")?.classList.add("show");
    }

    function closeFileTypeModal() {
      $("fileTypeModal")?.classList.remove("show");
    }

    function openPreviewLightbox(title, kind, url) {
      $("previewLightboxTitle").textContent = title || "ดูไฟล์";
      const body = $("previewLightboxBody");
      body.innerHTML = "";
      if (kind === "pdf") {
        const frame = document.createElement("iframe");
        frame.src = url;
        frame.title = title || "PDF";
        body.appendChild(frame);
      } else {
        const img = document.createElement("img");
        img.src = url;
        img.alt = title || "preview";
        body.appendChild(img);
      }
      $("previewLightbox")?.classList.add("show");
    }

    function closePreviewLightbox() {
      $("previewLightbox")?.classList.remove("show");
      const body = $("previewLightboxBody");
      if (body) body.innerHTML = "";
    }

    function renderFieldHint(f) {
      const items = Array.isArray(f.hint_items) ? f.hint_items.filter(Boolean) : [];
      if (!f.hint_intro && !items.length && !f.hint_accept) {
        return `<p>${esc(f.hint || "")}</p>`;
      }
      const intro = f.hint_intro ? `<p class="uh-intro">${esc(f.hint_intro)}</p>` : "";
      const list = items.length
        ? `<ul class="uh-list">${items.map(t => {
            if (typeof t === "string" && t.startsWith("(")) {
              return `<li class="uh-note">${esc(t)}</li>`;
            }
            return `<li>${esc(t)}</li>`;
          }).join("")}</ul>`
        : "";
      const accept = f.hint_accept ? `<p class="uh-accept">${esc(f.hint_accept)}</p>` : "";
      return `<div class="uh-hint">${intro}${list}${accept}</div>`;
    }

    function renderDocFields() {
      const wrap = $("docUploadFields");
      if (!wrap) return;
      const schema = currentDocSchema();
      wrap.innerHTML = schema.map(f => {
        const id = fieldDomId(f.key);
        const req = f.required ? ` <span class="req">*</span>` : "";
        const name = esc(f.name || f.key);
        const allowed = fieldAllowedExt(f);
        if ((f.kind || "file") === "text") {
          const rows = Number(f.rows || 5);
          const ph = esc(f.placeholder || "");
          return `<div class="upload-box" data-doc-key="${esc(f.key)}" data-doc-kind="text">
            <div class="upload-head">
              ${uploadHeadIcon(f, allowed)}
              <div class="uh-body">
                <strong>${esc(f.label || f.key)}${req}</strong>
                ${renderFieldHint(f)}
              </div>
            </div>
            <textarea id="${id}" name="${name}" rows="${rows}" placeholder="${ph}"${f.required ? " required" : ""}></textarea>
          </div>`;
        }
        const multi = f.multiple ? " multiple" : "";
        const accept = esc(f.accept || "");
        return `<div class="upload-box" data-doc-key="${esc(f.key)}" data-doc-kind="file">
          <div class="upload-head">
            ${uploadHeadIcon(f, allowed)}
            <div class="uh-body">
              <strong>${esc(f.label || f.key)}${req}</strong>
              ${renderFieldHint(f)}
            </div>
          </div>
          <label class="file-pick">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/></svg>
            เลือกไฟล์
            <input type="file" id="${id}" name="${name}" accept="${accept}"${multi}${f.required ? " required" : ""} />
          </label>
          <div class="preview" id="prev_${id}"></div>
          <div class="preview-empty" id="empty_${id}">ยังไม่ได้เลือกไฟล์</div>
        </div>`;
      }).join("");
      schema.forEach(f => {
        if ((f.kind || "file") === "text") return;
        const input = $(fieldDomId(f.key));
        const prev = $("prev_" + fieldDomId(f.key));
        if (input && prev) bindPreview(input, prev, f);
      });
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
        const res = await fetch(apiUrl("captcha", { purpose: "apply" }), { credentials: "same-origin" });
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
        const res = await fetch(apiUrl("captcha", { purpose: "status" }), { credentials: "same-origin" });
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

    function selectedRound() {
      const id = Number($("roundId")?.value || 0);
      return (state.meta?.rounds || []).find(r => Number(r.id) === id) || null;
    }

    function focusRound() {
      const id = Number(state.wantedRoundId || 0);
      if (!id) return null;
      return (state.meta?.rounds || []).find(r => Number(r.id) === id) || null;
    }

    function isCategoryOnly(round) {
      return (round?.apply_flow || "zone_then_category") === "category_only";
    }

    function zoneCodesForCategory(cat) {
      return (state.meta?.category_zones?.[cat] || []).map(z => String(z).toUpperCase());
    }

    function setZoneChecked(code) {
      const want = String(code || "").toUpperCase();
      document.querySelectorAll('input[name="zone"]').forEach(r => {
        r.checked = String(r.value || "").toUpperCase() === want && want !== "";
      });
    }

    function fillAllCategories() {
      const cats = state.meta?.categories_all || [];
      $("category").innerHTML = cats.length
        ? `<option value="">— เลือกประเภทร้านค้า —</option>` + cats.map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join("")
        : `<option value="">— ยังไม่มีประเภทร้านค้า —</option>`;
    }

    function syncZoneVisibilityForCategory(cat) {
      const wrap = $("zonePickWrap");
      const hint = $("zoneAutoHint");
      const zones = zoneCodesForCategory(cat);
      document.querySelectorAll("#zoneGrid label").forEach(label => {
        const inp = label.querySelector('input[name="zone"]');
        const ok = !cat || zones.includes(String(inp?.value || "").toUpperCase());
        label.classList.toggle("hidden", !ok);
        if (!ok && inp) inp.checked = false;
      });
      if (!cat) {
        wrap?.classList.add("hidden");
        hint?.classList.add("hidden");
        setZoneChecked("");
        return;
      }
      if (zones.length <= 1) {
        wrap?.classList.add("hidden");
        const z = zones[0] || "";
        setZoneChecked(z);
        if (hint) {
          if (z) {
            hint.className = "hint";
            hint.textContent = "ระบบจะจัดร้านไว้ที่ " + formatZoneLabel(z) + " ตามประเภทร้านที่เลือก";
          } else {
            hint.className = "hint hidden";
            hint.textContent = "";
          }
        }
        return;
      }
      wrap?.classList.remove("hidden");
      const checked = String(document.querySelector('input[name="zone"]:checked')?.value || "").toUpperCase();
      if (checked && !zones.includes(checked)) setZoneChecked("");
      if (hint) {
        hint.className = "hint";
        hint.textContent = "ประเภทนี้อยู่ได้หลายโซน กรุณาเลือกโซนที่ต้องการ";
      }
    }

    function syncApplyFlow() {
      const round = selectedRound() || focusRound();
      const catOnly = isCategoryOnly(round);
      const catEl = $("category");
      const prevCat = catEl?.value || "";
      if ($("step2IndLabel")) $("step2IndLabel").textContent = catOnly ? "2. ประเภท" : "2. โซน";
      if ($("step2TitleText")) {
        $("step2TitleText").textContent = catOnly ? "เลือกประเภทร้านค้า" : "เลือกโซนและประเภทร้าน";
      }
      if ($("step2Hint")) {
        $("step2Hint").textContent = catOnly
          ? "เลือกประเภทร้านค้าหรือรายการอาหารได้เลย ระบบจะจัดโซนให้อัตโนมัติ"
          : "เลือกโซนก่อน แล้วระบบจะแสดงประเภทร้านในโซนนั้น";
      }
      if (catOnly) {
        $("zonePickWrap")?.classList.add("hidden");
        fillAllCategories();
        if (prevCat && [...catEl.options].some(o => o.value === prevCat)) catEl.value = prevCat;
        catEl.onchange = () => syncZoneVisibilityForCategory(catEl.value);
        syncZoneVisibilityForCategory(catEl.value);
        document.querySelectorAll('input[name="zone"]').forEach(r => {
          r.onchange = null;
        });
      } else {
        $("zonePickWrap")?.classList.remove("hidden");
        if ($("zoneAutoHint")) {
          $("zoneAutoHint").className = "hint hidden";
          $("zoneAutoHint").textContent = "";
        }
        document.querySelectorAll("#zoneGrid label").forEach(label => label.classList.remove("hidden"));
        catEl.onchange = null;
        const zone = document.querySelector('input[name="zone"]:checked')?.value;
        if (zone) fillCategories(zone);
        else catEl.innerHTML = `<option value="">— เลือกโซนก่อน —</option>`;
        document.querySelectorAll('input[name="zone"]').forEach(r => {
          r.onchange = () => fillCategories(r.value);
        });
      }
    }

    function extraReviewHtml() {
      const r = selectedRound();
      let html = "";
      if (r?.ask_high_power) {
        const v = document.querySelector('input[name="need_high_power"]:checked')?.value;
        const text = v === "1"
          ? "ต้องการใช้ · จะนำสายไฟอย่างน้อย 25 เมตร"
          : (v === "0" ? "ไม่ใช้" : "—");
        html += `<div class="review-item full"><span class="k">ไฟฟ้ากำลังสูง</span><span class="v">${esc(text)}</span></div>`;
      }
      if (r?.ask_ice_bucket) {
        const v = document.querySelector('input[name="need_ice"]:checked')?.value;
        let text = "—";
        if (v === "0") text = "ไม่ใช้";
        else if (v === "1") text = ($("iceBucketCount")?.value || "—") + " ถัง";
        html += `<div class="review-item full"><span class="k">ถังน้ำแข็ง</span><span class="v">${esc(text)}</span></div>`;
      }
      return html;
    }

    function syncPowerNote() {
      const on = document.querySelector('input[name="need_high_power"]:checked')?.value === "1";
      $("powerNote")?.classList.toggle("hidden", !on);
    }

    function syncIceCount() {
      const on = document.querySelector('input[name="need_ice"]:checked')?.value === "1";
      $("iceCountWrap")?.classList.toggle("hidden", !on);
    }

    function syncExtraQuestions() {
      const r = selectedRound();
      $("powerAskWrap")?.classList.toggle("hidden", !r?.ask_high_power);
      $("iceAskWrap")?.classList.toggle("hidden", !r?.ask_ice_bucket);
      syncPowerNote();
      syncIceCount();
    }

    function compactZoneKey(s) {
      return String(s || "").replace(/\s+/g, "").toUpperCase();
    }

    function zoneNameIsRedundant(nameTh, code) {
      const n = compactZoneKey(nameTh);
      const c = compactZoneKey(code);
      if (!n || !c) return true;
      return n === c || n === ("โซน" + c) || n === ("ZONE" + c);
    }

    function formatZoneLabel(zoneOrCode) {
      const meta = (typeof zoneOrCode === "object" && zoneOrCode)
        ? zoneOrCode
        : (state.meta?.zones || []).find(z => String(z.code).toUpperCase() === String(zoneOrCode || "").toUpperCase());
      const code = String(meta?.code || (typeof zoneOrCode === "string" ? zoneOrCode : "") || "").trim();
      const codeLabel = code ? ("โซน " + code) : "โซน";
      const nameTh = String(meta?.name_th || "").trim();
      if (!nameTh || zoneNameIsRedundant(nameTh, code)) return codeLabel;
      return codeLabel + " · " + nameTh;
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
      if (state.step === state.maxStep) $("btnSubmit").disabled = !state.accepting;
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
        const extraRound = selectedRound();
        if (extraRound?.ask_high_power) {
          const power = document.querySelector('input[name="need_high_power"]:checked')?.value;
          if (power !== "0" && power !== "1") return "กรุณาระบุว่ามีความจำเป็นใช้ไฟฟ้ากำลังสูงหรือไม่";
        }
        if (extraRound?.ask_ice_bucket) {
          const ice = document.querySelector('input[name="need_ice"]:checked')?.value;
          if (ice !== "0" && ice !== "1") return "กรุณาระบุว่ามีความจำเป็นต้องใช้ถังน้ำแข็งหรือไม่";
          if (ice === "1") {
            const n = Number($("iceBucketCount")?.value || 0);
            if (!Number.isInteger(n) || n < 1 || n > 50) return "กรุณาระบุจำนวนถังน้ำแข็ง (1–50 ถัง)";
          }
        }
      }
      if (step === 2) {
        const round = selectedRound();
        if (isCategoryOnly(round)) {
          if (!$("category").value) return "กรุณาเลือกประเภทร้านค้า";
          const zones = zoneCodesForCategory($("category").value);
          if (!zones.length) return "ไม่พบโซนสำหรับประเภทร้านนี้";
          if (zones.length > 1) {
            const zone = document.querySelector('input[name="zone"]:checked')?.value;
            if (!zone || !zones.includes(String(zone).toUpperCase())) return "ประเภทนี้อยู่ได้หลายโซน กรุณาเลือกโซนร้านค้า";
          }
        } else {
          const zone = document.querySelector('input[name="zone"]:checked')?.value;
          if (!zone) return "กรุณาเลือกโซน";
          if (!$("category").value) return "กรุณาเลือกประเภทร้านค้า";
        }
      }
      if (step === 3) {
        const schema = currentDocSchema();
        for (const f of schema) {
          const input = $(fieldDomId(f.key));
          if ((f.kind || "file") === "text") {
            const val = (input?.value || "").trim();
            if (f.required && !val) return "กรุณากรอก" + (f.label || f.key);
            continue;
          }
          const n = input?.files?.length || 0;
          if (f.required && n < 1) {
            return "กรุณาอัปโหลด" + (f.label || f.key);
          }
          const maxFiles = Number(f.max_files || (f.multiple ? 5 : 1));
          if (n > maxFiles) {
            return (f.label || f.key) + " อัปโหลดได้สูงสุด " + maxFiles + " ไฟล์";
          }
        }
      }
      if (step === 4) {
        if (!$("captchaToken").value) return "รหัสป้องกันสแปมยังไม่พร้อม กรุณากดเปลี่ยนข้อ";
        if (!$("captchaAnswer").value.trim()) return "กรุณาตอบคำถามป้องกันสแปม";
      }
      return "";
    }

    function paintReview() {
      const zone = document.querySelector('input[name="zone"]:checked')?.value || "";
      const zoneName = formatZoneLabel(zone);
      const roundText = selectedRound()?.title || "-";
      const qualify = $("qualify").value + ($("qualify").value === "อื่นๆ" ? " — " + $("qualifyOther").value : "");
      $("reviewBox").innerHTML = `
        <div class="review-head">สรุปใบสมัครร้านค้า</div>
        <div class="review-grid">
          <div class="review-item"><span class="k">รอบที่เปิดรับ</span><span class="v">${esc(roundText)}</span></div>
          <div class="review-item"><span class="k">เบอร์ติดต่อ</span><span class="v">${esc($("phone").value.trim())}</span></div>
          <div class="review-item full"><span class="k">ชื่อ–นามสกุล</span><span class="v">${esc(fullApplicantName())}</span></div>
          <div class="review-item full"><span class="k">คุณสมบัติ</span><span class="v">${esc(qualify)}</span></div>
          <div class="review-item"><span class="k">โซนร้านค้า</span><span class="v">${esc(zoneName)}</span></div>
          <div class="review-item"><span class="k">ประเภทร้าน</span><span class="v">${esc($("category").value)}</span></div>
          ${extraReviewHtml()}
          <div class="review-item full">
            <span class="k">เอกสาร / ข้อมูลที่แนบ</span>
            <div class="review-docs" id="reviewDocsList"></div>
          </div>
        </div>`;

      const list = $("reviewDocsList");
      if (!list) return;
      const eyeSvg = `<span class="eye" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg></span>`;
      const pdfSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h4"/></svg>`;

      currentDocSchema().forEach(f => {
        const input = $(fieldDomId(f.key));
        const row = document.createElement("div");
        row.className = "review-doc-row";

        if ((f.kind || "file") === "text") {
          const val = (input?.value || "").trim();
          row.innerHTML = `
            <div class="rd-title">${esc(f.label || f.key)}
              <span class="rd-status ${val ? "" : "miss"}">${val ? "กรอกแล้ว" : "ยังไม่กรอก"}</span>
            </div>
            ${val ? `<p class="rd-text">${esc(val)}</p>` : `<p class="rd-empty">ไม่มีข้อความ</p>`}`;
          list.appendChild(row);
          return;
        }

        const files = [...(input?.files || [])];
        const title = document.createElement("div");
        title.className = "rd-title";
        title.innerHTML = `${esc(f.label || f.key)}
          <span class="rd-status ${files.length ? "" : "miss"}">${files.length ? files.length + " ไฟล์" : "ยังไม่มีไฟล์"}</span>`;
        row.appendChild(title);

        if (!files.length) {
          const empty = document.createElement("p");
          empty.className = "rd-empty";
          empty.textContent = "ยังไม่ได้แนบไฟล์";
          row.appendChild(empty);
          list.appendChild(row);
          return;
        }

        const filesWrap = document.createElement("div");
        filesWrap.className = "rd-files";
        files.forEach(file => {
          const ext = normalizeExt(fileExt(file.name));
          const isPdf = ext === "pdf" || file.type === "application/pdf";
          const url = URL.createObjectURL(file);
          const btn = document.createElement("button");
          btn.type = "button";
          btn.className = "preview-card";
          btn.title = "คลิกเพื่อดูไฟล์ · " + file.name;
          if (isPdf) {
            btn.innerHTML = `<div class="pc-media pdf">${pdfSvg}${eyeSvg}</div><div class="pc-name">${esc(file.name)}</div>`;
            btn.onclick = () => openPreviewLightbox(file.name, "pdf", url);
          } else {
            btn.innerHTML = `<div class="pc-media"><img alt="" />${eyeSvg}</div><div class="pc-name">${esc(file.name)}</div>`;
            const img = btn.querySelector("img");
            if (img) {
              img.src = url;
              img.alt = file.name;
            }
            btn.onclick = () => openPreviewLightbox(file.name, "image", url);
          }
          filesWrap.appendChild(btn);
        });
        row.appendChild(filesWrap);
        list.appendChild(row);
      });
    }

    async function checkPhoneDuplicate() {
      const hint = $("phoneHint");
      const phone = ($("phone").value || "").trim().replace(/[\s\-]/g, "");
      const roundId = $("roundId").value;
      if (!hint) return "";
      if (!phone || phone.length < 8) {
        hint.className = "phone-hint";
        hint.textContent = "";
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
        const url = apiUrl("check_phone", { round_id: roundId, phone });
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

    function bindPreview(input, box, fieldMeta) {
      const empty = $("empty_" + input.id);
      const allowed = fieldAllowedExt(fieldMeta || {});
      const eyeSvg = `<span class="eye" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg></span>`;
      const pdfSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h4"/></svg>`;

      input.addEventListener("change", () => {
        const files = [...(input.files || [])];
        const ok = [];
        const bad = [];
        files.forEach(f => {
          if (isFileAllowed(f, allowed)) ok.push(f);
          else bad.push(f);
        });

        if (bad.length) {
          openFileTypeModal(fieldMeta?.label || "", allowed, bad.map(f => f.name));
          try {
            const dt = new DataTransfer();
            ok.forEach(f => dt.items.add(f));
            input.files = dt.files;
          } catch (_) {
            input.value = "";
            ok.length = 0;
          }
        }

        box.innerHTML = "";
        const finalFiles = [...(input.files || [])];
        if (empty) empty.style.display = finalFiles.length ? "none" : "";

        finalFiles.forEach(f => {
          const ext = normalizeExt(fileExt(f.name));
          const isPdf = ext === "pdf" || f.type === "application/pdf";
          const url = URL.createObjectURL(f);
          const btn = document.createElement("button");
          btn.type = "button";
          btn.className = "preview-card";
          btn.title = "คลิกเพื่อดูไฟล์ · " + f.name;

          if (isPdf) {
            btn.innerHTML = `<div class="pc-media pdf">${pdfSvg}${eyeSvg}</div><div class="pc-name">${esc(f.name)}</div>`;
            btn.onclick = () => openPreviewLightbox(f.name, "pdf", url);
          } else {
            btn.innerHTML = `<div class="pc-media"><img alt="" /><span class="eye" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg></span></div><div class="pc-name">${esc(f.name)}</div>`;
            const img = btn.querySelector("img");
            if (img) {
              img.src = url;
              img.alt = f.name;
            }
            btn.onclick = () => openPreviewLightbox(f.name, "image", url);
          }
          box.appendChild(btn);
        });
      });
    }

    $("qualify").onchange = () => {
      $("qualifyOtherWrap").classList.toggle("hidden", $("qualify").value !== "อื่นๆ");
      if ((state.meta?.apply_program || state.meta?.event?.apply_program) === "scisquare") {
        renderDocFields();
      }
    };

    let phoneCheckTimer;
    $("phone").oninput = () => {
      const hint = $("phoneHint");
      if (hint) {
        hint.className = "phone-hint";
        hint.textContent = "";
      }
      clearTimeout(phoneCheckTimer);
      phoneCheckTimer = setTimeout(() => { checkPhoneDuplicate(); }, 450);
    };
    $("phone").onblur = () => checkPhoneDuplicate();
    $("roundId").onchange = () => {
      const id = Number($("roundId").value || 0);
      if (id > 0) {
        state.wantedRoundId = id;
        const url = new URL(location.href);
        if (state.eventCode) url.searchParams.set("event", state.eventCode);
        url.searchParams.set("round", String(id));
        history.replaceState(null, "", url.pathname + url.search + url.hash);
      }
      checkPhoneDuplicate();
      syncExtraQuestions();
      syncApplyFlow();
    };
    document.querySelectorAll('input[name="need_high_power"]').forEach(el => {
      el.onchange = () => syncPowerNote();
    });
    document.querySelectorAll('input[name="need_ice"]').forEach(el => {
      el.onchange = () => syncIceCount();
    });

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
      if (!state.accepting) {
        return showErr("ขณะนี้ยังไม่เปิดรับสมัคร หรือรอบนี้ยังไม่ถึงกำหนดเปิดรับ");
      }
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
      appendEventField(fd);

      $("btnSubmit").disabled = true;
      $("btnSubmit").textContent = "กำลังส่ง...";
      showErr("");
      try {
        const res = await fetch(apiUrl("submit"), { method: "POST", body: fd, credentials: "same-origin" });
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
    $("btnFileTypeOk")?.addEventListener("click", () => closeFileTypeModal());
    $("fileTypeModal")?.addEventListener("click", (e) => {
      if (e.target.id === "fileTypeModal") closeFileTypeModal();
    });
    $("btnCloseLightbox")?.addEventListener("click", () => closePreviewLightbox());
    $("previewLightbox")?.addEventListener("click", (e) => {
      if (e.target.id === "previewLightbox") closePreviewLightbox();
    });
    document.addEventListener("keydown", (e) => {
      if (e.key !== "Escape") return;
      if ($("previewLightbox")?.classList.contains("show")) closePreviewLightbox();
      else if ($("fileTypeModal")?.classList.contains("show")) closeFileTypeModal();
      else if ($("exitModal")?.classList.contains("show")) closeExitModal();
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
        appendEventField(fd);
        const res = await fetch(apiUrl("status"), {
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

    // File preview bindings are attached in renderDocFields() after meta load.

    loadMeta().catch(err => {
      state.accepting = false;
      $("statusBanner").className = "banner closed";
      $("statusBanner").innerHTML = `<span class="ban-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg></span>`
        + `<div class="ban-body"><b>เปิดหน้าสมัครไม่สำเร็จ</b><br>${esc(err.message || err)}</div>`;
      updateGateCards();
    });
  </script>
</body>
</html>
