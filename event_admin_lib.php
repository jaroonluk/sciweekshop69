<?php
/**
 * Admin CRUD for events, rounds, zones, slots + copy / swap.
 * Requires MySQL sciweekshop.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db_data_lib.php';

function sci_admin_require_mysql(): void {
  try {
    sci_db()->query('SELECT 1 FROM events LIMIT 1');
  } catch (Throwable $e) {
    throw new RuntimeException('ฐานข้อมูลยังไม่พร้อม: ' . $e->getMessage());
  }
}

function sci_admin_normalize_datetime(?string $v): ?string {
  $v = trim((string)$v);
  if ($v === '') return null;
  $v = str_replace('T', ' ', $v);
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
    $v .= ' 00:00:00';
  }
  if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $v)) {
    $v .= ':00';
  }
  $ts = strtotime($v);
  if ($ts === false) {
    throw new InvalidArgumentException('รูปแบบวันเวลาไม่ถูกต้อง');
  }
  return date('Y-m-d H:i:s', $ts);
}

function sci_admin_normalize_slot_code(string $code): string {
  $code = strtoupper(trim($code));
  if (!preg_match('/^[A-Z]\d{1,2}$/', $code)) {
    throw new InvalidArgumentException('รหัสล็อกต้องเป็นตัวอักษรตามด้วยตัวเลข เช่น A8, B12');
  }
  return $code;
}

function sci_admin_normalize_zone_code(string $code): string {
  $code = strtoupper(trim($code));
  if (!preg_match('/^[A-Z]$/', $code)) {
    throw new InvalidArgumentException('รหัสโซนต้องเป็นตัวอักษร A–Z หนึ่งตัว');
  }
  return $code;
}

function sci_admin_slug_code(string $title, int $yearBe): string {
  $base = strtolower(trim($title));
  $base = preg_replace('/[^a-z0-9ก-๙]+/u', '-', $base) ?: 'event';
  $base = trim($base, '-');
  if ($base === '' || strlen($base) < 3) {
    $base = 'event-' . $yearBe;
  }
  // Prefer ASCII-ish code for URLs
  $ascii = preg_replace('/[^a-z0-9\-]+/', '', strtolower('event-' . $yearBe . '-' . substr(md5($title . microtime()), 0, 6)));
  return substr($ascii, 0, 64);
}

/** @return list<array> */
function sci_admin_list_events(): array {
  sci_admin_require_mysql();
  $st = sci_db()->query(
    'SELECT e.id, e.code, e.title, e.year_be, e.description, e.apply_program, e.is_active, e.created_at, e.updated_at,
            (SELECT COUNT(*) FROM zones z WHERE z.event_id = e.id) AS zone_count,
            (SELECT COUNT(*) FROM slots s WHERE s.event_id = e.id) AS slot_count,
            (SELECT COUNT(*) FROM event_rounds r WHERE r.event_id = e.id) AS round_count,
            (SELECT COUNT(*) FROM applicants a WHERE a.event_id = e.id) AS applicant_count
     FROM events e
     ORDER BY e.year_be DESC, e.id DESC'
  );
  return array_map(static function ($row) {
    $row['apply_program'] = sci_normalize_apply_program($row['apply_program'] ?? 'sciweek');
    return $row;
  }, $st->fetchAll());
}

