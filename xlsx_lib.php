<?php
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/sci_zip.php';

const SCI_STATUS_HEADERS = [
  'P' => 'สถานะเอกสาร (ระบบตรวจ)',
  'Q' => 'เอกสารที่ขาด / รายละเอียด',
  'R' => 'คุณสมบัติ / หมายเหตุการพิจารณา',
  'S' => 'ผลการคัดเลือก',
  'T' => 'วันเวลาที่ตรวจ',
  'U' => 'ล็อกร้านที่ได้รับ',
];

/** Columns that the system may UPDATE. Never delete applicant rows or alter A–O form data. */
const SCI_WRITABLE_STATUS_COLS = ['P', 'Q', 'R', 'S', 'T', 'U'];

function sci_dir(): string {
  return __DIR__;
}

function sci_max_round(): int {
  if (function_exists('sci_use_mysql') && sci_use_mysql() && function_exists('sci_db_max_round')) {
    try {
      return max(1, sci_db_max_round());
    } catch (Throwable $e) {
      // fall through
    }
  }
  return 3;
}

function sci_normalize_round($round): int {
  $n = (int)$round;
  if ($n >= 1 && $n <= sci_max_round()) return $n;
  return 1;
}

function sci_set_round($round): int {
  $n = sci_normalize_round($round);
  $GLOBALS['SCI_ROUND'] = $n;
  return $n;
}

function sci_current_round(): int {
  return sci_normalize_round($GLOBALS['SCI_ROUND'] ?? ($_GET['round'] ?? $_POST['round'] ?? 1));
}

function sci_apply_round_from_request(?array $jsonBody = null): int {
  $round = $_GET['round'] ?? $_POST['round'] ?? null;
  if ($round === null && is_array($jsonBody) && array_key_exists('round', $jsonBody)) {
    $round = $jsonBody['round'];
  }
  return sci_set_round($round ?? 1);
}

function sci_round_meta(?int $round = null): array {
  $round = sci_normalize_round($round ?? sci_current_round());
  if (function_exists('sci_use_mysql') && sci_use_mysql() && function_exists('sci_db_active_event')) {
    try {
      $event = sci_db_active_event();
      $year = (int)$event['year_be'];
      $title = (string)$event['title'];
      $label = 'รอบที่ ' . $round;
      $isOpen = null;
      foreach (sci_db_event_rounds() as $r) {
        if ((int)$r['round_no'] === $round) {
          if (!empty($r['title'])) $label = (string)$r['title'];
          $isOpen = (int)($r['is_open'] ?? 0) === 1;
          break;
        }
      }
      return [
        'id' => $round,
        'year' => $year,
        'label' => $label,
        'short' => 'รอบ ' . $round,
        'title' => $title . ' · ' . $label,
        'event_id' => (int)$event['id'],
        'event_code' => (string)$event['code'],
        'event_title' => $title,
        'is_open' => $isOpen,
        'status_store' => 'mysql',
        'payload' => 'mysql',
        'xlsx_canonical' => '',
      ];
    } catch (Throwable $e) {
      // fall through to legacy Excel meta
    }
  }
  if ($round === 3) {
    return [
      'id' => 3,
      'year' => 2569,
      'label' => 'รอบที่ 3',
      'short' => 'รอบ 3',
      'title' => 'ร้านค้าประจำปี 2569 รอบที่ 3',
      'status_store' => 'status_store_r3.json',
      'payload' => 'applicants_r3.json',
      'xlsx_canonical' => 'แบบตอบรับ69_รอบ3.xlsx',
    ];
  }
  if ($round === 2) {
    return [
      'id' => 2,
      'year' => 2569,
      'label' => 'รอบที่ 2',
      'short' => 'รอบ 2',
      'title' => 'ร้านค้าประจำปี 2569 รอบที่ 2',
      'status_store' => 'status_store_r2.json',
      'payload' => 'applicants_r2.json',
      'xlsx_canonical' => 'แบบตอบรับ69_รอบ2.xlsx',
    ];
  }
  return [
    'id' => 1,
    'year' => 2569,
    'label' => 'รอบที่ 1',
    'short' => 'รอบ 1',
    'title' => 'ร้านค้าประจำปี 2569 รอบที่ 1',
    'status_store' => 'status_store.json',
    'payload' => 'applicants.json',
    'xlsx_canonical' => 'แบบตอบรับ.xlsx',
  ];
}

function sci_is_followup_round(?int $round = null): bool {
  return sci_normalize_round($round ?? sci_current_round()) >= 2;
}

function sci_xlsx_round_from_name(string $name): int {
  if (preg_match('/_r3\b|round\s*3/i', $name)) return 3;
  if (preg_match('/รอบ\s*ที่\s*3|รอบที่3|รอบ3/u', $name)) return 3;
  if (preg_match('/_r2\b|round\s*2/i', $name)) return 2;
  if (preg_match('/รอบ\s*ที่\s*2|รอบที่2|รอบ2/u', $name)) return 2;
  return 1;
}

function sci_xlsx_is_round2_name(string $name): bool {
  return sci_xlsx_round_from_name($name) === 2;
}

function sci_prior_rounds_label(?int $round = null): string {
  $round = sci_normalize_round($round ?? sci_current_round());
  if ($round <= 2) return 'รอบที่ 1';
  $from = [];
  for ($r = 1; $r < $round; $r++) {
    $from[] = (string)$r;
  }
  if (count($from) === 2) {
    return 'รอบที่ ' . $from[0] . '–' . $from[1];
  }
  return 'รอบที่ ' . implode(', ', $from);
}

/**
 * Writable data directory for status persistence on servers
 * where Excel (.xlsx) may be read-only.
 */
function sci_data_dir(): string {
  $dir = sci_dir() . DIRECTORY_SEPARATOR . 'data';
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }
  return $dir;
}

function sci_status_store_path(?int $round = null): string {
  $meta = sci_round_meta($round);
  return sci_data_dir() . DIRECTORY_SEPARATOR . $meta['status_store'];
}

function sci_is_writable_path(string $path): bool {
  if (is_dir($path)) {
    $probe = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.write_probe_' . getmypid();
    $ok = @file_put_contents($probe, 'ok') !== false;
    if ($ok) @unlink($probe);
    return $ok;
  }
  if (file_exists($path)) {
    return is_writable($path);
  }
  $parent = dirname($path);
  return is_dir($parent) && is_writable($parent);
}

function sci_storage_health(): array {
  if (function_exists('sci_use_mysql') && sci_use_mysql() && function_exists('sci_db_storage_health')) {
    return sci_db_storage_health();
  }
  $meta = sci_round_meta();
  $storeRel = 'data/' . $meta['status_store'];
  $xlsx = null;
  $xlsxWritable = false;
  try {
    $xlsx = sci_xlsx_path();
    $xlsxWritable = sci_is_writable_path($xlsx) && sci_is_writable_path(dirname($xlsx));
  } catch (Throwable $e) {
    $xlsx = null;
  }
  $dataDir = sci_data_dir();
  $store = sci_status_store_path();
  $dataWritable = sci_is_writable_path($dataDir);
  return [
    'data_dir' => basename($dataDir),
    'data_writable' => $dataWritable,
    'status_store' => basename($store),
    'status_store_writable' => $dataWritable && sci_is_writable_path(dirname($store)),
    'xlsx' => $xlsx ? basename($xlsx) : null,
    'xlsx_writable' => $xlsxWritable,
    'can_save_status' => $dataWritable,
    'round' => $meta,
    'hint' => $dataWritable
      ? ($xlsxWritable
        ? 'บันทึกสถานะได้ทั้งไฟล์สถานะและ Excel (' . $meta['title'] . ')'
        : 'บันทึกสถานะได้ที่ ' . $storeRel . ' (Excel บนเซิร์ฟเวอร์เขียนไม่ได้ — ข้อมูลผู้สมัครยังอ่านจาก Excel ได้ตามปกติ)')
      : 'โฟลเดอร์ data/ ยังเขียนไม่ได้ — ให้ตั้งสิทธิ์ chmod 775 หรือ 777 ที่โฟลเดอร์ sci_shop/data',
  ];
}

function sci_load_status_store(?int $round = null): array {
  $path = sci_status_store_path($round);
  if (!is_file($path)) {
    return ['version' => 1, 'updated_at' => null, 'by_row' => []];
  }
  $raw = @file_get_contents($path);
  $data = json_decode($raw ?: '{}', true);
  if (!is_array($data)) {
    return ['version' => 1, 'updated_at' => null, 'by_row' => []];
  }
  if (!isset($data['by_row']) || !is_array($data['by_row'])) {
    $data['by_row'] = [];
  }
  $data['version'] = 1;
  return $data;
}

function sci_save_status_store(array $store): void {
  $dir = sci_data_dir();
  if (!sci_is_writable_path($dir)) {
    throw new RuntimeException(
      'ไม่สามารถบันทึกสถานะได้: โฟลเดอร์ data/ เขียนไม่ได้บนเซิร์ฟเวอร์ — ตั้งสิทธิ์ chmod 775 หรือ 777 ให้โฟลเดอร์ data (และ sci_shop ถ้าจำเป็น)'
    );
  }
  $store['version'] = 1;
  $store['round'] = sci_current_round();
  $store['updated_at'] = date('c');
  if (!isset($store['by_row']) || !is_array($store['by_row'])) {
    $store['by_row'] = [];
  }
  $path = sci_status_store_path();
  $json = json_encode($store, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  $tmp = $path . '.tmp';
  if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
    throw new RuntimeException('เขียนไฟล์สถานะชั่วคราวไม่สำเร็จ (ตรวจสิทธิ์ data/)');
  }
  if (!@rename($tmp, $path)) {
    @unlink($path);
    if (!@rename($tmp, $path)) {
      @unlink($tmp);
      throw new RuntimeException('บันทึก data/' . basename($path) . ' ไม่สำเร็จ');
    }
  }
}

/**
 * Recompute display status + doc_check from stored admin fields + attachment checklist.
 */
