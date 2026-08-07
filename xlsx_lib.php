<?php
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/sci_zip.php';

const SCI_STATUS_HEADERS = [
  'P' => 'สถานะเอกสาร (ระบบตรวจ)',
  'Q' => 'เอกสารที่ขาด / รายละเอียด',
  'R' => 'คุณสมบัติ / หมายเหตุการพิจารณา',
  'S' => 'ผลการคัดเลือก',
  'T' => 'วันเวลาที่ตรวจ',
  'U' => 'ล็อคร้านที่ได้รับ',
];

/** Columns that the system may UPDATE. Never delete applicant rows or alter A–O form data. */
const SCI_WRITABLE_STATUS_COLS = ['P', 'Q', 'R', 'S', 'T', 'U'];

function sci_dir(): string {
  return __DIR__;
}

function sci_xlsx_path(): string {
  $dir = sci_dir();
  $files = array_values(array_filter(scandir($dir), function ($n) {
    return !str_starts_with($n, '~$') && !str_starts_with($n, '_') && str_ends_with($n, '.xlsx');
  }));
  if (!$files) {
    throw new RuntimeException('ไม่พบไฟล์ .xlsx');
  }
  // Prefer แบบตอบรับ if present
  foreach ($files as $f) {
    if (mb_strpos($f, 'แบบตอบรับ') !== false || mb_strpos($f, 'ตอบรับ') !== false) {
      return $dir . DIRECTORY_SEPARATOR . $f;
    }
  }
  return $dir . DIRECTORY_SEPARATOR . $files[0];
}

function sci_slots(): array {
  return [
    ['id'=>'A1','zone'=>'A','cat'=>'เครื่องดื่มไม่มีแอลกอฮอล์','limit'=>3],
    ['id'=>'A2','zone'=>'A','cat'=>'ข้าวไข่เจียว อาหารตามสั่ง','limit'=>1],
    ['id'=>'A3','zone'=>'A','cat'=>'มันฝรั่งทอด','limit'=>1],
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
    ['id'=>'B6','zone'=>'B','cat'=>'ยำ','limit'=>1],
    ['id'=>'C1','zone'=>'C','cat'=>'ไอศกรีม','limit'=>1],
    ['id'=>'C2','zone'=>'C','cat'=>'ลูกชุบ/ขนมเบื้อง/ขนมไทย','limit'=>1],
    ['id'=>'C3','zone'=>'C','cat'=>'พิซซ่า','limit'=>1],
    ['id'=>'C4','zone'=>'C','cat'=>'ข้าวเหนียวหมูปิ้ง','limit'=>1],
    ['id'=>'C5','zone'=>'C','cat'=>'ข้าวไข่เจียว อาหารตามสั่ง','limit'=>1],
    ['id'=>'C6','zone'=>'C','cat'=>'แจ่วฮ้อน/ก๋วยจั๊บ','limit'=>1],
    ['id'=>'C7','zone'=>'C','cat'=>'สื่อเกมการศึกษา/บอร์ดเกม','limit'=>1],
    ['id'=>'C8','zone'=>'C','cat'=>'สื่อเกมการศึกษา/บอร์ดเกม','limit'=>1],
    ['id'=>'C9','zone'=>'C','cat'=>'สื่อเกมการศึกษา/บอร์ดเกม','limit'=>1],
    ['id'=>'C10','zone'=>'C','cat'=>'วาฟเฟิล','limit'=>1],
    ['id'=>'C11','zone'=>'C','cat'=>'ขนมจีบ/ซาลาเปา','limit'=>1],
    ['id'=>'C12','zone'=>'C','cat'=>'สุกี้โรล/เกี๊ยวต้ม/ชาบู','limit'=>1],
    ['id'=>'C13','zone'=>'C','cat'=>'ยำ','limit'=>1],
    ['id'=>'C14','zone'=>'C','cat'=>'ผลไม้','limit'=>1],
    ['id'=>'D1','zone'=>'D','cat'=>'หม่าล่าย่าง (เสียบไม้)','limit'=>1],
    ['id'=>'D2','zone'=>'D','cat'=>'ลูกชิ้นทอด/นึ่ง','limit'=>1],
    ['id'=>'D3','zone'=>'D','cat'=>'ซูชิ/อาหารญี่ปุ่น','limit'=>1],
    ['id'=>'D4','zone'=>'D','cat'=>'สโมกี้ไบท์','limit'=>1],
    ['id'=>'D5','zone'=>'D','cat'=>'ขนมจีบ/ซาลาเปา','limit'=>1],
    ['id'=>'D6','zone'=>'D','cat'=>'ไอศกรีม','limit'=>1],
    ['id'=>'D7','zone'=>'D','cat'=>'แจ่วฮ้อน/ก๋วยจั๊บ','limit'=>1],
    ['id'=>'D8','zone'=>'D','cat'=>'ไก่ย่าง/ส้มตำ','limit'=>1],
  ];
}