function sci_admin_get_event(int $eventId): array {
  sci_admin_require_mysql();
  $st = sci_db()->prepare(
    'SELECT id, code, title, year_be, description, apply_program, is_active, created_at, updated_at
     FROM events WHERE id = ? LIMIT 1'
  );
  $st->execute([$eventId]);
  $event = $st->fetch();
  if (!$event) throw new RuntimeException('ไม่พบกิจกรรม');
  $event['apply_program'] = sci_normalize_apply_program($event['apply_program'] ?? 'sciweek');

  $rounds = sci_db()->prepare(
    'SELECT id, event_id, round_no, title, apply_open_at, apply_close_at, is_open,
            ask_high_power, ask_ice_bucket, apply_flow, notes
     FROM event_rounds WHERE event_id = ? ORDER BY round_no'
  );
  $rounds->execute([$eventId]);

  $zones = sci_db()->prepare(
    'SELECT id, event_id, code, name_th, sort_order FROM zones WHERE event_id = ? ORDER BY sort_order, code'
  );
  $zones->execute([$eventId]);
  $zoneRows = $zones->fetchAll();

  $slots = sci_db()->prepare(
    'SELECT s.id, s.event_id, s.zone_id, s.code, s.category, s.slot_limit, s.sort_order, s.is_active, s.notes,
            z.code AS zone_code
     FROM slots s
     JOIN zones z ON z.id = s.zone_id
     WHERE s.event_id = ?
     ORDER BY s.sort_order, s.code'
  );
  $slots->execute([$eventId]);
  $slotRows = $slots->fetchAll();

  // Occupants for active/selected event (all rounds) — for swap UI
  $occ = sci_db()->prepare(
    'SELECT a.id AS applicant_id, a.legacy_excel_row, a.name, a.selection, a.round_id,
            er.round_no, s.id AS slot_id, s.code AS slot_code
     FROM applicants a
     JOIN slots s ON s.id = a.assigned_slot_id
     JOIN event_rounds er ON er.id = a.round_id
     WHERE a.event_id = ? AND a.selection = ? AND a.assigned_slot_id IS NOT NULL
     ORDER BY er.round_no, s.code'
  );
  $occ->execute([$eventId, 'ได้รับการคัดเลือก']);

  return [
    'event' => $event,
    'rounds' => $rounds->fetchAll(),
    'zones' => $zoneRows,
    'slots' => $slotRows,
    'assignments' => $occ->fetchAll(),
  ];
}

function sci_admin_save_event(array $body, ?int $actorId = null): array {
  sci_admin_require_mysql();
  $id = (int)($body['id'] ?? 0);
  $title = trim((string)($body['title'] ?? ''));
  $yearBe = (int)($body['year_be'] ?? 0);
  $description = trim((string)($body['description'] ?? ''));
  $code = trim((string)($body['code'] ?? ''));
  $applyProgram = sci_normalize_apply_program($body['apply_program'] ?? 'sciweek');

  if ($title === '') throw new InvalidArgumentException('ต้องระบุชื่อกิจกรรม');
  if ($yearBe < 2500 || $yearBe > 2700) throw new InvalidArgumentException('ปี พ.ศ. ไม่ถูกต้อง');
  if ($code === '') {
    $code = sci_admin_slug_code($title, $yearBe);
  }
  $code = substr(preg_replace('/[^a-zA-Z0-9\-_]/', '', $code) ?: ('event-' . $yearBe), 0, 64);

  $pdo = sci_db();
  if ($id > 0) {
    $chk = $pdo->prepare('SELECT id FROM events WHERE id = ?');
    $chk->execute([$id]);
    if (!$chk->fetch()) throw new RuntimeException('ไม่พบกิจกรรม');
    $upd = $pdo->prepare(
      'UPDATE events SET code = ?, title = ?, year_be = ?, description = ?, apply_program = ?, updated_at = NOW() WHERE id = ?'
    );
    try {
      $upd->execute([$code, $title, $yearBe, $description !== '' ? $description : null, $applyProgram, $id]);
    } catch (PDOException $e) {
      if (str_contains($e->getMessage(), 'Duplicate')) {
        throw new RuntimeException('รหัสกิจกรรมซ้ำกับที่มีอยู่แล้ว');
      }
      throw $e;
    }
    if (function_exists('sci_rbac_audit')) {
      sci_rbac_audit($actorId, 'event_update', 'events', $id, [
        'code' => $code,
        'title' => $title,
        'apply_program' => $applyProgram,
      ]);
    }
  } else {
    $ins = $pdo->prepare(
      'INSERT INTO events (code, title, year_be, description, apply_program, is_active) VALUES (?, ?, ?, ?, ?, 0)'
    );
    try {
      $ins->execute([$code, $title, $yearBe, $description !== '' ? $description : null, $applyProgram]);
    } catch (PDOException $e) {
      if (str_contains($e->getMessage(), 'Duplicate')) {
        throw new RuntimeException('รหัสกิจกรรมซ้ำกับที่มีอยู่แล้ว');
      }
      throw $e;
    }
    $id = (int)$pdo->lastInsertId();
    // default round 1
    $pdo->prepare(
      'INSERT INTO event_rounds (event_id, round_no, title, is_open) VALUES (?, 1, ?, 0)'
    )->execute([$id, 'รอบที่ 1']);
    if (function_exists('sci_rbac_audit')) {
      sci_rbac_audit($actorId, 'event_create', 'events', $id, [
        'code' => $code,
        'title' => $title,
        'apply_program' => $applyProgram,
      ]);
    }
  }

  sci_db_clear_runtime_caches();
  return sci_admin_get_event($id);
}

