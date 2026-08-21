<?php
/**
 * Import SCI Week 2568 selected vendors from Shop-Sci-Week 2025-signed.pdf
 * (canonical list: data/alumni_2568.json — same announcement 107/2568).
 *
 * Creates event `sciweek-2568` (inactive) with round 1 + applicants (คัดเลือกแล้ว).
 * Does NOT touch sciweek-2569.
 *
 * Usage:
 *   C:\xampp\php\php.exe migrate_import_2568.php
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');

putenv('SCI_USE_MYSQL=0'); // use hardcoded slot layout, not active-event slots
require_once __DIR__ . '/xlsx_lib.php';
require_once __DIR__ . '/db.php';

function mig_out(string $msg): void {
  echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
}

function mig_slot_sort(string $code): int {
  if (!preg_match('/^([A-Z])(\d+)$/', strtoupper($code), $m)) return 9999;
  return (ord($m[1]) - 64) * 100 + (int)$m[2];
}

$pdfPath = __DIR__ . DIRECTORY_SEPARATOR . 'Shop-Sci-Week 2025-signed.pdf';
$alumniPath = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'alumni_2568.json';

if (!is_file($alumniPath)) {
  fwrite(STDERR, "Missing {$alumniPath}\n");
  exit(1);
}
if (!is_file($pdfPath)) {
  mig_out('WARNING: PDF not found at ' . $pdfPath . ' — importing from alumni_2568.json only');
}

$alumni = json_decode((string)file_get_contents($alumniPath), true);
if (!is_array($alumni) || empty($alumni['vendors'])) {
  fwrite(STDERR, "Invalid alumni_2568.json\n");
  exit(1);
}

$vendors = [];
foreach ($alumni['vendors'] as $v) {
  if (!is_array($v)) continue;
  $name = trim((string)($v['name'] ?? ''));
  $slot = strtoupper(trim((string)($v['slot'] ?? '')));
  $cat = trim((string)($v['category'] ?? ''));
  if ($name === '' || $slot === '') continue;
  $vendors[] = [
    'name' => $name,
    'slot' => $slot,
    'category' => $cat,
    'aliases' => $v['aliases'] ?? null,
    'zone' => substr($slot, 0, 1),
  ];
}
mig_out('Vendors from alumni_2568.json: ' . count($vendors) . ' (source: ' . ($alumni['source'] ?? 'pdf') . ')');

$pdo = sci_db();
$need = $pdo->query("SHOW TABLES LIKE 'events'")->fetchColumn();
if (!$need) {
  fwrite(STDERR, "Schema missing. Run apply_schema.php first.\n");
  exit(1);
}

$pdo->beginTransaction();
try {
  $st = $pdo->prepare('SELECT id FROM events WHERE code = ?');
  $st->execute(['sciweek-2568']);
  $oldId = $st->fetchColumn();
  if ($oldId) {
    $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([(int)$oldId]);
    mig_out('Removed previous event sciweek-2568 for clean re-import');
  }

  // Keep 2569 as the active event
  $pdo->prepare(
    'INSERT INTO events (code, title, year_be, description, is_active)
     VALUES (?,?,?,?,0)'
  )->execute([
    'sciweek-2568',
    'ร้านค้าสัปดาห์วิทยาศาสตร์แห่งชาติ ส่วนภูมิภาค ณ คณะวิทยาศาสตร์ มข. ประจำปี 2568',
    2568,
    'นำเข้าจากประกาศคณะวิทยาศาสตร์ ที่ 107/2568 (Shop-Sci-Week 2025-signed.pdf) · วันที่จัด 18–20 สิงหาคม 2568',
  ]);
  $eventId = (int)$pdo->lastInsertId();
  mig_out("Created event sciweek-2568 id={$eventId} (is_active=0)");

  $zoneIds = [];
  $insZone = $pdo->prepare(
    'INSERT INTO zones (event_id, code, name_th, sort_order) VALUES (?,?,?,?)'
  );
  foreach (['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4] as $code => $ord) {
    $insZone->execute([$eventId, $code, 'โซน ' . $code, $ord]);
    $zoneIds[$code] = (int)$pdo->lastInsertId();
  }

  // Base slot layout (2569 template) with category overrides from 2568 selected vendors
  $catBySlot = [];
  foreach ($vendors as $v) {
    $catBySlot[$v['slot']] = $v['category'];
  }

  $insSlot = $pdo->prepare(
    'INSERT INTO slots (event_id, zone_id, code, category, slot_limit, sort_order, is_active)
     VALUES (?,?,?,?,?,?,1)'
  );
  $slotIds = [];
  foreach (sci_slots() as $s) {
    $code = (string)$s['id'];
    $z = (string)$s['zone'];
    if (!isset($zoneIds[$z])) continue;
    $cat = $catBySlot[$code] ?? (string)$s['cat'];
    $insSlot->execute([
      $eventId,
      $zoneIds[$z],
      $code,
      $cat,
      (int)$s['limit'],
      mig_slot_sort($code),
    ]);
    $slotIds[$code] = (int)$pdo->lastInsertId();
  }
  // Ensure every assigned slot exists even if not in template
  foreach ($vendors as $v) {
    $code = $v['slot'];
    if (isset($slotIds[$code])) continue;
    $z = $v['zone'];
    if (!isset($zoneIds[$z])) {
      $insZone->execute([$eventId, $z, 'โซน ' . $z, ord($z) - 64]);
      $zoneIds[$z] = (int)$pdo->lastInsertId();
    }
    $insSlot->execute([$eventId, $zoneIds[$z], $code, $v['category'], 1, mig_slot_sort($code)]);
    $slotIds[$code] = (int)$pdo->lastInsertId();
  }
  mig_out('Slots: ' . count($slotIds));

  $pdo->prepare(
    'INSERT INTO event_rounds (event_id, round_no, title, apply_open_at, apply_close_at, is_open, notes)
     VALUES (?,?,?,?,?,0,?)'
  )->execute([
    $eventId,
    1,
    'รอบที่ 1 (ปี 2568)',
    '2025-08-06 00:00:00',
    '2025-08-08 23:59:59',
    'รับสมัคร 6–8 ส.ค. 2568 ตามประกาศ 96/2568 · คัดเลือกตามประกาศ 107/2568',
  ]);
  $roundId = (int)$pdo->lastInsertId();

  $insApp = $pdo->prepare(
    'INSERT INTO applicants (
      event_id, round_id, legacy_excel_row, applied_at, name, phone, zone_code, category, detail, qualifications,
      doc_status, missing_detail, review_note, reviewed_at, selection, assigned_slot_id,
      payment_status, payment_at, payment_note, is_returning, alumni_year, alumni_slot, alumni_category
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
  );

  $row = 2;
  $n = 0;
  foreach ($vendors as $v) {
    $slotId = $slotIds[$v['slot']] ?? null;
    $insApp->execute([
      $eventId,
      $roundId,
      $row,
      '2025-08-08 12:00:00',
      $v['name'],
      '',
      $v['zone'],
      $v['category'],
      '',
      '',
      'ครบถ้วน',
      '',
      'นำเข้าจากประกาศ 107/2568 (Shop-Sci-Week 2025-signed.pdf)',
      '2025-08-11 00:00:00',
      'ได้รับการคัดเลือก',
      $slotId,
      'paid',
      '2025-08-18 00:00:00',
      'นำเข้าจากประกาศ 107/2568 — ตั้งเป็นชำระแล้ว',
      0,
      null,
      null,
      null,
    ]);
    $row++;
    $n++;
  }
  mig_out("Applicants inserted: {$n} (selected + assigned slot)");

  // Refresh alumni_vendors for year 2568 from the same list
  $pdo->prepare('DELETE FROM alumni_vendors WHERE year_be = 2568')->execute();
  $insAl = $pdo->prepare(
    'INSERT INTO alumni_vendors (year_be, name, aliases, slot_code, category, event_label, source_ref, payment_status)
     VALUES (?,?,?,?,?,?,?,?)'
  );
  $label = (string)($alumni['label'] ?? 'SCI Week 2568');
  $sourceRef = (string)($alumni['source_ref'] ?? 'ประกาศคณะวิทยาศาสตร์ ที่ 107/2568');
  foreach ($vendors as $v) {
    $aliases = null;
    if (!empty($v['aliases']) && is_array($v['aliases'])) {
      $aliases = json_encode($v['aliases'], JSON_UNESCAPED_UNICODE);
    }
    $insAl->execute([
      2568,
      $v['name'],
      $aliases,
      $v['slot'],
      $v['category'],
      $label,
      $sourceRef . ' · Shop-Sci-Week 2025-signed.pdf',
      'paid',
    ]);
  }
  mig_out('alumni_vendors 2568 refreshed: ' . count($vendors) . ' (payment_status=paid)');

  $pdo->commit();
  mig_out('Done. Active event remains sciweek-2569.');
  mig_out('Switch UI to year 2568 via แท็บกิจกรรม if you need to review these rows.');
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
  exit(1);
}
