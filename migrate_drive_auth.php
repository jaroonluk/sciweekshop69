<?php
/**
 * One-time OAuth to get Drive readonly refresh_token for migrate_drive_to_minio.php
 *
 * Uses the SAME redirect URI as normal login (auth_callback.php).
 * Browser: http://localhost/sci_shop/migrate_drive_auth.php
 */
declare(strict_types=1);

require_once __DIR__ . '/auth_lib.php';
require_once __DIR__ . '/drive_lib.php';

sci_auth_start_session();

if (!sci_auth_configured()) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "ยังไม่ได้ตั้งค่า Google OAuth ใน data/auth_secrets.php\n";
  exit;
}

$cfg = sci_drive_migrate_config();
if ($cfg['client_id'] === '' || $cfg['client_secret'] === '') {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "ยังไม่มี Google client_id/secret\n";
  exit;
}

$redirect = sci_auth_redirect_uri();

if (!empty($_GET['done'])) {
  $has = trim((string)sci_drive_migrate_config()['refresh_token']) !== '';
  header('Content-Type: text/html; charset=utf-8');
  if ($has) {
    echo '<pre style="font:16px/1.5 system-ui;padding:1.5rem">';
    echo "บันทึก Drive refresh_token แล้ว\n\n";
    echo "รันคำสั่ง:\n";
    echo "  C:\\xampp\\php\\php.exe migrate_drive_to_minio.php\n";
    echo '</pre>';
  } else {
    echo '<pre style="font:16px/1.5 system-ui;padding:1.5rem;color:#9b1c1c">';
    echo "ยังไม่มี refresh_token — ลองเปิดหน้านี้ใหม่แล้วอนุญาตสิทธิ์ Drive อีกครั้ง\n";
    echo "หรือถอนสิทธิ์แอปใน https://myaccount.google.com/permissions แล้วลองใหม่\n";
    echo '</pre>';
  }
  exit;
}

if (!empty($_GET['error'])) {
  header('Content-Type: text/html; charset=utf-8');
  echo '<pre style="font:16px/1.5 system-ui;padding:1.5rem;color:#9b1c1c">';
  echo 'OAuth ไม่สำเร็จ: ' . htmlspecialchars((string)$_GET['error'], ENT_QUOTES, 'UTF-8') . "\n";
  echo 'redirect_uri ที่ใช้: ' . htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') . "\n";
  echo "\nลองใหม่: เปิดหน้านี้ใหม่ แล้วกด「ดำเนินการต่อด้วย Google」ครั้งเดียว\n";
  echo '</pre>';
  exit;
}

if (!empty($_GET['go'])) {
  // Create state ONLY when starting OAuth (not on the landing/error pages)
  $state = sci_auth_oauth_begin('drive_migrate', [
    'oauth_drive_migrate' => 1,
    'oauth_next' => 'migrate_drive_auth.php?done=1',
  ]);
  $url = sci_auth_google_authorize_url($state, [
    'scope' => 'openid email profile https://www.googleapis.com/auth/drive.readonly',
    'access_type' => 'offline',
    'prompt' => 'consent',
  ]);
  // Flush session before leaving to Google (avoids lost oauth_state)
  session_write_close();
  header('Location: ' . $url);
  exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>อนุญาต Google Drive สำหรับย้ายไฟล์</title>
  <style>
    body { font-family: "Segoe UI", system-ui, sans-serif; max-width: 40rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
    code { background: #f3f4f6; padding: .1rem .35rem; border-radius: 4px; word-break: break-all; }
    .btn { display: inline-block; margin-top: 1rem; padding: .55rem 1rem; background: #1a73e8; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; }
    .note { color: #555; font-size: .95rem; }
  </style>
</head>
<body>
  <h1>อนุญาตอ่าน Google Drive</h1>
  <p>ใช้บัญชีเจ้าของ Google Form / ไฟล์แนบใน Excel แล้วกดอนุญาตสิทธิ์ <b>Drive (อ่านอย่างเดียว)</b></p>
  <p class="note">ใช้ redirect URI เดียวกับหน้า login:<br><code><?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?></code></p>
  <p class="note">ต้องเปิด <b>Google Drive API</b> ในโปรเจกต์ OAuth เดียวกันด้วย</p>
  <a class="btn" href="?go=1" rel="noopener">ดำเนินการต่อด้วย Google</a>
</body>
</html>
