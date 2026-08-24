<?php
/**
 * MySQL data layer for sciweekshop — same payload shape as Excel/JSON path.
 * Enabled when DB is reachable and has an active event.
 */

require_once __DIR__ . '/db.php';

function sci_use_mysql(bool $reset = false): bool {
  static $flag = null;
  if ($reset) $flag = null;
  if ($flag !== null) return $flag;
  $env = getenv('SCI_USE_MYSQL');
  if ($env === '0' || $env === 'false') {
    $flag = false;
    return $flag;
  }
  try {
    $pdo = sci_db();
    $st = $pdo->query("SELECT id FROM events WHERE is_active = 1 ORDER BY year_be DESC, id DESC LIMIT 1");
    $flag = (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    $flag = false;
  }
  return $flag;
}

function sci_db_clear_runtime_caches(): void {
  $GLOBALS['SCI_DB_OCCUPIED_CACHE'] = [];
  $GLOBALS['SCI_DB_SLOTS_CACHE'] = null;
  $GLOBALS['SCI_DB_EVENT_CACHE'] = null;
  $GLOBALS['SCI_DB_ROUNDS_CACHE'] = null;
  $GLOBALS['SCI_ALUMNI_CACHE'] = null;
  if (function_exists('sci_use_mysql')) {
    sci_use_mysql(true);
  }
}

/** @return array{id:int,code:string,title:string,year_be:int} */
function sci_db_active_event(): array {
  if (!empty($GLOBALS['SCI_DB_EVENT_CACHE'])) {
    return $GLOBALS['SCI_DB_EVENT_CACHE'];
  }
  $pdo = sci_db();
  $st = $pdo->query(
    "SELECT id, code, title, year_be FROM events WHERE is_active = 1 ORDER BY year_be DESC, id DESC LIMIT 1"
  );
  $row = $st->fetch();
  if (!$row) {
    throw new RuntimeException('ไม่พบกิจกรรมในฐานข้อมูล sciweekshop');
  }
  $GLOBALS['SCI_DB_EVENT_CACHE'] = [
    'id' => (int)$row['id'],
    'code' => (string)$row['code'],
    'title' => (string)$row['title'],
    'year_be' => (int)$row['year_be'],
  ];
  return $GLOBALS['SCI_DB_EVENT_CACHE'];
}

/**
 * Resolve event by public code (for multi-event apply links).
 * Does not require is_active and does not overwrite the active-event cache.
 *
 * @return array{id:int,code:string,title:string,year_be:int}
 */
function sci_db_event_by_code(string $code): array {
  $code = trim($code);
  if ($code === '' || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}$/', $code)) {
    throw new InvalidArgumentException('รหัสกิจกรรมไม่ถูกต้อง');
  }
  $st = sci_db()->prepare(
    'SELECT id, code, title, year_be FROM events WHERE code = ? LIMIT 1'
  );
  $st->execute([$code]);
  $row = $st->fetch();
  if (!$row) {
    throw new InvalidArgumentException(
      'ไม่พบกิจกรรมรหัส "' . $code . '" กรุณาตรวจสอบลิงก์สมัคร'
    );
  }
  return [
    'id' => (int)$row['id'],
    'code' => (string)$row['code'],
    'title' => (string)$row['title'],
    'year_be' => (int)$row['year_be'],
  ];
}

/** @return list<array{id:int,round_no:int,title:string,apply_open_at:?string,apply_close_at:?string,is_open:int}> */
function sci_db_event_rounds(): array {
  if (isset($GLOBALS['SCI_DB_ROUNDS_CACHE']) && is_array($GLOBALS['SCI_DB_ROUNDS_CACHE'])) {
    return $GLOBALS['SCI_DB_ROUNDS_CACHE'];
  }
  $event = sci_db_active_event();
  $st = sci_db()->prepare(
    'SELECT id, round_no, title, apply_open_at, apply_close_at, is_open
     FROM event_rounds WHERE event_id = ? ORDER BY round_no'
  );
  $st->execute([(int)$event['id']]);
  $rows = $st->fetchAll();
  $GLOBALS['SCI_DB_ROUNDS_CACHE'] = $rows ?: [];
  return $GLOBALS['SCI_DB_ROUNDS_CACHE'];
}

function sci_db_round_id(?int $roundNo = null): int {
  $roundNo = sci_normalize_round($roundNo ?? sci_current_round());
  foreach (sci_db_event_rounds() as $r) {
    if ((int)$r['round_no'] === $roundNo) {
      return (int)$r['id'];
    }
  }
  throw new RuntimeException('ไม่พบรอบที่ ' . $roundNo . ' ในฐานข้อมูล');
}

function sci_db_max_round(): int {
  $rounds = sci_db_event_rounds();
  if (!$rounds) return 1;
  $max = 1;
  foreach ($rounds as $r) {
    $max = max($max, (int)$r['round_no']);
  }
  return $max;
}

