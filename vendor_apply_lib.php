<?php
/**
 * Public vendor registration: open-window checks, submit, secure uploads.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db_data_lib.php';
require_once __DIR__ . '/s3_lib.php';

function sci_vendor_upload_root(): string {
  $dir = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'uploads';
  if (!is_dir($dir)) {
    @mkdir($dir, 0750, true);
  }
  return $dir;
}

/** SCiWEEK uploads: images only (JPG/PNG/WEBP). */
function sci_vendor_image_mimes(): array {
  return ['image/jpeg', 'image/png', 'image/webp'];
}

/** SCiSQUARE document uploads: JPEG/PNG/PDF (no WEBP). */
function sci_vendor_doc_mimes(): array {
  return ['image/jpeg', 'image/png', 'application/pdf'];
}

function sci_vendor_settings(): array {
  $out = [
    'upload_max_mb' => 10,
    'upload_allowed_mimes' => sci_vendor_image_mimes(),
  ];
  try {
    $st = sci_db()->query('SELECT setting_key, setting_value FROM settings');
    foreach ($st->fetchAll() as $row) {
      $k = (string)$row['setting_key'];
      $v = (string)$row['setting_value'];
      if ($k === 'upload_max_mb') {
        $out['upload_max_mb'] = max(1, (int)$v);
      }
    }
  } catch (Throwable $e) {
    // defaults
  }
  $out['upload_allowed_mimes'] = sci_vendor_image_mimes();
  return $out;
}

/** Whether qualifications map to juristic-person docs (SCiSQUARE). */
function sci_vendor_is_juristic_qualify(string $qualify): bool {
  $q = trim($qualify);
  return $q === 'นิติบุคคล' || str_starts_with($q, 'นิติบุคคล');
}

/**
 * Document upload schema for the apply form.
 * @return list<array{key:string,name:string,label:string,hint:string,required:bool,multiple:bool,max_files:int,accept:string,accept_mimes:list<string>,accept_ext:list<string>}>
 */
