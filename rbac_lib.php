<?php
/**
 * Role-based access for SCI Shop (admin / committee / finance / vendor).
 * Staff accounts are stored in sciweekshop.users and linked to eoffice.tbluser.
 *
 * Access policy: only users granted by admin may sign in.
 * committee ≈ admin except user ACL + audit log viewing.
 */
require_once __DIR__ . '/db.php';

function sci_rbac_staff_role_ids(): array {
  return [1, 2, 4]; // admin, finance, committee
}

function sci_rbac_operator_role_codes(): array {
  return ['admin', 'committee'];
}

function sci_rbac_access_role_codes(): array {
  return ['admin', 'committee', 'finance'];
}

function sci_rbac_role_code_to_id(string $code): ?int {
  $map = [
    'admin' => 1,
    'finance' => 2,
    'vendor' => 3,
    'committee' => 4,
  ];
  $code = strtolower(trim($code));
  return $map[$code] ?? null;
}

function sci_rbac_role_id_to_code(int $id): string {
  return [
    1 => 'admin',
    2 => 'finance',
    3 => 'vendor',
    4 => 'committee',
  ][$id] ?? '';
}

function sci_rbac_role_label(string $code): string {
  return [
    'admin' => 'ผู้ดูแลระบบ',
    'finance' => 'เจ้าหน้าที่การเงิน',
    'vendor' => 'พ่อค้าแม่ค้า',
    'committee' => 'กรรมการฝ่ายจัดหารายได้',
  ][$code] ?? $code;
}

function sci_rbac_db_ready(): bool {
  try {
    sci_db()->query('SELECT 1 FROM users LIMIT 1');
    return true;
  } catch (Throwable $e) {
    return false;
  }
}

/** Ensure role rows exist (idempotent). */
function sci_rbac_ensure_roles(): void {
  static $done = false;
  if ($done) return;
  try {
    $pdo = sci_db();
    $pdo->exec(
      "INSERT INTO roles (id, code, name_th) VALUES
        (1, 'admin', 'ผู้ดูแลระบบ'),
        (2, 'finance', 'เจ้าหน้าที่การเงิน'),
        (3, 'vendor', 'พ่อค้าแม่ค้า / ผู้สมัคร'),
        (4, 'committee', 'กรรมการฝ่ายจัดหารายได้')
       ON DUPLICATE KEY UPDATE name_th = VALUES(name_th)"
    );
    $done = true;
  } catch (Throwable $e) {
    // ignore — caller checks readiness
  }
}

/** Active staff users with a real email. */
function sci_rbac_staff_count(): int {
  $ids = implode(',', sci_rbac_staff_role_ids());
  $st = sci_db()->query(
    "SELECT COUNT(*) FROM users WHERE is_active = 1 AND role_id IN ({$ids})
     AND email IS NOT NULL AND email <> '' AND email NOT LIKE '%@localhost'"
  );
  return (int)$st->fetchColumn();
}

/** Active admin accounts with a real email (excludes seed @localhost). */
function sci_rbac_admin_count(): int {
  $st = sci_db()->query(
    'SELECT COUNT(*) FROM users WHERE is_active = 1 AND role_id = 1
     AND email IS NOT NULL AND email <> "" AND email NOT LIKE "%@localhost"'
  );
  return (int)$st->fetchColumn();
}

function sci_rbac_find_staff_by_email(string $email): ?array {
  $email = strtolower(trim($email));
  if ($email === '') return null;
  $st = sci_db()->prepare(
    'SELECT u.id, u.role_id, u.eoffice_username, u.email, u.display_name, u.google_sub, u.is_active,
            r.code AS role_code, r.name_th AS role_name
     FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE LOWER(u.email) = ? AND u.is_active = 1
     LIMIT 1'
  );
  $st->execute([$email]);
  $row = $st->fetch();
  return $row ?: null;
}

function sci_rbac_find_staff_by_google_sub(string $sub): ?array {
  $sub = trim($sub);
  if ($sub === '') return null;
  $st = sci_db()->prepare(
    'SELECT u.id, u.role_id, u.eoffice_username, u.email, u.display_name, u.google_sub, u.is_active,
            r.code AS role_code, r.name_th AS role_name
     FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE u.google_sub = ? AND u.is_active = 1
     LIMIT 1'
  );
  $st->execute([$sub]);
  $row = $st->fetch();
  return $row ?: null;
}