function sci_admin_set_active_event(int $eventId, ?int $actorId = null): array {
  sci_admin_require_mysql();
  $pdo = sci_db();
  $chk = $pdo->prepare('SELECT id FROM events WHERE id = ?');
  $chk->execute([$eventId]);
  if (!$chk->fetch()) throw new RuntimeException('ไม่พบกิจกรรม');

  $pdo->beginTransaction();
  try {
    $pdo->exec('UPDATE events SET is_active = 0');
    $st = $pdo->prepare('UPDATE events SET is_active = 1, updated_at = NOW() WHERE id = ?');
    $st->execute([$eventId]);
    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
  sci_db_clear_runtime_caches();
  // Reset sci_use_mysql static by clearing — use_mysql caches flag; force refresh via env or leave
  if (function_exists('sci_rbac_audit')) {
    sci_rbac_audit($actorId, 'event_set_active', 'events', $eventId, null);
  }
  return ['ok' => true, 'event_id' => $eventId, 'events' => sci_admin_list_events()];
}

function sci_admin_delete_event(int $eventId, ?int $actorId = null): array {
  sci_admin_require_mysql();
  $pdo = sci_db();
  $st = $pdo->prepare('SELECT id, is_active, (SELECT COUNT(*) FROM applicants a WHERE a.event_id = events.id) AS n FROM events WHERE id = ?');
  $st->execute([$eventId]);
  $row = $st->fetch();
  if (!$row) throw new RuntimeException('ไม่พบกิจกรรม');
  if ((int)$row['is_active'] === 1) {
    throw new RuntimeException('ไม่สามารถลบกิจกรรมที่กำลังใช้งานอยู่ — สลับไปกิจกรรมอื่นก่อน');
  }
  if ((int)$row['n'] > 0) {
    throw new RuntimeException('มีผู้สมัครในกิจกรรมนี้แล้ว ไม่สามารถลบได้ (ปิดการใช้งานแทน)');
  }
  $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$eventId]);
  sci_db_clear_runtime_caches();
  if (function_exists('sci_rbac_audit')) {
    sci_rbac_audit($actorId, 'event_delete', 'events', $eventId, null);
  }
  return ['ok' => true, 'events' => sci_admin_list_events()];
}

/**
 * Copy zones+slots (+ optional rounds) from source event into a new or existing empty event.
 */
