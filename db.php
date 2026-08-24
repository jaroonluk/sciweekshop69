<?php
/**
 * PDO connection to localhost database `sciweekshop`.
 * Optional read-only helpers for eoffice.tbluser on the same MySQL server.
 */

// Application times (apply windows, display) are Asia/Bangkok.
// Without this, PHP strtotime() uses php.ini TZ and mis-judges open/close.
if (function_exists('date_default_timezone_set')) {
  date_default_timezone_set('Asia/Bangkok');
}

function sci_db_config(): array {
  static $cfg = null;
  if ($cfg !== null) return $cfg;
  $path = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'db_config.php';
  if (is_file($path)) {
    $loaded = require $path;
    if (is_array($loaded)) {
      $cfg = array_merge(sci_db_default_config(), $loaded);
      return $cfg;
    }
  }
  $cfg = sci_db_default_config();
  return $cfg;
}

function sci_db_default_config(): array {
  return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'dbname' => 'sciweekshop',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4',
    'eoffice_db' => 'eoffice',
  ];
}

function sci_db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;
  $c = sci_db_config();
  $dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $c['host'],
    (int)$c['port'],
    $c['dbname'],
    $c['charset']
  );
  $pdo = new PDO($dsn, $c['user'], $c['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
  $pdo->exec("SET time_zone = '+07:00'");
  sci_db_ensure_schema_patches($pdo);
  return $pdo;
}

/** Add missing columns used by newer features without a full re-import. */
function sci_db_ensure_schema_patches(PDO $pdo): void {
  static $done = false;
  if ($done) return;
  $done = true;
  $patches = [
    ['event_rounds', 'ask_high_power', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ถามการใช้ไฟฟ้ากำลังสูงในใบสมัคร'"],
    ['event_rounds', 'ask_ice_bucket', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ถามการใช้ถังน้ำแข็งในใบสมัคร'"],
    ['applicants', 'need_high_power', "TINYINT(1) NULL COMMENT '1=ใช้ไฟฟ้ากำลังสูง 0=ไม่ใช้ NULL=ไม่ถาม'"],
    ['applicants', 'ice_bucket_count', "SMALLINT UNSIGNED NULL COMMENT 'จำนวนถังน้ำแข็ง NULL=ไม่ถาม 0=ไม่ใช้'"],
    ['event_rounds', 'apply_flow', "VARCHAR(32) NOT NULL DEFAULT 'zone_then_category' COMMENT 'zone_then_category | category_only'"],
  ];
  foreach ($patches as [$table, $col, $ddl]) {
    $st = $pdo->prepare(
      'SELECT COUNT(*) FROM information_schema.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$table, $col]);
    if ((int)$st->fetchColumn() === 0) {
      $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $col . '` ' . $ddl);
    }
  }
}

function sci_normalize_apply_flow($value): string {
  return trim((string)$value) === 'category_only' ? 'category_only' : 'zone_then_category';
}

/**
 * List active staff from eoffice for admin user picker.
 * @return list<array{username:string,title:string,fname:string,lname:string,email:?string,department_id:int}>
 */
function sci_eoffice_list_personnel(?string $q = null, int $limit = 50): array {
  $c = sci_db_config();
  $db = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$c['eoffice_db']);
  $pdo = sci_db();
  $limit = max(1, min(200, $limit));
  $sql = "SELECT u.username, COALESCE(t.title_name_s, u.title, '') AS title,
                 u.fname, u.lname, u.email, u.department_id
          FROM `{$db}`.tbluser u
          LEFT JOIN `{$db}`.tbltitle t ON t.title_id = CAST(u.title AS UNSIGNED)
          WHERE (u.stat_flag IS NULL OR u.stat_flag = 0)";
  $params = [];
  if ($q !== null && trim($q) !== '') {
    $sql .= " AND (u.fname LIKE ? OR u.lname LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $like = '%' . trim($q) . '%';
    $params = [$like, $like, $like, $like];
  }
  $sql .= " ORDER BY u.fname, u.lname LIMIT {$limit}";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchAll();
}