function sci_rbac_touch_login(int $userId, string $googleSub, string $displayName, string $email): void {
  $st = sci_db()->prepare(
    'UPDATE users SET google_sub = COALESCE(NULLIF(?, ""), google_sub),
            display_name = IF(? <> "", ?, display_name),
            email = IF(? <> "", ?, email),
            last_login_at = NOW(),
            updated_at = NOW()
     WHERE id = ?'
  );
  $st->execute([
    $googleSub,
    $displayName, $displayName,
    $email, $email,
    $userId,
  ]);
}

/**
 * Emergency bootstrap: only when email is in auth allowed_emails.
 * Normal access requires an admin to grant the account first.
 */
function sci_rbac_bootstrap_admin(string $email, string $name, string $googleSub): array {
  $email = strtolower(trim($email));
  $pdo = sci_db();
  $existing = sci_rbac_find_staff_by_email($email);
  if ($existing) {
    sci_rbac_touch_login((int)$existing['id'], $googleSub, $name, $email);
    return sci_rbac_find_staff_by_email($email) ?: $existing;
  }

  $st = $pdo->prepare(
    'INSERT INTO users (role_id, email, display_name, google_sub, is_active, last_login_at)
     VALUES (1, ?, ?, ?, 1, NOW())'
  );
  $st->execute([$email, $name !== '' ? $name : $email, $googleSub !== '' ? $googleSub : null]);
  $id = (int)$pdo->lastInsertId();
  sci_rbac_audit(null, 'bootstrap_admin', 'users', $id, ['email' => $email]);
  return sci_rbac_find_staff_by_email($email) ?: [
    'id' => $id,
    'role_id' => 1,
    'role_code' => 'admin',
    'role_name' => 'ผู้ดูแลระบบ',
    'email' => $email,
    'display_name' => $name,
    'eoffice_username' => null,
    'google_sub' => $googleSub,
    'is_active' => 1,
  ];
}

/**
 * Resolve staff row after Google OAuth. Throws RuntimeException with known codes.
 * @return array staff row + role_code
 */
function sci_rbac_resolve_login(string $email, string $name, string $googleSub): array {
  $email = strtolower(trim($email));
  sci_rbac_ensure_roles();

  if (!sci_rbac_db_ready()) {
    // Legacy Excel-only mode without DB — treat as admin
    return [
      'id' => 0,
      'role_id' => 1,
      'role_code' => 'admin',
      'role_name' => 'ผู้ดูแลระบบ',
      'email' => $email,
      'display_name' => $name,
      'eoffice_username' => null,
      'legacy' => true,
    ];
  }

  $row = null;
  if ($googleSub !== '') {
    $row = sci_rbac_find_staff_by_google_sub($googleSub);
  }
  if (!$row) {
    $row = sci_rbac_find_staff_by_email($email);
  }

  if ($row) {
    $code = (string)($row['role_code'] ?? '');
    if (!in_array($code, sci_rbac_access_role_codes(), true)) {
      throw new RuntimeException('no_role');
    }
    sci_rbac_touch_login((int)$row['id'], $googleSub, $name, $email);
    sci_rbac_audit((int)$row['id'], 'login', 'users', (int)$row['id'], [
      'email' => $email,
      'role' => $code,
    ]);
    $row['role_code'] = $code;
    return $row;
  }

  // Only explicit allowlist may bootstrap (emergency). Everyone else must be granted by admin.
  $cfg = function_exists('sci_auth_config') ? sci_auth_config() : [];
  $allowedEmails = (array)($cfg['allowed_emails'] ?? []);
  $inAllowlist = in_array($email, $allowedEmails, true);
  if ($inAllowlist) {
    return sci_rbac_bootstrap_admin($email, $name, $googleSub);
  }

  throw new RuntimeException('no_role');
}

