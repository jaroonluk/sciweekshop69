<?php
require_once __DIR__ . '/auth_lib.php';

sci_auth_start_session();

if (sci_auth_logged_in()) {
  header('Location: ' . sci_auth_url('index.php'));
  exit;
}

$error = trim((string)($_GET['error'] ?? ''));
$next = (string)($_GET['next'] ?? 'index.php');
$next = sci_auth_sanitize_next($next);

if (isset($_GET['go']) && $_GET['go'] === 'google') {
  if (!sci_auth_configured()) {
    header('Location: ' . sci_auth_url('login.php') . '?error=' . rawurlencode('ยังไม่ได้ตั้งค่า Google Client ID/Secret'));
    exit;
  }
  $state = bin2hex(random_bytes(16));
  $_SESSION['oauth_state'] = $state;
  $_SESSION['oauth_next'] = $next;
  header('Location: ' . sci_auth_google_authorize_url($state));
  exit;
}

$configured = sci_auth_configured();
$redirectUri = sci_auth_redirect_uri();
$errorText = [
  'access_denied' => 'คุณยกเลิกการเข้าสู่ระบบด้วย Google',
  'state' => 'เซสชันไม่ถูกต้อง กรุณาลองใหม่',
  'not_allowed' => 'บัญชีนี้ไม่มีสิทธิ์เข้าใช้งานระบบ',
  'config' => 'ยังไม่ได้ตั้งค่า Google OAuth',
][$error] ?? ($error !== '' ? $error : '');
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>เข้าสู่ระบบ · SCI Shop Review</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    :root {
      --sun: #ffd84d;
      --ink: #1c160e;
      --muted: #6a5f50;
      --ok: #187a48;
      --danger: #c0392b;
      --teal: #0d8a7f;
    }
    * { box-sizing: border-box; }
    html, body { height: 100%; margin: 0; }
    body {
      font-family: "Sarabun", sans-serif;
      color: var(--ink);
      min-height: 100vh;
      min-height: 100dvh;
      display: grid;
      place-items: center;
      padding: 1.25rem;
      background:
        radial-gradient(1000px 520px at 8% -10%, #ffe27a 0%, transparent 55%),
        radial-gradient(900px 480px at 100% 0%, #ffb347 0%, transparent 52%),
        radial-gradient(700px 420px at 70% 110%, #ff8a5b55 0%, transparent 55%),
        linear-gradient(165deg, #fff1b8, #fff8e8 42%, #ffe9c8);
      overflow-x: hidden;
      position: relative;
    }
    body::before,
    body::after {
      content: "";
      position: fixed;
      border-radius: 50%;
      pointer-events: none;
      z-index: 0;
      filter: blur(2px);
    }
    body::before {
      width: 280px; height: 280px;
      left: -80px; bottom: -60px;
      background: rgba(255, 216, 77, .35);
      animation: float 9s ease-in-out infinite;
    }
    body::after {
      width: 220px; height: 220px;
      right: -50px; top: 18%;
      background: rgba(255, 138, 91, .22);
      animation: float 11s ease-in-out infinite reverse;
    }
    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-18px); }
    }
    @keyframes rise {
      from { opacity: 0; transform: translateY(18px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .shell {
      width: min(980px, 100%);
      display: grid;
      grid-template-columns: 1.05fr .95fr;
      gap: 0;
      background: rgba(255,252,240,.88);
      border: 1px solid rgba(28,22,14,.08);
      border-radius: 28px;
      overflow: hidden;
      box-shadow: 0 30px 80px rgba(140, 80, 0, .18);
      position: relative;
      z-index: 1;
      animation: rise .55s ease-out both;
    }
    .hero {
      padding: 2.2rem 2rem 2rem;
      background:
        linear-gradient(145deg, rgba(255,213,74,.95), rgba(255,179,71,.92) 55%, rgba(255,138,91,.9)),
        url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.12'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
      color: #2a210f;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 1.5rem;
      min-height: 520px;
    }
    .brand-mark {
      display: inline-flex;
      align-items: center;
      gap: .7rem;
    }
    .logo {
      width: 52px; height: 52px;
      border-radius: 16px;
      background: #1c160e;
      color: var(--sun);
      display: grid;
      place-items: center;
      box-shadow: 0 10px 24px rgba(0,0,0,.18);
    }
    .logo svg { width: 28px; height: 28px; }
    .brand-text strong {
      display: block;
      font-family: "Chakra Petch", sans-serif;
      font-size: 1.35rem;
      font-weight: 700;
      line-height: 1.15;
    }
    .brand-text span {
      font-size: .92rem;
      opacity: .85;
    }
    .hero h1 {
      margin: 0;
      font-family: "Chakra Petch", sans-serif;
      font-size: clamp(1.7rem, 3vw, 2.25rem);
      line-height: 1.2;
      max-width: 14ch;
    }
    .hero p.lead {
      margin: .7rem 0 0;
      font-size: 1.02rem;
      line-height: 1.55;
      max-width: 32ch;
      opacity: .92;
    }
    .feature-grid {
      display: grid;
      gap: .65rem;
    }
    .feature {
      display: flex;
      align-items: center;
      gap: .75rem;
      background: rgba(255,255,255,.42);
      border: 1px solid rgba(255,255,255,.5);
      border-radius: 14px;
      padding: .7rem .85rem;
      backdrop-filter: blur(6px);
      animation: rise .6s ease-out both;
    }
    .feature:nth-child(2) { animation-delay: .08s; }
    .feature:nth-child(3) { animation-delay: .16s; }
    .feature:nth-child(4) { animation-delay: .24s; }
    .feature-ico {
      width: 38px; height: 38px;
      border-radius: 12px;
      background: #1c160e;
      color: #ffd84d;
      display: grid;
      place-items: center;
      flex: 0 0 auto;
    }
    .feature-ico svg { width: 20px; height: 20px; }
    .feature b {
      display: block;
      font-family: "Chakra Petch", sans-serif;
      font-size: .95rem;
    }
    .feature span {
      font-size: .82rem;
      opacity: .85;
    }
    .panel {
      padding: 2.2rem 1.9rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 1.2rem;
      background: linear-gradient(180deg, #fffdf8, #fff8ec);
    }
    .panel h2 {
      margin: 0;
      font-family: "Chakra Petch", sans-serif;
      font-size: 1.55rem;
    }
    .panel .sub {
      margin: .35rem 0 0;
      color: var(--muted);
      line-height: 1.5;
    }
    .alert {
      background: #fdf2f0;
      color: #8e2a22;
      border: 1px solid #f0c2bc;
      border-radius: 14px;
      padding: .75rem .9rem;
      font-size: .92rem;
    }
    .alert.warn {
      background: #fff8e8;
      color: #8a5a00;
      border-color: #f0d48a;
    }
    .google-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .75rem;
      width: 100%;
      border: 1px solid rgba(28,22,14,.12);
      background: #fff;
      color: var(--ink);
      border-radius: 999px;
      padding: .95rem 1.2rem;
      font-family: "Chakra Petch", sans-serif;
      font-weight: 700;
      font-size: 1.05rem;
      text-decoration: none;
      cursor: pointer;
      box-shadow: 0 10px 28px rgba(140, 90, 0, .12);
      transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
    }
    .google-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 14px 32px rgba(140, 90, 0, .18);
      background: #fffefb;
    }
    .google-btn:active { transform: translateY(0); }
    .google-btn[aria-disabled="true"] {
      opacity: .55;
      pointer-events: none;
    }
    .g-icon {
      width: 22px; height: 22px;
      flex: 0 0 auto;
    }
    .meta-box {
      display: grid;
      gap: .55rem;
      padding: 1rem;
      border-radius: 16px;
      background: rgba(255, 248, 232, .9);
      border: 1px dashed rgba(28,22,14,.12);
    }
    .meta-row {
      display: flex;
      gap: .65rem;
      align-items: flex-start;
      font-size: .86rem;
      color: var(--muted);
      line-height: 1.45;
    }
    .meta-row svg {
      width: 18px; height: 18px;
      flex: 0 0 auto;
      margin-top: .1rem;
      color: var(--teal);
    }
    .meta-row code {
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size: .78rem;
      color: var(--ink);
      word-break: break-all;
    }
    .foot {
      font-size: .8rem;
      color: var(--muted);
      text-align: center;
    }
    @media (max-width: 860px) {
      .shell { grid-template-columns: 1fr; }
      .hero { min-height: auto; }
    }
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero" aria-label="แนะนำระบบ">
      <div>
        <div class="brand-mark">
          <div class="logo" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 9.5 12 4l9 5.5"/>
              <path d="M5 10.5V19a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8.5"/>
              <path d="M9 20v-5h6v5"/>
            </svg>
          </div>
          <div class="brand-text">
            <strong>SCI Shop Review</strong>
            <span>ร้านค้าประจำปี 2569</span>
          </div>
        </div>
        <h1>ระบบพิจารณา ร้านค้าคณะวิทย์</h1>
        <p class="lead">เข้าสู่ระบบเพื่อตรวจเอกสาร คัดเลือกร้าน และติดตามสถานะชำระเงินในที่เดียว</p>
      </div>

      <div class="feature-grid">
        <div class="feature">
          <div class="feature-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 11l3 3L22 4"/>
              <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
          </div>
          <div>
            <b>ตรวจเอกสาร</b>
            <span>ตรวจความครบถ้วนและคุณสมบัติผู้สมัคร</span>
          </div>
        </div>
        <div class="feature">
          <div class="feature-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2.8 14.7 8.3l6.1.9-4.4 4.3 1 6.1L12 16.7 6.6 19.6l1-6.1-4.4-4.3 6.1-.9L12 2.8z"/>
            </svg>
          </div>
          <div>
            <b>คัดเลือกร้าน</b>
            <span>จัดล็อกและอนุมัติร้านตามประเภท</span>
          </div>
        </div>
        <div class="feature">
          <div class="feature-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="5" width="18" height="14" rx="2"/>
              <path d="M3 10h18"/>
              <path d="M8 15h3"/>
            </svg>
          </div>
          <div>
            <b>การเงิน</b>
            <span>ติ๊กสถานะชำระเงินสำหรับปีต่อไป</span>
          </div>
        </div>
        <div class="feature">
          <div class="feature-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
              <rect x="5" y="11" width="14" height="10" rx="2"/>
              <path d="M8 11V8a4 4 0 0 1 8 0v3"/>
            </svg>
          </div>
          <div>
            <b>เข้าถึงเฉพาะทีมงาน</b>
            <span>ล็อกอินด้วย Google เพื่อความปลอดภัย</span>
          </div>
        </div>
      </div>
    </section>

    <section class="panel">
      <div>
        <h2>เข้าสู่ระบบ</h2>
        <p class="sub">ใช้บัญชี Google ที่ได้รับสิทธิ์เพื่อเปิดหน้าพิจารณาและบันทึกสถานะ</p>
      </div>

      <?php if ($errorText !== ''): ?>
        <div class="alert" role="alert"><?= htmlspecialchars($errorText, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <?php if (!$configured): ?>
        <div class="alert warn">ยังไม่ได้ตั้งค่าไฟล์ <code>data/auth_secrets.php</code></div>
      <?php endif; ?>

      <a class="google-btn" href="login.php?go=google&amp;next=<?= rawurlencode($next) ?>" <?= $configured ? '' : 'aria-disabled="true"' ?>>
        <svg class="g-icon" viewBox="0 0 24 24" aria-hidden="true">
          <path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.4h6.4c-.3 1.5-1.1 2.7-2.4 3.6v3h3.9c2.3-2.1 3.6-5.2 3.6-8.7z"/>
          <path fill="#34A853" d="M12 24c3.2 0 5.9-1.1 7.9-2.9l-3.9-3c-1.1.7-2.5 1.2-4 1.2-3.1 0-5.7-2.1-6.6-4.9H1.4v3.1C3.4 21.3 7.4 24 12 24z"/>
          <path fill="#FBBC05" d="M5.4 14.4c-.2-.7-.4-1.4-.4-2.4s.1-1.7.4-2.4V6.5H1.4C.5 8.2 0 10 0 12s.5 3.8 1.4 5.5l4-3.1z"/>
          <path fill="#EA4335" d="M12 4.8c1.7 0 3.3.6 4.5 1.8l3.4-3.4C17.9 1.1 15.2 0 12 0 7.4 0 3.4 2.7 1.4 6.5l4 3.1C6.3 6.9 8.9 4.8 12 4.8z"/>
        </svg>
        เข้าสู่ระบบด้วย Google
      </a>

     
      <p class="foot">คณะวิทยาศาสตร์ · มหาวิทยาลัยขอนแก่น</p>
    </section>
  </div>
</body>
</html>