/**
 * Slots for active event — same shape as sci_slots().
 * @return list<array{id:string,zone:string,cat:string,limit:int}>
 */
function sci_db_slots(): array {
  if (isset($GLOBALS['SCI_DB_SLOTS_CACHE']) && is_array($GLOBALS['SCI_DB_SLOTS_CACHE'])) {
    return $GLOBALS['SCI_DB_SLOTS_CACHE'];
  }
  $event = sci_db_active_event();
  $st = sci_db()->prepare(
    'SELECT s.code, z.code AS zone_code, s.category, s.slot_limit
     FROM slots s
     JOIN zones z ON z.id = s.zone_id
     WHERE s.event_id = ? AND s.is_active = 1
     ORDER BY s.sort_order, s.code'
  );
  $st->execute([(int)$event['id']]);
  $out = [];
  foreach ($st->fetchAll() as $row) {
    $out[] = [
      'id' => (string)$row['code'],
      'zone' => (string)$row['zone_code'],
      'cat' => (string)$row['category'],
      'limit' => (int)$row['slot_limit'],
    ];
  }
  $GLOBALS['SCI_DB_SLOTS_CACHE'] = $out;
  return $out;
}

/** @return array<string,int> slot code => slot id */
function sci_db_slot_id_map(): array {
  static $map = null;
  $event = sci_db_active_event();
  $cacheKey = 'e' . $event['id'];
  if (is_array($map) && isset($map['_key']) && $map['_key'] === $cacheKey) {
    return $map['data'];
  }
  $st = sci_db()->prepare('SELECT id, code FROM slots WHERE event_id = ?');
  $st->execute([(int)$event['id']]);
  $data = [];
  foreach ($st->fetchAll() as $row) {
    $data[(string)$row['code']] = (int)$row['id'];
  }
  $map = ['_key' => $cacheKey, 'data' => $data];
  return $data;
}

/**
 * @return array<string, array{slot:string,name:string,row:int,zone:string,category:string}>
 */
function sci_db_round_occupied_slots(int $round): array {
  $round = sci_normalize_round($round);
  $cache = $GLOBALS['SCI_DB_OCCUPIED_CACHE'] ?? [];
  if (isset($cache[$round])) {
    return $cache[$round];
  }

  $event = sci_db_active_event();
  $st = sci_db()->prepare(
    'SELECT a.legacy_excel_row, a.name, a.zone_code, a.category, s.code AS slot_code
     FROM applicants a
     JOIN event_rounds er ON er.id = a.round_id
     JOIN slots s ON s.id = a.assigned_slot_id
     WHERE a.event_id = ? AND er.round_no = ? AND a.selection = ?
       AND a.assigned_slot_id IS NOT NULL'
  );
  $st->execute([(int)$event['id'], $round, 'ได้รับการคัดเลือก']);
  $taken = [];
  foreach ($st->fetchAll() as $row) {
    $slot = strtoupper(trim((string)$row['slot_code']));
    if ($slot === '' || isset($taken[$slot])) continue;
    $taken[$slot] = [
      'slot' => $slot,
      'name' => (string)$row['name'],
      'row' => (int)($row['legacy_excel_row'] ?? 0),
      'zone' => (string)$row['zone_code'],
      'category' => sci_normalize_cat((string)$row['category']),
    ];
  }
  $cache[$round] = $taken;
  $GLOBALS['SCI_DB_OCCUPIED_CACHE'] = $cache;
  return $taken;
}