function sci_rbac_session_payload(array $staff, array $googleUser): array {
  $code = (string)($staff['role_code'] ?? sci_rbac_role_id_to_code((int)($staff['role_id'] ?? 0)));
  return [
    'email' => (string)($googleUser['email'] ?? $staff['email'] ?? ''),
    'name' => (string)($googleUser['name'] ?? $staff['display_name'] ?? ''),
    'picture' => (string)($googleUser['picture'] ?? ''),
    'sub' => (string)($googleUser['sub'] ?? $staff['google_sub'] ?? ''),
    'user_id' => (int)($staff['id'] ?? 0),
    'role' => $code,
    'role_name' => (string)($staff['role_name'] ?? sci_rbac_role_label($code)),
    'eoffice_username' => $staff['eoffice_username'] ?? null,
    'login_at' => date('c'),
  ];
}

function sci_rbac_user(): ?array {
  $u = sci_auth_user();
  if (!$u) return null;
  // Rehydrate role for sessions created before RBAC
  if (empty($u['role']) && sci_rbac_db_ready()) {
    try {
      $staff = sci_rbac_resolve_login(
        (string)($u['email'] ?? ''),
        (string)($u['name'] ?? ''),
        (string)($u['sub'] ?? '')
      );
      $payload = sci_rbac_session_payload($staff, $u);
      sci_auth_set_user($payload);
      $u = $payload;
    } catch (Throwable $e) {
      return null;
    }
  }
  if (empty($u['role'])) {
    return null;
  }
  if (!in_array((string)$u['role'], sci_rbac_access_role_codes(), true)) {
    return null;
  }
  return $u;
}

function sci_rbac_role(): string {
  $u = sci_rbac_user();
  return (string)($u['role'] ?? '');
}

function sci_rbac_is_admin(): bool {
  return sci_rbac_role() === 'admin';
}

function sci_rbac_is_committee(): bool {
  return sci_rbac_role() === 'committee';
}

function sci_rbac_is_finance(): bool {
  return sci_rbac_role() === 'finance';
}

/** Admin or committee — full operational access except ACL/logs. */
function sci_rbac_is_operator(): bool {
  return in_array(sci_rbac_role(), sci_rbac_operator_role_codes(), true);
}

function sci_rbac_is_staff(): bool {
  return in_array(sci_rbac_role(), sci_rbac_access_role_codes(), true);
}

/**
 * @param list<string> $roles
 */
function sci_rbac_require_roles(array $roles, bool $json = true): void {
  sci_auth_require_login($json);
  $role = sci_rbac_role();
  if (!in_array($role, $roles, true)) {
    if ($json) {
      http_response_code(403);
      header('Content-Type: application/json; charset=utf-8');
      header('Cache-Control: no-store');
      echo json_encode([
        'ok' => false,
        'error' => 'ไม่มีสิทธิ์ทำรายการนี้',
        'role' => $role,
      ], JSON_UNESCAPED_UNICODE);
      exit;
    }
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'ไม่มีสิทธิ์เข้าใช้งานส่วนนี้';
    exit;
  }
}

function sci_rbac_public_user(): array {
  $u = sci_rbac_user() ?: [];
  return [
    'email' => (string)($u['email'] ?? ''),
    'name' => (string)($u['name'] ?? ''),
    'picture' => (string)($u['picture'] ?? ''),
    'user_id' => (int)($u['user_id'] ?? 0),
    'role' => (string)($u['role'] ?? ''),
    'role_name' => (string)($u['role_name'] ?? ''),
    'eoffice_username' => $u['eoffice_username'] ?? null,
  ];
}

function sci_rbac_audit(?int $actorUserId, string $action, ?string $entityType = null, ?int $entityId = null, $detail = null): void {
  if (!sci_rbac_db_ready()) return;
  try {
    $st = sci_db()->prepare(
      'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, detail_json, ip_address)
       VALUES (?, ?, ?, ?, ?, ?)'
    );
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $json = $detail === null ? null : json_encode($detail, JSON_UNESCAPED_UNICODE);
    $st->execute([
      $actorUserId,
      $action,
      $entityType,
      $entityId,
      $json,
      $ip !== '' ? $ip : null,
    ]);
  } catch (Throwable $e) {
    // never block main flow
  }
}

