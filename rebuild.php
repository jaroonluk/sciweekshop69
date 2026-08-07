<?php
require_once __DIR__ . '/xlsx_lib.php';
$data = sci_parse_applicants();
sci_save_payload_json($data);
echo "OK: {$data['total_applicants']} from {$data['source']}\n";
