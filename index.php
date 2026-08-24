<?php
require_once __DIR__ . '/auth_lib.php';
require_once __DIR__ . '/rbac_lib.php';
sci_auth_require_login(false);

if (!sci_rbac_is_staff()) {
  http_response_code(403);
  header('Content-Type: text/html; charset=utf-8');
  echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>ไม่มีสิทธิ์</title></head><body style="font-family:sans-serif;padding:2rem;max-width:36rem;line-height:1.6">';
  echo '<h1>ไม่มีสิทธิ์เข้าใช้งาน</h1>';
  echo '<p>ระบบนี้ใช้สำหรับกรรมการฝ่ายจัดหารายได้เท่านั้น หากท่านต้องการใช้งาน กรุณาติดต่อผู้ดูแลระบบ</p>';
  echo '<p><a href="logout.php">ออกจากระบบ</a></p></body></html>';
  exit;
}

$userJson = json_encode(sci_rbac_public_user(), JSON_UNESCAPED_UNICODE);
// Path of this install from the live request (not public_base_url — that can differ and break API calls).
$appDir = sci_auth_detect_app_dir();
$appDirJson = json_encode($appDir, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$html = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'index.html');
if ($html === false) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'ไม่พบไฟล์ index.html';
  exit;
}

$inject = '<script>window.__SCI_AUTH_USER__=' . $userJson
  . ';window.__SCI_APP_DIR__=' . $appDirJson
  . ';</script>';
if (stripos($html, '</head>') !== false) {
  $html = preg_replace('/<\/head>/i', $inject . '</head>', $html, 1);
} else {
  $html = $inject . $html;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
echo $html;
