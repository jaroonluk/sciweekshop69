<?php
// JSON API: never leak HTML warnings into the response body
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/xlsx_lib.php';

function sci_json_out($data, int $code = 200): void {
  http_response_code($code);
  while (ob_get_level() > 0) {
    ob_end_clean();
  }
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function sci_read_json_body(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw ?: '{}', true);
  return is_array($data) ? $data : [];
}

try {
  $action = $_GET['action'] ?? $_POST['action'] ?? 'data';

  if ($action === 'data') {
    $data = sci_parse_applicants();
    sci_save_payload_json($data);
    sci_json_out($data);
  }

  if ($action === 'rebuild') {
    $data = sci_parse_applicants();
    sci_save_payload_json($data);
    sci_json_out(['ok' => true, 'total' => $data['total_applicants'], 'source' => $data['source'], 'data' => $data]);
  }

  if ($action === 'save_status') {
    $body = sci_read_json_body();
    $row = (int)($body['row'] ?? 0);
    if ($row < 2) sci_json_out(['ok' => false, 'error' => 'row ไม่ถูกต้อง'], 400);

    $selection = trim((string)($body['selection'] ?? 'รอพิจารณา'));
    $allowed = ['ได้รับการคัดเลือก', 'ไม่ได้รับการคัดเลือก', 'รอพิจารณา'];
    if (!in_array($selection, $allowed, true)) $selection = 'รอพิจารณา';

    $assignedSlot = strtoupper(trim((string)($body['assigned_slot'] ?? '')));
    if ($assignedSlot !== '' && !preg_match('/^[ABCD]\d{1,2}$/', $assignedSlot)) {
      sci_json_out(['ok' => false, 'error' => 'ล็อคร้านไม่ถูกต้อง'], 400);
    }
    if ($selection !== 'ได้รับการคัดเลือก') {
      $assignedSlot = '';
    }

    $update = [
      'row' => $row,
      'status' => trim((string)($body['status'] ?? '')),
      'missing_detail' => trim((string)($body['missing_detail'] ?? '')),
      'review_note' => trim((string)($body['review_note'] ?? '')),
      'selection' => $selection,
      'reviewed_at' => trim((string)($body['reviewed_at'] ?? date('Y-m-d H:i:s'))),
      'assigned_slot' => $assignedSlot,
    ];

    // Status-only write — never deletes Excel applicant rows
    $result = sci_ensure_status_and_write([$update]);
    $data = sci_parse_applicants();
    sci_save_payload_json($data);
    sci_json_out(['ok' => true, 'result' => $result, 'data' => $data]);
  }

  if ($action === 'assign_shop') {
    $body = sci_read_json_body();
    $row = (int)($body['row'] ?? 0);
    $slot = strtoupper(trim((string)($body['slot'] ?? '')));
    $allowCross = !empty($body['allow_cross']);
    if ($row < 2) sci_json_out(['ok' => false, 'error' => 'row ไม่ถูกต้อง'], 400);
    if ($slot === '') sci_json_out(['ok' => false, 'error' => 'ต้องระบุล็อคร้าน'], 400);

    $result = sci_assign_shop($row, $slot, null, $allowCross);
    $data = sci_parse_applicants();
    sci_save_payload_json($data);
    sci_json_out(['ok' => true, 'result' => $result, 'data' => $data]);
  }

  if ($action === 'unassign_shop') {
    $body = sci_read_json_body();
    $row = (int)($body['row'] ?? 0);
    $selection = trim((string)($body['selection'] ?? 'รอพิจารณา'));
    if ($row < 2) sci_json_out(['ok' => false, 'error' => 'row ไม่ถูกต้อง'], 400);

    $result = sci_unassign_shop($row, $selection);
    $data = sci_parse_applicants();
    sci_save_payload_json($data);
    sci_json_out(['ok' => true, 'result' => $result, 'data' => $data]);
  }

  if ($action === 'shop_report') {
    $data = sci_parse_applicants();
    sci_json_out([
      'ok' => true,
      'shop_report' => $data['shop_report'],
      'total_slots' => count($data['shop_report']),
      'filled' => count(array_filter($data['shop_report'], fn($r) => $r['filled'])),
      'policy' => $data['policy'] ?? '',
    ]);
  }

  if ($action === 'upload') {
    if (!isset($_FILES['file'])) {
      sci_json_out(['ok' => false, 'error' => 'ไม่พบไฟล์'], 400);
    }
    $f = $_FILES['file'];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      sci_json_out(['ok' => false, 'error' => 'อัปโหลดล้มเหลว code=' . ($f['error'] ?? '?')], 400);
    }
    $name = $f['name'] ?? '';
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'csv'], true)) {
      sci_json_out(['ok' => false, 'error' => 'รองรับเฉพาะ .xlsx หรือ .csv'], 400);
    }

    $target = sci_xlsx_path();
    // If csv, save beside and tell user to export xlsx - or convert simple csv
    if ($ext === 'csv') {
      sci_json_out(['ok' => false, 'error' => 'กรุณา Export เป็น Excel (.xlsx) จาก Google Sheet แล้วอัปโหลด'], 400);
    }

    $bak = $target . '.preupload.bak';
    @copy($target, $bak);
    if (!move_uploaded_file($f['tmp_name'], $target)) {
      // Windows fallback
      if (!copy($f['tmp_name'], $target)) {
        sci_json_out(['ok' => false, 'error' => 'บันทึกไฟล์ไม่สำเร็จ'], 500);
      }
      @unlink($f['tmp_name']);
    }

    $data = sci_parse_applicants($target);
    sci_save_payload_json($data);
    sci_json_out([
      'ok' => true,
      'total' => $data['total_applicants'],
      'source' => $data['source'],
      'names_sample' => array_slice(array_column($data['applicants'], 'name'), 0, 5),
      'data' => $data,
    ]);
  }

  sci_json_out(['ok' => false, 'error' => 'unknown action'], 400);
} catch (Throwable $e) {
  sci_json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