/** @return list<array> */
function sci_rbac_list_users(): array {
  $ids = implode(',', sci_rbac_staff_role_ids());
  $st = sci_db()->query(
    "SELECT u.id, u.role_id, u.eoffice_username, u.email, u.display_name, u.google_sub,
            u.is_active, u.last_login_at, u.created_at, u.updated_at,
            r.code AS role_code, r.name_th AS role_name
     FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE u.role_id IN ({$ids})
     ORDER BY u.is_active DESC, r.id ASC, u.display_name ASC, u.email ASC"
  );
  return $st->fetchAll();
}

/**
 * @return list<array>
 */
function sci_rbac_list_audit_logs(int $limit = 100, int $offset = 0): array {
  $limit = max(1, min(500, $limit));
  $offset = max(0, $offset);
  $st = sci_db()->prepare(
    "SELECT a.id, a.user_id, a.action, a.entity_type, a.entity_id, a.detail_json,
            a.ip_address, a.created_at,
            u.display_name, u.email, r.name_th AS role_name, r.code AS role_code
     FROM audit_logs a
     LEFT JOIN users u ON u.id = a.user_id
     LEFT JOIN roles r ON r.id = u.role_id
     ORDER BY a.id DESC
     LIMIT {$limit} OFFSET {$offset}"
  );
  $st->execute();
  return $st->fetchAll();
}

function sci_rbac_action_label(string $action): string {
  $map = [
    'login' => 'เข้าสู่ระบบ',
    'bootstrap_admin' => 'สร้างผู้ดูแลระบบเริ่มต้น',
    'user_create' => 'เพิ่มผู้ใช้งาน',
    'user_update' => 'แก้ไขผู้ใช้งาน',
    'user_activate' => 'เปิดสิทธิ์ผู้ใช้',
    'user_deactivate' => 'ปิดสิทธิ์ผู้ใช้',
    'user_set_role' => 'เปลี่ยนสิทธิ์ผู้ใช้',
  ];
  return $map[$action] ?? $action;
}

/**
 * Upsert staff user from eoffice personnel pick.
 * @return array{ok:bool,user?:array,error?:string}
 */
function sci_rbac_upsert_from_eoffice(string $username, string $roleCode, ?int $actorUserId = null): array {
  $roleId = sci_rbac_role_code_to_id($roleCode);
  if ($roleId === null || !in_array($roleId, sci_rbac_staff_role_ids(), true)) {
    return ['ok' => false, 'error' => 'บทบาทไม่ถูกต้อง (ใช้ได้เฉพาะ ผู้ดูแลระบบ / กรรมการ / การเงิน)'];
  }

  $username = trim($username);
  if ($username === '') {
    return ['ok' => false, 'error' => 'ต้องระบุ username จาก eoffice'];
  }

  $people = sci_eoffice_list_personnel($username, 20);
  $person = null;
  foreach ($people as $p) {
    if ((string)($p['username'] ?? '') === $username) {
      $person = $p;
      break;
    }
  }
  if (!$person) {
    $c = sci_db_config();
    $db = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$c['eoffice_db']);
    $st = sci_db()->prepare(
      "SELECT u.username, COALESCE(t.title_name_s, u.title, '') AS title,
              u.fname, u.lname, u.email, u.department_id
       FROM `{$db}`.tbluser u
       LEFT JOIN `{$db}`.tbltitle t ON t.title_id = CAST(u.title AS UNSIGNED)
       WHERE u.username = ? AND (u.stat_flag IS NULL OR u.stat_flag = 0)
       LIMIT 1"
    );
    $st->execute([$username]);
    $person = $st->fetch() ?: null;
  }
  if (!$person) {
    return ['ok' => false, 'error' => 'ไม่พบบุคลากรใน eoffice'];
  }

  $email = strtolower(trim((string)($person['email'] ?? '')));
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return ['ok' => false, 'error' => 'บุคลากรคนนี้ไม่มีอีเมลใน eoffice — ไม่สามารถเข้าสู่ระบบด้วย Google ได้'];
  }

  $title = trim((string)($person['title'] ?? ''));
  $fname = trim((string)($person['fname'] ?? ''));
  $lname = trim((string)($person['lname'] ?? ''));
  $display = trim($title . $fname);
  if ($display !== '' && $lname !== '') {
    $display .= '  ' . $lname;
  } elseif ($lname !== '') {
    $display = $lname;
  }
  if ($display === '') $display = $email;

  $pdo = sci_db();
  $find = $pdo->prepare(
    'SELECT id FROM users WHERE eoffice_username = ? OR LOWER(email) = ? LIMIT 1'
  );
  $find->execute([$username, $email]);
  $existingId = $find->fetchColumn();

  if ($existingId) {
    $upd = $pdo->prepare(
      'UPDATE users SET role_id = ?, eoffice_username = ?, email = ?, display_name = ?,
              is_active = 1, updated_at = NOW()
       WHERE id = ?'
    );
    $upd->execute([$roleId, $username, $email, $display, (int)$existingId]);
    $id = (int)$existingId;
    sci_rbac_audit($actorUserId, 'user_update', 'users', $id, [
      'eoffice_username' => $username,
      'role' => $roleCode,
      'email' => $email,
    ]);
  } else {
    $ins = $pdo->prepare(
      'INSERT INTO users (role_id, eoffice_username, email, display_name, is_active)
       VALUES (?, ?, ?, ?, 1)'
    );
    $ins->execute([$roleId, $username, $email, $display]);
    $id = (int)$pdo->lastInsertId();
    sci_rbac_audit($actorUserId, 'user_create', 'users', $id, [
      'eoffice_username' => $username,
      'role' => $roleCode,
      'email' => $email,
    ]);
  }

  $st = $pdo->prepare(
    'SELECT u.id, u.role_id, u.eoffice_username, u.email, u.display_name, u.google_sub,
            u.is_active, u.last_login_at, u.created_at, u.updated_at,
            r.code AS role_code, r.name_th AS role_name
     FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?'
  );
  $st->execute([$id]);
  return ['ok' => true, 'user' => $st->fetch()];
}