function sci_vendor_doc_schema(string $applyProgram, string $qualify = ''): array {
  $applyProgram = sci_normalize_apply_program($applyProgram);
  $imgAccept = '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp';
  $imgMimes = sci_vendor_image_mimes();
  $imgExt = ['jpg', 'jpeg', 'png', 'webp'];
  $photoAccept = '.jpg,.jpeg,.png,image/jpeg,image/png';
  $photoMimes = ['image/jpeg', 'image/png'];
  $photoExt = ['jpg', 'jpeg', 'png'];
  $docAccept = '.jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf';
  $docMimes = sci_vendor_doc_mimes();
  $docExt = ['jpg', 'jpeg', 'png', 'pdf'];

  if ($applyProgram === 'sciweek') {
    return [
      [
        'key' => 'id_card',
        'name' => 'id_card',
        'label' => 'สำเนาบัตรประชาชน',
        'hint' => 'ถ่ายให้ชัด อ่านตัวอักษรได้',
        'required' => true,
        'multiple' => false,
        'max_files' => 1,
        'accept' => $imgAccept,
        'accept_mimes' => $imgMimes,
        'accept_ext' => $imgExt,
      ],
      [
        'key' => 'house_reg',
        'name' => 'house_reg',
        'label' => 'สำเนาทะเบียนบ้าน',
        'hint' => 'หน้าที่มีชื่อผู้สมัคร',
        'required' => true,
        'multiple' => false,
        'max_files' => 1,
        'accept' => $imgAccept,
        'accept_mimes' => $imgMimes,
        'accept_ext' => $imgExt,
      ],
      [
        'key' => 'photo',
        'name' => 'photo',
        'label' => 'รูปถ่ายหน้าตรง',
        'hint' => 'เห็นใบหน้าชัดเจน',
        'required' => true,
        'multiple' => false,
        'max_files' => 1,
        'accept' => $imgAccept,
        'accept_mimes' => $imgMimes,
        'accept_ext' => $imgExt,
      ],
      [
        'key' => 'food',
        'name' => 'food[]',
        'label' => 'ภาพอาหาร / สินค้า',
        'hint' => 'เลือกได้หลายรูป (สูงสุด 5 รูป)',
        'required' => true,
        'multiple' => true,
        'max_files' => 5,
        'accept' => $imgAccept,
        'accept_mimes' => $imgMimes,
        'accept_ext' => $imgExt,
      ],
    ];
  }

  $isJuristic = sci_vendor_is_juristic_qualify($qualify);
  $propHint = 'รองรับไฟล์ JPEG, PNG หรือ PDF';
  $menuIntro = 'ส่งไฟล์รายละเอียดอาหารหรือสินค้า ประกอบด้วย';
  $menuItems = [
    'ราคาและภาพแสดงปริมาณอาหารเหมือนกับที่จะจำหน่ายจริงทุกประการ',
    'ระบุขนาดจานหรือภาชนะบรรจุอาหาร เพื่อให้เห็นภาพปริมาณอาหารได้ชัดเจน',
    'ระบุแหล่งที่มาของวัตถุดิบที่ใช้ทำอาหาร',
  ];
  $mgmtIntro = 'ไฟล์รายละเอียดแผนการบริหารจัดการร้านค้า ประกอบด้วย';
  $mgmtItems = [
    'การรักษาความสะอาด',
    'การจัดการขยะ',
    'การซ่อมบำรุง',
    'การรักษามาตรฐานหรือการกำกับควบคุมคุณภาพสินค้า',
    'การตกแต่งร้านค้า',
    'ช่วงเวลาการเปิด–ปิดร้าน',
  ];
  $props = [
    [
      'key' => 'prop_menu',
      'label' => '1) รายละเอียดอาหารและสินค้า',
      'hint' => $menuIntro . ' ' . implode(' · ', $menuItems) . ' · ' . $propHint,
      'hint_intro' => $menuIntro,
      'hint_items' => $menuItems,
      'hint_accept' => $propHint,
      'required' => true,
    ],
    [
      'key' => 'prop_mgmt',
      'label' => '2) แผนการบริหารจัดการร้านค้า',
      'hint' => $mgmtIntro . ' ' . implode(' · ', $mgmtItems) . ' · ' . $propHint,
      'hint_intro' => $mgmtIntro,
      'hint_items' => $mgmtItems,
      'hint_accept' => $propHint,
      'required' => true,
    ],
  ];
  if ($isJuristic) {
    $props[] = [
      'key' => 'prop_ops',
      'label' => '3) การซ่อมบำรุง / มาตรฐาน / การตกแต่งร้านค้า',
      'hint' => 'การซ่อมบำรุง การรักษามาตรฐานหรือกำกับคุณภาพสินค้า การตกแต่งร้านค้า ช่วงเวลาเปิด–ปิดร้าน · ' . $propHint,
      'required' => true,
    ];
  }
  $expNo = $isJuristic ? '4)' : '3)';
  $extraNo = $isJuristic ? '5)' : '4)';
  $props[] = [
    'key' => 'prop_exp',
    'label' => $expNo . ' ประสบการณ์ ความรู้ และความชำนาญ',
    'hint' => 'กรอกประสบการณ์ ความรู้ และความชำนาญในการประกอบกิจการ',
    'required' => true,
    'kind' => 'text',
  ];
  $props[] = [
    'key' => 'prop_extra',
    'label' => $extraNo . ' หลักฐานเพิ่มเติม (ถ้ามี)',
    'hint' => 'เช่น สำเนาประกาศนียบัตร หนังสือรับรองการอบรม หรือรางวัลที่เกี่ยวข้อง ภาพกิจการที่ผ่านมา · ' . $propHint,
    'required' => false,
  ];

  $schema = [];
  if ($isJuristic) {
    $schema[] = [
      'key' => 'company_cert',
      'name' => 'company_cert',
      'label' => 'สำเนาหนังสือรับรองการจดทะเบียนนิติบุคคล',
      'hint' => 'นับตั้งแต่วันที่ออกหนังสือไม่เกิน 6 เดือน · รับรองสำเนาถูกต้อง และประทับตราสำคัญ (ถ้ามี) · ' . $propHint,
      'required' => true,
      'kind' => 'file',
      'multiple' => false,
      'max_files' => 1,
      'accept' => $docAccept,
      'accept_mimes' => $docMimes,
      'accept_ext' => $docExt,
    ];
    $schema[] = [
      'key' => 'id_card',
      'name' => 'id_card',
      'label' => 'สำเนาบัตรประจำตัวประชาชนของผู้มีอำนาจลงนาม',
      'hint' => 'พร้อมรับรองสำเนาถูกต้อง · ณ วันยื่นสมัครบัตรต้องยังไม่หมดอายุ · ' . $propHint,
      'required' => true,
      'kind' => 'file',
      'multiple' => false,
      'max_files' => 1,
      'accept' => $docAccept,
      'accept_mimes' => $docMimes,
      'accept_ext' => $docExt,
    ];
  } else {
    $schema[] = [
      'key' => 'photo',
      'name' => 'photo',
      'label' => 'ไฟล์รูปถ่ายดิจิทัลหน้าตรง',
      'hint' => 'ไม่สวมหมวก ไม่สวมแว่นตาดำ · ถ่ายไว้ไม่เกิน 6 เดือน · รองรับเฉพาะไฟล์ JPEG, PNG',
      'required' => true,
      'kind' => 'file',
      'multiple' => false,
      'max_files' => 1,
      'accept' => $photoAccept,
      'accept_mimes' => $photoMimes,
      'accept_ext' => $photoExt,
    ];
    $schema[] = [
      'key' => 'id_card',
      'name' => 'id_card',
      'label' => 'สำเนาบัตรประจำตัวประชาชน',
      'hint' => 'พร้อมรับรองสำเนาถูกต้อง · ณ วันยื่นสมัครบัตรต้องยังไม่หมดอายุ · ' . $propHint,
      'required' => true,
      'kind' => 'file',
      'multiple' => false,
      'max_files' => 1,
      'accept' => $docAccept,
      'accept_mimes' => $docMimes,
      'accept_ext' => $docExt,
    ];
    $schema[] = [
      'key' => 'house_reg',
      'name' => 'house_reg',
      'label' => 'สำเนาทะเบียนบ้าน',
      'hint' => 'พร้อมรับรองสำเนาถูกต้อง · ' . $propHint,
      'required' => true,
      'kind' => 'file',
      'multiple' => false,
      'max_files' => 1,
      'accept' => $docAccept,
      'accept_mimes' => $docMimes,
      'accept_ext' => $docExt,
    ];
  }

  foreach ($props as $p) {
    $kind = (string)($p['kind'] ?? 'file');
    $hintExtra = [];
    if (!empty($p['hint_intro'])) $hintExtra['hint_intro'] = (string)$p['hint_intro'];
    if (!empty($p['hint_items']) && is_array($p['hint_items'])) {
      $hintExtra['hint_items'] = array_values(array_map('strval', $p['hint_items']));
    }
    if (!empty($p['hint_accept'])) $hintExtra['hint_accept'] = (string)$p['hint_accept'];
    if ($kind === 'text') {
      $schema[] = array_merge([
        'key' => $p['key'],
        'name' => $p['key'] === 'prop_exp' ? 'experience_text' : $p['key'],
        'label' => $p['label'],
        'hint' => $p['hint'],
        'required' => (bool)$p['required'],
        'kind' => 'text',
        'rows' => 5,
        'placeholder' => 'เช่น เคยประกอบกิจการมากี่ปี มีความรู้ด้านใดบ้าง',
      ], $hintExtra);
      continue;
    }
    $schema[] = array_merge([
      'key' => $p['key'],
      'name' => $p['key'],
      'label' => $p['label'],
      'hint' => $p['hint'],
      'required' => (bool)$p['required'],
      'kind' => 'file',
      'multiple' => false,
      'max_files' => 1,
      'accept' => $docAccept,
      'accept_mimes' => $docMimes,
      'accept_ext' => $docExt,
    ], $hintExtra);
  }
  return $schema;
}