function sci_recompute_app_status(array &$app): void {
  $autoMissing = $app['auto_missing'] ?? [];
  $statusRaw = (string)($app['status_raw'] ?? $app['status'] ?? '');
  $missingDetail = (string)($app['missing_detail'] ?? '');
  $note = (string)($app['review_note'] ?? '');
  $reviewedAt = (string)($app['reviewed_at'] ?? '');
  $selection = (string)($app['selection'] ?? 'รอพิจารณา');
  $assignedSlot = strtoupper(trim((string)($app['assigned_slot'] ?? '')));

  $selectionValues = ['ได้รับการคัดเลือก', 'ไม่ได้รับการคัดเลือก', 'รอพิจารณา'];
  if ($selection !== '' && !in_array($selection, $selectionValues, true)) {
    $selection = 'รอพิจารณา';
  }
  if ($selection === '') $selection = 'รอพิจารณา';
  if ($assignedSlot !== '' && !preg_match('/^[ABCD]\d{1,2}$/', $assignedSlot)) {
    $assignedSlot = '';
  }
  if ($selection !== 'ได้รับการคัดเลือก') {
    $assignedSlot = '';
  }

  $sysPass = count($autoMissing) === 0;
  $sysLabel = $sysPass ? 'ครบถ้วน' : 'ไม่ครบถ้วน';
  $adminClean = trim(preg_replace('/\s*\(อัตโนมัติ\)\s*/u', '', $statusRaw));
  $isAutoOnly = ($statusRaw === '' || mb_strpos($statusRaw, 'อัตโนมัติ') !== false);
  $adminStatuses = ['รอตรวจสอบ', 'ครบถ้วน', 'ไม่ครบถ้วน', 'ขาดคุณสมบัติ'];
  $adminReviewed = !$isAutoOnly && in_array($adminClean, $adminStatuses, true);
  $adminStatus = $adminReviewed ? $adminClean : '';
  $adminPass = $adminReviewed && $adminStatus === 'ครบถ้วน';
  $adminFail = $adminReviewed && in_array($adminStatus, ['ไม่ครบถ้วน', 'ขาดคุณสมบัติ'], true);

  if ($adminReviewed) {
    $status = $adminStatus;
    $effSource = 'admin';
    $effPass = $adminPass;
    $effLabel = $adminStatus;
    $effReason = '';
    if ($adminFail) {
      $effReason = $missingDetail !== '' ? $missingDetail : (
        $adminStatus === 'ขาดคุณสมบัติ'
          ? 'Admin ระบุว่าขาดคุณสมบัติ'
          : 'Admin ระบุว่าเอกสารไม่ครบถ้วน'
      );
      if ($note !== '' && $adminStatus === 'ขาดคุณสมบัติ') {
        $effReason = trim($effReason . ($effReason ? ' · ' : '') . $note);
      }
    }
  } else {
    $status = $sysPass ? 'ครบถ้วน (อัตโนมัติ)' : 'รอตรวจสอบ';
    $effSource = 'system';
    $effPass = $sysPass;
    $effLabel = $sysPass ? 'ครบถ้วน (ระบบ)' : 'ไม่ครบถ้วน (ระบบ)';
    $effReason = $sysPass ? '' : ('ระบบตรวจพบขาด: ' . implode(', ', $autoMissing));
  }

  $app['selection'] = $selection;
  $app['assigned_slot'] = $assignedSlot;
  $app['status_raw'] = $statusRaw;
  $app['status'] = $status;
  $app['missing_detail'] = $missingDetail;
  $app['review_note'] = $note;
  $app['reviewed_at'] = $reviewedAt;
  $app['doc_check'] = [
    'system' => [
      'pass' => $sysPass,
      'label' => $sysLabel,
      'missing' => $autoMissing,
    ],
    'admin' => [
      'reviewed' => $adminReviewed,
      'status' => $adminStatus,
      'pass' => $adminPass,
      'fail' => $adminFail,
      'missing_detail' => $missingDetail,
      'review_note' => $note,
      'reviewed_at' => $reviewedAt,
    ],
    'effective' => [
      'source' => $effSource,
      'source_label' => $effSource === 'admin' ? 'ยึดตามที่ Admin ตรวจ' : 'ยึดตามที่ระบบตรวจ (รอ Admin)',
      'pass' => $effPass,
      'label' => $effLabel,
      'reason' => $effReason,
    ],
  ];
}

/**
 * Overlay durable status store onto applicants (never removes applicant rows).
 * Store wins over Excel columns P–U so servers can save without writable xlsx.
 */
function sci_merge_status_store(array &$apps): array {
  $store = sci_load_status_store();
  $byRow = $store['by_row'] ?? [];
  $merged = 0;
  foreach ($apps as &$app) {
    $rowKey = (string)(int)$app['row'];
    if (!isset($byRow[$rowKey]) || !is_array($byRow[$rowKey])) {
      continue;
    }
    $o = $byRow[$rowKey];
    if (array_key_exists('status', $o)) $app['status_raw'] = (string)$o['status'];
    if (array_key_exists('missing_detail', $o)) $app['missing_detail'] = (string)$o['missing_detail'];
    if (array_key_exists('review_note', $o)) $app['review_note'] = (string)$o['review_note'];
    if (array_key_exists('selection', $o)) $app['selection'] = (string)$o['selection'];
    if (array_key_exists('reviewed_at', $o)) $app['reviewed_at'] = (string)$o['reviewed_at'];
    if (array_key_exists('assigned_slot', $o)) $app['assigned_slot'] = (string)$o['assigned_slot'];
    // Optional overlay: move an application to another zone/category (A–O in Excel stays intact)
    if (array_key_exists('zone', $o) && trim((string)$o['zone']) !== '') {
      $z = strtoupper(substr(preg_replace('/[^ABCD]/i', '', (string)$o['zone']), 0, 1));
      if ($z !== '') {
        $app['zone'] = $z;
        $app['zone_raw'] = 'โซน ' . $z;
      }
    }
    if (array_key_exists('category', $o) && trim((string)$o['category']) !== '') {
      $app['category'] = sci_normalize_cat((string)$o['category']);
      $app['category_raw'] = (string)$o['category'];
    }
    if (array_key_exists('payment_status', $o)) $app['payment_status'] = sci_normalize_payment_status((string)$o['payment_status']);
    if (array_key_exists('payment_at', $o)) $app['payment_at'] = (string)$o['payment_at'];
    if (array_key_exists('payment_note', $o)) $app['payment_note'] = (string)$o['payment_note'];
    sci_recompute_app_status($app);
    $app['status_from_store'] = true;
    $merged++;
  }
  unset($app);
  return [
    'merged' => $merged,
    'store_rows' => count($byRow),
    'store_updated_at' => $store['updated_at'] ?? null,
  ];
}

function sci_list_xlsx_files(): array {
  $dir = sci_dir();
  $names = @scandir($dir);
  if (!is_array($names)) {
    return [];
  }
  return array_values(array_filter($names, function ($n) {
    return !str_starts_with($n, '~$') && !str_starts_with($n, '_') && str_ends_with($n, '.xlsx');
  }));
}

function sci_xlsx_path(?int $round = null): string {
  $round = sci_normalize_round($round ?? sci_current_round());
  $dir = sci_dir();
  $files = sci_list_xlsx_files();
  if (!$files) {
    throw new RuntimeException('ไม่พบไฟล์ .xlsx');
  }

  $canonical = sci_round_meta($round)['xlsx_canonical'];
  foreach ($files as $f) {
    if ($f === $canonical) {
      return $dir . DIRECTORY_SEPARATOR . $f;
    }
  }
  foreach ($files as $f) {
    if (sci_xlsx_round_from_name($f) === $round) {
      return $dir . DIRECTORY_SEPARATOR . $f;
    }
  }

  throw new RuntimeException('ไม่พบไฟล์ Excel ' . sci_round_meta($round)['label'] . ' (เช่น ' . $canonical . ')');
}

/** Path to overwrite when uploading Excel for the current/selected round. */
function sci_xlsx_write_path(?int $round = null): string {
  $round = sci_normalize_round($round ?? sci_current_round());
  try {
    return sci_xlsx_path($round);
  } catch (Throwable $e) {
    return sci_dir() . DIRECTORY_SEPARATOR . sci_round_meta($round)['xlsx_canonical'];
  }
}

function sci_available_rounds(): array {
  if (function_exists('sci_use_mysql') && sci_use_mysql() && function_exists('sci_db_event_rounds')) {
    try {
      $out = [];
      $cur = sci_current_round();
      $event = sci_db_active_event();
      $dbRounds = sci_db_event_rounds();
      if (!$dbRounds) {
        $meta = sci_round_meta(1);
        return [array_merge($meta, [
          'available' => true,
          'error' => null,
          'active' => true,
          'xlsx' => null,
        ])];
      }
      foreach ($dbRounds as $r) {
        $no = (int)$r['round_no'];
        $meta = sci_round_meta($no);
        $out[] = array_merge($meta, [
          'db_id' => (int)$r['id'],
          'round_no' => $no,
          'apply_open_at' => $r['apply_open_at'] ?? null,
          'apply_close_at' => $r['apply_close_at'] ?? null,
          'is_open' => (int)($r['is_open'] ?? 0) === 1,
          'xlsx' => null,
          'available' => true,
          'error' => null,
          'active' => $cur === $no,
          'event_year' => (int)$event['year_be'],
        ]);
      }
      return $out;
    } catch (Throwable $e) {
      // fall through
    }
  }
  $out = [];
  foreach (range(1, sci_max_round()) as $r) {
    $meta = sci_round_meta($r);
    $xlsx = null;
    $error = null;
    try {
      $xlsx = basename(sci_xlsx_path($r));
    } catch (Throwable $e) {
      $error = $e->getMessage();
    }
    $out[] = array_merge($meta, [
      'xlsx' => $xlsx,
      'available' => $xlsx !== null,
      'error' => $error,
      'active' => sci_current_round() === $r,
    ]);
  }
  return $out;
}

function sci_slots(): array {
  if (function_exists('sci_use_mysql') && sci_use_mysql() && function_exists('sci_db_slots')) {
    try {
      $dbSlots = sci_db_slots();
      if ($dbSlots) return $dbSlots;
    } catch (Throwable $e) {
      // fall through to hardcoded layout
    }
  }
  return [
    ['id'=>'A1','zone'=>'A','cat'=>'เครื่องดื่มไม่มีแอลกอฮอล์','limit'=>3],
    ['id'=>'A2','zone'=>'A','cat'=>'ข้าวไข่เจียว อาหารตามสั่ง','limit'=>1],
    ['id'=>'A3','zone'=>'A','cat'=>'ยำ','limit'=>1],
    ['id'=>'A4','zone'=>'A','cat'=>'เครื่องดื่มไม่มีแอลกอฮอล์','limit'=>3],
    ['id'=>'A5','zone'=>'A','cat'=>'ข้าวราดแกง','limit'=>1],
    ['id'=>'A6','zone'=>'A','cat'=>'ผัดไทย/หอยทอด','limit'=>1],
    ['id'=>'A7','zone'=>'A','cat'=>'ขนมจีนน้ำยา (น้ำยาหลากหลาย)/หมี่กะทิ','limit'=>1],
    ['id'=>'A8','zone'=>'A','cat'=>'ปอเปี๊ยะ/แหนมคลุก','limit'=>1],
    ['id'=>'A9','zone'=>'A','cat'=>'หม่าล่าย่าง (เสียบไม้)','limit'=>1],
    ['id'=>'A10','zone'=>'A','cat'=>'เครื่องดื่มไม่มีแอลกอฮอล์','limit'=>3],
    ['id'=>'A11','zone'=>'A','cat'=>'ไส้กรอกอีสาน','limit'=>1],
    ['id'=>'A12','zone'=>'A','cat'=>'ไก่ย่าง/ส้มตำ','limit'=>1],
    ['id'=>'B1','zone'=>'B','cat'=>'เครื่องดื่มไม่มีแอลกอฮอล์','limit'=>1],
    ['id'=>'B2','zone'=>'B','cat'=>'ลูกชิ้นทอด/นึ่ง','limit'=>1],
    ['id'=>'B3','zone'=>'B','cat'=>'ซูชิ/อาหารญี่ปุ่น','limit'=>1],
    ['id'=>'B4','zone'=>'B','cat'=>'อาหารทอดทานเล่น','limit'=>1],
    ['id'=>'B5','zone'=>'B','cat'=>'ผลไม้','limit'=>1],
    ['id'=>'B6','zone'=>'B','cat'=>'มันฝรั่งทอด','limit'=>1],
    ['id'=>'C1','zone'=>'C','cat'=>'ไอศกรีม','limit'=>1],
    ['id'=>'C2','zone'=>'C','cat'=>'วาฟเฟิล','limit'=>1],
    ['id'=>'C3','zone'=>'C','cat'=>'พิซซ่า','limit'=>1],
    ['id'=>'C4','zone'=>'C','cat'=>'ข้าวเหนียวหมูปิ้ง','limit'=>1],
    ['id'=>'C5','zone'=>'C','cat'=>'ข้าวไข่เจียว อาหารตามสั่ง','limit'=>1],
    ['id'=>'C6','zone'=>'C','cat'=>'แจ่วฮ้อน/ก๋วยจั๊บ','limit'=>1],
    ['id'=>'C7','zone'=>'C','cat'=>'สื่อเกมการศึกษา/บอร์ดเกม','limit'=>1],
    ['id'=>'C8','zone'=>'C','cat'=>'สุกี้โรล/เกี๊ยวต้ม/ชาบู','limit'=>1],
    ['id'=>'C9','zone'=>'C','cat'=>'เฉาก๊วยนมสดและเครื่องดื่ม','limit'=>1],
    ['id'=>'C10','zone'=>'C','cat'=>'ลูกชุบ/ขนมเบื้อง/ขนมไทย','limit'=>1],
    ['id'=>'C11','zone'=>'C','cat'=>'ขนมจีบ/ซาลาเปา','limit'=>1],
    ['id'=>'C12','zone'=>'C','cat'=>'สุกี้โรล/เกี๊ยวต้ม/ชาบู','limit'=>1],
    ['id'=>'C13','zone'=>'C','cat'=>'ยำ','limit'=>1],
    ['id'=>'C14','zone'=>'C','cat'=>'ผลไม้','limit'=>1],
    ['id'=>'D1','zone'=>'D','cat'=>'หม่าล่าย่าง (เสียบไม้)','limit'=>1],
    ['id'=>'D2','zone'=>'D','cat'=>'ลูกชิ้นทอด/นึ่ง','limit'=>1],
    ['id'=>'D3','zone'=>'D','cat'=>'ซูชิ/อาหารญี่ปุ่น','limit'=>1],
    ['id'=>'D4','zone'=>'D','cat'=>'สโมกี้ไบท์','limit'=>1],
    ['id'=>'D5','zone'=>'D','cat'=>'เครื่องดื่มไม่มีแอลกอฮอล์','limit'=>1],
    ['id'=>'D6','zone'=>'D','cat'=>'เบเกอร์รี่','limit'=>1],
    ['id'=>'D7','zone'=>'D','cat'=>'ข้าวไข่เจียว/ข้าวราดแกง','limit'=>1],
    ['id'=>'D8','zone'=>'D','cat'=>'ผลไม้/ขนมหวาน/ขนมไทย','limit'=>1],
  ];
}

