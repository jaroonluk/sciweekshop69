<?php
/**
 * Public JSON API for vendor registration (no staff login).
 * apply_api.php?action=meta|submit|status|check_phone|captcha
 * Optional: &event={events.code} for multi-event apply links.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/vendor_apply_lib.php';

header('Cache-Control: no-store');

function sci_apply_json($data, int $code = 200): void {
  http_response_code($code);
  while (ob_get_level() > 0) ob_end_clean();
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

/** Optional event code from query/body; empty → null (active event fallback). */
function sci_apply_event_param(?array $body = null): ?string {
  $code = trim((string)($_GET['event'] ?? $_POST['event'] ?? ''));
  if ($code === '' && is_array($body)) {
    $code = trim((string)($body['event'] ?? $body['event_code'] ?? ''));
  }
  return $code !== '' ? $code : null;
}

// Simple rate limit by IP (file-based)
function sci_apply_rate_limit(string $bucket, int $max, int $windowSec): void {
  $ip = preg_replace('/[^0-9a-fA-F:\.]/', '', (string)($_SERVER['REMOTE_ADDR'] ?? '0')) ?: '0';
  $dir = __DIR__ . '/data/uploads/_rate';
  if (!is_dir($dir)) @mkdir($dir, 0750, true);
  $file = $dir . '/' . $bucket . '_' . hash('sha256', $ip) . '.json';
  $now = time();
  $hits = [];
  if (is_file($file)) {
    $raw = @file_get_contents($file);
    $hits = json_decode((string)$raw, true);
    if (!is_array($hits)) $hits = [];
  }
  $hits = array_values(array_filter($hits, static fn($t) => is_int($t) && $t > $now - $windowSec));
  if (count($hits) >= $max) {
    sci_apply_json(['ok' => false, 'error' => 'ส่งคำขอบ่อยเกินไป กรุณาลองใหม่ในอีกสักครู่'], 429);
  }
  $hits[] = $now;
  @file_put_contents($file, json_encode($hits));
}

try {
  $action = $_GET['action'] ?? $_POST['action'] ?? 'meta';
  $eventCode = sci_apply_event_param();

  if ($action === 'meta') {
    sci_apply_rate_limit('meta', 60, 60);
    $meta = sci_vendor_form_meta(true, $eventCode);
    sci_apply_json(['ok' => true] + $meta);
  }

  if ($action === 'captcha') {
    sci_apply_rate_limit('captcha', 40, 60);
    $purpose = (string)($_GET['purpose'] ?? $_POST['purpose'] ?? 'apply');
    if (!in_array($purpose, ['apply', 'status'], true)) $purpose = 'apply';
    sci_apply_json(['ok' => true, 'captcha' => sci_vendor_captcha_issue($purpose)]);
  }

  if ($action === 'check_phone') {
    sci_apply_rate_limit('check_phone', 30, 60);
    $roundId = (int)($_GET['round_id'] ?? $_POST['round_id'] ?? 0);
    $phone = (string)($_GET['phone'] ?? $_POST['phone'] ?? '');
    $body = null;
    if ($roundId <= 0 || $phone === '' || $eventCode === null) {
      $body = json_decode(file_get_contents('php://input') ?: '{}', true);
      if (is_array($body)) {
        if ($roundId <= 0) $roundId = (int)($body['round_id'] ?? 0);
        if ($phone === '') $phone = (string)($body['phone'] ?? '');
        if ($eventCode === null) $eventCode = sci_apply_event_param($body);
      }
    }
    if ($roundId <= 0) sci_apply_json(['ok' => false, 'error' => 'กรุณาเลือกรอบสมัคร'], 400);
    $result = sci_vendor_phone_taken($roundId, $phone, $eventCode);
    sci_apply_json(['ok' => true] + $result);
  }

  if ($action === 'status') {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
      sci_apply_json(['ok' => false, 'error' => 'ต้องใช้ POST พร้อมรหัสป้องกันสแปม'], 405);
    }
    sci_apply_rate_limit('status', 12, 60);
    $phone = (string)($_POST['phone'] ?? '');
    $name = (string)($_POST['name'] ?? '');
    sci_vendor_captcha_verify($_POST, 'status', false);
    $result = sci_vendor_status_lookup($phone, $name, $eventCode);
    $result['status_captcha'] = sci_vendor_captcha_issue('status');
    sci_apply_json(['ok' => true] + $result);
  }

  if ($action === 'submit') {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
      sci_apply_json(['ok' => false, 'error' => 'ต้องใช้ POST'], 405);
    }
    sci_apply_rate_limit('submit', 8, 600);
    // Ensure event from query is on POST if client only put it in URL
    if ($eventCode !== null && trim((string)($_POST['event'] ?? '')) === '') {
      $_POST['event'] = $eventCode;
    }
    $result = sci_vendor_submit($_POST, $_FILES);
    sci_apply_json([
      'ok' => true,
      'message' => 'ส่งใบสมัครสำเร็จ',
      'application' => $result,
    ]);
  }

  sci_apply_json(['ok' => false, 'error' => 'unknown action'], 400);
} catch (InvalidArgumentException $e) {
  sci_apply_json(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
  sci_apply_json(['ok' => false, 'error' => $e->getMessage()], 500);
}