function sci_vendor_ensure_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  if (session_status() === PHP_SESSION_NONE) {
    session_start([
      'cookie_httponly' => true,
      'cookie_samesite' => 'Lax',
      'use_strict_mode' => true,
    ]);
  }
}

/** Normalize phone for storage / duplicate checks. */
function sci_vendor_normalize_phone(string $phone): string {
  $phone = trim($phone);
  return preg_replace('/[\s\-]+/', '', $phone) ?? '';
}

/**
 * Resolve target event for public apply/status.
 * Empty code → active event (legacy). Non-empty → exact code match only.
 *
 * @return array{id:int,code:string,title:string,year_be:int}
 */
function sci_vendor_resolve_event(?string $eventCode = null): array {
  $eventCode = trim((string)$eventCode);
  if ($eventCode !== '') {
    return sci_db_event_by_code($eventCode);
  }
  return sci_db_active_event();
}

/**
 * Check if phone already registered in a round (within the given event).
 * @return array{taken:bool,applicant_id?:int,name?:string,round_title?:string,message:string}
 */
function sci_vendor_phone_taken(int $roundId, string $phone, ?string $eventCode = null): array {
  $phone = sci_vendor_normalize_phone($phone);
  if ($phone === '' || !preg_match('/^[0-9+]{8,20}$/', $phone)) {
    throw new InvalidArgumentException('กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง');
  }
  $meta = sci_vendor_form_meta(false, $eventCode);
  $eventId = (int)$meta['event']['id'];
  $roundTitle = '';
  $roundOk = false;
  foreach ($meta['rounds'] as $r) {
    if ((int)$r['id'] === $roundId) {
      $roundOk = true;
      $roundTitle = (string)($r['title'] ?? ('รอบที่ ' . ($r['round_no'] ?? '')));
      break;
    }
  }
  if (!$roundOk) {
    throw new InvalidArgumentException('ไม่พบรอบที่เลือก');
  }

  $st = sci_db()->prepare(
    "SELECT a.id, a.name
     FROM applicants a
     WHERE a.event_id = ? AND a.round_id = ?
       AND REPLACE(REPLACE(a.phone, '-', ''), ' ', '') = ?
     LIMIT 1"
  );
  $st->execute([$eventId, $roundId, $phone]);
  $row = $st->fetch();
  if ($row) {
    return [
      'taken' => true,
      'applicant_id' => (int)$row['id'],
      'name' => (string)$row['name'],
      'round_title' => $roundTitle,
      'message' => 'เบอร์โทรศัพท์นี้มีการใช้งานสมัครไปแล้วใน' . $roundTitle
        . ' กรุณาใช้เบอร์อื่น หรือตรวจสอบสถานะใบสมัครเดิม',
    ];
  }
  return [
    'taken' => false,
    'round_title' => $roundTitle,
    'message' => 'เบอร์นี้ยังไม่ถูกใช้ในรอบนี้',
  ];
}

/** @return list<string> */
function sci_vendor_name_titles(): array {
  return ['นาย', 'นาง', 'นางสาว'];
}

/**
 * Issue a simple math captcha bound to the session.
 * @param string $purpose apply|status
 * @return array{question:string,token:string,purpose:string}
 */
function sci_vendor_captcha_issue(string $purpose = 'apply'): array {
  sci_vendor_ensure_session();
  $purpose = preg_replace('/[^a-z]/', '', strtolower($purpose)) ?: 'apply';
  $a = random_int(2, 9);
  $b = random_int(1, 8);
  $token = bin2hex(random_bytes(16));
  $_SESSION['sci_captcha_' . $purpose] = [
    'answer' => $a + $b,
    'token' => $token,
    'issued_at' => time(),
  ];
  // keep legacy key for older clients during apply
  if ($purpose === 'apply') {
    $_SESSION['sci_apply_captcha'] = $_SESSION['sci_captcha_apply'];
  }
  return [
    'question' => $a . ' + ' . $b . ' = ?',
    'token' => $token,
    'purpose' => $purpose,
  ];
}

/**
 * Verify honeypot (optional) + math captcha. Consumes the captcha on success.
 * @param string $purpose apply|status
 */
function sci_vendor_captcha_verify(array $post, string $purpose = 'apply', bool $checkHoneypot = true): void {
  if ($checkHoneypot) {
    $hp = trim((string)($post['company_url'] ?? $post['website'] ?? ''));
    if ($hp !== '') {
      throw new InvalidArgumentException('ไม่สามารถดำเนินการได้');
    }
  }

  sci_vendor_ensure_session();
  $purpose = preg_replace('/[^a-z]/', '', strtolower($purpose)) ?: 'apply';
  $key = 'sci_captcha_' . $purpose;
  $stored = $_SESSION[$key] ?? null;
  if ($purpose === 'apply' && !is_array($stored)) {
    $stored = $_SESSION['sci_apply_captcha'] ?? null;
  }
  if (!is_array($stored) || empty($stored['token']) || !isset($stored['answer'])) {
    throw new InvalidArgumentException('รหัสป้องกันสแปมหมดอายุ กรุณากดเปลี่ยนข้อแล้วลองใหม่');
  }

  $token = trim((string)($post['captcha_token'] ?? ''));
  $answerRaw = trim((string)($post['captcha_answer'] ?? ''));
  if ($token === '' || !hash_equals((string)$stored['token'], $token)) {
    throw new InvalidArgumentException('รหัสป้องกันสแปมไม่ถูกต้อง กรุณากดเปลี่ยนข้อแล้วลองใหม่');
  }
  if ($answerRaw === '' || !preg_match('/^-?\d+$/', $answerRaw)) {
    throw new InvalidArgumentException('กรุณาตอบคำถามป้องกันสแปม');
  }
  if ((int)$answerRaw !== (int)$stored['answer']) {
    unset($_SESSION[$key]);
    if ($purpose === 'apply') unset($_SESSION['sci_apply_captcha']);
    throw new InvalidArgumentException('คำตอบป้องกันสแปมไม่ถูกต้อง กรุณาลองใหม่');
  }

  $issuedAt = (int)($stored['issued_at'] ?? 0);
  if ($issuedAt > 0 && (time() - $issuedAt) < 2) {
    throw new InvalidArgumentException('ส่งเร็วเกินไป กรุณาลองอีกครั้ง');
  }

  unset($_SESSION[$key]);
  if ($purpose === 'apply') unset($_SESSION['sci_apply_captcha']);
}