/**
 * Slots already assigned in a round (status store wins over Excel S/U).
 * @return array<string, array{slot:string,name:string,row:int,zone:string,category:string}>
 */
function sci_round_occupied_slots(int $round): array {
  static $cache = [];
  $round = sci_normalize_round($round);
  if (function_exists('sci_use_mysql') && sci_use_mysql() && function_exists('sci_db_round_occupied_slots')) {
    return sci_db_round_occupied_slots($round);
  }
  if (isset($cache[$round])) {
    return $cache[$round];
  }

  $byRow = [];
  try {
    $path = sci_xlsx_path($round);
    $rows = sci_read_sheet_rows($path);
    if ($rows) {
      $header = array_shift($rows) ?? [];
      $cmap = sci_detect_column_map($header);
      foreach ($rows as $r) {
        $name = sci_row_get($r, $cmap, 'name');
        if ($name === '') continue;
        $rowNum = (int)($r['_row'] ?? 0);
        $sel = sci_row_get($r, $cmap, 'selection');
        $slot = strtoupper(sci_row_get($r, $cmap, 'assigned_slot'));
        $allowed = ['ได้รับการคัดเลือก', 'ไม่ได้รับการคัดเลือก', 'รอพิจารณา'];
        if ($sel !== '' && !in_array($sel, $allowed, true)) $sel = 'รอพิจารณา';
        if ($sel === '') $sel = 'รอพิจารณา';
        if ($slot !== '' && !preg_match('/^[ABCD]\d{1,2}$/', $slot)) $slot = '';
        if ($sel !== 'ได้รับการคัดเลือก') $slot = '';
        $zoneRaw = sci_row_get($r, $cmap, 'zone');
        $zone = sci_infer_zone($r, $cmap, $zoneRaw);
        $cat = sci_row_category($r, $cmap, $zone);
        $byRow[$rowNum] = [
          'row' => $rowNum,
          'name' => $name,
          'selection' => $sel,
          'assigned_slot' => $slot,
          'zone' => $zone,
          'category' => sci_normalize_cat($cat),
        ];
      }
    }
  } catch (Throwable $e) {
    $byRow = [];
  }

  $store = sci_load_status_store($round);
  foreach ($store['by_row'] ?? [] as $key => $o) {
    if (!is_array($o)) continue;
    $rowNum = (int)($o['row'] ?? $key);
    if ($rowNum < 2) continue;
    $prev = $byRow[$rowNum] ?? [
      'row' => $rowNum,
      'name' => '',
      'selection' => 'รอพิจารณา',
      'assigned_slot' => '',
      'zone' => '',
      'category' => '',
    ];
    if (array_key_exists('selection', $o)) $prev['selection'] = (string)$o['selection'];
    if (array_key_exists('assigned_slot', $o)) {
      $prev['assigned_slot'] = strtoupper(trim((string)$o['assigned_slot']));
    }
    if (array_key_exists('zone', $o) && trim((string)$o['zone']) !== '') {
      $z = strtoupper(substr(preg_replace('/[^ABCD]/i', '', (string)$o['zone']), 0, 1));
      if ($z !== '') $prev['zone'] = $z;
    }
    if (array_key_exists('category', $o) && trim((string)$o['category']) !== '') {
      $prev['category'] = sci_normalize_cat((string)$o['category']);
    }
    $allowed = ['ได้รับการคัดเลือก', 'ไม่ได้รับการคัดเลือก', 'รอพิจารณา'];
    if ($prev['selection'] !== '' && !in_array($prev['selection'], $allowed, true)) {
      $prev['selection'] = 'รอพิจารณา';
    }
    if ($prev['selection'] !== 'ได้รับการคัดเลือก') {
      $prev['assigned_slot'] = '';
    } elseif ($prev['assigned_slot'] !== '' && !preg_match('/^[ABCD]\d{1,2}$/', $prev['assigned_slot'])) {
      $prev['assigned_slot'] = '';
    }
    $byRow[$rowNum] = $prev;
  }

  $taken = [];
  foreach ($byRow as $row) {
    $slot = strtoupper(trim((string)($row['assigned_slot'] ?? '')));
    if ($slot === '' || ($row['selection'] ?? '') !== 'ได้รับการคัดเลือก') continue;
    if (isset($taken[$slot])) continue;
    $taken[$slot] = [
      'slot' => $slot,
      'name' => (string)($row['name'] ?? ''),
      'row' => (int)($row['row'] ?? 0),
      'zone' => (string)($row['zone'] ?? ''),
      'category' => (string)($row['category'] ?? ''),
    ];
  }
  $cache[$round] = $taken;
  return $taken;
}

/**
 * Slots already selected in earlier rounds (round 3 excludes 1+2, round 2 excludes 1).
 * @return array<string, array{slot:string,name:string,row:int,zone:string,category:string,from_round:int}>
 */
function sci_prior_occupied_slots(?int $round = null): array {
  $round = sci_normalize_round($round ?? sci_current_round());
  static $cache = [];
  if (isset($cache[$round])) return $cache[$round];

  $taken = [];
  for ($r = 1; $r < $round; $r++) {
    try {
      foreach (sci_round_occupied_slots($r) as $id => $info) {
        if (isset($taken[$id])) continue;
        $info['from_round'] = $r;
        $taken[$id] = $info;
      }
    } catch (Throwable $e) {
      continue;
    }
  }
  $cache[$round] = $taken;
  return $taken;
}

function sci_assert_slot_usable(string $slotId): void {
  $slotId = strtoupper(trim($slotId));
  if ($slotId === '' || !sci_is_followup_round()) return;
  $taken = sci_prior_occupied_slots();
  if (isset($taken[$slotId])) {
    $who = trim((string)($taken[$slotId]['name'] ?? ''));
    $from = (int)($taken[$slotId]['from_round'] ?? 1);
    $suffix = $who !== '' ? ' (' . $who . ')' : '';
    $n = sci_current_round();
    throw new InvalidArgumentException(
      'ล็อก ' . $slotId . ' ถูกคัดเลือกแล้วในรอบที่ ' . $from . $suffix
      . ' — รอบที่ ' . $n . ' ใช้ได้เฉพาะล็อกที่ยังว่าง'
    );
  }
}

/** Slots the current round may display and assign. Later rounds exclude earlier selections. */
function sci_active_slots(): array {
  $all = sci_slots();
  if (!sci_is_followup_round()) return $all;
  $taken = sci_prior_occupied_slots();
  return array_values(array_filter($all, fn($s) => !isset($taken[$s['id']])));
}

/**
 * @return array{groups:array, shared:array}
 */
function sci_build_groups_from_slots(array $slots): array {
  $shared = [];
  foreach ($slots as $s) {
    $shared[$s['zone'] . '|' . $s['cat']][] = $s['id'];
  }
  $groups = [];
  $seen = [];
  $followup = sci_is_followup_round();
  foreach ($slots as $s) {
    $k = $s['zone'] . '|' . $s['cat'];
    if (isset($seen[$k])) continue;
    $seen[$k] = true;
    $ids = $shared[$k];
    $groups[] = [
      'zone' => $s['zone'],
      'cat' => $s['cat'],
      'slots' => $ids,
      'label' => implode(', ', $ids),
      'limit' => $followup ? count($ids) : $s['limit'],
      'primary' => $ids[0],
    ];
  }
  return ['groups' => $groups, 'shared' => $shared];
}

function sci_slot_layout(): array {
  $all = sci_slots();
  $active = sci_active_slots();
  $built = sci_build_groups_from_slots($active);
  $followup = sci_is_followup_round();
  $occupied = $followup ? sci_prior_occupied_slots() : [];

  $closed = [];
  if ($followup) {
    $openCat = [];
    foreach ($active as $s) {
      $openCat[$s['zone'] . '|' . sci_normalize_cat($s['cat'])] = true;
    }
    $closedMap = [];
    foreach ($all as $s) {
      $k = $s['zone'] . '|' . sci_normalize_cat($s['cat']);
      if (isset($openCat[$k])) continue;
      if (!isset($closedMap[$k])) {
        $closedMap[$k] = [
          'zone' => $s['zone'],
          'cat' => $s['cat'],
          'slots' => [],
          'taken_by' => [],
        ];
      }
      $closedMap[$k]['slots'][] = $s['id'];
      if (isset($occupied[$s['id']])) {
        $closedMap[$k]['taken_by'][] = $occupied[$s['id']];
      }
    }
    $closed = array_values($closedMap);
  }

  $n = sci_current_round();
  return [
    'slots' => $active,
    'groups' => $built['groups'],
    'shared' => $built['shared'],
    'round1_occupied' => [
      'enabled' => $followup,
      'count' => count($occupied),
      'total' => count($all),
      'remaining' => count($active),
      'slots' => array_values($occupied),
      'closed_categories' => $closed,
      'prior_label' => sci_prior_rounds_label($n),
      'prior_rounds' => $n > 1 ? range(1, $n - 1) : [],
    ],
  ];
}

function sci_normalize_cat(string $cat): string {
  $cat = preg_replace('/\s*\(จำกัด[^)]*\)\s*/u', '', $cat);
  $cat = preg_replace('/\*+/u', '', $cat ?? '');
  return trim(preg_replace('/\s+/u', ' ', $cat ?? ''));
}

/** If a follow-up-round category matches exactly one open slot, use that slot's zone. */
function sci_remap_zone_from_open_slots(string $zone, string $cat): string {
  if (!sci_is_followup_round()) return $zone;
  $norm = sci_normalize_cat($cat);
  if ($norm === '') return $zone;
  $taken = sci_prior_occupied_slots();
  $hits = [];
  foreach (sci_slots() as $s) {
    if (isset($taken[$s['id']])) continue;
    if (sci_normalize_cat($s['cat']) === $norm) {
      $hits[$s['zone']] = true;
    }
  }
  if (count($hits) === 1) {
    return (string)array_key_first($hits);
  }
  return $zone;
}

