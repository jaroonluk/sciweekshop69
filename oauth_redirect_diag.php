<?php
/**
 * Prints only the OAuth redirect URI the app will send (no secrets).
 * Localhost / 127.0.0.1 only.
 */
$remote = (string)($_SERVER['REMOTE_ADDR'] ?? 'cli');
if (PHP_SAPI !== 'cli' && !in_array($remote, ['127.0.0.1', '::1'], true)) {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Forbidden\n";
  exit;
}

require_once __DIR__ . '/auth_lib.php';

$out = [
  'configured' => sci_auth_configured(),
  'current_request_host' => sci_auth_request_host(),
  'current_redirect_uri' => sci_auth_redirect_uri(),
  'add_these_in_google_cloud_authorized_redirect_uris' => [
    'http://127.0.0.1/sci_shop/auth_callback.php',
    'http://localhost/sci_shop/auth_callback.php',
  ],
  'steps' => [
    '1. เปิด https://console.cloud.google.com/apis/credentials',
    '2. เลือก OAuth 2.0 Client ID ของแอปนี้',
    '3. ช่อง Authorized redirect URIs → Add URI',
    '4. วาง URI จาก current_redirect_uri ให้ตรงทุกตัวอักษร แล้ว Save',
    '5. รอ 1–5 นาที แล้วลองล็อกอินใหม่ (ใช้ URL เดิม เช่น http://127.0.0.1/sci_shop/)',
  ],
];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