function sci_admin_copy_event_structure(array $body, ?int $actorId = null): array {
  sci_admin_require_mysql();
  $sourceId = (int)($body['source_event_id'] ?? 0);
  $targetId = (int)($body['target_event_id'] ?? 0);
  $copyRounds = !empty($body['copy_rounds']);
  $newTitle = trim((string)($body['title'] ?? ''));
  $newYear = (int)($body['year_be'] ?? 0);
  $newCode = trim((string)($body['code'] ?? ''));

  if ($sourceId <= 0) throw new InvalidArgumentException('ต้องระบุกิจกรรมต้นทาง');
  $src = sci_admin_get_event($sourceId);
  $pdo = sci_db();

  $pdo->beginTransaction();
  try {
    if ($targetId <= 0) {
      if ($newTitle === '') {
        $newTitle = (string)$src['event']['title'] . ' (สำเนา)';
      }
      if ($newYear < 2500) {
        $newYear = (int)$src['event']['year_be'] + 1;
      }
      if ($newCode === '') {
        $newCode = sci_admin_slug_code($newTitle, $newYear);
      }
      $ins = $pdo->prepare(
        'INSERT INTO events (code, title, year_be, description, apply_program, is_active) VALUES (?, ?, ?, ?, ?, 0)'
      );
      $ins->execute([
        substr(preg_replace('/[^a-zA-Z0-9\-_]/', '', $newCode) ?: ('event-' . $newYear), 0, 64),
        $newTitle,
        $newYear,
        $src['event']['description'] ?? null,
        sci_normalize_apply_program($src['event']['apply_program'] ?? 'sciweek'),
      ]);
      $targetId = (int)$pdo->lastInsertId();
    } else {
      // Keep existing target metadata; still copy apply_program from source when target is empty structure.
      $pdo->prepare(
        'UPDATE events SET apply_program = ?, updated_at = NOW() WHERE id = ?'
      )->execute([
        sci_normalize_apply_program($src['event']['apply_program'] ?? 'sciweek'),
        $targetId,
      ]);
      $cnt = $pdo->prepare('SELECT COUNT(*) FROM slots WHERE event_id = ?');
      $cnt->execute([$targetId]);
      if ((int)$cnt->fetchColumn() > 0) {
        throw new RuntimeException('กิจกรรมปลายทางมีล็อกอยู่แล้ว — ใช้กิจกรรมว่าง หรือสร้างใหม่');
      }
      $zcnt = $pdo->prepare('SELECT COUNT(*) FROM zones WHERE event_id = ?');
      $zcnt->execute([$targetId]);
      if ((int)$zcnt->fetchColumn() > 0) {
        throw new RuntimeException('กิจกรรมปลายทางมีโซนอยู่แล้ว — ใช้กิจกรรมว่าง หรือสร้างใหม่');
      }
    }

    $zoneMap = []; // old zone id => new zone id
    $zin = $pdo->prepare(
      'INSERT INTO zones (event_id, code, name_th, sort_order) VALUES (?, ?, ?, ?)'
    );
    foreach ($src['zones'] as $z) {
      $zin->execute([$targetId, $z['code'], $z['name_th'], (int)$z['sort_order']]);
      $zoneMap[(int)$z['id']] = (int)$pdo->lastInsertId();
    }

    $sin = $pdo->prepare(
      'INSERT INTO slots (event_id, zone_id, code, category, slot_limit, sort_order, is_active, notes)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($src['slots'] as $s) {
      $newZoneId = $zoneMap[(int)$s['zone_id']] ?? null;
      if (!$newZoneId) continue;
      $sin->execute([
        $targetId,
        $newZoneId,
        $s['code'],
        $s['category'],
        (int)$s['slot_limit'],
        (int)$s['sort_order'],
        (int)$s['is_active'],
        $s['notes'] ?? null,
      ]);
    }

    if ($copyRounds) {
      $rcnt = $pdo->prepare('SELECT COUNT(*) FROM event_rounds WHERE event_id = ?');
      $rcnt->execute([$targetId]);
      if ((int)$rcnt->fetchColumn() === 0) {
        $rin = $pdo->prepare(
          'INSERT INTO event_rounds (event_id, round_no, title, apply_open_at, apply_close_at, is_open, ask_high_power, ask_ice_bucket, apply_flow, notes)
           VALUES (?, ?, ?, NULL, NULL, 0, ?, ?, ?, ?)'
        );
        foreach ($src['rounds'] as $r) {
          $rin->execute([
            $targetId,
            (int)$r['round_no'],
            $r['title'],
            !empty($r['ask_high_power']) ? 1 : 0,
            !empty($r['ask_ice_bucket']) ? 1 : 0,
            sci_normalize_apply_flow($r['apply_flow'] ?? ''),
            $r['notes'] ?? null,
          ]);
        }
      }
    } else {
      $rcnt = $pdo->prepare('SELECT COUNT(*) FROM event_rounds WHERE event_id = ?');
      $rcnt->execute([$targetId]);
      if ((int)$rcnt->fetchColumn() === 0) {
        $pdo->prepare(
          'INSERT INTO event_rounds (event_id, round_no, title, is_open) VALUES (?, 1, ?, 0)'
        )->execute([$targetId, 'รอบที่ 1']);
      }
    }

    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }

  sci_db_clear_runtime_caches();
  if (function_exists('sci_rbac_audit')) {
    sci_rbac_audit($actorId, 'event_copy', 'events', $targetId, [
      'source_event_id' => $sourceId,
      'copy_rounds' => $copyRounds,
    ]);
  }
  return sci_admin_get_event($targetId);
}