function sci_excel_wall_clock(float $ts): string {
  // Excel serial = local wall-clock (Google Forms Asia/Bangkok), not UTC
  return gmdate('Y-m-d H:i:s', (int)round(($ts - 25569) * 86400));
}

/**
 * Parse timestamp cell: Excel serial OR text like "6/8/2026, 8:33:32" (d/m/Y, Thai local).
 * Returns [timestamp_sort_key (float unix-ish), datetime "Y-m-d H:i:s", display_th]
 */
function sci_parse_timestamp($raw): array {
  $raw = trim((string)$raw);
  if ($raw === '') {
    return ['timestamp' => 0.0, 'datetime' => '', 'datetime_th' => '-', 'time_label' => '-'];
  }

  $dt = null;

  // Numeric Excel serial
  if (is_numeric($raw) && (float)$raw > 30000) {
    $serial = (float)$raw;
    $wall = sci_excel_wall_clock($serial);
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $wall, new DateTimeZone('Asia/Bangkok'));
    if ($dt) {
      return sci_format_thai_time($dt, (float)$dt->format('U'));
    }
  }

  // Text formats commonly exported from Google Sheets (already Thai local time)
  $patterns = [
    'j/n/Y, G:i:s',
    'j/n/Y, H:i:s',
    'd/m/Y, H:i:s',
    'd/m/Y, G:i:s',
    'j/n/Y G:i:s',
    'd/m/Y H:i:s',
    'd/m/Y H:i',
    'Y-m-d H:i:s',
    'Y-m-d H:i',
    'j/n/Y',
    'd/m/Y',
  ];
  $normalized = str_replace(['.', 'น.', 'น'], [':', '', ''], $raw);
  $normalized = preg_replace('/\s*,\s*/u', ', ', $normalized);
  $normalized = trim(preg_replace('/\s+/u', ' ', $normalized));
  foreach ($patterns as $fmt) {
    $dt = DateTimeImmutable::createFromFormat('!' . $fmt, $normalized, new DateTimeZone('Asia/Bangkok'));
    if ($dt instanceof DateTimeImmutable) {
      $errs = DateTimeImmutable::getLastErrors();
      if ((($errs['warning_count'] ?? 0) === 0) && (($errs['error_count'] ?? 0) === 0)) {
        return sci_format_thai_time($dt, (float)$dt->format('U'));
      }
    }
  }

  // Fallback: strtotime with Bangkok
  try {
    $dt = new DateTimeImmutable($raw, new DateTimeZone('Asia/Bangkok'));
    return sci_format_thai_time($dt, (float)$dt->format('U'));
  } catch (Throwable $e) {
    return ['timestamp' => 0.0, 'datetime' => $raw, 'datetime_th' => $raw, 'time_label' => $raw];
  }
}

function sci_format_thai_time(DateTimeImmutable $dt, float $sortKey): array {
  $months = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];
  $d = (int)$dt->format('j');
  $m = $months[(int)$dt->format('n')];
  $y = (int)$dt->format('Y') + 543;
  $hms = $dt->format('H:i:s');
  $datetime = $dt->format('Y-m-d H:i:s');
  $th = "{$d} {$m} {$y} · {$hms} น.";
  return [
    'timestamp' => $sortKey,
    'datetime' => $datetime,
    'datetime_th' => $th,
    'time_label' => $th,
  ];
}

function sci_is_app_file_url(string $url): bool {
  $url = trim($url);
  if ($url === '') return false;
  if (str_starts_with($url, 'file_serve.php')) return true;
  if (str_starts_with($url, 's3://')) return true;
  if (str_starts_with($url, 'uploads/')) return true;
  if (preg_match('#(^|/)file_serve\.php(\?|$)#i', $url)) return true;
  return false;
}

function sci_drive_view(string $url): string {
  $url = trim($url);
  if ($url === '' || sci_is_app_file_url($url)) {
    return $url;
  }
  // Prefer never rewriting non-Drive URLs
  if (!preg_match('#drive\.google\.com#i', $url)) {
    return $url;
  }
  if (preg_match('#/d/([a-zA-Z0-9_-]+)#', $url, $m)) {
    return 'https://drive.google.com/file/d/' . $m[1] . '/view';
  }
  if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $url, $m)) {
    return 'https://drive.google.com/file/d/' . $m[1] . '/view';
  }
  return $url;
}

function sci_drive_id(string $url): ?string {
  $url = trim($url);
  if ($url === '' || sci_is_app_file_url($url)) return null;
  // Only extract IDs from real Google Drive URLs (never from file_serve.php?id=…)
  if (!preg_match('#drive\.google\.com#i', $url)) return null;
  if (preg_match('#/d/([a-zA-Z0-9_-]+)#', $url, $m)) return $m[1];
  if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $url, $m)) return $m[1];
  return null;
}

function sci_load_shared_strings($zip): array {
  $ss = $zip->getFromName('xl/sharedStrings.xml');
  $strings = [];
  if (!$ss) return $strings;
  $sx = simplexml_load_string($ss);
  foreach ($sx->si as $si) {
    $t = '';
    if (isset($si->t)) $t = (string)$si->t;
    else foreach ($si->r as $r) $t .= (string)$r->t;
    $strings[] = $t;
  }
  return $strings;
}

function sci_read_sheet_rows(string $path): array {
  $zip = sci_new_zip();
  if ($zip->open($path) !== true) {
    throw new RuntimeException('เปิดไฟล์ Excel ไม่ได้');
  }
  $strings = sci_load_shared_strings($zip);
  $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
  $zip->close();
  if (!$sheet) throw new RuntimeException('ไม่พบ sheet1');

  $sx = simplexml_load_string($sheet);
  $rows = [];
  foreach ($sx->sheetData->row as $row) {
    $rnum = (int)$row['r'];
    $r = ['_row' => $rnum];
    foreach ($row->c as $c) {
      preg_match('/([A-Z]+)/', (string)$c['r'], $m);
      $col = $m[1];
      $t = (string)$c['t'];
      $v = isset($c->v) ? (string)$c->v : '';
      if ($t === 's' && $v !== '') $v = $strings[(int)$v] ?? $v;
      if ($t === 'inlineStr' && isset($c->is)) {
        $v = '';
        if (isset($c->is->t)) $v = (string)$c->is->t;
        else foreach ($c->is->r as $rr) $v .= (string)$rr->t;
      }
      $r[$col] = $v;
    }
    $rows[] = $r;
  }
  return $rows;
}

function sci_default_column_map(): array {
  return [
    'timestamp' => 'A',
    'qualify' => 'C',
    'name' => 'D',
    'phone' => 'E',
    'zone' => 'F',
    'cat_a' => 'G',
    'cat_b' => 'H',
    'cat_c' => 'I',
    'cat_d' => 'J',
    'detail' => 'K',
    'id_card' => 'L',
    'house_reg' => 'M',
    'photo' => 'N',
    'food' => 'O',
    'status' => 'P',
    'missing_detail' => 'Q',
    'review_note' => 'R',
    'selection' => 'S',
    'reviewed_at' => 'T',
    'assigned_slot' => 'U',
  ];
}

function sci_col_after(string $col): string {
  $n = sci_col_index($col) + 1;
  $out = '';
  while ($n > 0) {
    $n--;
    $out = chr(65 + ($n % 26)) . $out;
    $n = intdiv($n, 26);
  }
  return $out;
}

/**
 * Detect Excel column layout from header row.
 * Round 2 form omits Zone B, so C/D categories and uploads shift left.
 */
function sci_detect_column_map(array $header): array {
  $map = sci_default_column_map();
  $cats = [];
  $zoneFound = false;
  foreach ($header as $col => $title) {
    if (!is_string($col) || !preg_match('/^[A-Z]+$/', $col)) continue;
    $t = trim((string)$title);
    if ($t === '') continue;

    if (preg_match('/ประทับเวลา/u', $t)) $map['timestamp'] = $col;
    if (preg_match('/กรุณาระบุคุณสมบัติ|คุณสมบัติของท่าน/u', $t)) $map['qualify'] = $col;
    if (preg_match('/ชื่อ/u', $t) && preg_match('/นามสกุล|ผู้สมัคร/u', $t)) $map['name'] = $col;
    if (preg_match('/เบอร์ติดต่อ|เบอร์โทร/u', $t)) $map['phone'] = $col;
    if ((preg_match('/^โซนร้านค้า$/u', $t) || preg_match('/โซนร้าน/u', $t)) && !preg_match('/ประเภท/u', $t)) {
      $map['zone'] = $col;
      $zoneFound = true;
    }
    if (preg_match('/ประเภท/u', $t) && preg_match('/โซน\s*([ABCD])/u', $t, $m)) {
      $cats[$m[1]] = $col;
    }
    if (preg_match('/จุดเด่น|รายละเอียดเพิ่มเติม/u', $t)) $map['detail'] = $col;
    if (preg_match('/บัตรประจำตัว|บัตรประชาชน/u', $t)) $map['id_card'] = $col;
    if (preg_match('/ทะเบียนบ้าน/u', $t)) $map['house_reg'] = $col;
    if (preg_match('/รูปถ่ายหน้าตรง/u', $t)) $map['photo'] = $col;
    if (preg_match('/ภาพถ่ายอาหาร|สินค้าจริงที่จะนำมาจำหน่าย/u', $t)) $map['food'] = $col;
    if (preg_match('/สถานะเอกสาร/u', $t)) $map['status'] = $col;
    if (preg_match('/เอกสารที่ขาด/u', $t)) $map['missing_detail'] = $col;
    if (preg_match('/หมายเหตุการพิจารณา/u', $t)) $map['review_note'] = $col;
    if (preg_match('/ผลการคัดเลือก/u', $t)) $map['selection'] = $col;
    if (preg_match('/วันเวลาที่ตรวจ/u', $t)) $map['reviewed_at'] = $col;
    if (preg_match('/ล็อกร้านที่ได้รับ/u', $t)) $map['assigned_slot'] = $col;
  }

  if (!$zoneFound) {
    $map['zone'] = '';
  }

  if ($cats) {
    $map['cat_a'] = $cats['A'] ?? '';
    $map['cat_b'] = $cats['B'] ?? '';
    $map['cat_c'] = $cats['C'] ?? '';
    $map['cat_d'] = $cats['D'] ?? '';
    if ($map['cat_a'] === '' && $zoneFound) {
      $guess = sci_col_after((string)($map['zone'] ?? 'F'));
      if ($guess !== '' && !in_array($guess, $cats, true)) {
        $map['cat_a'] = $guess;
      }
    }
  }

  return $map;
}

function sci_row_get(array $row, array $map, string $field): string {
  $col = $map[$field] ?? '';
  if ($col === '') return '';
  return trim((string)($row[$col] ?? ''));
}

function sci_parse_zone_value(string $zoneRaw): string {
  return strtoupper(substr(preg_replace('/[^ABCD]/ui', '', str_replace(['โซน', 'โซต', ' '], '', $zoneRaw)), 0, 1));
}

function sci_infer_zone(array $row, array $map, string $zoneRaw): string {
  $zone = sci_parse_zone_value($zoneRaw);
  if ($zone !== '') return $zone;
  foreach (['A' => 'cat_a', 'B' => 'cat_b', 'C' => 'cat_c', 'D' => 'cat_d'] as $z => $f) {
    if (sci_row_get($row, $map, $f) !== '') return $z;
  }
  return '';
}

function sci_row_category(array $row, array $map, string $zone): string {
  $field = match ($zone) {
    'A' => 'cat_a',
    'B' => 'cat_b',
    'C' => 'cat_c',
    'D' => 'cat_d',
    default => '',
  };
  if ($field !== '') {
    $cat = sci_row_get($row, $map, $field);
    if ($cat !== '') return $cat;
  }
  foreach (['cat_a', 'cat_b', 'cat_c', 'cat_d'] as $f) {
    $v = sci_row_get($row, $map, $f);
    if ($v !== '') return $v;
  }
  return '';
}

