<?php
// JSON API: never leak HTML warnings into the response body
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/xlsx_lib.php';
require_once __DIR__ . '/db_data_lib.php';
require_once __DIR__ . '/docx_export.php';
require_once __DIR__ . '/auth_lib.php';
require_once __DIR__ . '/rbac_lib.php';
require_once __DIR__ . '/event_admin_lib.php';

sci_rbac_require_roles(['admin', 'committee', 'finance'], true);
sci_rbac_ensure_roles();

function sci_json_out($data, int $code = 200): void {
  http_response_code($code);
  while (ob_get_level() > 0) {
    ob_end_clean();
  }
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function sci_read_json_body(): array {
  static $cached = null;
  if ($cached !== null) return $cached;
  $raw = file_get_contents('php://input');
  $data = json_decode($raw ?: '{}', true);
  $cached = is_array($data) ? $data : [];
  return $cached;
}

function sci_api_actor_id(): ?int {
  $id = (int)(sci_rbac_user()['user_id'] ?? 0);
  return $id > 0 ? $id : null;
}

try {
  $action = $_GET['action'] ?? $_POST['action'] ?? '';
  if ($action === '') {
    $bodyAction = sci_read_json_body()['action'] ?? '';
    $action = is_string($bodyAction) ? $bodyAction : '';
  }
  if ($action === '') $action = 'data';
  sci_apply_round_from_request(sci_read_json_body());

  // Binary download — must run before JSON Content-Type headers
  if ($action === 'export_selected_docx') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    sci_stream_selected_announcement_docx();
  }
  if ($action === 'export_selected_excel') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    sci_stream_selected_announcement_excel();
  }

  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');

  if ($action === 'me') {
    sci_json_out([
      'ok' => true,
      'user' => sci_rbac_public_user(),
      'rbac' => [
        'db_ready' => sci_rbac_db_ready(),
        'staff_count' => sci_rbac_db_ready() ? sci_rbac_staff_count() : 0,
      ],
    ]);
  }

  if ($action === 'health' || $action === 'storage') {
    sci_json_out([
      'ok' => true,
      'round' => sci_round_meta(),
      'rounds' => sci_available_rounds(),
      'storage' => sci_storage_health(),
      'user' => sci_rbac_public_user(),
    ]);
  }

  if ($action === 'rounds') {
    sci_json_out([
      'ok' => true,
      'round' => sci_round_meta(),
      'rounds' => sci_available_rounds(),
    ]);
  }

  if ($action === 'data') {
    $data = sci_parse_applicants();
    sci_save_payload_json($data);
    $data['user'] = sci_rbac_public_user();
    sci_json_out($data);
  }

  if ($action === 'rebuild') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    $data = sci_parse_applicants();
    sci_save_payload_json($data);
    sci_json_out(['ok' => true, 'total' => $data['total_applicants'], 'source' => $data['source'], 'data' => $data, 'storage' => $data['storage'] ?? sci_storage_health()]);
  }

  if ($action === 'save_status') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    $body = sci_read_json_body();
    sci_apply_round_from_request($body);
    $row = (int)($body['row'] ?? 0);
    if ($row < 2) sci_json_out(['ok' => false, 'error' => 'row ไม่ถูกต้อง'], 400);

    $selection = trim((string)($body['selection'] ?? 'รอพิจารณา'));
    $allowed = ['ได้รับการคัดเลือก', 'ไม่ได้รับการคัดเลือก', 'รอพิจารณา'];
    if (!in_array($selection, $allowed, true)) $selection = 'รอพิจารณา';

    $assignedSlot = strtoupper(trim((string)($body['assigned_slot'] ?? '')));
    if ($assignedSlot !== '' && !preg_match('/^[ABCD]\d{1,2}$/', $assignedSlot)) {
      sci_json_out(['ok' => false, 'error' => 'ล็อกร้านไม่ถูกต้อง'], 400);
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

    $result = sci_ensure_status_and_write([$update]);
    $data = sci_parse_applicants();
    sci_save_payload_json($data);
    sci_json_out([
      'ok' => true,
      'result' => $result,
      'message' => $result['message'] ?? 'บันทึกสถานะสำเร็จ',
      'storage' => $data['storage'] ?? sci_storage_health(),
      'data' => $data,
    ]);
  }

  if ($action === 'save_payment') {
    sci_rbac_require_roles(['admin', 'committee', 'finance'], true);
    $body = sci_read_json_body();
    sci_apply_round_from_request($body);
    $row = (int)($body['row'] ?? 0);
    if ($row < 2) sci_json_out(['ok' => false, 'error' => 'row ไม่ถูกต้อง'], 400);

    $paymentStatus = (string)($body['payment_status'] ?? 'unpaid');
    $paymentNote = trim((string)($body['payment_note'] ?? ''));
    $result = sci_save_payment_status($row, $paymentStatus, $paymentNote);
    $data = sci_parse_applicants();
    sci_save_payload_json($data);
    sci_json_out([
      'ok' => true,
      'result' => $result,
      'message' => $result['message'] ?? 'บันทึกสถานะชำระเงินสำเร็จ',
      'storage' => $data['storage'] ?? sci_storage_health(),
      'data' => $data,
    ]);
  }

  if ($action === 'assign_shop') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    $body = sci_read_json_body();
    sci_apply_round_from_request($body);
    $row = (int)($body['row'] ?? 0);
    $slot = strtoupper(trim((string)($body['slot'] ?? '')));
    $allowCross = !empty($body['allow_cross']);
    if ($row < 2) sci_json_out(['ok' => false, 'error' => 'row ไม่ถูกต้อง'], 400);
    if ($slot === '') sci_json_out(['ok' => false, 'error' => 'ต้องระบุล็อคร้าน'], 400);

    $result = sci_assign_shop($row, $slot, null, $allowCross);
    $data = sci_parse_applicants();
    sci_save_payload_json($data);
    sci_json_out([
      'ok' => true,
      'result' => $result,
      'message' => $result['message'] ?? 'คัดเลือกร้านสำเร็จ',
      'storage' => $data['storage'] ?? sci_storage_health(),
      'data' => $data,
    ]);
  }

  if ($action === 'unassign_shop') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    $body = sci_read_json_body();
    sci_apply_round_from_request($body);
    $row = (int)($body['row'] ?? 0);
    $selection = trim((string)($body['selection'] ?? 'รอพิจารณา'));
    if ($row < 2) sci_json_out(['ok' => false, 'error' => 'row ไม่ถูกต้อง'], 400);

    $result = sci_unassign_shop($row, $selection);
    $data = sci_parse_applicants();
    sci_save_payload_json($data);
    sci_json_out([
      'ok' => true,
      'result' => $result,
      'message' => $result['message'] ?? 'ถอนสถานะสำเร็จ',
      'storage' => $data['storage'] ?? sci_storage_health(),
      'data' => $data,
    ]);
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
    sci_rbac_require_roles(['admin', 'committee'], true);
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
    if ($ext === 'csv') {
      sci_json_out(['ok' => false, 'error' => 'กรุณา Export เป็น Excel (.xlsx) จาก Google Sheet แล้วอัปโหลด'], 400);
    }

    // MySQL mode: import Excel into current round, keep a backup copy of the file
    if (sci_use_mysql()) {
      $target = sci_xlsx_write_path();
      $bak = $target . '.preupload.bak';
      if (is_file($target)) @copy($target, $bak);
      if (!move_uploaded_file($f['tmp_name'], $target)) {
        if (!@copy($f['tmp_name'], $target)) {
          sci_json_out(['ok' => false, 'error' => 'บันทึกไฟล์ไม่สำเร็จ'], 500);
        }
        @unlink($f['tmp_name']);
      }
      $import = sci_db_import_xlsx_for_current_round($target);
      $data = sci_parse_applicants();
      sci_save_payload_json($data);
      sci_json_out([
        'ok' => true,
        'total' => $data['total_applicants'],
        'source' => $data['source'],
        'import' => $import,
        'names_sample' => array_slice(array_column($data['applicants'], 'name'), 0, 5),
        'data' => $data,
      ]);
    }

    $target = sci_xlsx_write_path();
    $bak = $target . '.preupload.bak';
    @copy($target, $bak);
    if (!move_uploaded_file($f['tmp_name'], $target)) {
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

  // ---- RBAC: user management (admin only) ----
  if ($action === 'users_list') {
    sci_rbac_require_roles(['admin'], true);
    if (!sci_rbac_db_ready()) {
      sci_json_out(['ok' => false, 'error' => 'ฐานข้อมูลยังไม่พร้อม'], 503);
    }
    sci_json_out([
      'ok' => true,
      'users' => sci_rbac_list_users(),
      'roles' => [
        ['code' => 'admin', 'name_th' => 'ผู้ดูแลระบบ'],
        ['code' => 'committee', 'name_th' => 'กรรมการฝ่ายจัดหารายได้'],
        ['code' => 'finance', 'name_th' => 'เจ้าหน้าที่การเงิน'],
      ],
    ]);
  }

  if ($action === 'eoffice_search') {
    sci_rbac_require_roles(['admin'], true);
    if (!sci_rbac_db_ready()) {
      sci_json_out(['ok' => false, 'error' => 'ฐานข้อมูลยังไม่พร้อม'], 503);
    }
    $q = trim((string)($_GET['q'] ?? ''));
    if ($q === '') {
      $body = sci_read_json_body();
      $q = trim((string)($body['q'] ?? ''));
    }
    try {
      $people = sci_eoffice_list_personnel($q !== '' ? $q : null, 40);
    } catch (Throwable $e) {
      sci_json_out(['ok' => false, 'error' => 'ค้นหา eoffice ไม่สำเร็จ: ' . $e->getMessage()], 500);
    }
    sci_json_out(['ok' => true, 'q' => $q, 'people' => $people]);
  }

  if ($action === 'users_upsert') {
    sci_rbac_require_roles(['admin'], true);
    if (!sci_rbac_db_ready()) {
      sci_json_out(['ok' => false, 'error' => 'ฐานข้อมูลยังไม่พร้อม'], 503);
    }
    $body = sci_read_json_body();
    $username = trim((string)($body['eoffice_username'] ?? $body['username'] ?? ''));
    $role = strtolower(trim((string)($body['role'] ?? 'finance')));
    $result = sci_rbac_upsert_from_eoffice($username, $role, sci_api_actor_id());
    if (empty($result['ok'])) {
      sci_json_out(['ok' => false, 'error' => $result['error'] ?? 'บันทึกไม่สำเร็จ'], 400);
    }
    sci_json_out([
      'ok' => true,
      'user' => $result['user'],
      'users' => sci_rbac_list_users(),
      'message' => 'บันทึกสิทธิ์ผู้ใช้สำเร็จ',
    ]);
  }

  if ($action === 'users_set_active') {
    sci_rbac_require_roles(['admin'], true);
    $body = sci_read_json_body();
    $id = (int)($body['id'] ?? 0);
    $active = !empty($body['is_active']);
    $result = sci_rbac_set_user_active($id, $active, sci_api_actor_id());
    if (empty($result['ok'])) {
      sci_json_out(['ok' => false, 'error' => $result['error'] ?? 'อัปเดตไม่สำเร็จ'], 400);
    }
    sci_json_out(['ok' => true, 'users' => sci_rbac_list_users()]);
  }

  if ($action === 'users_set_role') {
    sci_rbac_require_roles(['admin'], true);
    $body = sci_read_json_body();
    $id = (int)($body['id'] ?? 0);
    $role = strtolower(trim((string)($body['role'] ?? '')));
    $result = sci_rbac_set_user_role($id, $role, sci_api_actor_id());
    if (empty($result['ok'])) {
      sci_json_out(['ok' => false, 'error' => $result['error'] ?? 'อัปเดตไม่สำเร็จ'], 400);
    }
    sci_json_out(['ok' => true, 'users' => sci_rbac_list_users()]);
  }

  if ($action === 'audit_logs') {
    sci_rbac_require_roles(['admin'], true);
    if (!sci_rbac_db_ready()) {
      sci_json_out(['ok' => false, 'error' => 'ฐานข้อมูลยังไม่พร้อม'], 503);
    }
    $limit = (int)($_GET['limit'] ?? 100);
    $offset = (int)($_GET['offset'] ?? 0);
    $rows = sci_rbac_list_audit_logs($limit, $offset);
    $extra = sci_admin_action_labels();
    foreach ($rows as &$row) {
      $act = (string)($row['action'] ?? '');
      $row['action_label'] = $extra[$act] ?? sci_rbac_action_label($act);
    }
    unset($row);
    sci_json_out(['ok' => true, 'logs' => $rows]);
  }

  // ---- Event / zone / slot admin (operator) ----
  if ($action === 'events_list') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    sci_json_out(['ok' => true, 'events' => sci_admin_list_events()]);
  }

  if ($action === 'events_get') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
      $body = sci_read_json_body();
      $id = (int)($body['id'] ?? 0);
    }
    if ($id <= 0) sci_json_out(['ok' => false, 'error' => 'ต้องระบุ id กิจกรรม'], 400);
    sci_json_out(['ok' => true] + sci_admin_get_event($id));
  }

  if ($action === 'events_save') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    $body = sci_read_json_body();
    $detail = sci_admin_save_event($body, sci_api_actor_id());
    sci_json_out(['ok' => true, 'message' => 'บันทึกกิจกรรมสำเร็จ'] + $detail + ['events' => sci_admin_list_events()]);
  }

  if ($action === 'events_set_active') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    $body = sci_read_json_body();
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) sci_json_out(['ok' => false, 'error' => 'ต้องระบุ id กิจกรรม'], 400);
    $result = sci_admin_set_active_event($id, sci_api_actor_id());
    sci_json_out($result + ['message' => 'ตั้งเป็นกิจกรรมที่ใช้งานแล้ว']);
  }

  if ($action === 'events_delete') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    $body = sci_read_json_body();
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) sci_json_out(['ok' => false, 'error' => 'ต้องระบุ id กิจกรรม'], 400);
    $result = sci_admin_delete_event($id, sci_api_actor_id());
    sci_json_out($result + ['message' => 'ลบกิจกรรมสำเร็จ']);
  }

  if ($action === 'events_copy') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    $body = sci_read_json_body();
    $detail = sci_admin_copy_event_structure($body, sci_api_actor_id());
    sci_json_out(['ok' => true, 'message' => 'คัดลอกโซน/ล็อกสำเร็จ'] + $detail + ['events' => sci_admin_list_events()]);
  }

  if ($action === 'rounds_save') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    $body = sci_read_json_body();
    $isUpdate = (int)($body['id'] ?? 0) > 0;
    $detail = sci_admin_save_round($body, sci_api_actor_id());
    sci_json_out(['ok' => true, 'message' => $isUpdate ? 'บันทึกรอบแล้ว' : 'เพิ่มรอบแล้ว'] + $detail);
  }

  if ($action === 'rounds_delete') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    $body = sci_read_json_body();
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) sci_json_out(['ok' => false, 'error' => 'ต้องระบุ id รอบ'], 400);
    $detail = sci_admin_delete_round($id, sci_api_actor_id());
    sci_json_out(['ok' => true, 'message' => 'ลบรอบสำเร็จ'] + $detail);
  }

  if ($action === 'zones_save') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    $body = sci_read_json_body();
    $detail = sci_admin_save_zone($body, sci_api_actor_id());
    sci_json_out(['ok' => true, 'message' => 'บันทึกโซนสำเร็จ'] + $detail);
  }

  if ($action === 'zones_delete') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    $body = sci_read_json_body();
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) sci_json_out(['ok' => false, 'error' => 'ต้องระบุ id โซน'], 400);
    $detail = sci_admin_delete_zone($id, sci_api_actor_id());
    sci_json_out(['ok' => true, 'message' => 'ลบโซนสำเร็จ'] + $detail);
  }

  if ($action === 'slots_save') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    $body = sci_read_json_body();
    $detail = sci_admin_save_slot($body, sci_api_actor_id());
    sci_json_out(['ok' => true, 'message' => 'บันทึกล็อกสำเร็จ'] + $detail);
  }

  if ($action === 'slots_delete') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    $body = sci_read_json_body();
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) sci_json_out(['ok' => false, 'error' => 'ต้องระบุ id ล็อก'], 400);
    $detail = sci_admin_delete_slot($id, sci_api_actor_id());
    sci_json_out(['ok' => true, 'message' => 'ลบล็อกสำเร็จ'] + $detail);
  }

  if ($action === 'slots_swap') {
    sci_rbac_require_roles(['admin', 'committee'], true);
    $body = sci_read_json_body();
    $detail = sci_admin_swap_assignments($body, sci_api_actor_id());
    sci_json_out(['ok' => true, 'message' => 'สลับล็อกสำเร็จ'] + $detail);
  }

  if ($action === 'alumni_sync') {
    sci_rbac_require_roles(['admin', 'committee', 'finance'], true);
    $body = sci_read_json_body();
    $eventId = (int)($body['event_id'] ?? 0);
    $result = sci_db_sync_event_alumni($eventId > 0 ? $eventId : null);
    $data = sci_parse_applicants();
    sci_save_payload_json($data);
    sci_json_out([
      'ok' => true,
      'message' => 'ซิงก์รายชื่อผู้ได้รับคัดเลือกเข้า alumni แล้ว (' . $result['synced'] . ' ราย · ค้างชำระ ' . $result['unpaid'] . ')',
      'sync' => $result,
      'data' => $data,
    ]);
  }

  sci_json_out(['ok' => false, 'error' => 'unknown action'], 400);
} catch (Throwable $e) {
  sci_json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
