<?php
require_once __DIR__ . '/auth_lib.php';
sci_auth_require_login(false);

$user = sci_auth_user();
$userJson = json_encode([
  'email' => $user['email'] ?? '',
  'name' => $user['name'] ?? '',
  'picture' => $user['picture'] ?? '',
], JSON_UNESCAPED_UNICODE);

$html = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'index.html');
if ($html === false) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'ไม่พบไฟล์ index.html';
  exit;
}

$inject = '<script>window.__SCI_AUTH_USER__=' . $userJson . ';</script>';
if (stripos($html, '</head>') !== false) {
  $html = preg_replace('/<\/head>/i', $inject . '</head>', $html, 1);
} else {
  $html = $inject . $html;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
echo $html;
