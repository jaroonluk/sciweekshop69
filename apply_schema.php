<?php
/**
 * Apply sql/001_schema.sql via mysqli multi_query.
 * Usage: php apply_schema.php
 */
mb_internal_encoding('UTF-8');

$path = __DIR__ . '/sql/001_schema.sql';
$sql = file_get_contents($path);
if ($sql === false) {
  fwrite(STDERR, "Cannot read {$path}\n");
  exit(1);
}
if (strncmp($sql, "\xEF\xBB\xBF", 3) === 0) {
  $sql = substr($sql, 3);
}

$configPath = __DIR__ . '/data/db_config.php';
$c = is_file($configPath) ? require $configPath : [
  'host' => '127.0.0.1',
  'port' => 3306,
  'user' => 'root',
  'pass' => '',
];

$mysqli = mysqli_init();
if (!$mysqli->real_connect($c['host'], $c['user'], $c['pass'], null, (int)($c['port'] ?? 3306))) {
  fwrite(STDERR, 'Connect failed: ' . mysqli_connect_error() . "\n");
  exit(1);
}
$mysqli->set_charset('utf8mb4');
$mysqli->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

if (!$mysqli->multi_query($sql)) {
  fwrite(STDERR, 'multi_query failed: ' . $mysqli->error . "\n");
  exit(1);
}
$n = 0;
do {
  $n++;
  if ($result = $mysqli->store_result()) {
    $result->free();
  }
  if ($mysqli->errno) {
    fwrite(STDERR, "Error after stmt {$n}: {$mysqli->error}\n");
    exit(1);
  }
} while ($mysqli->more_results() && $mysqli->next_result());

echo "Schema applied OK ({$n} result sets)\n";
$mysqli->close();
