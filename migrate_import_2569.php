<?php
/**
 * Import SCI Week 2569 Excel/JSON data into MySQL `sciweekshop`.
 * Usage (CLI): php migrate_import_2569.php
 */
mb_internal_encoding('UTF-8');
require_once __DIR__ . '/xlsx_lib.php';
require_once __DIR__ . '/db.php';

function mig_out(string $msg): void {
  echo $msg . PHP_EOL;
}

function mig_slot_sort(string $code): int {
  if (!preg_match('/^([A-Z])(\d+)$/', strtoupper($code), $m)) return 9999;
  return (ord($m[1]) - 64) * 100 + (int)$m[2];
}

$pdo = sci_db();

// Expect schema already applied: mysql … < sql/001_schema.sql
$need = $pdo->query("SHOW TABLES LIKE 'events'")->fetchColumn();
if (!$need) {
  fwrite(STDERR, "Schema missing. Run: mysql -u root --default-character-set=utf8mb4 < sql/001_schema.sql\n");
  exit(1);
}

// Fresh import for 2569 event (idempotent by wiping that event tree)
$pdo->beginTransaction();
try {
  $st = $pdo->prepare('SELECT id FROM events WHERE code = ?');
  $st->execute(['sciweek-2569']);
  $oldId = $st->fetchColumn();
  if ($oldId) {
    $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([(int)$oldId]);
    mig_out('Removed previous event sciweek-2569 for clean re-import');
  }

  $pdo->prepare(
    'INSERT INTO events (code, title, year_be, description, is_active)
     VALUES (?,?,?,?,1)'
  )->execute([
    'sciweek-2569',
    'ร้านค้าสัปดาห์วิทยาศาสตร์แห่งชาติ ส่วนภูมิภาค ณ คณะวิทยาศาสตร์ มข. ประจำปี 2569',
    2569,
    'นำเข้าจากระบบ Excel/JSON เดิม',
  ]);
  $eventId = (int)$pdo->lastInsertId();

  // Zones A–D
  $zoneIds = [];
  $insZone = $pdo->prepare(
    'INSERT INTO zones (event_id, code, name_th, sort_order) VALUES (?,?,?,?)'
  );
  foreach (['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4] as $code => $ord) {
    $insZone->execute([$eventId, $code, 'โซน ' . $code, $ord]);
    $zoneIds[$code] = (int)$pdo->lastInsertId();
  }

  // Slots from sci_slots()
  $insSlot = $pdo->prepare(
    'INSERT INTO slots (event_id, zone_id, code, category, slot_limit, sort_order, is_active)
     VALUES (?,?,?,?,?,?,1)'
  );
  $slotIds = [];
  foreach (sci_slots() as $s) {
    $z = $s['zone'];
    $insSlot->execute([
      $eventId,
      $zoneIds[$z],
      $s['id'],
      $s['cat'],
      (int)$s['limit'],
      mig_slot_sort($s['id']),
    ]);
    $slotIds[$s['id']] = (int)$pdo->lastInsertId();
  }
  mig_out('Slots imported: ' . count($slotIds));

  // Alumni 2568
  $alumniPath = __DIR__ . '/data/alumni_2568.json';
  if (is_file($alumniPath)) {
    $alumni = json_decode((string)file_get_contents($alumniPath), true);
    $insAl = $pdo->prepare(
      'INSERT INTO alumni_vendors (year_be, name, aliases, slot_code, category, event_label, source_ref, payment_status)
       VALUES (?,?,?,?,?,?,?,?)'
    );
    $nAl = 0;
    foreach (($alumni['vendors'] ?? []) as $v) {
      $aliases = isset($v['aliases']) ? json_encode($v['aliases'], JSON_UNESCAPED_UNICODE) : null;
      $insAl->execute([
        (int)($alumni['year'] ?? 2568),
        (string)($v['name'] ?? ''),
        $aliases,
        (string)($v['slot'] ?? ''),
        (string)($v['category'] ?? ''),
        (string)($alumni['label'] ?? ''),
        (string)($alumni['source_ref'] ?? ''),
        'unknown',
      ]);
      $nAl++;
    }
    mig_out("Alumni vendors: {$nAl}");
  }

  $insRound = $pdo->prepare(
    'INSERT INTO event_rounds (event_id, round_no, title, apply_open_at, apply_close_at, is_open)
     VALUES (?,?,?,?,?,0)'
  );
  $insApp = $pdo->prepare(
    'INSERT INTO applicants (
      event_id, round_id, legacy_excel_row, applied_at, name, phone, zone_code, category, detail, qualifications,
      doc_status, missing_detail, review_note, reviewed_at, selection, assigned_slot_id,
      payment_status, payment_at, payment_note, is_returning, alumni_year, alumni_slot, alumni_category
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
  );
  $insFile = $pdo->prepare(
    'INSERT INTO applicant_files (applicant_id, file_type, original_name, stored_path, drive_url)
     VALUES (?,?,?,?,?)'
  );

  $totals = [];
  for ($r = 1; $r <= 3; $r++) {
    sci_set_round($r);
    $meta = sci_round_meta($r);
    $data = sci_parse_applicants();
    $insRound->execute([
      $eventId,
      $r,
      $meta['title'],
      null,
      null,
    ]);
    $roundId = (int)$pdo->lastInsertId();

    $count = 0;
    foreach ($data['applicants'] as $a) {
      $slotCode = strtoupper(trim((string)($a['assigned_slot'] ?? '')));
      $slotId = ($slotCode !== '' && isset($slotIds[$slotCode])) ? $slotIds[$slotCode] : null;
      $appliedAt = (string)($a['datetime'] ?? '');
      if ($appliedAt === '' || $appliedAt === '0000-00-00 00:00:00') {
        $appliedAt = date('Y-m-d H:i:s');
      }
      $pay = (($a['payment_status'] ?? '') === 'paid') ? 'paid' : 'unpaid';
      $payAt = $pay === 'paid' ? ((string)($a['payment_at'] ?? '') ?: null) : null;
      if ($payAt === '') $payAt = null;
      $reviewedAt = (string)($a['reviewed_at'] ?? '');
      if ($reviewedAt === '') $reviewedAt = null;
      $sel = (string)($a['selection'] ?? 'รอพิจารณา');
      if (!in_array($sel, ['รอพิจารณา', 'ได้รับการคัดเลือก', 'ไม่ได้รับการคัดเลือก'], true)) {
        $sel = 'รอพิจารณา';
      }
      if ($sel !== 'ได้รับการคัดเลือก') $slotId = null;

      $alumni = $a['alumni'] ?? null;
      $insApp->execute([
        $eventId,
        $roundId,
        (int)$a['row'],
        $appliedAt,
        (string)$a['name'],
        (string)($a['phone'] ?? ''),
        (string)($a['zone'] ?? ''),
        (string)($a['category'] ?? ''),
        (string)($a['detail'] ?? ''),
        (string)($a['qualifications'] ?? ''),
        (string)($a['status'] ?? 'รอตรวจสอบ'),
        (string)($a['missing_detail'] ?? ''),
        (string)($a['review_note'] ?? ''),
        $reviewedAt,
        $sel,
        $slotId,
        $pay,
        $payAt,
        (string)($a['payment_note'] ?? ''),
        !empty($a['returning']) ? 1 : 0,
        $alumni['year'] ?? null,
        $alumni['slot'] ?? null,
        $alumni['category'] ?? null,
      ]);
      $appId = (int)$pdo->lastInsertId();

      $fileMap = [
        'id_card' => (string)($a['id_card'] ?? ''),
        'house_reg' => (string)($a['house_reg'] ?? ''),
        'photo' => (string)($a['photo'] ?? ''),
      ];
      foreach ($fileMap as $type => $url) {
        if ($url === '') continue;
        $insFile->execute([$appId, $type, '', 'legacy://drive', $url]);
      }
      foreach (($a['food_photos'] ?? []) as $fp) {
        $url = (string)($fp['url'] ?? '');
        if ($url === '') continue;
        $insFile->execute([$appId, 'food', '', 'legacy://drive', $url]);
      }
      $count++;
    }
    $totals[$r] = $count;
    mig_out("Round {$r}: {$count} applicants");
  }

  // Seed one admin placeholder (link later from eoffice UI)
  $pdo->exec(
    "INSERT INTO users (role_id, email, display_name, is_active)
     SELECT 1, 'admin@localhost', 'Admin (seed)', 1
     FROM DUAL
     WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@localhost')"
  );

  $pdo->commit();
  mig_out('OK: event_id=' . $eventId);
  mig_out('Totals: r1=' . ($totals[1] ?? 0) . ' r2=' . ($totals[2] ?? 0) . ' r3=' . ($totals[3] ?? 0));
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
  exit(1);
}
