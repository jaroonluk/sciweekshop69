<?php
/**
 * PDO connection to localhost database `sciweekshop`.
 * Optional read-only helpers for eoffice.tbluser on the same MySQL server.
 */

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
  return $pdo;
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