function sci_rbac_set_user_active(int $userId, bool $active, ?int $actorUserId = null): array {
  if ($userId <= 0) return ['ok' => false, 'error' => 'user id ไม่ถูกต้อง'];
  if ($actorUserId && $actorUserId === $userId && !$active) {
    return ['ok' => false, 'error' => 'ไม่สามารถปิดสิทธิ์บัญชีของตนเองได้'];
  }
  $ids = implode(',', sci_rbac_staff_role_ids());
  $st = sci_db()->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ? AND role_id IN ({$ids})");
  $st->execute([$active ? 1 : 0, $userId]);
  if ($st->rowCount() === 0) {
    $chk = sci_db()->prepare("SELECT id FROM users WHERE id = ? AND role_id IN ({$ids})");
    $chk->execute([$userId]);
    if (!$chk->fetch()) return ['ok' => false, 'error' => 'ไม่พบผู้ใช้'];
  }
  sci_rbac_audit($actorUserId, $active ? 'user_activate' : 'user_deactivate', 'users', $userId, null);
  return ['ok' => true];
}

function sci_rbac_set_user_role(int $userId, string $roleCode, ?int $actorUserId = null): array {
  $roleId = sci_rbac_role_code_to_id($roleCode);
  if ($roleId === null || !in_array($roleId, sci_rbac_staff_role_ids(), true)) {
    return ['ok' => false, 'error' => 'บทบาทไม่ถูกต้อง'];
  }
  if ($actorUserId && $actorUserId === $userId && $roleCode !== 'admin') {
    return ['ok' => false, 'error' => 'ไม่สามารถลดสิทธิ์บัญชีของตนเองได้'];
  }
  $ids = implode(',', sci_rbac_staff_role_ids());
  $st = sci_db()->prepare("UPDATE users SET role_id = ?, updated_at = NOW() WHERE id = ? AND role_id IN ({$ids})");
  $st->execute([$roleId, $userId]);
  $chk = sci_db()->prepare(
    'SELECT u.id, r.code AS role_code FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?'
  );
  $chk->execute([$userId]);
  $row = $chk->fetch();
  if (!$row) return ['ok' => false, 'error' => 'ไม่พบผู้ใช้'];
  sci_rbac_audit($actorUserId, 'user_set_role', 'users', $userId, ['role' => $roleCode]);
  return ['ok' => true, 'role_code' => $row['role_code']];
}