function sci_normalize_cat(string $cat): string {
  $cat = preg_replace('/\s*\(จำกัด[^)]*\)\s*/u', '', $cat);
  return trim(preg_replace('/\s+/u', ' ', $cat));
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

function sci_drive_view(string $url): string {
  if (preg_match('/id=([a-zA-Z0-9_-]+)/', $url, $m)) {
    return 'https://drive.google.com/file/d/' . $m[1] . '/view';
  }
  if (preg_match('#/d/([a-zA-Z0-9_-]+)#', $url, $m)) {
    return 'https://drive.google.com/file/d/' . $m[1] . '/view';
  }
  return $url;
}

function sci_drive_id(string $url): ?string {
  if (preg_match('/id=([a-zA-Z0-9_-]+)/', $url, $m)) return $m[1];
  if (preg_match('#/d/([a-zA-Z0-9_-]+)#', $url, $m)) return $m[1];
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

function sci_parse_applicants(?string $path = null): array {
  $path = $path ?? sci_xlsx_path();
  $rows = sci_read_sheet_rows($path);
  if (!$rows) return ['source' => basename($path), 'applicants' => [], 'slots' => sci_slots(), 'groups' => [], 'shared' => []];

  $header = array_shift($rows);
  $apps = [];
  foreach ($rows as $r) {
    $name = trim($r['D'] ?? '');
    if ($name === '') continue;

    $zoneRaw = $r['F'] ?? '';
    $zone = strtoupper(substr(preg_replace('/[^ABCD]/ui', '', str_replace(['โซน', 'โซต', ' '], '', $zoneRaw)), 0, 1));
    $cat = match ($zone) {
      'A' => $r['G'] ?? '',
      'B' => $r['H'] ?? '',
      'C' => $r['I'] ?? '',
      'D' => $r['J'] ?? '',
      default => '',
    };
    $tsRaw = $r['A'] ?? '';
    $timeInfo = sci_parse_timestamp($tsRaw);
    $foodRaw = array_values(array_filter(array_map('trim', explode(',', $r['O'] ?? ''))));
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

    $autoMissing = [];
    if (trim($r['L'] ?? '') === '') $autoMissing[] = 'สำเนาบัตรประชาชน';
    if (trim($r['M'] ?? '') === '') $autoMissing[] = 'สำเนาทะเบียนบ้าน';
    if (trim($r['N'] ?? '') === '') $autoMissing[] = 'รูปถ่ายหน้าตรง';
    if (count($food) === 0) $autoMissing[] = 'ภาพถ่ายอาหาร/สินค้า';
    if (trim($r['C'] ?? '') === '') $autoMissing[] = 'คุณสมบัติตามประกาศ';

    $statusRaw = trim($r['P'] ?? '');
    $missingDetail = trim($r['Q'] ?? '');
    $note = trim($r['R'] ?? '');
    $selection = trim($r['S'] ?? '');
    $reviewedAt = trim($r['T'] ?? '');
    $assignedSlot = strtoupper(trim($r['U'] ?? ''));

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
      'phone' => trim($r['E'] ?? ''),
      'zone' => $zone,
      'zone_raw' => $zoneRaw,
      'category' => sci_normalize_cat($cat),
      'category_raw' => $cat,
      'detail' => trim($r['K'] ?? ''),
      'qualifications' => trim($r['C'] ?? ''),
      'id_card' => sci_drive_view($r['L'] ?? ''),
      'house_reg' => sci_drive_view($r['M'] ?? ''),
      'photo' => sci_drive_view($r['N'] ?? ''),
      'food_photos' => $food,
      'docs' => [
        'id_card' => trim($r['L'] ?? '') !== '',
        'house_reg' => trim($r['M'] ?? '') !== '',
        'photo' => trim($r['N'] ?? '') !== '',
        'food' => count($food) > 0,
        'qualify' => trim($r['C'] ?? '') !== '',
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
      'key' => md5($timeInfo['datetime'] . '|' . $name . '|' . $zone . '|' . ((int)$r['_row'])),
    ];
  }

  usort($apps, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

  $slots = sci_slots();
  $shared = [];
  foreach ($slots as $s) {
    $shared[$s['zone'] . '|' . $s['cat']][] = $s['id'];
  }

  // Unique groups in slot order A1..D8
  $groups = [];
  $seen = [];
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
      'limit' => $s['limit'],
      'primary' => $ids[0],
    ];
  }

  $shopReport = sci_build_shop_report($slots, $apps);
  $docSummary = sci_build_doc_summary($apps);

  return [
    'generated_at' => date('c'),
    'source' => basename($path),
    'source_mtime' => date('c', filemtime($path)),
    'total_applicants' => count($apps),
    'slots' => $slots,
    'groups' => $groups,
    'shared' => $shared,
    'applicants' => $apps,
    'shop_report' => $shopReport,
    'doc_summary' => $docSummary,
    'policy' => 'ระบบอัปเดตเฉพาะคอลัมน์สถานะ (P–U) เท่านั้น ไม่ลบรายการผู้สมัครใน Excel',
    'notice' => 'นำเข้า Excel ล่าสุดจาก Google Sheet ได้ที่ปุ่มนำเข้าข้อมูล · การเช็คเอกสาร/คัดเลือกเป็นการอัปเดตสถานะเท่านั้น ไม่ลบแถวผู้สมัคร · สถานะเอกสารยึดตาม Admin เมื่อบันทึกแล้ว · เวลาที่แสดงเป็นเวลาไทย (Asia/Bangkok)',
  ];
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
      ] : null,
    ];
  }
  return $report;
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