function sci_alumni_path(): string {
  return sci_data_dir() . DIRECTORY_SEPARATOR . 'alumni_2568.json';
}

/**
 * Load previous-year selected vendors (SCI Week 2568).
 */
function sci_load_alumni(): array {
  if (isset($GLOBALS['SCI_ALUMNI_CACHE']) && is_array($GLOBALS['SCI_ALUMNI_CACHE'])) {
    return $GLOBALS['SCI_ALUMNI_CACHE'];
  }
  if (function_exists('sci_use_mysql') && sci_use_mysql() && function_exists('sci_db_load_alumni')) {
    $cache = sci_db_load_alumni();
    $GLOBALS['SCI_ALUMNI_CACHE'] = $cache;
    return $cache;
  }
  $path = sci_alumni_path();
  if (!is_file($path)) {
    $cache = ['year' => 2568, 'vendors' => [], 'label' => 'SCI Week 2568', 'unpaid_count' => 0];
    $GLOBALS['SCI_ALUMNI_CACHE'] = $cache;
    return $cache;
  }
  $raw = json_decode((string)file_get_contents($path), true);
  if (!is_array($raw) || !isset($raw['vendors']) || !is_array($raw['vendors'])) {
    $cache = ['year' => 2568, 'vendors' => [], 'label' => 'SCI Week 2568', 'unpaid_count' => 0];
    $GLOBALS['SCI_ALUMNI_CACHE'] = $cache;
    return $cache;
  }
  $unpaid = 0;
  foreach ($raw['vendors'] as &$v) {
    if (!is_array($v)) continue;
    $pay = (string)($v['payment_status'] ?? '');
    if ($pay === 'unpaid' || !empty($v['payment_warning'])) {
      $v['payment_warning'] = true;
      $v['payment_status'] = 'unpaid';
      $unpaid++;
    }
  }
  unset($v);
  $raw['unpaid_count'] = $unpaid;
  $GLOBALS['SCI_ALUMNI_CACHE'] = $raw;
  return $raw;
}

/**
 * Fold Thai name for comparison (NFC, titles, common เเ→แ typo, spaces).
 */
function sci_fold_person_name(string $name): string {
  $name = trim(preg_replace('/\s+/u', ' ', $name));
  if ($name === '') return '';
  if (class_exists('Normalizer')) {
    $n = Normalizer::normalize($name, Normalizer::FORM_C);
    if (is_string($n) && $n !== '') $name = $n;
  }
  // คูณเเก้ว → คูณแก้ว (double Sara E typed instead of Sara Ae)
  $name = str_replace('เเ', 'แ', $name);
  $titles = [
    '/^จ\.?\s*ส\.?\s*ต\.?\s*หญิง\s*/u',
    '/^นางสาว\s*/u',
    '/^น\.?\s*ส\.?\s*/u',
    '/^นาย\s*/u',
    '/^นาง\s*/u',
    '/^ด\.?\s*ช\.?\s*/u',
    '/^ด\.?\s*ญ\.?\s*/u',
  ];
  foreach ($titles as $re) {
    $stripped = preg_replace($re, '', $name);
    if (is_string($stripped) && $stripped !== $name) {
      $name = trim($stripped);
      break;
    }
  }
  return trim(preg_replace('/\s+/u', ' ', $name));
}

function sci_name_similarity(string $a, string $b): float {
  if ($a === '' || $b === '') return 0.0;
  if ($a === $b) return 1.0;
  similar_text($a, $b, $pct);
  return $pct / 100.0;
}

/**
 * Match applicant name against alumni list. Returns best hit or null.
 */
function sci_match_alumni(string $applicantName, array $alumni): ?array {
  $core = sci_fold_person_name($applicantName);
  if ($core === '') return null;

  $parts = preg_split('/\s+/u', $core) ?: [];
  $sur = $parts ? $parts[count($parts) - 1] : '';
  $given = $parts ? implode(' ', array_slice($parts, 0, -1)) : '';

  $best = null;
  $bestScore = 0.0;

  foreach ($alumni['vendors'] ?? [] as $v) {
    if (!is_array($v)) continue;
    $candidates = [($v['name'] ?? '')];
    foreach ($v['aliases'] ?? [] as $alias) {
      if (is_string($alias) && $alias !== '') $candidates[] = $alias;
    }
    foreach ($candidates as $cand) {
      $cCore = sci_fold_person_name($cand);
      if ($cCore === '') continue;
      $score = sci_name_similarity($core, $cCore);
      if ($score < 0.92 && $sur !== '') {
        $cParts = preg_split('/\s+/u', $cCore) ?: [];
        $cSur = $cParts ? $cParts[count($cParts) - 1] : '';
        $cGiven = $cParts ? implode(' ', array_slice($cParts, 0, -1)) : '';
        if ($cSur !== '' && $cSur === $sur && $given !== '' && $cGiven !== '') {
          $gScore = sci_name_similarity($given, $cGiven);
          if ($gScore >= 0.78) {
            $score = max($score, 0.86 + ($gScore * 0.12));
          }
        }
      }
      if ($score > $bestScore) {
        $bestScore = $score;
        $best = [
          'year' => (int)($v['year'] ?? $alumni['year'] ?? 2568),
          'label' => (string)($alumni['label'] ?? 'SCI Week 2568'),
          'name' => (string)($v['name'] ?? $cand),
          'slot' => (string)($v['slot'] ?? ''),
          'category' => (string)($v['category'] ?? ''),
          'score' => round($score, 3),
          'match' => $score >= 0.995 ? 'exact' : 'fuzzy',
          'payment_status' => (string)($v['payment_status'] ?? 'unknown'),
          'payment_warning' => !empty($v['payment_warning']) || (($v['payment_status'] ?? '') === 'unpaid'),
        ];
      }
    }
  }

  if ($best === null || $bestScore < 0.86) return null;
  return $best;
}

/**
 * Alumni rows from years before the active event (for “returning / unpaid prior year” matching).
 * Syncing the current event into alumni_vendors must not mark this year’s applicants as returning.
 */
function sci_prior_alumni(?int $beforeYearBe = null): array {
  $alumni = sci_load_alumni();
  if ($beforeYearBe === null && function_exists('sci_use_mysql') && sci_use_mysql() && function_exists('sci_db_active_event')) {
    try {
      $beforeYearBe = (int)(sci_db_active_event()['year_be'] ?? 0);
    } catch (Throwable $e) {
      $beforeYearBe = 0;
    }
  }
  if ($beforeYearBe === null || $beforeYearBe <= 0) {
    return $alumni;
  }

  $vendors = [];
  $unpaid = 0;
  $metaYear = 0;
  $metaLabel = (string)($alumni['label'] ?? '');
  foreach ($alumni['vendors'] ?? [] as $v) {
    if (!is_array($v)) continue;
    $y = (int)($v['year'] ?? 0);
    if ($y <= 0 || $y >= $beforeYearBe) continue;
    $vendors[] = $v;
    if (!empty($v['payment_warning']) || (($v['payment_status'] ?? '') === 'unpaid')) $unpaid++;
    if ($y > $metaYear) {
      $metaYear = $y;
      if (!empty($v['event_label'])) $metaLabel = (string)$v['event_label'];
    }
  }
  $alumni['vendors'] = $vendors;
  $alumni['unpaid_count'] = $unpaid;
  if ($metaYear > 0) $alumni['year'] = $metaYear;
  if ($metaLabel !== '') $alumni['label'] = $metaLabel;
  $alumni['before_year_be'] = $beforeYearBe;
  return $alumni;
}

/**
 * Attach returning-vendor flags and summary counts.
 */
function sci_attach_alumni(array &$apps): array {
  $alumni = sci_prior_alumni();
  $returning = 0;
  $returningUnpaid = 0;
  foreach ($apps as &$a) {
    $hit = sci_match_alumni((string)($a['name'] ?? ''), $alumni);
    if ($hit) {
      $a['returning'] = true;
      $a['alumni'] = $hit;
      $a['payment_warning'] = !empty($hit['payment_warning']);
      $returning++;
      if (!empty($hit['payment_warning'])) $returningUnpaid++;
    } else {
      $a['returning'] = !empty($a['returning']);
      if (empty($a['alumni'])) $a['alumni'] = null;
      $a['payment_warning'] = !empty($a['payment_warning']);
    }
  }
  unset($a);
  return [
    'year' => (int)($alumni['year'] ?? 2568),
    'label' => (string)($alumni['label'] ?? 'SCI Week 2568'),
    'source' => (string)($alumni['source'] ?? ''),
    'source_ref' => (string)($alumni['source_ref'] ?? ''),
    'total_prev_selected' => count($alumni['vendors'] ?? []),
    'returning_count' => $returning,
    'returning_unpaid_count' => $returningUnpaid,
    'alumni_unpaid_count' => (int)($alumni['unpaid_count'] ?? 0),
  ];
}