function sci_admin_save_round(array $body, ?int $actorId = null): array {
  sci_admin_require_mysql();
  $id = (int)($body['id'] ?? 0);
  $eventId = (int)($body['event_id'] ?? 0);
  $roundNo = (int)($body['round_no'] ?? 0);
  $title = trim((string)($body['title'] ?? ''));
  $openAt = sci_admin_normalize_datetime($body['apply_open_at'] ?? null);
  $closeAt = sci_admin_normalize_datetime($body['apply_close_at'] ?? null);
  $isOpen = !empty($body['is_open']) ? 1 : 0;
  $askHighPower = !empty($body['ask_high_power']) ? 1 : 0;
  $askIceBucket = !empty($body['ask_ice_bucket']) ? 1 : 0;
  $applyFlow = sci_normalize_apply_flow($body['apply_flow'] ?? '');
  $notes = trim((string)($body['notes'] ?? ''));

  if ($eventId <= 0) throw new InvalidArgumentException('ต้องระบุกิจกรรม');
  if ($roundNo < 1 || $roundNo > 20) throw new InvalidArgumentException('หมายเลขรอบไม่ถูกต้อง');
  if ($title === '') $title = 'รอบที่ ' . $roundNo;
  if ($openAt && $closeAt && strtotime($closeAt) < strtotime($openAt)) {
    throw new InvalidArgumentException('วันปิดรับสมัครต้องไม่ก่อนวันเปิดรับสมัคร');
  }

  $pdo = sci_db();
  if ($id > 0) {
    $upd = $pdo->prepare(
      'UPDATE event_rounds SET round_no = ?, title = ?, apply_open_at = ?, apply_close_at = ?, is_open = ?,
              ask_high_power = ?, ask_ice_bucket = ?, apply_flow = ?, notes = ?, updated_at = NOW()
       WHERE id = ? AND event_id = ?'
    );
    try {
      $upd->execute([$roundNo, $title, $openAt, $closeAt, $isOpen, $askHighPower, $askIceBucket, $applyFlow, $notes !== '' ? $notes : null, $id, $eventId]);
    } catch (PDOException $e) {
      if (str_contains($e->getMessage(), 'Duplicate')) {
        throw new RuntimeException('หมายเลขรอบซ้ำในกิจกรรมนี้');
      }
      throw $e;
    }
    if (function_exists('sci_rbac_audit')) {
      sci_rbac_audit($actorId, 'round_update', 'event_rounds', $id, ['event_id' => $eventId, 'round_no' => $roundNo]);
    }
  } else {
    $ins = $pdo->prepare(
      'INSERT INTO event_rounds (event_id, round_no, title, apply_open_at, apply_close_at, is_open, ask_high_power, ask_ice_bucket, apply_flow, notes)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    try {
      $ins->execute([$eventId, $roundNo, $title, $openAt, $closeAt, $isOpen, $askHighPower, $askIceBucket, $applyFlow, $notes !== '' ? $notes : null]);
    } catch (PDOException $e) {
      if (str_contains($e->getMessage(), 'Duplicate')) {
        throw new RuntimeException('หมายเลขรอบซ้ำในกิจกรรมนี้');
      }
      throw $e;
    }
    $id = (int)$pdo->lastInsertId();
    if (function_exists('sci_rbac_audit')) {
      sci_rbac_audit($actorId, 'round_create', 'event_rounds', $id, ['event_id' => $eventId, 'round_no' => $roundNo]);
    }
  }
  sci_db_clear_runtime_caches();
  return sci_admin_get_event($eventId);
}

function sci_admin_delete_round(int $roundId, ?int $actorId = null): array {
  sci_admin_require_mysql();
  $pdo = sci_db();
  $st = $pdo->prepare(
    'SELECT r.id, r.event_id, (SELECT COUNT(*) FROM applicants a WHERE a.round_id = r.id) AS n
     FROM event_rounds r WHERE r.id = ?'
  );
  $st->execute([$roundId]);
  $row = $st->fetch();
  if (!$row) throw new RuntimeException('ไม่พบรอบ');
  if ((int)$row['n'] > 0) {
    throw new RuntimeException('มีผู้สมัครในรอบนี้แล้ว ไม่สามารถลบได้');
  }
  $cnt = $pdo->prepare('SELECT COUNT(*) FROM event_rounds WHERE event_id = ?');
  $cnt->execute([(int)$row['event_id']]);
  if ((int)$cnt->fetchColumn() <= 1) {
    throw new RuntimeException('ต้องเหลืออย่างน้อย 1 รอบในกิจกรรม');
  }
  $pdo->prepare('DELETE FROM event_rounds WHERE id = ?')->execute([$roundId]);
  sci_db_clear_runtime_caches();
  if (function_exists('sci_rbac_audit')) {
    sci_rbac_audit($actorId, 'round_delete', 'event_rounds', $roundId, null);
  }
  return sci_admin_get_event((int)$row['event_id']);
}