/**
 * Build full applicant display name from title + name parts.
 */
function sci_vendor_compose_name(array $post): string {
  $titles = sci_vendor_name_titles();
  $title = trim((string)($post['name_title'] ?? ''));
  $name = trim(preg_replace('/\s+/u', ' ', (string)($post['name'] ?? '')) ?? '');

  // Strip accidental title already typed into name
  foreach ($titles as $t) {
    if (mb_strpos($name, $t) === 0) {
      $rest = trim(mb_substr($name, mb_strlen($t)));
      if ($rest !== '') $name = $rest;
      if ($title === '') $title = $t;
      break;
    }
  }

  if ($title === '' || !in_array($title, $titles, true)) {
    throw new InvalidArgumentException('กรุณาเลือกคำนำหน้าชื่อ (นาย / นาง / นางสาว)');
  }
  if ($name === '' || mb_strlen($name) < 2) {
    throw new InvalidArgumentException('กรุณากรอกชื่อ–นามสกุลให้ครบ');
  }
  // Reject if name still starts with another title-like token
  return $title . ' ' . $name;
}

/**
 * @return array{open:bool,reason:string,opens_at:?string,closes_at:?string}
 */
function sci_vendor_round_accepting(array $round): array {
  $opensAt = $round['apply_open_at'] ?? null;
  $closesAt = $round['apply_close_at'] ?? null;
  $isOpenFlag = (int)($round['is_open'] ?? 0) === 1;
  $now = time();

  if (!$isOpenFlag) {
    return [
      'open' => false,
      'reason' => 'รอบนี้ยังไม่เปิดรับสมัคร',
      'opens_at' => $opensAt ? (string)$opensAt : null,
      'closes_at' => $closesAt ? (string)$closesAt : null,
    ];
  }

  if ($opensAt) {
    $t = strtotime((string)$opensAt);
    if ($t !== false && $now < $t) {
      return [
        'open' => false,
        'reason' => 'ยังไม่ถึงวันเปิดรับสมัคร',
        'opens_at' => (string)$opensAt,
        'closes_at' => $closesAt ? (string)$closesAt : null,
      ];
    }
  }

  if ($closesAt) {
    $t = strtotime((string)$closesAt);
    if ($t !== false && $now > $t) {
      return [
        'open' => false,
        'reason' => 'ปิดรับสมัครแล้ว',
        'opens_at' => $opensAt ? (string)$opensAt : null,
        'closes_at' => (string)$closesAt,
      ];
    }
  }

  return [
    'open' => true,
    'reason' => '',
    'opens_at' => $opensAt ? (string)$opensAt : null,
    'closes_at' => $closesAt ? (string)$closesAt : null,
  ];
}

/**
 * Catalog for the public apply form (target event + accepting rounds).
 * Pass $eventCode from apply.php?event=... ; null/empty uses active event.
 */