function sci_parse_applicants(?string $path = null): array {
  if ($path === null && function_exists('sci_use_mysql') && sci_use_mysql() && function_exists('sci_db_parse_applicants')) {
    return sci_db_parse_applicants();
  }
  $path = $path ?? sci_xlsx_path();
  $rows = sci_read_sheet_rows($path);
  if (!$rows) {
    $layout = sci_slot_layout();
    return sci_with_round_context([
      'source' => basename($path),
      'applicants' => [],
      'slots' => $layout['slots'],
      'groups' => $layout['groups'],
      'shared' => $layout['shared'],
      'round1_occupied' => $layout['round1_occupied'],
      'total_applicants' => 0,
    ]);
  }

  $header = array_shift($rows);
  $cmap = sci_detect_column_map($header ?? []);
  $apps = [];
  foreach ($rows as $r) {
    $name = sci_row_get($r, $cmap, 'name');
    if ($name === '') continue;

    $zoneRaw = sci_row_get($r, $cmap, 'zone');
    $zone = sci_infer_zone($r, $cmap, $zoneRaw);
    $cat = sci_row_category($r, $cmap, $zone);
    $mappedZone = sci_remap_zone_from_open_slots($zone, $cat);
    if ($mappedZone !== $zone) {
      $zone = $mappedZone;
      $zoneRaw = 'โซน ' . $zone;
    } elseif ($zoneRaw === '' && $zone !== '') {
      $zoneRaw = 'โซน ' . $zone;
    }
    $tsRaw = sci_row_get($r, $cmap, 'timestamp');
    $timeInfo = sci_parse_timestamp($tsRaw);
    $foodRaw = array_values(array_filter(array_map('trim', explode(',', sci_row_get($r, $cmap, 'food')))));
    $food = [];
    foreach ($foodRaw as $u) {
      $id = sci_drive_id($u);
      $food[] = [
        'url' => sci_drive_view($u),
        'id' => $id,
        'thumb' => $id ? ('https://drive.google.com/thumbnail?id=' . $id . '&sz=w220') : null,
        'full' => $id ? ('https://drive.google.com/thumbnail?id=' . $id . '&sz=w1600') : sci_drive_view($u),
      ];
    }

    $idCard = sci_row_get($r, $cmap, 'id_card');
    $houseReg = sci_row_get($r, $cmap, 'house_reg');
    $photo = sci_row_get($r, $cmap, 'photo');
    $qualify = sci_row_get($r, $cmap, 'qualify');
    $detail = sci_row_get($r, $cmap, 'detail');
    $phone = sci_row_get($r, $cmap, 'phone');

    $autoMissing = [];
    if ($idCard === '') $autoMissing[] = 'สำเนาบัตรประชาชน';
    if ($houseReg === '') $autoMissing[] = 'สำเนาทะเบียนบ้าน';
    if ($photo === '') $autoMissing[] = 'รูปถ่ายหน้าตรง';
    if (count($food) === 0) $autoMissing[] = 'ภาพถ่ายอาหาร/สินค้า';
    if ($qualify === '') $autoMissing[] = 'คุณสมบัติตามประกาศ';

    $statusRaw = sci_row_get($r, $cmap, 'status');
    $missingDetail = sci_row_get($r, $cmap, 'missing_detail');
    $note = sci_row_get($r, $cmap, 'review_note');
    $selection = sci_row_get($r, $cmap, 'selection');
    $reviewedAt = sci_row_get($r, $cmap, 'reviewed_at');
    $assignedSlot = strtoupper(sci_row_get($r, $cmap, 'assigned_slot'));

    // Migrate old "ผู้ตรวจ" text accidentally stored in S (not a selection value)
    $selectionValues = ['ได้รับการคัดเลือก', 'ไม่ได้รับการคัดเลือก', 'รอพิจารณา'];
    if ($selection !== '' && !in_array($selection, $selectionValues, true)) {
      $selection = 'รอพิจารณา';
    }
    if ($selection === '') $selection = 'รอพิจารณา';

    if ($assignedSlot !== '' && !preg_match('/^[ABCD]\d{1,2}$/', $assignedSlot)) {
      $assignedSlot = '';
    }

    // --- Document checks: system (attachments) vs admin (column P) ---
    $sysPass = count($autoMissing) === 0;
    $sysLabel = $sysPass ? 'ครบถ้วน' : 'ไม่ครบถ้วน';

    $adminClean = trim(preg_replace('/\s*\(อัตโนมัติ\)\s*/u', '', $statusRaw));
    $isAutoOnly = ($statusRaw === '' || mb_strpos($statusRaw, 'อัตโนมัติ') !== false);
    $adminStatuses = ['รอตรวจสอบ', 'ครบถ้วน', 'ไม่ครบถ้วน', 'ขาดคุณสมบัติ'];
    $adminReviewed = !$isAutoOnly && in_array($adminClean, $adminStatuses, true);
    $adminStatus = $adminReviewed ? $adminClean : '';

    $adminPass = $adminReviewed && $adminStatus === 'ครบถ้วน';
    $adminFail = $adminReviewed && in_array($adminStatus, ['ไม่ครบถ้วน', 'ขาดคุณสมบัติ'], true);

    // Effective status: Admin wins when reviewed
    if ($adminReviewed) {
      $status = $adminStatus;
      $effSource = 'admin';
      $effPass = $adminPass;
      $effLabel = $adminStatus;
      $effReason = '';
      if ($adminFail) {
        $effReason = $missingDetail !== '' ? $missingDetail : (
          $adminStatus === 'ขาดคุณสมบัติ'
            ? 'Admin ระบุว่าขาดคุณสมบัติ'
            : 'Admin ระบุว่าเอกสารไม่ครบถ้วน'
        );
        if ($note !== '' && $adminStatus === 'ขาดคุณสมบัติ') {
          $effReason = trim($effReason . ($effReason ? ' · ' : '') . $note);
        }
      }
    } else {
      $status = $sysPass ? 'ครบถ้วน (อัตโนมัติ)' : 'รอตรวจสอบ';
      $effSource = 'system';
      $effPass = $sysPass;
      $effLabel = $sysPass ? 'ครบถ้วน (ระบบ)' : 'ไม่ครบถ้วน (ระบบ)';
      $effReason = $sysPass ? '' : ('ระบบตรวจพบขาด: ' . implode(', ', $autoMissing));
    }

    $docCheck = [
      'system' => [
        'pass' => $sysPass,
        'label' => $sysLabel,
        'missing' => $autoMissing,
      ],
      'admin' => [
        'reviewed' => $adminReviewed,
        'status' => $adminStatus,
        'pass' => $adminPass,
        'fail' => $adminFail,
        'missing_detail' => $missingDetail,
        'review_note' => $note,
        'reviewed_at' => $reviewedAt,
      ],
      'effective' => [
        'source' => $effSource,
        'source_label' => $effSource === 'admin' ? 'ยึดตามที่ Admin ตรวจ' : 'ยึดตามที่ระบบตรวจ (รอ Admin)',
        'pass' => $effPass,
        'label' => $effLabel,
        'reason' => $effReason,
      ],
    ];

    $apps[] = [
      'row' => (int)$r['_row'],
      'timestamp' => $timeInfo['timestamp'],
      'datetime' => $timeInfo['datetime'],
      'datetime_th' => $timeInfo['datetime_th'],
      'time_label' => $timeInfo['time_label'],
      'timestamp_raw' => $tsRaw,
      'name' => $name,
      'phone' => $phone,
      'zone' => $zone,
      'zone_raw' => $zoneRaw,
      'category' => sci_normalize_cat($cat),
      'category_raw' => $cat,
      'detail' => $detail,
      'qualifications' => $qualify,
      'id_card' => sci_drive_view($idCard),
      'house_reg' => sci_drive_view($houseReg),
      'photo' => sci_drive_view($photo),
      'food_photos' => $food,
      'docs' => [
        'id_card' => $idCard !== '',
        'house_reg' => $houseReg !== '',
        'photo' => $photo !== '',
        'food' => count($food) > 0,
        'qualify' => $qualify !== '',
      ],
      'auto_missing' => $autoMissing,
      'doc_check' => $docCheck,
      'status' => $status,
      'status_raw' => $statusRaw,
      'missing_detail' => $missingDetail,
      'review_note' => $note,
      'selection' => $selection,
      'reviewed_at' => $reviewedAt,
      'assigned_slot' => $assignedSlot,
      'payment_status' => 'unpaid',
      'payment_at' => '',
      'payment_note' => '',
      'key' => md5($timeInfo['datetime'] . '|' . $name . '|' . $zone . '|' . ((int)$r['_row'])),
    ];
  }

  usort($apps, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

  // Durable status overlay (works even when Excel is read-only on the server)
  $storeMeta = sci_merge_status_store($apps);
  $health = sci_storage_health();
  $alumniMeta = sci_attach_alumni($apps);
  $paymentAlerts = function_exists('sci_build_payment_alerts')
    ? sci_build_payment_alerts($apps, $alumniMeta)
    : ['banner' => '', 'unpaid_alumni' => [], 'returning_unpaid' => []];

  $layout = sci_slot_layout();
  $slots = $layout['slots'];
  $groups = $layout['groups'];
  $shared = $layout['shared'];
  $r1occ = $layout['round1_occupied'];

  $shopReport = sci_build_shop_report($slots, $apps);
  $docSummary = sci_build_doc_summary($apps);
  $roundMeta = sci_round_meta();
  $storeRel = 'data/' . $roundMeta['status_store'];
  $notice = 'ข้อมูลผู้สมัครอ่านจาก Excel เสมอ · สถานะ Admin/คัดเลือกระบบบันทึกที่ ' . $storeRel . ' (แม้ Excel บนเซิร์ฟเวอร์ล็อกสิทธิ์เขียน) · ไม่ลบแถวผู้สมัคร · เวลาที่แสดงเป็นเวลาไทย';
  if (!empty($r1occ['enabled'])) {
    $n = sci_current_round();
    $prior = (string)($r1occ['prior_label'] ?? sci_prior_rounds_label($n));
    $notice = 'รอบที่ ' . $n . ' แสดงเฉพาะล็อกที่ยังไม่ถูกคัดเลือกใน' . $prior
      . ' (เหลือ ' . (int)$r1occ['remaining'] . ' จาก ' . (int)$r1occ['total'] . ' ล็อก) · ' . $notice;
  }

  return sci_with_round_context([
    'generated_at' => date('c'),
    'source' => basename($path),
    'source_mtime' => date('c', filemtime($path)),
    'total_applicants' => count($apps),
    'slots' => $slots,
    'groups' => $groups,
    'shared' => $shared,
    'round1_occupied' => $r1occ,
    'applicants' => $apps,
    'shop_report' => $shopReport,
    'doc_summary' => $docSummary,
    'alumni' => $alumniMeta,
    'payment_alerts' => $paymentAlerts,
    'storage' => $health,
    'status_store' => $storeMeta,
    'policy' => 'บันทึกเฉพาะสถานะตรวจเอกสาร/คัดเลือก/ล็อก — ไม่ลบรายการผู้สมัคร · บนเซิร์ฟเวอร์สถานะเก็บที่ ' . $storeRel . ' เป็นหลัก และซิงก์ Excel เมื่อเขียนได้',
    'notice' => $notice,
  ]);
}

function sci_with_round_context(array $data): array {
  $data['round'] = sci_round_meta();
  $data['rounds'] = sci_available_rounds();
  if (!isset($data['storage'])) {
    $data['storage'] = sci_storage_health();
  }
  if (function_exists('sci_use_mysql') && sci_use_mysql() && function_exists('sci_db_active_event')) {
    try {
      $ev = sci_db_active_event();
      $data['event'] = $ev;
      if (function_exists('sci_admin_list_events')) {
        $data['events'] = array_map(static function ($e) {
          return [
            'id' => (int)$e['id'],
            'code' => (string)$e['code'],
            'title' => (string)$e['title'],
            'year_be' => (int)$e['year_be'],
            'is_active' => (int)$e['is_active'] === 1,
            'round_count' => (int)($e['round_count'] ?? 0),
            'slot_count' => (int)($e['slot_count'] ?? 0),
            'applicant_count' => (int)($e['applicant_count'] ?? 0),
          ];
        }, sci_admin_list_events());
      } else {
        // Lightweight list without event_admin_lib
        $st = sci_db()->query(
          'SELECT id, code, title, year_be, is_active,
                  (SELECT COUNT(*) FROM event_rounds r WHERE r.event_id = e.id) AS round_count,
                  (SELECT COUNT(*) FROM slots s WHERE s.event_id = e.id) AS slot_count,
                  (SELECT COUNT(*) FROM applicants a WHERE a.event_id = e.id) AS applicant_count
           FROM events e ORDER BY year_be DESC, id DESC'
        );
        $data['events'] = array_map(static function ($e) {
          return [
            'id' => (int)$e['id'],
            'code' => (string)$e['code'],
            'title' => (string)$e['title'],
            'year_be' => (int)$e['year_be'],
            'is_active' => (int)$e['is_active'] === 1,
            'round_count' => (int)($e['round_count'] ?? 0),
            'slot_count' => (int)($e['slot_count'] ?? 0),
            'applicant_count' => (int)($e['applicant_count'] ?? 0),
          ];
        }, $st->fetchAll());
      }
    } catch (Throwable $e) {
      $data['event'] = null;
      $data['events'] = [];
    }
  }
  return $data;
}

/**
 * Overview counts for document verification (system vs admin).
 */
function sci_build_doc_summary(array $apps): array {
  $total = count($apps);
  $sysOk = 0;
  $sysFail = 0;
  $adminOk = 0;
  $adminFail = 0;
  $adminPending = 0;
  $effOk = 0;
  $effFail = 0;
  foreach ($apps as $a) {
    $dc = $a['doc_check'] ?? null;
    if (!$dc) continue;
    if (!empty($dc['system']['pass'])) $sysOk++; else $sysFail++;
    if (!empty($dc['admin']['reviewed'])) {
      if (!empty($dc['admin']['pass'])) $adminOk++;
      elseif (!empty($dc['admin']['fail'])) $adminFail++;
      else $adminPending++; // e.g. รอตรวจสอบ by admin
    } else {
      $adminPending++;
    }
    if (!empty($dc['effective']['pass'])) $effOk++; else $effFail++;
  }
  return [
    'total' => $total,
    'system_ok' => $sysOk,
    'system_fail' => $sysFail,
    'admin_ok' => $adminOk,
    'admin_fail' => $adminFail,
    'admin_pending' => $adminPending,
    'effective_ok' => $effOk,
    'effective_fail' => $effFail,
  ];
}

/**
 * Map each shop slot -> assigned operator (status-based; applicants never removed).
 */
function sci_build_shop_report(array $slots, array $apps): array {
  $bySlot = [];
  foreach ($apps as $a) {
    $slot = strtoupper(trim((string)($a['assigned_slot'] ?? '')));
    if ($slot === '') continue;
    if (($a['selection'] ?? '') !== 'ได้รับการคัดเลือก') continue;
    // First approved assignee wins if duplicates (should be prevented on write)
    if (!isset($bySlot[$slot])) {
      $bySlot[$slot] = $a;
    }
  }

  $report = [];
  foreach ($slots as $s) {
    $op = $bySlot[$s['id']] ?? null;
    $cross = false;
    if ($op) {
      $cross = sci_normalize_cat($op['category'] ?? '') !== sci_normalize_cat($s['cat']);
    }
    $report[] = [
      'slot' => $s['id'],
      'zone' => $s['zone'],
      'category' => $s['cat'],
      'filled' => $op !== null,
      'cross_assigned' => $cross,
      'operator' => $op ? [
        'row' => $op['row'],
        'key' => $op['key'],
        'name' => $op['name'],
        'phone' => $op['phone'],
        'status' => $op['status'],
        'selection' => $op['selection'],
        'reviewed_at' => $op['reviewed_at'],
        'time_label' => $op['time_label'] ?? '',
        'applied_zone' => $op['zone'],
        'applied_category' => $op['category'],
        'review_note' => $op['review_note'] ?? '',
        'returning' => !empty($op['returning']),
        'alumni' => $op['alumni'] ?? null,
        'payment_status' => sci_normalize_payment_status((string)($op['payment_status'] ?? 'unpaid')),
        'payment_at' => (string)($op['payment_at'] ?? ''),
        'payment_note' => (string)($op['payment_note'] ?? ''),
      ] : null,
    ];
  }
  return $report;
}

/** Normalize finance payment flag stored in status_store (JSON only, not Excel). */
function sci_normalize_payment_status(string $status): string {
  $s = trim($status);
  $lower = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
  if (in_array($lower, ['paid', 'yes', '1', 'true'], true)) return 'paid';
  if (in_array($s, ['ชำระแล้ว', 'ชำระเงินแล้ว'], true)) return 'paid';
  return 'unpaid';
}

/**
 * Save payment status for an approved shop (status_store only — no Excel sync).
 */
function sci_save_payment_status(int $row, string $paymentStatus, string $paymentNote = ''): array {
  if (function_exists('sci_use_mysql') && sci_use_mysql() && function_exists('sci_db_save_payment_status')) {
    return sci_db_save_payment_status($row, $paymentStatus, $paymentNote);
  }
  if ($row < 2) {
    throw new InvalidArgumentException('row ไม่ถูกต้อง');
  }
  $status = sci_normalize_payment_status($paymentStatus);
  $store = sci_load_status_store();
  $key = (string)$row;
  $prev = isset($store['by_row'][$key]) && is_array($store['by_row'][$key]) ? $store['by_row'][$key] : [];
  $nowBkk = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
  $store['by_row'][$key] = array_merge($prev, [
    'row' => $row,
    'payment_status' => $status,
    'payment_at' => $status === 'paid' ? $nowBkk : '',
    'payment_note' => trim($paymentNote),
    'saved_at' => date('c'),
  ]);
  sci_save_status_store($store);
  return [
    'ok' => true,
    'row' => $row,
    'payment_status' => $status,
    'payment_at' => $store['by_row'][$key]['payment_at'],
    'payment_note' => $store['by_row'][$key]['payment_note'],
    'status_store' => 'data/' . basename(sci_status_store_path()),
    'message' => $status === 'paid' ? 'บันทึกว่าชำระเงินแล้ว' : 'บันทึกว่ายังไม่ชำระเงิน',
  ];
}

function sci_xml_escape(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function sci_col_index(string $col): int {
  $n = 0;
  foreach (str_split($col) as $ch) {
    $n = $n * 26 + (ord($ch) - 64);
  }
  return $n;
}

/**
 * Replace or insert a shared-string cell without risky cross-tag regex.
 * Handles both <c ...>...</c> and self-closing <c .../>.
 */
function sci_sheet_set_shared_cell(string &$xml, string $cellRef, int $sharedIdx): bool {
  if ($xml === '' || !str_contains($xml, '<sheetData')) return false;

  $cellXml = '<c r="' . $cellRef . '" t="s"><v>' . $sharedIdx . '</v></c>';
  $needle = 'r="' . $cellRef . '"';
  $searchFrom = 0;

  while (($pos = strpos($xml, $needle, $searchFrom)) !== false) {
    $prefix = substr($xml, 0, $pos);
    $start = strrpos($prefix, '<c');
    if ($start === false) {
      $searchFrom = $pos + strlen($needle);
      continue;
    }
    // Confirm this is a <c ...> element (not something else starting with <c)
    $startSlice = substr($xml, $start, 3);
    if ($startSlice !== '<c ' && $startSlice !== '<c>' && !str_starts_with(substr($xml, $start), '<c\t')) {
      // also allow <c\r\n
      if (!preg_match('/^<c(\s|>)/', substr($xml, $start, 8))) {
        $searchFrom = $pos + strlen($needle);
        continue;
      }
    }
    // The r="REF" must belong to this opening tag (before '>')
    $openEnd = strpos($xml, '>', $start);
    if ($openEnd === false || $openEnd < $pos) {
      $searchFrom = $pos + strlen($needle);
      continue;
    }

    // Self-closing <c ... />
    if ($openEnd > $start && $xml[$openEnd - 1] === '/') {
      $xml = substr($xml, 0, $start) . $cellXml . substr($xml, $openEnd + 1);
      return true;
    }

    $close = strpos($xml, '</c>', $openEnd);
    if ($close === false) return false;
    $xml = substr($xml, 0, $start) . $cellXml . substr($xml, $close + 4);
    return true;
  }

  if (!preg_match('/^([A-Z]+)(\d+)$/', $cellRef, $m)) return false;
  $rowNum = $m[2];

  if (preg_match('/<row r="' . preg_quote($rowNum, '/') . '"[^>]*>/', $xml, $rm, PREG_OFFSET_CAPTURE)) {
    $rowPos = $rm[0][1];
    $rowTagLen = strlen($rm[0][0]);
    $xml = substr($xml, 0, $rowPos + $rowTagLen) . $cellXml . substr($xml, $rowPos + $rowTagLen);
    return true;
  }

  $closeSheet = strpos($xml, '</sheetData>');
  if ($closeSheet === false) return false;
  $insert = '<row r="' . $rowNum . '">' . $cellXml . '</row>';
  $xml = substr($xml, 0, $closeSheet) . $insert . substr($xml, $closeSheet);
  return true;
}

function sci_count_sheet_rows(string $sheetXml): int {
  return preg_match_all('/<row\b/i', $sheetXml) ?: 0;
}

/**
 * Persist status updates:
 * 1) Always write data/status_store.json (works on most servers)
 * 2) Best-effort sync into Excel P–U (never deletes applicant rows A–O)
 */
function sci_ensure_status_and_write(array $updates): array {
  if (function_exists('sci_use_mysql') && sci_use_mysql() && function_exists('sci_db_ensure_status_and_write')) {
    return sci_db_ensure_status_and_write($updates);
  }
  if (!$updates) {
    throw new InvalidArgumentException('ไม่มีรายการอัปเดต');
  }

  foreach ($updates as $u) {
    $sel = (string)($u['selection'] ?? '');
    $slot = strtoupper(trim((string)($u['assigned_slot'] ?? '')));
    if ($sel === 'ได้รับการคัดเลือก' && $slot !== '') {
      sci_assert_slot_usable($slot);
    }
  }

  // ---- 1) Durable JSON store (primary on servers) ----
  $store = sci_load_status_store();
  foreach ($updates as $u) {
    $row = (int)($u['row'] ?? 0);
    if ($row < 2) continue;
    $key = (string)$row;
    $prev = isset($store['by_row'][$key]) && is_array($store['by_row'][$key]) ? $store['by_row'][$key] : [];
    $store['by_row'][$key] = array_merge($prev, [
      'row' => $row,
      'status' => (string)($u['status'] ?? ($prev['status'] ?? '')),
      'missing_detail' => (string)($u['missing_detail'] ?? ($prev['missing_detail'] ?? '')),
      'review_note' => (string)($u['review_note'] ?? ($prev['review_note'] ?? '')),
      'selection' => (string)($u['selection'] ?? ($prev['selection'] ?? 'รอพิจารณา')),
      'reviewed_at' => (string)($u['reviewed_at'] ?? ($prev['reviewed_at'] ?? date('Y-m-d H:i:s'))),
      'assigned_slot' => strtoupper(trim((string)($u['assigned_slot'] ?? ($prev['assigned_slot'] ?? '')))),
      'saved_at' => date('c'),
    ]);
    if (($store['by_row'][$key]['selection'] ?? '') !== 'ได้รับการคัดเลือก') {
      $store['by_row'][$key]['assigned_slot'] = '';
    }
  }
  sci_save_status_store($store);

  $result = [
    'ok' => true,
    'updated' => count($updates),
    'mode' => 'status_store',
    'storage' => 'json',
    'status_store' => 'data/' . basename(sci_status_store_path()),
    'excel_synced' => false,
    'excel_error' => null,
    'path' => null,
    'applicants_before' => null,
    'applicants_after' => null,
    'message' => 'บันทึกสถานะใน data/' . basename(sci_status_store_path()) . ' สำเร็จ (ข้อมูลผู้สมัครใน Excel ไม่ถูกลบ)',
  ];

  // ---- 2) Best-effort Excel sync (optional) ----
  try {
    $excelResult = sci_sync_status_to_excel($updates);
    $result = array_merge($result, $excelResult);
    $result['storage'] = 'json+excel';
    $result['excel_synced'] = true;
    $result['message'] = 'บันทึกสถานะแล้วทั้งไฟล์สถานะและ Excel (ไม่ลบผู้สมัคร)';
  } catch (Throwable $e) {
    $result['excel_synced'] = false;
    $result['excel_error'] = $e->getMessage();
    $result['message'] = 'บันทึกสถานะในระบบสำเร็จแล้ว แต่ซิงก์ Excel ไม่ได้: ' . $e->getMessage();
  }

  return $result;
}

/**
 * Status-only Excel write (P–U). NEVER deletes applicant rows or alters A–O.
 */
function sci_sync_status_to_excel(array $updates): array {
  $path = sci_xlsx_path();
  if (!sci_is_writable_path(dirname($path)) || (file_exists($path) && !is_writable($path))) {
    throw new RuntimeException('ไฟล์/โฟลเดอร์ Excel เขียนไม่ได้บนเซิร์ฟเวอร์');
  }

  $beforeCount = 0;
  try {
    $rows = sci_read_sheet_rows($path);
    $beforeCount = max(0, count($rows) - 1);
  } catch (Throwable $e) {
    $beforeCount = 0;
  }

  $tmp = $path . '.tmp.zip';
  if (!@copy($path, $tmp)) {
    throw new RuntimeException('สร้างไฟล์ชั่วคราวไม่สำเร็จ (ตรวจสิทธิ์โฟลเดอร์บนเซิร์ฟเวอร์)');
  }

  $zip = sci_new_zip();
  if ($zip->open($tmp) !== true) {
    @unlink($tmp);
    throw new RuntimeException('เปิดไฟล์ชั่วคราวไม่ได้');
  }

  $ssXml = $zip->getFromName('xl/sharedStrings.xml');
  $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
  if (!$ssXml || !$sheetXml) {
    $zip->close();
    @unlink($tmp);
    throw new RuntimeException('โครงสร้าง Excel ไม่ครบ');
  }

  $rowsBefore = sci_count_sheet_rows($sheetXml);
  $ssLenBefore = strlen($ssXml);
  $sheetLenBefore = strlen($sheetXml);

  $strings = [];
  $sx = @simplexml_load_string($ssXml);
  if ($sx === false) {
    $zip->close();
    @unlink($tmp);
    throw new RuntimeException('อ่าน sharedStrings ไม่สำเร็จ');
  }
  foreach ($sx->si as $si) {
    $t = '';
    if (isset($si->t)) $t = (string)$si->t;
    else foreach ($si->r as $r) $t .= (string)$r->t;
    $strings[] = $t;
  }
  $origStringCount = count($strings);

  $stringIndex = function (string $value) use (&$strings): int {
    foreach ($strings as $i => $s) {
      if ($s === $value) return $i;
    }
    $strings[] = $value;
    return count($strings) - 1;
  };

  foreach (SCI_STATUS_HEADERS as $h) {
    $stringIndex($h);
  }
  foreach ($updates as $u) {
    foreach (['status', 'missing_detail', 'review_note', 'selection', 'reviewed_at', 'assigned_slot'] as $f) {
      if (array_key_exists($f, $u)) $stringIndex((string)$u[$f]);
    }
  }

  if (count($strings) < $origStringCount) {
    $zip->close();
    @unlink($tmp);
    throw new RuntimeException('ตารางข้อความเสียหาย ยกเลิกการซิงก์ Excel');
  }

  $count = count($strings);
  $ssOut = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $count . '" uniqueCount="' . $count . '">';
  foreach ($strings as $s) {
    $ssOut .= '<si><t xml:space="preserve">' . sci_xml_escape($s) . '</t></si>';
  }
  $ssOut .= '</sst>';

  foreach (SCI_STATUS_HEADERS as $col => $title) {
    if (!sci_sheet_set_shared_cell($sheetXml, $col . '1', $stringIndex($title))) {
      $zip->close();
      @unlink($tmp);
      throw new RuntimeException('อัปเดตหัวตารางสถานะไม่สำเร็จ');
    }
  }

  foreach ($updates as $u) {
    $row = (int)$u['row'];
    if ($row < 2) continue;
    $map = [
      'P' => (string)($u['status'] ?? ''),
      'Q' => (string)($u['missing_detail'] ?? ''),
      'R' => (string)($u['review_note'] ?? ''),
      'S' => (string)($u['selection'] ?? 'รอพิจารณา'),
      'T' => (string)($u['reviewed_at'] ?? ''),
      'U' => strtoupper(trim((string)($u['assigned_slot'] ?? ''))),
    ];
    foreach ($map as $col => $val) {
      if (!sci_sheet_set_shared_cell($sheetXml, $col . $row, $stringIndex($val))) {
        $zip->close();
        @unlink($tmp);
        throw new RuntimeException('อัปเดตสถานะแถว ' . $row . ' ไม่สำเร็จ');
      }
    }
  }

  $rowsAfter = sci_count_sheet_rows($sheetXml);
  if ($rowsAfter < $rowsBefore || strlen($sheetXml) < (int)($sheetLenBefore * 0.5) || strlen($ssOut) < (int)($ssLenBefore * 0.5)) {
    $zip->close();
    @unlink($tmp);
    throw new RuntimeException('ตรวจพบว่าชีตจะเสียหาย จึงยกเลิกการซิงก์ Excel');
  }

  if (!$zip->addFromString('xl/sharedStrings.xml', $ssOut) || !$zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml)) {
    $zip->close();
    @unlink($tmp);
    throw new RuntimeException('เขียนข้อมูลเข้าไฟล์ชั่วคราวไม่สำเร็จ');
  }
  $zip->close();

  $bak = $path . '.bak';
  if (!@copy($path, $bak)) {
    @unlink($tmp);
    throw new RuntimeException('สำรองไฟล์เดิมไม่สำเร็จ (ตรวจสิทธิ์โฟลเดอร์)');
  }

  $swapped = @rename($tmp, $path);
  if (!$swapped) {
    $removed = @unlink($path);
    $swapped = @rename($tmp, $path);
    if (!$swapped) {
      if ($removed) @copy($bak, $path);
      @unlink($tmp);
      throw new RuntimeException('บันทึกไฟล์ Excel ไม่สำเร็จ (ไฟล์อาจถูกเปิดอยู่หรือโฟลเดอร์เขียนไม่ได้)');
    }
  }

  try {
    $afterRows = sci_read_sheet_rows($path);
    $afterCount = max(0, count($afterRows) - 1);
  } catch (Throwable $e) {
    @copy($bak, $path);
    throw new RuntimeException('ไฟล์หลังบันทึกอ่านไม่ได้ จึงคืนค่าจาก .bak — ' . $e->getMessage());
  }
  if ($beforeCount > 0 && $afterCount < max(1, (int)floor($beforeCount * 0.8))) {
    @copy($bak, $path);
    throw new RuntimeException(
      "ซิงก์ Excel แล้วแต่จำนวนผู้สมัครลดลงผิดปกติ ({$beforeCount} → {$afterCount}) จึงคืนค่าจาก .bak"
    );
  }

  return [
    'path' => basename($path),
    'mode' => 'status_only',
    'applicants_before' => $beforeCount,
    'applicants_after' => $afterCount,
  ];
}

/**
 * Assign operator to a shop slot via status fields only (no row deletion).
 * Previous assignee of the same slot is cleared to รอพิจารณา (row kept).
 *
 * $allowCross: allow applicant from another category when the slot category has no applicants.
 */
function sci_assign_shop(int $row, string $slotId, ?array $currentData = null, bool $allowCross = false): array {
  $slotId = strtoupper(trim($slotId));
  sci_assert_slot_usable($slotId);
  $slots = sci_active_slots();
  $slot = null;
  foreach ($slots as $s) {
    if ($s['id'] === $slotId) { $slot = $s; break; }
  }
  if (!$slot) {
    throw new InvalidArgumentException(
      sci_is_followup_round()
        ? 'ล็อก ' . $slotId . ' ไม่ได้อยู่ในรอบที่ ' . sci_current_round() . ' (อาจถูกคัดเลือกแล้วใน' . sci_prior_rounds_label() . ')'
        : 'ไม่พบล็อกร้าน ' . $slotId
    );
  }

  $data = $currentData ?? sci_parse_applicants();
  $target = null;
  foreach ($data['applicants'] as $a) {
    if ((int)$a['row'] === $row) { $target = $a; break; }
  }
  if (!$target) {
    throw new InvalidArgumentException('ไม่พบผู้สมัครแถว ' . $row);
  }

  $normTarget = sci_normalize_cat($target['category']);
  $normSlot = sci_normalize_cat($slot['cat']);
  $sameCat = ($normTarget === $normSlot);
  $isCross = !$sameCat;

  if ($isCross) {
    if (!$allowCross) {
      throw new InvalidArgumentException('ผู้สมัครไม่ตรงประเภทของล็อก ' . $slotId);
    }
    // Only fill empty categories (no native applicants for this slot type)
    $hasNative = false;
    foreach ($data['applicants'] as $a) {
      if (sci_normalize_cat($a['category']) === $normSlot && ($a['zone'] ?? '') === $slot['zone']) {
        $hasNative = true;
        break;
      }
    }
    if ($hasNative) {
      throw new InvalidArgumentException('ล็อกนี้ยังมีผู้สมัครประเภทตรงอยู่ ใช้การคัดเลือกปกติ');
    }
    // Cross-fill: exclude shops already selected into another lock
    if (($target['selection'] ?? '') === 'ได้รับการคัดเลือก') {
      $alreadySlot = strtoupper(trim((string)($target['assigned_slot'] ?? '')));
      if ($alreadySlot !== $slotId) {
        throw new InvalidArgumentException(
          $alreadySlot !== ''
            ? 'ร้านนี้ถูกคัดเลือกเข้าล็อก ' . $alreadySlot . ' แล้ว — เลือกร้านที่ยังไม่ถูกคัดเลือก'
            : 'ร้านนี้ได้รับการคัดเลือกแล้ว — เลือกร้านที่ยังไม่ถูกคัดเลือก'
        );
      }
    }
  }

  $now = date('Y-m-d H:i:s');
  $updates = [];

  foreach ($data['applicants'] as $a) {
    if ((int)$a['row'] === $row) continue;
    if (strtoupper(trim((string)($a['assigned_slot'] ?? ''))) !== $slotId) continue;
    $updates[] = [
      'row' => (int)$a['row'],
      'status' => preg_replace('/\s*\(อัตโนมัติ\)\s*/u', '', (string)($a['status'] ?? 'รอตรวจสอบ')) ?: 'รอตรวจสอบ',
      'missing_detail' => (string)($a['missing_detail'] ?? ''),
      'review_note' => (string)($a['review_note'] ?? ''),
      'selection' => 'รอพิจารณา',
      'reviewed_at' => $now,
      'assigned_slot' => '',
    ];
  }

  $note = (string)($target['review_note'] ?? '');
  if ($isCross) {
    $tag = 'จัดลงล็อกว่าง ' . $slotId . ' / ' . $slot['cat'] . ' (สมัครเดิม: โซน ' . $target['zone'] . ' · ' . $target['category'] . ')';
    if (mb_strpos($note, 'จัดลงล็อกว่าง ' . $slotId) === false) {
      $note = trim($note . ($note !== '' ? ' · ' : '') . $tag);
    }
  }

  $updates[] = [
    'row' => $row,
    'status' => preg_replace('/\s*\(อัตโนมัติ\)\s*/u', '', (string)($target['status'] ?? 'รอตรวจสอบ')) ?: 'รอตรวจสอบ',
    'missing_detail' => (string)($target['missing_detail'] ?? ''),
    'review_note' => $note,
    'selection' => 'ได้รับการคัดเลือก',
    'reviewed_at' => $now,
    'assigned_slot' => $slotId,
  ];

  return sci_ensure_status_and_write($updates);
}

/**
 * Clear shop assignment (status only). Applicant row is never deleted.
 */
function sci_unassign_shop(int $row, string $selection = 'รอพิจารณา'): array {
  $data = sci_parse_applicants();
  $target = null;
  foreach ($data['applicants'] as $a) {
    if ((int)$a['row'] === $row) { $target = $a; break; }
  }
  if (!$target) {
    throw new InvalidArgumentException('ไม่พบผู้สมัครแถว ' . $row);
  }
  $allowed = ['ได้รับการคัดเลือก', 'ไม่ได้รับการคัดเลือก', 'รอพิจารณา'];
  if (!in_array($selection, $allowed, true)) $selection = 'รอพิจารณา';

  return sci_ensure_status_and_write([[
    'row' => $row,
    'status' => preg_replace('/\s*\(อัตโนมัติ\)\s*/u', '', (string)($target['status'] ?? 'รอตรวจสอบ')) ?: 'รอตรวจสอบ',
    'missing_detail' => (string)($target['missing_detail'] ?? ''),
    'review_note' => (string)($target['review_note'] ?? ''),
    'selection' => $selection,
    'reviewed_at' => date('Y-m-d H:i:s'),
    'assigned_slot' => '',
  ]]);
}

function sci_save_payload_json(array $data): void {
  $meta = sci_round_meta();
  $path = sci_dir() . DIRECTORY_SEPARATOR . $meta['payload'];
  $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  // Cache file is optional — do not break API if directory is not writable
  if (@file_put_contents($path, $json) === false) {
    // ignore permission errors on shared hosting
  }
}