function sci_admin_save_zone(array $body, ?int $actorId = null): array {
  sci_admin_require_mysql();
  $id = (int)($body['id'] ?? 0);
  $eventId = (int)($body['event_id'] ?? 0);
  $code = sci_admin_normalize_zone_code((string)($body['code'] ?? ''));
  $nameTh = trim((string)($body['name_th'] ?? ''));
  $sort = (int)($body['sort_order'] ?? 0);
  if ($eventId <= 0) throw new InvalidArgumentException('ต้องระบุกิจกรรม');
  if ($nameTh === '') $nameTh = 'โซน ' . $code;
  if ($sort === 0) $sort = ord($code) - 64;

  $pdo = sci_db();
  if ($id > 0) {
    $upd = $pdo->prepare(
      'UPDATE zones SET code = ?, name_th = ?, sort_order = ? WHERE id = ? AND event_id = ?'
    );
    try {
      $upd->execute([$code, $nameTh, $sort, $id, $eventId]);
    } catch (PDOException $e) {
      if (str_contains($e->getMessage(), 'Duplicate')) {
        throw new RuntimeException('รหัสโซนซ้ำในกิจกรรมนี้');
      }
      throw $e;
    }
    if (function_exists('sci_rbac_audit')) {
      sci_rbac_audit($actorId, 'zone_update', 'zones', $id, ['code' => $code]);
    }
  } else {
    $ins = $pdo->prepare(
      'INSERT INTO zones (event_id, code, name_th, sort_order) VALUES (?, ?, ?, ?)'
    );
    try {
      $ins->execute([$eventId, $code, $nameTh, $sort]);
    } catch (PDOException $e) {
      if (str_contains($e->getMessage(), 'Duplicate')) {
        throw new RuntimeException('รหัสโซนซ้ำในกิจกรรมนี้');
      }
      throw $e;
    }
    $id = (int)$pdo->lastInsertId();
    if (function_exists('sci_rbac_audit')) {
      sci_rbac_audit($actorId, 'zone_create', 'zones', $id, ['code' => $code]);
    }
  }
  sci_db_clear_runtime_caches();
  return sci_admin_get_event($eventId);
}

function sci_admin_delete_zone(int $zoneId, ?int $actorId = null): array {
  sci_admin_require_mysql();
  $pdo = sci_db();
  $st = $pdo->prepare(
    'SELECT z.id, z.event_id,
            (SELECT COUNT(*) FROM slots s WHERE s.zone_id = z.id) AS slots_n,
            (SELECT COUNT(*) FROM applicants a
              JOIN slots s2 ON s2.id = a.assigned_slot_id
              WHERE s2.zone_id = z.id) AS assigned_n
     FROM zones z WHERE z.id = ?'
  );
  $st->execute([$zoneId]);
  $row = $st->fetch();
  if (!$row) throw new RuntimeException('ไม่พบโซน');
  if ((int)$row['assigned_n'] > 0) {
    throw new RuntimeException('มีร้านที่ได้รับล็อกในโซนนี้แล้ว ไม่สามารถลบได้');
  }
  // slots cascade via FK
  $pdo->prepare('DELETE FROM zones WHERE id = ?')->execute([$zoneId]);
  sci_db_clear_runtime_caches();
  if (function_exists('sci_rbac_audit')) {
    sci_rbac_audit($actorId, 'zone_delete', 'zones', $zoneId, null);
  }
  return sci_admin_get_event((int)$row['event_id']);
}