function sci_vendor_form_meta(bool $withCaptcha = true, ?string $eventCode = null): array {
  $event = sci_vendor_resolve_event($eventCode);
  $eventId = (int)$event['id'];

  $st = sci_db()->prepare(
    'SELECT id, round_no, title, apply_open_at, apply_close_at, is_open, ask_high_power, ask_ice_bucket, apply_flow, notes
     FROM event_rounds WHERE event_id = ? ORDER BY round_no'
  );
  $st->execute([$eventId]);
  $roundsRaw = $st->fetchAll();
  $rounds = [];
  $anyOpen = false;
  foreach ($roundsRaw as $r) {
    $status = sci_vendor_round_accepting($r);
    $item = [
      'id' => (int)$r['id'],
      'round_no' => (int)$r['round_no'],
      'title' => (string)$r['title'],
      'apply_open_at' => $r['apply_open_at'],
      'apply_close_at' => $r['apply_close_at'],
      'is_open_flag' => (int)$r['is_open'],
      'ask_high_power' => (int)($r['ask_high_power'] ?? 0) === 1,
      'ask_ice_bucket' => (int)($r['ask_ice_bucket'] ?? 0) === 1,
      'apply_flow' => sci_normalize_apply_flow($r['apply_flow'] ?? ''),
      'accepting' => $status['open'],
      'status_reason' => $status['reason'],
    ];
    if ($status['open']) $anyOpen = true;
    $rounds[] = $item;
  }

  $zst = sci_db()->prepare(
    'SELECT id, code, name_th, sort_order FROM zones WHERE event_id = ? ORDER BY sort_order, code'
  );
  $zst->execute([$eventId]);
  $zones = $zst->fetchAll();

  $sst = sci_db()->prepare(
    'SELECT s.id, s.code, s.category, s.slot_limit, s.is_active, z.code AS zone_code
     FROM slots s
     JOIN zones z ON z.id = s.zone_id
     WHERE s.event_id = ? AND s.is_active = 1
     ORDER BY s.sort_order, s.code'
  );
  $sst->execute([$eventId]);
  $slots = $sst->fetchAll();

  $categoriesByZone = [];
  foreach ($slots as $s) {
    $z = strtoupper((string)$s['zone_code']);
    $cat = trim((string)$s['category']);
    if ($cat === '') continue;
    if (!isset($categoriesByZone[$z])) $categoriesByZone[$z] = [];
    if (!in_array($cat, $categoriesByZone[$z], true)) {
      $categoriesByZone[$z][] = $cat;
    }
  }
  $categoriesAll = [];
  $categoryZones = [];
  foreach ($categoriesByZone as $z => $cats) {
    foreach ($cats as $cat) {
      if (!in_array($cat, $categoriesAll, true)) $categoriesAll[] = $cat;
      if (!isset($categoryZones[$cat])) $categoryZones[$cat] = [];
      if (!in_array($z, $categoryZones[$cat], true)) $categoryZones[$cat][] = $z;
    }
  }

  $settings = sci_vendor_settings();
  $applyProgram = sci_normalize_apply_program($event['apply_program'] ?? 'sciweek');
  $programLabel = $applyProgram === 'scisquare' ? 'SCiSQUARE' : 'SCiWEEK';
  $qualifyOptions = $applyProgram === 'scisquare'
    ? [
      'บุคคลธรรมดา / ผู้ประกอบการทั่วไป',
      'นิติบุคคล',
    ]
    : [
      'บุคคลธรรมดา / ผู้ประกอบการทั่วไป',
      'นิติบุคคล',
      'ศิษย์เก่าคณะวิทยาศาสตร์ มข.',
      'บุคลากร / นักศึกษา มข.',
      'อื่นๆ',
    ];

  $defaultQualify = $qualifyOptions[0] ?? '';
  $docSchema = sci_vendor_doc_schema($applyProgram, $defaultQualify);
  $allowedExt = $applyProgram === 'scisquare'
    ? ['jpg', 'jpeg', 'png', 'pdf', 'webp']
    : ['jpg', 'jpeg', 'png', 'webp'];
  $allowedMimes = $applyProgram === 'scisquare'
    ? array_values(array_unique(array_merge(sci_vendor_image_mimes(), sci_vendor_doc_mimes())))
    : sci_vendor_image_mimes();

  $uploadHint = $applyProgram === 'scisquare'
    ? 'เอกสารทั่วไปรองรับไฟล์ JPEG / PNG / PDF · รูปหน้าตรงรองรับเฉพาะไฟล์ JPEG / PNG · ไฟล์ละไม่เกิน'
    : 'รองรับเฉพาะไฟล์ภาพ JPG / PNG / WEBP · ไฟล์ละไม่เกิน';

  return [
    'event' => [
      'id' => $eventId,
      'code' => $event['code'],
      'title' => $event['title'],
      'year_be' => (int)$event['year_be'],
      'apply_program' => $applyProgram,
    ],
    'apply_program' => $applyProgram,
    'apply_program_label' => $programLabel,
    'branding' => [
      'org' => 'คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น',
      'org_short' => 'คณะวิทยาศาสตร์ มข.',
      'product' => 'ระบบรับสมัครร้านค้า',
      'headline' => 'รับสมัครร้านค้า ' . $programLabel . ' · คณะวิทยาศาสตร์ มข.',
      'page_title' => 'รับสมัครร้านค้า ' . $programLabel . ' · ' . $event['title'],
      'subline' => trim((string)$event['title']) !== ''
        ? (string)$event['title']
        : ('กิจกรรมประจำปี ' . (int)$event['year_be']),
    ],
    'name_titles' => sci_vendor_name_titles(),
    'captcha' => $withCaptcha ? sci_vendor_captcha_issue('apply') : null,
    'status_captcha' => $withCaptcha ? sci_vendor_captcha_issue('status') : null,
    'rounds' => $rounds,
    'accepting' => $anyOpen,
    'zones' => array_map(static function ($z) {
      return [
        'id' => (int)$z['id'],
        'code' => (string)$z['code'],
        'name_th' => (string)$z['name_th'],
      ];
    }, $zones),
    'categories_by_zone' => $categoriesByZone,
    'categories_all' => $categoriesAll,
    'category_zones' => $categoryZones,
    'slot_count' => count($slots),
    'upload' => [
      'max_mb' => $settings['upload_max_mb'],
      'allowed_mimes' => $allowedMimes,
      'allowed_ext' => $allowedExt,
      'food_max' => 5,
      'storage' => sci_s3_configured() ? 'minio' : 'local',
      'hint' => $uploadHint,
      'doc_schema' => $docSchema,
      'doc_schemas' => [
        'default' => $docSchema,
        'individual' => sci_vendor_doc_schema($applyProgram, 'บุคคลธรรมดา / ผู้ประกอบการทั่วไป'),
        'juristic' => sci_vendor_doc_schema($applyProgram, 'นิติบุคคล'),
      ],
    ],
    'qualify_options' => $qualifyOptions,
  ];
}

function sci_vendor_next_legacy_row(int $eventId, int $roundId): int {
  $st = sci_db()->prepare(
    'SELECT COALESCE(MAX(legacy_excel_row), 1) FROM applicants WHERE event_id = ? AND round_id = ?'
  );
  $st->execute([$eventId, $roundId]);
  return max(2, (int)$st->fetchColumn() + 1);
}

function sci_vendor_detect_mime(string $tmpPath): string {
  if (!is_file($tmpPath)) return '';
  if (function_exists('finfo_open')) {
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    if ($fi) {
      $mime = (string)finfo_file($fi, $tmpPath);
      finfo_close($fi);
      if ($mime !== '') return strtolower($mime);
    }
  }
  $fh = @fopen($tmpPath, 'rb');
  $head = $fh ? (string)fread($fh, 5) : '';
  if ($fh) fclose($fh);
  if (str_starts_with($head, '%PDF')) {
    return 'application/pdf';
  }
  $img = @getimagesize($tmpPath);
  if (is_array($img) && !empty($img['mime'])) {
    return strtolower((string)$img['mime']);
  }
  return '';
}

function sci_vendor_ext_for_mime(string $mime): string {
  return match ($mime) {
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
    default => '',
  };
}

/**
 * @param array $file one $_FILES entry
 * @param list<string>|null $allowedMimes null = images only (legacy)
 * @return array{ok:bool,error?:string,path?:string,mime?:string,size?:int,original?:string}
 */
