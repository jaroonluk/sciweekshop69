<?php
/**
 * Authenticated file download for applicant_files (staff only).
 * Serves MinIO (s3://) or local uploads/. Does not redirect to Google Drive.
 * file_serve.php?id=123
 */
require_once __DIR__ . '/auth_lib.php';
require_once __DIR__ . '/rbac_lib.php';
require_once __DIR__ . '/vendor_apply_lib.php';
require_once __DIR__ . '/s3_lib.php';

sci_rbac_require_roles(['admin', 'committee', 'finance'], false);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'ไม่พบไฟล์';
  exit;
}

try {
  $st = sci_db()->prepare(
    'SELECT id, original_name, stored_path, mime_type, drive_url FROM applicant_files WHERE id = ? LIMIT 1'
  );
  $st->execute([$id]);
  $row = $st->fetch();
  if (!$row) {
    http_response_code(404);
    echo 'ไม่พบไฟล์';
    exit;
  }

  $stored = trim((string)($row['stored_path'] ?? ''));
  $name = (string)($row['original_name'] ?? 'file');
  $name = preg_replace('/[\r\n"]+/', '', $name) ?: 'file';
  $mime = (string)($row['mime_type'] ?? '');

  if (function_exists('sci_s3_is_stored_path') && sci_s3_is_stored_path($stored)) {
    $get = sci_s3_get_object($stored);
    if (!$get['ok']) {
      http_response_code(404);
      header('Content-Type: text/plain; charset=utf-8');
      echo 'ไม่พบไฟล์ใน MinIO';
      exit;
    }
    if ($mime === '') $mime = (string)($get['content_type'] ?? 'application/octet-stream');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($get['body']));
    header('Content-Disposition: inline; filename="' . $name . '"');
    header('Cache-Control: private, max-age=300');
    echo $get['body'];
    exit;
  }

  $path = sci_vendor_absolute_path($stored);
  if ($path !== null && is_file($path)) {
    if ($mime === '') {
      $mime = sci_vendor_detect_mime($path) ?: 'application/octet-stream';
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="' . $name . '"');
    header('Cache-Control: private, max-age=300');
    readfile($path);
    exit;
  }

  http_response_code(404);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'ไม่พบไฟล์ในระบบ (ย้ายไป MinIO แล้ว หรือยังไม่อัปโหลด)';
  exit;
} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'เปิดไฟล์ไม่สำเร็จ';
  exit;
}