function sci_admin_save_slot(array $body, ?int $actorId = null): array {
  sci_admin_require_mysql();
  $id = (int)($body['id'] ?? 0);
  $eventId = (int)($body['event_id'] ?? 0);
  $zoneId = (int)($body['zone_id'] ?? 0);
  $code = sci_admin_normalize_slot_code((string)($body['code'] ?? ''));
  $category = trim((string)($body['category'] ?? ''));
  $limit = (int)($body['slot_limit'] ?? 1);
  $sort = (int)($body['sort_order'] ?? 0);
  $isActive = array_key_exists('is_active', $body) ? (!empty($body['is_active']) ? 1 : 0) : 1;
  $notes = trim((string)($body['notes'] ?? ''));

  if ($eventId <= 0) throw new InvalidArgumentException('ต้องระบุกิจกรรม');
  if ($zoneId <= 0) throw new InvalidArgumentException('ต้องระบุโซน');
  if ($category === '') throw new InvalidArgumentException('ต้องระบุประเภท/รายละเอียดล็อก');
  if ($limit < 1) $limit = 1;
  if ($sort <= 0) {
    if (preg_match('/^([A-Z])(\d+)$/', $code, $m)) {
      $sort = (ord($m[1]) - 64) * 100 + (int)$m[2];
    } else {
      $sort = 1;
    }
  }

  $pdo = sci_db();
  $zchk = $pdo->prepare('SELECT id FROM zones WHERE id = ? AND event_id = ?');
  $zchk->execute([$zoneId, $eventId]);
  if (!$zchk->fetch()) throw new RuntimeException('โซนไม่ตรงกับกิจกรรม');

  if ($id > 0) {
    $upd = $pdo->prepare(
      'UPDATE slots SET zone_id = ?, code = ?, category = ?, slot_limit = ?, sort_order = ?, is_active = ?, notes = ?
       WHERE id = ? AND event_id = ?'
    );
    try {
      $upd->execute([
        $zoneId, $code, $category, $limit, $sort, $isActive,
        $notes !== '' ? $notes : null, $id, $eventId,
      ]);
    } catch (PDOException $e) {
      if (str_contains($e->getMessage(), 'Duplicate')) {
        throw new RuntimeException('รหัสล็อกซ้ำในกิจกรรมนี้');
      }
      throw $e;
    }
    if (function_exists('sci_rbac_audit')) {
      sci_rbac_audit($actorId, 'slot_update', 'slots', $id, [
        'code' => $code,
        'category' => $category,
      ]);
    }
  } else {
    $ins = $pdo->prepare(
      'INSERT INTO slots (event_id, zone_id, code, category, slot_limit, sort_order, is_active, notes)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    try {
      $ins->execute([
        $eventId, $zoneId, $code, $category, $limit, $sort, $isActive,
        $notes !== '' ? $notes : null,
      ]);
    } catch (PDOException $e) {
      if (str_contains($e->getMessage(), 'Duplicate')) {
        throw new RuntimeException('รหัสล็อกซ้ำในกิจกรรมนี้');
      }
      throw $e;
    }
    $id = (int)$pdo->lastInsertId();
    if (function_exists('sci_rbac_audit')) {
      sci_rbac_audit($actorId, 'slot_create', 'slots', $id, ['code' => $code, 'category' => $category]);
    }
  }
  sci_db_clear_runtime_caches();
  return sci_admin_get_event($eventId);
}

function sci_admin_delete_slot(int $slotId, ?int $actorId = null): array {
  sci_admin_require_mysql();
  $pdo = sci_db();
  $st = $pdo->prepare(
    'SELECT s.id, s.event_id, s.code,
            (SELECT COUNT(*) FROM applicants a WHERE a.assigned_slot_id = s.id) AS n
     FROM slots s WHERE s.id = ?'
  );
  $st->execute([$slotId]);
  $row = $st->fetch();
  if (!$row) throw new RuntimeException('ไม่พบล็อก');
  if ((int)$row['n'] > 0) {
    throw new RuntimeException('มีร้านที่ได้รับล็อกนี้แล้ว — ปิดการใช้งานแทนการลบ');
  }
  $pdo->prepare('DELETE FROM slots WHERE id = ?')->execute([$slotId]);
  sci_db_clear_runtime_caches();
  if (function_exists('sci_rbac_audit')) {
    sci_rbac_audit($actorId, 'slot_delete', 'slots', $slotId, ['code' => $row['code']]);
  }
  return sci_admin_get_event((int)$row['event_id']);
}

/**
 * Swap assigned slots between two selected applicants (same event).
 * Body: applicant_id_a, applicant_id_b  OR  slot_code_a, slot_code_b (+ optional round_no)
 */