function sci_vendor_store_upload(array $file, string $fileType, int $eventId, int $roundId, int $applicantId, ?array $allowedMimes = null): array {
  $settings = sci_vendor_settings();
  $maxBytes = (int)$settings['upload_max_mb'] * 1024 * 1024;
  $allowed = $allowedMimes ?: sci_vendor_image_mimes();
  $allowPdf = in_array('application/pdf', $allowed, true);

  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    return ['ok' => false, 'error' => 'อัปโหลดไฟล์ไม่สำเร็จ (รหัส ' . ($file['error'] ?? '?') . ')'];
  }
  $tmp = (string)($file['tmp_name'] ?? '');
  if ($tmp === '' || !is_uploaded_file($tmp)) {
    return ['ok' => false, 'error' => 'ไม่พบไฟล์อัปโหลด'];
  }
  $size = (int)($file['size'] ?? 0);
  if ($size <= 0 || $size > $maxBytes) {
    return ['ok' => false, 'error' => 'ขนาดไฟล์ต้องไม่เกิน ' . $settings['upload_max_mb'] . ' MB'];
  }

  $mime = sci_vendor_detect_mime($tmp);
  if ($mime === '' || !in_array($mime, $allowed, true)) {
    $labels = [];
    if (in_array('image/jpeg', $allowed, true)) $labels[] = 'JPEG';
    if (in_array('image/png', $allowed, true)) $labels[] = 'PNG';
    if (in_array('image/webp', $allowed, true)) $labels[] = 'WEBP';
    if (in_array('application/pdf', $allowed, true)) $labels[] = 'PDF';
    $fmt = $labels ? implode(', ', $labels) : 'ชนิดไฟล์ที่กำหนด';
    return [
      'ok' => false,
      'error' => 'รองรับเฉพาะไฟล์ ' . $fmt,
    ];
  }
  if ($mime === 'application/pdf') {
    $fh = @fopen($tmp, 'rb');
    $head = $fh ? (string)fread($fh, 5) : '';
    if ($fh) fclose($fh);
    if (!str_starts_with($head, '%PDF')) {
      return ['ok' => false, 'error' => 'ไฟล์ PDF ไม่ถูกต้อง'];
    }
  } elseif (@getimagesize($tmp) === false) {
    return ['ok' => false, 'error' => 'ไฟล์ไม่ใช่รูปภาพที่ถูกต้อง'];
  }
  $ext = sci_vendor_ext_for_mime($mime);
  if ($ext === '') {
    return ['ok' => false, 'error' => 'ชนิดไฟล์ไม่รองรับ'];
  }

  $fileType = preg_replace('/[^a-z_]/', '', strtolower($fileType)) ?: 'other';
  $name = $fileType . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
  $relKey = 'uploads/' . $eventId . '/' . $roundId . '/' . $applicantId . '/' . $name;
  $original = (string)($file['name'] ?? $name);

  if (!sci_s3_configured()) {
    return ['ok' => false, 'error' => 'ระบบยังไม่ได้ตั้งค่าคลังไฟล์ กรุณาแจ้งเจ้าหน้าที่'];
  }

  $put = sci_s3_put_file($relKey, $tmp, $mime);
  @unlink($tmp);
  if (empty($put['ok'])) {
    return ['ok' => false, 'error' => sci_s3_public_error($put['error'] ?? '')];
  }
  return [
    'ok' => true,
    'path' => $put['stored_path'],
    'mime' => $mime,
    'size' => (int)($put['size'] ?? $size),
    'original' => $original,
  ];
}

function sci_vendor_insert_file_row(int $applicantId, string $fileType, array $stored): void {
  $st = sci_db()->prepare(
    'INSERT INTO applicant_files (applicant_id, file_type, original_name, stored_path, mime_type, size_bytes)
     VALUES (?, ?, ?, ?, ?, ?)'
  );
  $st->execute([
    $applicantId,
    $fileType,
    $stored['original'] ?? '',
    $stored['path'] ?? '',
    $stored['mime'] ?? null,
    (int)($stored['size'] ?? 0),
  ]);
}

/**
 * Submit registration (multipart). Returns applicant summary.
 * @param array $post
 * @param array $files $_FILES
 */