function sci_ensure_status_and_write(array $updates): array {
  // Status-only write (P–U). NEVER deletes applicant rows or alters form columns A–O.
  $path = sci_xlsx_path();
  $beforeCount = 0;
  try {
    $beforeCount = (int)(sci_parse_applicants($path)['total_applicants'] ?? 0);
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

  // Parse shared strings into array and rebuild later (append-only index)
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
    throw new RuntimeException('ตารางข้อความเสียหาย ยกเลิกการบันทึก');
  }

  $count = count($strings);
  $ssOut = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $count . '" uniqueCount="' . $count . '">';
  foreach ($strings as $s) {
    $ssOut .= '<si><t xml:space="preserve">' . sci_xml_escape($s) . '</t></si>';
  }
  $ssOut .= '</sst>';

  // Headers on row 1
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
    throw new RuntimeException('ตรวจพบว่าชีตจะเสียหาย จึงยกเลิกการบันทึก (แถวเดิมถูกเก็บไว้)');
  }

  if (!$zip->addFromString('xl/sharedStrings.xml', $ssOut) || !$zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml)) {
    $zip->close();
    @unlink($tmp);
    throw new RuntimeException('เขียนข้อมูลเข้าไฟล์ชั่วคราวไม่สำเร็จ');
  }
  $zip->close();

  // Backup then replace — never leave a half-written workbook
  $bak = $path . '.bak';
  if (!@copy($path, $bak)) {
    @unlink($tmp);
    throw new RuntimeException('สำรองไฟล์เดิมไม่สำเร็จ (ตรวจสิทธิ์โฟลเดอร์)');
  }

  $swapped = @rename($tmp, $path);
  if (!$swapped) {
    // Windows / some hosts need unlink first; keep bak for restore
    $removed = @unlink($path);
    $swapped = @rename($tmp, $path);
    if (!$swapped) {
      if ($removed) @copy($bak, $path);
      @unlink($tmp);
      throw new RuntimeException('บันทึกไฟล์ Excel ไม่สำเร็จ (ไฟล์อาจถูกเปิดอยู่หรือโฟลเดอร์เขียนไม่ได้)');
    }
  }

  // Verify applicants still present; restore bak if catastrophic loss
  try {
    $afterCount = (int)(sci_parse_applicants($path)['total_applicants'] ?? 0);
  } catch (Throwable $e) {
    @copy($bak, $path);
    throw new RuntimeException('ไฟล์หลังบันทึกอ่านไม่ได้ จึงคืนค่าจาก .bak — ' . $e->getMessage());
  }
  if ($beforeCount > 0 && $afterCount < max(1, (int)floor($beforeCount * 0.8))) {
    @copy($bak, $path);
    throw new RuntimeException(
      "บันทึกแล้วแต่จำนวนผู้สมัครลดลงผิดปกติ ({$beforeCount} → {$afterCount}) จึงคืนค่าจาก .bak อัตโนมัติ"
    );
  }

  return [
    'ok' => true,
    'path' => basename($path),
    'updated' => count($updates),
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
  $slots = sci_slots();
  $slot = null;
  foreach ($slots as $s) {
    if ($s['id'] === $slotId) { $slot = $s; break; }
  }
  if (!$slot) {
    throw new InvalidArgumentException('ไม่พบล็อคร้าน ' . $slotId);
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
      throw new InvalidArgumentException('ผู้สมัครไม่ตรงประเภทของล็อค ' . $slotId);
    }
    // Only fill empty categories (no native applicants for this slot type)
    $hasNative = false;
    foreach ($data['applicants'] as $a) {
      if (sci_normalize_cat($a['category']) === $normSlot && ($a['zone'] ?? '') === $slot['zone']) {
        $hasNative = true;
        break;
      }
    }
    // Also check other zones with same cat? User said empty shops - per slot category in that zone
    if ($hasNative) {
      throw new InvalidArgumentException('ล็อคนี้ยังมีผู้สมัครประเภทตรงอยู่ ใช้การคัดเลือกปกติ');
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
    $tag = 'จัดลงล็อคว่าง ' . $slotId . ' / ' . $slot['cat'] . ' (สมัครเดิม: โซน ' . $target['zone'] . ' · ' . $target['category'] . ')';
    if (mb_strpos($note, 'จัดลงล็อคว่าง ' . $slotId) === false) {
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
  $path = sci_dir() . DIRECTORY_SEPARATOR . 'applicants.json';
  $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  // Cache file is optional — do not break API if directory is not writable
  if (@file_put_contents($path, $json) === false) {
    // ignore permission errors on shared hosting
  }
}