function sci_admin_swap_assignments(array $body, ?int $actorId = null): array {
  sci_admin_require_mysql();
  $pdo = sci_db();
  $eventId = (int)($body['event_id'] ?? 0);
  $idA = (int)($body['applicant_id_a'] ?? 0);
  $idB = (int)($body['applicant_id_b'] ?? 0);
  $slotA = strtoupper(trim((string)($body['slot_code_a'] ?? '')));
  $slotB = strtoupper(trim((string)($body['slot_code_b'] ?? '')));
  $roundNo = isset($body['round_no']) ? (int)$body['round_no'] : 0;

  if ($eventId <= 0) {
    $eventId = (int)sci_db_active_event()['id'];
  }

  $findBySlot = function (string $code, int $round) use ($pdo, $eventId): ?array {
    $sql = 'SELECT a.id, a.assigned_slot_id, a.name, a.selection, s.code AS slot_code
            FROM applicants a
            JOIN slots s ON s.id = a.assigned_slot_id
            JOIN event_rounds er ON er.id = a.round_id
            WHERE a.event_id = ? AND s.code = ? AND a.selection = ?
              AND a.assigned_slot_id IS NOT NULL';
    $params = [$eventId, $code, 'ได้รับการคัดเลือก'];
    if ($round > 0) {
      $sql .= ' AND er.round_no = ?';
      $params[] = $round;
    }
    $sql .= ' LIMIT 1';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row ?: null;
  };

  if ($idA <= 0 && $slotA !== '') {
    $row = $findBySlot($slotA, $roundNo);
    if (!$row) throw new RuntimeException('ไม่พบผู้ได้รับสิทธิ์ที่ล็อก ' . $slotA);
    $idA = (int)$row['id'];
  }
  if ($idB <= 0 && $slotB !== '') {
    $row = $findBySlot($slotB, $roundNo);
    if (!$row) throw new RuntimeException('ไม่พบผู้ได้รับสิทธิ์ที่ล็อก ' . $slotB);
    $idB = (int)$row['id'];
  }
  if ($idA <= 0 || $idB <= 0) {
    throw new InvalidArgumentException('ต้องระบุผู้สมัครหรือรหัสล็อก 2 รายการ');
  }
  if ($idA === $idB) {
    throw new InvalidArgumentException('ต้องเลือกคนละร้านกัน');
  }

  $st = $pdo->prepare(
    'SELECT id, event_id, name, assigned_slot_id, selection FROM applicants WHERE id IN (?, ?)'
  );
  $st->execute([$idA, $idB]);
  $rows = $st->fetchAll();
  if (count($rows) !== 2) throw new RuntimeException('ไม่พบผู้สมัครครบทั้งสองราย');
  $byId = [];
  foreach ($rows as $r) {
    $byId[(int)$r['id']] = $r;
  }
  $a = $byId[$idA];
  $b = $byId[$idB];
  if ((int)$a['event_id'] !== $eventId || (int)$b['event_id'] !== $eventId) {
    throw new RuntimeException('ผู้สมัครต้องอยู่ในกิจกรรมเดียวกัน');
  }
  if ($a['selection'] !== 'ได้รับการคัดเลือก' || $b['selection'] !== 'ได้รับการคัดเลือก') {
    throw new RuntimeException('สลับได้เฉพาะร้านที่ได้รับการคัดเลือกแล้ว');
  }
  if (empty($a['assigned_slot_id']) || empty($b['assigned_slot_id'])) {
    throw new RuntimeException('ทั้งสองร้านต้องมีล็อกที่ได้รับแล้ว');
  }

  $slotIdA = (int)$a['assigned_slot_id'];
  $slotIdB = (int)$b['assigned_slot_id'];

  $pdo->beginTransaction();
  try {
    // clear first to avoid unique conflicts if any
    $pdo->prepare('UPDATE applicants SET assigned_slot_id = NULL WHERE id IN (?, ?)')->execute([$idA, $idB]);
    $pdo->prepare('UPDATE applicants SET assigned_slot_id = ?, updated_at = NOW() WHERE id = ?')->execute([$slotIdB, $idA]);
    $pdo->prepare('UPDATE applicants SET assigned_slot_id = ?, updated_at = NOW() WHERE id = ?')->execute([$slotIdA, $idB]);
    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }

  sci_db_clear_runtime_caches();
  if (function_exists('sci_rbac_audit')) {
    sci_rbac_audit($actorId, 'slot_swap', 'applicants', $idA, [
      'applicant_a' => $idA,
      'applicant_b' => $idB,
      'name_a' => $a['name'],
      'name_b' => $b['name'],
      'slot_a_was' => $slotIdA,
      'slot_b_was' => $slotIdB,
    ]);
  }
  return sci_admin_get_event($eventId);
}

function sci_admin_action_labels(): array {
  return [
    'event_create' => 'สร้างกิจกรรม',
    'event_update' => 'แก้ไขกิจกรรม',
    'event_set_active' => 'ตั้งกิจกรรมใช้งาน',
    'event_delete' => 'ลบกิจกรรม',
    'event_copy' => 'คัดลอกโครงสร้างกิจกรรม',
    'round_create' => 'เพิ่มรอบ',
    'round_update' => 'แก้ไขรอบ',
    'round_delete' => 'ลบรอบ',
    'zone_create' => 'เพิ่มโซน',
    'zone_update' => 'แก้ไขโซน',
    'zone_delete' => 'ลบโซน',
    'slot_create' => 'เพิ่มล็อก',
    'slot_update' => 'แก้ไขล็อก',
    'slot_delete' => 'ลบล็อก',
    'slot_swap' => 'สลับล็อกผู้ได้รับสิทธิ์',
  ];
}