function sci_vendor_submit(array $post, array $files): array {
  $eventCode = trim((string)($post['event'] ?? $post['event_code'] ?? ''));
  $meta = sci_vendor_form_meta(false, $eventCode !== '' ? $eventCode : null);
  if (empty($meta['accepting'])) {
    throw new RuntimeException('ขณะนี้ยังไม่เปิดรับสมัคร หรือปิดรับสมัครแล้ว');
  }

  $roundId = (int)($post['round_id'] ?? 0);
  $round = null;
  foreach ($meta['rounds'] as $r) {
    if ((int)$r['id'] === $roundId) {
      $round = $r;
      break;
    }
  }
  if ($roundId > 0 && !$round) {
    throw new InvalidArgumentException('ไม่พบรอบที่เลือกในกิจกรรมนี้');
  }
  if ($round && empty($round['accepting'])) {
    throw new RuntimeException($round['status_reason'] ?: 'รอบนี้ยังไม่เปิดรับสมัคร หรือปิดรับแล้ว');
  }
  if (!$round) {
    foreach ($meta['rounds'] as $r) {
      if (!empty($r['accepting'])) {
        $round = $r;
        $roundId = (int)$r['id'];
        break;
      }
    }
  }
  if (!$round || empty($round['accepting'])) {
    throw new RuntimeException('ไม่มีรอบที่เปิดรับสมัครในขณะนี้');
  }

  $name = sci_vendor_compose_name($post);
  $phone = sci_vendor_normalize_phone((string)($post['phone'] ?? ''));
  $zone = strtoupper(substr(trim((string)($post['zone'] ?? '')), 0, 1));
  $category = trim((string)($post['category'] ?? ''));
  $detail = trim((string)($post['detail'] ?? ''));
  $qualify = trim((string)($post['qualifications'] ?? ''));
  if ($qualify === 'อื่นๆ') {
    $other = trim((string)($post['qualifications_other'] ?? ''));
    if ($other !== '') $qualify = 'อื่นๆ: ' . $other;
  }

  if ($phone === '' || !preg_match('/^[0-9+]{8,20}$/', $phone)) {
    throw new InvalidArgumentException('กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง');
  }

  $dupCheck = sci_vendor_phone_taken($roundId, $phone, $eventCode !== '' ? $eventCode : null);
  if (!empty($dupCheck['taken'])) {
    throw new InvalidArgumentException((string)$dupCheck['message']);
  }

  $flow = sci_normalize_apply_flow($round['apply_flow'] ?? '');
  $catZones = $meta['category_zones'][$category] ?? [];
  $zoneCodes = array_map(static fn($z) => (string)$z['code'], $meta['zones']);

  if ($flow === 'category_only') {
    if ($category === '' || !$catZones) {
      throw new InvalidArgumentException('กรุณาเลือกประเภทร้านค้า');
    }
    if ($zone === '' || !in_array($zone, $catZones, true)) {
      if (count($catZones) === 1) {
        $zone = (string)$catZones[0];
      } else {
        throw new InvalidArgumentException('ประเภทนี้อยู่ได้หลายโซน กรุณาเลือกโซนร้านค้า');
      }
    }
  } else {
    $zoneOk = in_array($zone, $zoneCodes, true);
    if (!$zoneOk) {
      throw new InvalidArgumentException('กรุณาเลือกโซนร้านค้า');
    }
    $cats = $meta['categories_by_zone'][$zone] ?? [];
    if ($category === '' || !in_array($category, $cats, true)) {
      throw new InvalidArgumentException('กรุณาเลือกประเภทร้านค้าในโซนที่เลือก');
    }
  }
  if ($qualify === '') {
    throw new InvalidArgumentException('กรุณาระบุคุณสมบัติของผู้สมัคร');
  }
  $applyProgram = sci_normalize_apply_program($meta['apply_program'] ?? 'sciweek');
  $allowedQualify = $meta['qualify_options'] ?? [];
  if ($applyProgram === 'scisquare') {
    $qualifyBase = sci_vendor_is_juristic_qualify($qualify) ? 'นิติบุคคล' : 'บุคคลธรรมดา / ผู้ประกอบการทั่วไป';
    if (!in_array($qualifyBase, $allowedQualify, true) && !in_array($qualify, $allowedQualify, true)) {
      throw new InvalidArgumentException('กรุณาเลือกคุณสมบัติให้ถูกต้อง');
    }
  }

  $needHighPower = null;
  $iceBucketCount = null;
  if (!empty($round['ask_high_power'])) {
    $raw = (string)($post['need_high_power'] ?? '');
    if ($raw !== '0' && $raw !== '1') {
      throw new InvalidArgumentException('กรุณาระบุว่ามีความจำเป็นใช้ไฟฟ้ากำลังสูงหรือไม่');
    }
    $needHighPower = (int)$raw;
  }
  if (!empty($round['ask_ice_bucket'])) {
    $needIce = (string)($post['need_ice'] ?? '');
    if ($needIce !== '0' && $needIce !== '1') {
      throw new InvalidArgumentException('กรุณาระบุว่ามีความจำเป็นต้องใช้ถังน้ำแข็งหรือไม่');
    }
    if ($needIce === '1') {
      $iceBucketCount = (int)($post['ice_bucket_count'] ?? 0);
      if ($iceBucketCount < 1 || $iceBucketCount > 50) {
        throw new InvalidArgumentException('กรุณาระบุจำนวนถังน้ำแข็ง (1–50 ถัง)');
      }
    } else {
      $iceBucketCount = 0;
    }
  }

  $docSchema = sci_vendor_doc_schema($applyProgram, $qualify);
  $pendingUploads = []; // list of [fileType, fileArray, allowedMimes]
  $experienceText = null;
  foreach ($docSchema as $field) {
    $key = (string)$field['key'];
    $label = (string)$field['label'];
    $required = !empty($field['required']);
    $kind = (string)($field['kind'] ?? 'file');

    if ($kind === 'text') {
      $postName = (string)($field['name'] ?? $key);
      $text = trim((string)($post[$postName] ?? $post[$key] ?? ''));
      if ($text === '' && $required) {
        throw new InvalidArgumentException('กรุณากรอก' . $label);
      }
      if ($key === 'prop_exp' || $postName === 'experience_text') {
        $experienceText = $text !== '' ? $text : null;
      }
      continue;
    }

    $multiple = !empty($field['multiple']);
    $maxFiles = max(1, (int)($field['max_files'] ?? 1));
    $allowedMimes = $field['accept_mimes'] ?? sci_vendor_image_mimes();

    if ($multiple) {
      $list = sci_vendor_normalize_multi_files($files[$key] ?? null);
      if (!$list) {
        if ($required) {
          throw new InvalidArgumentException('กรุณาอัปโหลด' . $label . 'อย่างน้อย 1 ไฟล์');
        }
        continue;
      }
      if (count($list) > $maxFiles) {
        throw new InvalidArgumentException($label . ' อัปโหลดได้สูงสุด ' . $maxFiles . ' ไฟล์');
      }
      foreach ($list as $ff) {
        $pendingUploads[] = [$key, $ff, $allowedMimes];
      }
    } else {
      $one = $files[$key] ?? null;
      $missing = !is_array($one) || ($one['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE;
      if ($missing) {
        if ($required) {
          throw new InvalidArgumentException('กรุณาอัปโหลด' . $label);
        }
        continue;
      }
      $pendingUploads[] = [$key, $one, $allowedMimes];
    }
  }

  sci_vendor_captcha_verify($post, 'apply', true);

  $eventId = (int)$meta['event']['id'];
  $pdo = sci_db();

  $isReturning = 0;
  $alumniYear = null;
  $alumniSlot = null;
  $alumniCat = null;
  if (function_exists('sci_load_alumni') && function_exists('sci_match_alumni')) {
    require_once __DIR__ . '/xlsx_lib.php';
    $alumni = sci_load_alumni();
    $hit = sci_match_alumni($name, $alumni);
    if ($hit) {
      $isReturning = 1;
      $alumniYear = isset($hit['year']) ? (int)$hit['year'] : 2568;
      $alumniSlot = $hit['slot'] ?? null;
      $alumniCat = $hit['category'] ?? null;
    }
  }

  $legacyRow = sci_vendor_next_legacy_row($eventId, $roundId);
  $pdo->beginTransaction();
  try {
    $ins = $pdo->prepare(
      'INSERT INTO applicants (
         event_id, round_id, legacy_excel_row, applied_at, name, phone, zone_code, category, detail, qualifications,
         need_high_power, ice_bucket_count, experience_text,
         doc_status, selection, payment_status, is_returning, alumni_year, alumni_slot, alumni_category
       ) VALUES (
         ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?,
         ?, ?, ?,
         ?, ?, ?, ?, ?, ?, ?
       )'
    );
    $ins->execute([
      $eventId,
      $roundId,
      $legacyRow,
      $name,
      $phone,
      $zone,
      $category,
      $detail !== '' ? $detail : null,
      $qualify,
      $needHighPower,
      $iceBucketCount,
      $experienceText,
      'รอตรวจสอบ',
      'รอพิจารณา',
      'unpaid',
      $isReturning,
      $alumniYear,
      $alumniSlot,
      $alumniCat,
    ]);
    $applicantId = (int)$pdo->lastInsertId();

    foreach ($pendingUploads as [$type, $fileArr, $allowedMimes]) {
      $stored = sci_vendor_store_upload($fileArr, $type, $eventId, $roundId, $applicantId, $allowedMimes);
      if (empty($stored['ok'])) {
        throw new RuntimeException($stored['error'] ?? 'อัปโหลดไม่สำเร็จ');
      }
      sci_vendor_insert_file_row($applicantId, $type, $stored);
    }

    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }

  sci_db_clear_runtime_caches();

  return [
    'applicant_id' => $applicantId,
    'ref' => 'A' . $applicantId,
    'name' => $name,
    'phone' => $phone,
    'zone' => $zone,
    'category' => $category,
    'round' => [
      'id' => $roundId,
      'round_no' => (int)$round['round_no'],
      'title' => (string)$round['title'],
    ],
    'event' => $meta['event'],
    'apply_program' => $applyProgram,
    'is_returning' => (bool)$isReturning,
    'selection' => 'รอพิจารณา',
    'doc_status' => 'รอตรวจสอบ',
  ];
}

/** Normalize $_FILES['food'] single or multi into list of file arrays. */
function sci_vendor_normalize_multi_files($food): array {
  if (!is_array($food) || !isset($food['error'])) return [];
  $out = [];
  if (is_array($food['error'])) {
    $n = count($food['error']);
    for ($i = 0; $i < $n; $i++) {
      if (($food['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
      $out[] = [
        'name' => $food['name'][$i] ?? '',
        'type' => $food['type'][$i] ?? '',
        'tmp_name' => $food['tmp_name'][$i] ?? '',
        'error' => $food['error'][$i],
        'size' => $food['size'][$i] ?? 0,
      ];
    }
  } else {
    if (($food['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
      $out[] = $food;
    }
  }
  return $out;
}

/**
 * Public status lookup by phone (+ optional name) within one event.
 */
function sci_vendor_status_lookup(string $phone, string $name = '', ?string $eventCode = null): array {
  $phone = sci_vendor_normalize_phone($phone);
  $name = trim($name);
  if ($phone === '') {
    throw new InvalidArgumentException('กรุณากรอกเบอร์โทรศัพท์');
  }
  $event = sci_vendor_resolve_event($eventCode);
  $sql = "SELECT a.id, a.name, a.phone, a.zone_code, a.category, a.selection, a.doc_status,
                 a.assigned_slot_id, a.payment_status, a.applied_at, a.is_returning,
                 er.round_no, er.title AS round_title, s.code AS slot_code
          FROM applicants a
          JOIN event_rounds er ON er.id = a.round_id
          LEFT JOIN slots s ON s.id = a.assigned_slot_id
          WHERE a.event_id = ? AND REPLACE(REPLACE(a.phone, '-', ''), ' ', '') = ?";
  $params = [(int)$event['id'], $phone];
  if ($name !== '') {
    $sql .= ' AND a.name LIKE ?';
    $params[] = '%' . $name . '%';
  }
  $sql .= ' ORDER BY a.applied_at DESC LIMIT 20';
  $st = sci_db()->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll();
  return [
    'event' => [
      'id' => (int)$event['id'],
      'code' => (string)$event['code'],
      'title' => $event['title'],
      'year_be' => (int)$event['year_be'],
    ],
    'count' => count($rows),
    'items' => array_map(static function ($r) {
      return [
        'ref' => 'A' . $r['id'],
        'name' => $r['name'],
        'phone' => $r['phone'],
        'zone' => $r['zone_code'],
        'category' => $r['category'],
        'round_no' => (int)$r['round_no'],
        'round_title' => $r['round_title'],
        'applied_at' => $r['applied_at'],
        'doc_status' => $r['doc_status'],
        'selection' => $r['selection'],
        'assigned_slot' => $r['slot_code'],
        'payment_status' => $r['payment_status'],
        'is_returning' => (int)$r['is_returning'] === 1,
      ];
    }, $rows),
  ];
}

function sci_vendor_absolute_path(string $storedPath): ?string {
  $storedPath = str_replace(['\\', "\0"], ['/', ''], $storedPath);
  $storedPath = ltrim($storedPath, '/');
  if ($storedPath === '' || str_contains($storedPath, '..')) return null;
  if (!str_starts_with($storedPath, 'uploads/')) return null;
  $full = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storedPath);
  $real = realpath($full);
  $root = realpath(sci_vendor_upload_root());
  if ($real === false || $root === false) return null;
  if (!str_starts_with($real, $root)) return null;
  return $real;
}
