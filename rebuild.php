<?php
require_once __DIR__ . '/xlsx_lib.php';
require_once __DIR__ . '/auth_lib.php';

$roundArg = $_GET['round'] ?? null;
if (PHP_SAPI === 'cli' && isset($argv[1])) {
  $roundArg = $argv[1];
} elseif (PHP_SAPI !== 'cli') {
  sci_auth_require_login(false);
}

sci_set_round($roundArg ?? 1);
$data = sci_parse_applicants();
sci_save_payload_json($data);
$round = sci_round_meta();
echo "OK: {$round['title']} — {$data['total_applicants']} from {$data['source']}\n";