function sci_db_storage_health(): array {
  $meta = sci_round_meta();
  $ok = false;
  $err = null;
  try {
    sci_db()->query('SELECT 1');
    $ok = true;
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
  $event = null;
  try {
    $event = sci_db_active_event();
  } catch (Throwable $e) {
    $err = $err ?: $e->getMessage();
  }
  return [
    'backend' => 'mysql',
    'database' => sci_db_config()['dbname'] ?? 'sciweekshop',
    'mysql_ok' => $ok,
    'event' => $event,
    'data_dir' => 'mysql',
    'data_writable' => $ok,
    'status_store' => 'applicants',
    'status_store_writable' => $ok,
    'xlsx' => null,
    'xlsx_writable' => false,
    'can_save_status' => $ok,
    'round' => $meta,
    'hint' => $ok
      ? ('บันทึกสถานะลง MySQL ฐานข้อมูล ' . (sci_db_config()['dbname'] ?? 'sciweekshop') . ' · ' . $meta['title'])
      : ('เชื่อม MySQL ไม่ได้' . ($err ? ': ' . $err : '')),
  ];
}

function sci_db_load_alumni(): array {
  if (isset($GLOBALS['SCI_ALUMNI_CACHE']) && is_array($GLOBALS['SCI_ALUMNI_CACHE'])) {
    return $GLOBALS['SCI_ALUMNI_CACHE'];
  }
  try {
    $st = sci_db()->query(
      'SELECT id, year_be, name, aliases, slot_code, category, event_label, source_ref, payment_status
       FROM alumni_vendors ORDER BY year_be DESC, id'
    );
    $vendors = [];
    $year = 2568;
    $label = 'SCI Week 2568';
    $sourceRef = '';
    $unpaidCount = 0;
    foreach ($st->fetchAll() as $row) {
      $year = (int)$row['year_be'];
      if ($row['event_label']) $label = (string)$row['event_label'];
      if ($row['source_ref']) $sourceRef = (string)$row['source_ref'];
      $aliases = [];
      if (!empty($row['aliases'])) {
        $decoded = json_decode((string)$row['aliases'], true);
        if (is_array($decoded)) $aliases = $decoded;
      }
      $pay = (string)($row['payment_status'] ?? 'unknown');
      $v = [
        'id' => (int)$row['id'],
        'name' => (string)$row['name'],
        'slot' => (string)($row['slot_code'] ?? ''),
        'category' => (string)($row['category'] ?? ''),
        'year' => (int)$row['year_be'],
        'payment_status' => $pay,
      ];
      if ($aliases) $v['aliases'] = $aliases;
      if ($pay === 'unpaid') {
        $v['payment_warning'] = true;
        $unpaidCount++;
      }
      $vendors[] = $v;
    }
    $cache = [
      'year' => $year,
      'label' => $label,
      'source_ref' => $sourceRef,
      'vendors' => $vendors,
      'unpaid_count' => $unpaidCount,
    ];
  } catch (Throwable $e) {
    $cache = ['year' => 2568, 'vendors' => [], 'label' => 'SCI Week 2568', 'unpaid_count' => 0];
  }
  $GLOBALS['SCI_ALUMNI_CACHE'] = $cache;
  return $cache;
}

/**
 * Upsert alumni_vendors from a selected applicant (for next-year warnings).
 */
function sci_db_upsert_alumni_from_applicant(array $appRow, array $event): void {
  $name = trim((string)($appRow['name'] ?? ''));
  if ($name === '') return;
  $selection = (string)($appRow['selection'] ?? '');
  if ($selection !== 'ได้รับการคัดเลือก') return;

  $yearBe = (int)($event['year_be'] ?? 0);
  if ($yearBe < 2500) return;

  $pay = sci_normalize_payment_status((string)($appRow['payment_status'] ?? 'unpaid'));
  $slot = strtoupper(trim((string)($appRow['assigned_slot_code'] ?? $appRow['assigned_slot'] ?? '')));
  if ($slot === '' && !empty($appRow['assigned_slot_id'])) {
    $st = sci_db()->prepare('SELECT code FROM slots WHERE id = ?');
    $st->execute([(int)$appRow['assigned_slot_id']]);
    $slot = strtoupper(trim((string)$st->fetchColumn()));
  }
  $category = trim((string)($appRow['category'] ?? ''));
  $label = (string)($event['title'] ?? ('SCI Week ' . $yearBe));
  $sourceRef = 'event:' . (string)($event['code'] ?? $event['id'] ?? '');

  $pdo = sci_db();
  $find = $pdo->prepare(
    'SELECT id FROM alumni_vendors WHERE year_be = ? AND name = ? LIMIT 1'
  );
  $find->execute([$yearBe, $name]);
  $id = $find->fetchColumn();
  if ($id) {
    $upd = $pdo->prepare(
      'UPDATE alumni_vendors
       SET slot_code = ?, category = ?, event_label = ?, source_ref = ?, payment_status = ?
       WHERE id = ?'
    );
    $upd->execute([
      $slot !== '' ? $slot : null,
      $category !== '' ? $category : null,
      $label,
      $sourceRef,
      $pay,
      (int)$id,
    ]);
  } else {
    $ins = $pdo->prepare(
      'INSERT INTO alumni_vendors (year_be, name, slot_code, category, event_label, source_ref, payment_status)
       VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([
      $yearBe,
      $name,
      $slot !== '' ? $slot : null,
      $category !== '' ? $category : null,
      $label,
      $sourceRef,
      $pay,
    ]);
  }
  $GLOBALS['SCI_ALUMNI_CACHE'] = null;
}

/**
 * Sync all selected applicants of an event into alumni_vendors.
 * @return array{synced:int,unpaid:int,paid:int}
 */
function sci_db_sync_event_alumni(?int $eventId = null): array {
  $pdo = sci_db();
  if ($eventId === null || $eventId <= 0) {
    $event = sci_db_active_event();
    $eventId = (int)$event['id'];
  } else {
    $st = $pdo->prepare('SELECT id, code, title, year_be FROM events WHERE id = ?');
    $st->execute([$eventId]);
    $event = $st->fetch();
    if (!$event) throw new RuntimeException('ไม่พบกิจกรรม');
  }

  $st = $pdo->prepare(
    'SELECT a.*, s.code AS assigned_slot_code
     FROM applicants a
     LEFT JOIN slots s ON s.id = a.assigned_slot_id
     WHERE a.event_id = ? AND a.selection = ?'
  );
  $st->execute([$eventId, 'ได้รับการคัดเลือก']);
  $rows = $st->fetchAll();
  $paid = 0;
  $unpaid = 0;
  foreach ($rows as $row) {
    sci_db_upsert_alumni_from_applicant($row, $event);
    if (sci_normalize_payment_status((string)($row['payment_status'] ?? 'unpaid')) === 'paid') $paid++;
    else $unpaid++;
  }
  $GLOBALS['SCI_ALUMNI_CACHE'] = null;
  return ['synced' => count($rows), 'unpaid' => $unpaid, 'paid' => $paid, 'year_be' => (int)$event['year_be']];
}

/**
 * Build payment alert payload for UI (prior-year unpaid alumni + returning applicants this round).
 */
function sci_build_payment_alerts(array $apps, array $alumniMeta = []): array {
  $alumni = function_exists('sci_prior_alumni')
    ? sci_prior_alumni()
    : (function_exists('sci_load_alumni') ? sci_load_alumni() : ['vendors' => []]);
  $unpaidAlumni = [];
  foreach ($alumni['vendors'] ?? [] as $v) {
    if (!empty($v['payment_warning']) || (($v['payment_status'] ?? '') === 'unpaid')) {
      $unpaidAlumni[] = [
        'name' => (string)($v['name'] ?? ''),
        'year' => (int)($v['year'] ?? $alumni['year'] ?? 0),
        'slot' => (string)($v['slot'] ?? ''),
        'category' => (string)($v['category'] ?? ''),
        'payment_status' => 'unpaid',
      ];
    }
  }

  $returningUnpaid = [];
  foreach ($apps as $a) {
    if (empty($a['returning'])) continue;
    if (empty($a['payment_warning']) && empty($a['alumni']['payment_warning'])) continue;
    $returningUnpaid[] = [
      'row' => (int)($a['row'] ?? 0),
      'name' => (string)($a['name'] ?? ''),
      'zone' => (string)($a['zone'] ?? ''),
      'category' => (string)($a['category'] ?? ''),
      'phone' => (string)($a['phone'] ?? ''),
      'alumni_year' => (int)($a['alumni']['year'] ?? 0),
      'alumni_slot' => (string)($a['alumni']['slot'] ?? ''),
      'selection' => (string)($a['selection'] ?? ''),
    ];
  }

  $banner = '';
  if ($returningUnpaid) {
    $banner = 'พบผู้สมัครเจ้าเดิม ' . count($returningUnpaid) . ' ราย ที่ค้างชำระเงินปีก่อน — ควรตรวจสอบก่อนคัดเลือก';
  } elseif ($unpaidAlumni) {
    $banner = 'มีรายชื่อค้างชำระเงินจากปีก่อน ' . count($unpaidAlumni) . ' ราย ในฐาน alumni';
  }

  return [
    'banner' => $banner,
    'unpaid_alumni_count' => count($unpaidAlumni),
    'returning_unpaid_count' => count($returningUnpaid),
    'unpaid_alumni' => $unpaidAlumni,
    'returning_unpaid' => $returningUnpaid,
    'alumni_year' => (int)($alumni['year'] ?? ($alumniMeta['year'] ?? 0)),
    'alumni_label' => (string)($alumni['label'] ?? ($alumniMeta['label'] ?? '')),
  ];
}

/**
 * Find applicant by legacy Excel row within current event+round.
 * @return array<string,mixed>|null
 */
function sci_db_find_applicant_by_row(int $row): ?array {
  if ($row < 2) return null;
  $event = sci_db_active_event();
  $roundId = sci_db_round_id();
  $st = sci_db()->prepare(
    'SELECT a.*, s.code AS assigned_slot_code
     FROM applicants a
     LEFT JOIN slots s ON s.id = a.assigned_slot_id
     WHERE a.event_id = ? AND a.round_id = ? AND a.legacy_excel_row = ?
     LIMIT 1'
  );
  $st->execute([(int)$event['id'], $roundId, $row]);
  $hit = $st->fetch();
  return $hit ?: null;
}

/** @return array<int, list<array<string,mixed>>> */
function sci_db_files_by_applicant(array $applicantIds): array {
  if (!$applicantIds) return [];
  $placeholders = implode(',', array_fill(0, count($applicantIds), '?'));
  $st = sci_db()->prepare(
    "SELECT id, applicant_id, file_type, drive_url, stored_path, original_name, mime_type
     FROM applicant_files WHERE applicant_id IN ($placeholders) ORDER BY id"
  );
  $st->execute(array_values($applicantIds));
  $by = [];
  foreach ($st->fetchAll() as $f) {
    $aid = (int)$f['applicant_id'];
    $by[$aid][] = $f;
  }
  return $by;
}

function sci_db_file_public_url(array $f): string {
  $fid = (int)($f['id'] ?? 0);
  $stored = trim((string)($f['stored_path'] ?? ''));
  // Prefer local / MinIO serve URL whenever we have a real stored object
  if ($fid > 0 && $stored !== '' && !str_starts_with($stored, 'legacy://')) {
    return 'file_serve.php?id=' . $fid;
  }
  // Do not fall back to Google Drive once migrated storage is expected
  return '';
}

function sci_db_drive_bundle(?string $url): array {
  $url = trim((string)$url);
  if ($url === '' || str_starts_with($url, 'legacy://')) {
    return ['url' => '', 'id' => null, 'thumb' => null, 'full' => ''];
  }
  if (sci_is_app_file_url($url)) {
    return ['url' => $url, 'id' => null, 'thumb' => $url, 'full' => $url];
  }
  // Legacy Excel/Drive-only path (no MinIO object yet)
  $view = sci_drive_view($url);
  $id = sci_drive_id($url);
  return [
    'url' => $view,
    'id' => $id,
    'thumb' => $id ? ('https://drive.google.com/thumbnail?id=' . $id . '&sz=w220') : null,
    'full' => $id ? ('https://drive.google.com/thumbnail?id=' . $id . '&sz=w1600') : $view,
  ];
}

function sci_db_format_applied_at(string $appliedAt): array {
  $appliedAt = trim($appliedAt);
  if ($appliedAt === '' || $appliedAt === '0000-00-00 00:00:00') {
    $appliedAt = date('Y-m-d H:i:s');
  }
  try {
    $dt = new DateTimeImmutable($appliedAt, new DateTimeZone('Asia/Bangkok'));
  } catch (Throwable $e) {
    $dt = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
  }
  $sortKey = (float)$dt->format('U');
  return sci_format_thai_time($dt, $sortKey);
}

function sci_db_build_applicant_payload(array $row, array $files): array {
  $idCard = '';
  $houseReg = '';
  $photo = '';
  $food = [];
  foreach ($files as $f) {
    $url = sci_db_file_public_url($f);
    $type = (string)($f['file_type'] ?? '');
    if ($type === 'id_card') $idCard = $url;
    elseif ($type === 'house_reg') $houseReg = $url;
    elseif ($type === 'photo') $photo = $url;
    elseif ($type === 'food' && $url !== '') {
      $food[] = sci_db_drive_bundle($url);
    }
  }

  $qualify = (string)($row['qualifications'] ?? '');
  $autoMissing = [];
  if ($idCard === '') $autoMissing[] = 'สำเนาบัตรประชาชน';
  if ($houseReg === '') $autoMissing[] = 'สำเนาทะเบียนบ้าน';
  if ($photo === '') $autoMissing[] = 'รูปถ่ายหน้าตรง';
  if (count($food) === 0) $autoMissing[] = 'ภาพถ่ายอาหาร/สินค้า';
  if ($qualify === '') $autoMissing[] = 'คุณสมบัติตามประกาศ';

  $timeInfo = sci_db_format_applied_at((string)($row['applied_at'] ?? ''));
  $zone = strtoupper(substr(trim((string)($row['zone_code'] ?? '')), 0, 1));
  $cat = sci_normalize_cat((string)($row['category'] ?? ''));
  $legacyRow = (int)($row['legacy_excel_row'] ?? $row['id'] ?? 0);
  $assigned = strtoupper(trim((string)($row['assigned_slot_code'] ?? '')));
  $selection = (string)($row['selection'] ?? 'รอพิจารณา');
  if ($selection !== 'ได้รับการคัดเลือก') $assigned = '';

  $statusRaw = (string)($row['doc_status'] ?? 'รอตรวจสอบ');
  $app = [
    'id' => (int)$row['id'],
    'row' => $legacyRow,
    'timestamp' => $timeInfo['timestamp'],
    'datetime' => $timeInfo['datetime'],
    'datetime_th' => $timeInfo['datetime_th'],
    'time_label' => $timeInfo['time_label'],
    'timestamp_raw' => (string)($row['applied_at'] ?? ''),
    'name' => (string)$row['name'],
    'phone' => (string)($row['phone'] ?? ''),
    'zone' => $zone,
    'zone_raw' => $zone !== '' ? ('โซน ' . $zone) : '',
    'category' => $cat,
    'category_raw' => (string)($row['category'] ?? ''),
    'detail' => (string)($row['detail'] ?? ''),
    'qualifications' => $qualify,
    'need_high_power' => isset($row['need_high_power']) && $row['need_high_power'] !== null && $row['need_high_power'] !== ''
      ? (int)$row['need_high_power']
      : null,
    'ice_bucket_count' => isset($row['ice_bucket_count']) && $row['ice_bucket_count'] !== null && $row['ice_bucket_count'] !== ''
      ? (int)$row['ice_bucket_count']
      : null,
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
    'status' => $statusRaw,
    'status_raw' => $statusRaw,
    'missing_detail' => (string)($row['missing_detail'] ?? ''),
    'review_note' => (string)($row['review_note'] ?? ''),
    'selection' => $selection,
    'reviewed_at' => (string)($row['reviewed_at'] ?? ''),
    'assigned_slot' => $assigned,
    'payment_status' => sci_normalize_payment_status((string)($row['payment_status'] ?? 'unpaid')),
    'payment_at' => (string)($row['payment_at'] ?? ''),
    'payment_note' => (string)($row['payment_note'] ?? ''),
    'returning' => !empty($row['is_returning']),
    'alumni' => null,
    'key' => md5($timeInfo['datetime'] . '|' . $row['name'] . '|' . $zone . '|' . $legacyRow),
  ];

  if (!empty($row['is_returning'])) {
    $app['alumni'] = [
      'year' => $row['alumni_year'] ?? null,
      'slot' => $row['alumni_slot'] ?? null,
      'category' => $row['alumni_category'] ?? null,
    ];
  }

  sci_recompute_app_status($app);
  return $app;
}

function sci_db_parse_applicants(): array {
  $event = sci_db_active_event();
  $roundId = sci_db_round_id();
  $roundNo = sci_current_round();

  $st = sci_db()->prepare(
    'SELECT a.*, s.code AS assigned_slot_code
     FROM applicants a
     LEFT JOIN slots s ON s.id = a.assigned_slot_id
     WHERE a.event_id = ? AND a.round_id = ?
     ORDER BY a.applied_at ASC, a.id ASC'
  );
  $st->execute([(int)$event['id'], $roundId]);
  $rows = $st->fetchAll();

  $ids = array_map(fn($r) => (int)$r['id'], $rows);
  $filesBy = sci_db_files_by_applicant($ids);

  $apps = [];
  foreach ($rows as $row) {
    $apps[] = sci_db_build_applicant_payload($row, $filesBy[(int)$row['id']] ?? []);
  }

  // Re-attach alumni fuzzy match for display (DB may already have flags)
  $alumniMeta = sci_attach_alumni($apps);
  $paymentAlerts = sci_build_payment_alerts($apps, $alumniMeta);

  $layout = sci_slot_layout();
  $slots = $layout['slots'];
  $shopReport = sci_build_shop_report($slots, $apps);
  $docSummary = sci_build_doc_summary($apps);
  $health = sci_db_storage_health();
  $r1occ = $layout['round1_occupied'];

  $notice = 'ข้อมูลอ่าน/เขียนจาก MySQL (`' . (sci_db_config()['dbname'] ?? 'sciweekshop') . '`) · กิจกรรม '
    . $event['code'] . ' · ไม่ลบแถวผู้สมัคร';
  if (!empty($r1occ['enabled'])) {
    $prior = (string)($r1occ['prior_label'] ?? sci_prior_rounds_label($roundNo));
    $notice = 'รอบที่ ' . $roundNo . ' แสดงเฉพาะล็อกที่ยังไม่ถูกคัดเลือกใน' . $prior
      . ' (เหลือ ' . (int)$r1occ['remaining'] . ' จาก ' . (int)$r1occ['total'] . ' ล็อก) · ' . $notice;
  }

  return sci_with_round_context([
    'generated_at' => date('c'),
    'source' => 'mysql:' . $event['code'] . ':r' . $roundNo,
    'source_mtime' => date('c'),
    'total_applicants' => count($apps),
    'slots' => $slots,
    'groups' => $layout['groups'],
    'shared' => $layout['shared'],
    'round1_occupied' => $r1occ,
    'applicants' => $apps,
    'shop_report' => $shopReport,
    'doc_summary' => $docSummary,
    'alumni' => $alumniMeta,
    'payment_alerts' => $paymentAlerts,
    'storage' => $health,
    'status_store' => [
      'backend' => 'mysql',
      'event_id' => $event['id'],
      'round_id' => $roundId,
      'count' => count($apps),
    ],
    'policy' => 'บันทึกสถานะตรวจเอกสาร/คัดเลือก/ล็อก/ชำระเงินลง MySQL — ไม่ลบรายการผู้สมัคร',
    'notice' => $notice,
  ]);
}

function sci_db_ensure_status_and_write(array $updates): array {
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

  $pdo = sci_db();
  $event = sci_db_active_event();
  $roundId = sci_db_round_id();
  $slotMap = sci_db_slot_id_map();

  $find = $pdo->prepare(
    'SELECT id, doc_status, missing_detail, review_note, selection, reviewed_at, assigned_slot_id
     FROM applicants WHERE event_id = ? AND round_id = ? AND legacy_excel_row = ? LIMIT 1'
  );
  $upd = $pdo->prepare(
    'UPDATE applicants SET
      doc_status = ?, missing_detail = ?, review_note = ?, selection = ?,
      reviewed_at = ?, assigned_slot_id = ?, updated_at = CURRENT_TIMESTAMP
     WHERE id = ?'
  );

  $pdo->beginTransaction();
  try {
    $n = 0;
    foreach ($updates as $u) {
      $row = (int)($u['row'] ?? 0);
      if ($row < 2) continue;
      $find->execute([(int)$event['id'], $roundId, $row]);
      $cur = $find->fetch();
      if (!$cur) {
        throw new InvalidArgumentException('ไม่พบผู้สมัครแถว ' . $row . ' ในฐานข้อมูล');
      }

      $selection = (string)($u['selection'] ?? $cur['selection'] ?? 'รอพิจารณา');
      $allowed = ['ได้รับการคัดเลือก', 'ไม่ได้รับการคัดเลือก', 'รอพิจารณา'];
      if (!in_array($selection, $allowed, true)) $selection = 'รอพิจารณา';

      $slotCode = strtoupper(trim((string)($u['assigned_slot'] ?? '')));
      if ($selection !== 'ได้รับการคัดเลือก') $slotCode = '';
      $slotId = null;
      if ($slotCode !== '') {
        if (!isset($slotMap[$slotCode])) {
          throw new InvalidArgumentException('ไม่พบล็อก ' . $slotCode . ' ในฐานข้อมูล');
        }
        $slotId = $slotMap[$slotCode];
      }

      $status = trim((string)($u['status'] ?? $cur['doc_status'] ?? 'รอตรวจสอบ'));
      if ($status === '') $status = 'รอตรวจสอบ';
      $missing = (string)($u['missing_detail'] ?? $cur['missing_detail'] ?? '');
      $note = (string)($u['review_note'] ?? $cur['review_note'] ?? '');
      $reviewedAt = trim((string)($u['reviewed_at'] ?? $cur['reviewed_at'] ?? ''));
      if ($reviewedAt === '') $reviewedAt = date('Y-m-d H:i:s');

      $upd->execute([$status, $missing, $note, $selection, $reviewedAt, $slotId, (int)$cur['id']]);
      $n++;
    }
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }

  // Sync selected shops into alumni for next-year unpaid warnings
  try {
    foreach ($updates as $u) {
      if (($u['selection'] ?? '') !== 'ได้รับการคัดเลือก') continue;
      $full = sci_db_find_applicant_by_row((int)($u['row'] ?? 0));
      if ($full) {
        if (!isset($full['payment_status'])) $full['payment_status'] = 'unpaid';
        sci_db_upsert_alumni_from_applicant($full, $event);
      }
    }
  } catch (Throwable $e) {
    // non-fatal
  }

  sci_db_clear_runtime_caches();

  return [
    'ok' => true,
    'updated' => $n,
    'mode' => 'mysql',
    'storage' => 'mysql',
    'status_store' => 'mysql:applicants',
    'excel_synced' => false,
    'excel_error' => null,
    'path' => null,
    'applicants_before' => null,
    'applicants_after' => null,
    'message' => 'บันทึกสถานะลง MySQL สำเร็จ (ไม่ลบผู้สมัคร)',
  ];
}

function sci_db_save_payment_status(int $row, string $paymentStatus, string $paymentNote = ''): array {
  if ($row < 2) {
    throw new InvalidArgumentException('row ไม่ถูกต้อง');
  }
  $status = sci_normalize_payment_status($paymentStatus);
  $cur = sci_db_find_applicant_by_row($row);
  if (!$cur) {
    throw new InvalidArgumentException('ไม่พบผู้สมัครแถว ' . $row);
  }
  $nowBkk = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
  $payAt = $status === 'paid' ? $nowBkk : null;
  $st = sci_db()->prepare(
    'UPDATE applicants SET payment_status = ?, payment_at = ?, payment_note = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
  );
  $st->execute([$status, $payAt, trim($paymentNote), (int)$cur['id']]);

  // Keep alumni payment flag in sync for next-year warnings
  try {
    $event = sci_db_active_event();
    $cur['payment_status'] = $status;
    sci_db_upsert_alumni_from_applicant($cur, $event);
  } catch (Throwable $e) {
    // non-fatal
  }

  sci_db_clear_runtime_caches();

  return [
    'ok' => true,
    'row' => $row,
    'payment_status' => $status,
    'payment_at' => $payAt ?? '',
    'payment_note' => trim($paymentNote),
    'status_store' => 'mysql:applicants',
    'message' => $status === 'paid' ? 'บันทึกว่าชำระเงินแล้ว' : 'บันทึกว่ายังไม่ชำระเงิน',
  ];
}

/**
 * Import an uploaded Excel into the current round (replace applicants for that round).
 */
function sci_db_import_xlsx_for_current_round(string $path): array {
  // Parse via Excel path temporarily
  $prev = sci_use_mysql();
  // Force excel parse by calling internal excel parser pieces:
  // Use a one-shot: disable mysql flag via env isn't possible mid-request.
  // Instead call the excel implementation directly by temporarily monkey-patching:
  // Simplest: read with sci_read_sheet_rows and reuse migrate-like insert.

  if (!is_file($path)) {
    throw new InvalidArgumentException('ไม่พบไฟล์ Excel');
  }

  // Build applicant list using Excel parser without MySQL delegation:
  // sci_parse_applicants_excel is created as the original body — we call a dedicated importer.

  $rows = sci_read_sheet_rows($path);
  if (!$rows) {
    throw new InvalidArgumentException('ไฟล์ Excel ว่างหรืออ่านไม่ได้');
  }
  $header = array_shift($rows);
  $cmap = sci_detect_column_map($header ?? []);

  $event = sci_db_active_event();
  $roundId = sci_db_round_id();
  $slotMap = sci_db_slot_id_map();
  $pdo = sci_db();

  $pdo->beginTransaction();
  try {
    // Remove existing applicants (+ files via CASCADE) for this round
    $del = $pdo->prepare('DELETE FROM applicants WHERE event_id = ? AND round_id = ?');
    $del->execute([(int)$event['id'], $roundId]);

    $insApp = $pdo->prepare(
      'INSERT INTO applicants (
        event_id, round_id, legacy_excel_row, applied_at, name, phone, zone_code, category, detail, qualifications,
        doc_status, missing_detail, review_note, reviewed_at, selection, assigned_slot_id,
        payment_status, payment_at, payment_note, is_returning
      ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)'
    );
    $insFile = $pdo->prepare(
      'INSERT INTO applicant_files (applicant_id, file_type, original_name, stored_path, drive_url)
       VALUES (?,?,?,?,?)'
    );

    $count = 0;
    foreach ($rows as $r) {
      $name = sci_row_get($r, $cmap, 'name');
      if ($name === '') continue;
      $zoneRaw = sci_row_get($r, $cmap, 'zone');
      $zone = sci_infer_zone($r, $cmap, $zoneRaw);
      $cat = sci_normalize_cat(sci_row_category($r, $cmap, $zone));
      $mappedZone = sci_remap_zone_from_open_slots($zone, $cat);
      if ($mappedZone !== $zone) $zone = $mappedZone;

      $tsRaw = sci_row_get($r, $cmap, 'timestamp');
      $timeInfo = sci_parse_timestamp($tsRaw);
      $appliedAt = $timeInfo['datetime'] ?: date('Y-m-d H:i:s');

      $selection = sci_row_get($r, $cmap, 'selection');
      $allowed = ['ได้รับการคัดเลือก', 'ไม่ได้รับการคัดเลือก', 'รอพิจารณา'];
      if ($selection === '' || !in_array($selection, $allowed, true)) $selection = 'รอพิจารณา';
      $slotCode = strtoupper(sci_row_get($r, $cmap, 'assigned_slot'));
      if ($selection !== 'ได้รับการคัดเลือก') $slotCode = '';
      $slotId = ($slotCode !== '' && isset($slotMap[$slotCode])) ? $slotMap[$slotCode] : null;

      $status = sci_row_get($r, $cmap, 'status');
      if ($status === '') $status = 'รอตรวจสอบ';

      $insApp->execute([
        (int)$event['id'],
        $roundId,
        (int)$r['_row'],
        $appliedAt,
        $name,
        sci_row_get($r, $cmap, 'phone'),
        $zone,
        $cat,
        sci_row_get($r, $cmap, 'detail'),
        sci_row_get($r, $cmap, 'qualify'),
        $status,
        sci_row_get($r, $cmap, 'missing_detail'),
        sci_row_get($r, $cmap, 'review_note'),
        sci_row_get($r, $cmap, 'reviewed_at') ?: null,
        $selection,
        $slotId,
        'unpaid',
        null,
        '',
      ]);
      $appId = (int)$pdo->lastInsertId();

      foreach ([
        'id_card' => sci_row_get($r, $cmap, 'id_card'),
        'house_reg' => sci_row_get($r, $cmap, 'house_reg'),
        'photo' => sci_row_get($r, $cmap, 'photo'),
      ] as $type => $url) {
        if ($url === '') continue;
        $insFile->execute([$appId, $type, '', 'legacy://drive', $url]);
      }
      $foodRaw = array_values(array_filter(array_map('trim', explode(',', sci_row_get($r, $cmap, 'food')))));
      foreach ($foodRaw as $fu) {
        if ($fu === '') continue;
        $insFile->execute([$appId, 'food', '', 'legacy://drive', $fu]);
      }
      $count++;
    }
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }

  sci_db_clear_runtime_caches();
  return [
    'ok' => true,
    'imported' => $count,
    'round' => sci_current_round(),
    'event' => $event['code'],
  ];
}
