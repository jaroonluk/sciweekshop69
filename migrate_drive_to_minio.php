<?php
/**
 * Download Google Drive files referenced by Excel imports into MinIO bucket sci-shop.
 *
 * Sources:
 *  - applicant_files rows with drive_url (from แบบตอบรับ.xlsx / รอบ2 / รอบ3)
 *  - optional re-scan of the three Excel files if --from-xlsx
 *
 * Usage:
 *   C:\xampp\php\php.exe migrate_drive_to_minio.php
 *   C:\xampp\php\php.exe migrate_drive_to_minio.php --limit=50
 *   C:\xampp\php\php.exe migrate_drive_to_minio.php --dry-run
 *   C:\xampp\php\php.exe migrate_drive_to_minio.php --from-xlsx
 *   C:\xampp\php\php.exe migrate_drive_to_minio.php --local-too   # also push data/uploads/* to MinIO
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/xlsx_lib.php';
require_once __DIR__ . '/db_data_lib.php';
require_once __DIR__ . '/s3_lib.php';
require_once __DIR__ . '/vendor_apply_lib.php';
require_once __DIR__ . '/drive_lib.php';

function mig_log(string $msg): void {
  echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
}

function mig_args(array $argv): array {
  $out = ['limit' => 0, 'dry_run' => false, 'from_xlsx' => false, 'local_too' => false, 'sleep_ms' => 150, 'reset_bad' => false, 'force_anonymous' => false];
  foreach (array_slice($argv, 1) as $a) {
    if ($a === '--dry-run') $out['dry_run'] = true;
    elseif ($a === '--from-xlsx') $out['from_xlsx'] = true;
    elseif ($a === '--local-too') $out['local_too'] = true;
    elseif ($a === '--reset-bad') $out['reset_bad'] = true;
    elseif ($a === '--force-anonymous') $out['force_anonymous'] = true;
    elseif (preg_match('/^--limit=(\d+)$/', $a, $m)) $out['limit'] = (int)$m[1];
    elseif (preg_match('/^--sleep-ms=(\d+)$/', $a, $m)) $out['sleep_ms'] = (int)$m[1];
  }
  return $out;
}

/**
 * Download a Google Drive file by id. Prefers Drive API (OAuth); anonymous uc? is fallback only.
 * @return array{ok:bool,path?:string,mime?:string,size?:int,error?:string,name?:string}
 */
function sci_drive_download_file(string $driveId): array {
  if ($driveId === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $driveId)) {
    return ['ok' => false, 'error' => 'drive id ไม่ถูกต้อง'];
  }

  $cfg = sci_drive_migrate_config();
  if (trim((string)$cfg['refresh_token']) !== '') {
    return sci_drive_api_download($driveId);
  }

  if (!extension_loaded('curl')) {
    return ['ok' => false, 'error' => 'ต้องการ curl'];
  }

  $cookie = tempnam(sys_get_temp_dir(), 'gdcookie');
  $url = 'https://drive.google.com/uc?export=download&id=' . rawurlencode($driveId) . '&confirm=t';
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 8,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; sci_shop-migrate/1.0)',
    CURLOPT_COOKIEJAR => $cookie,
    CURLOPT_COOKIEFILE => $cookie,
    CURLOPT_HEADER => true,
  ]);
  $raw = curl_exec($ch);
  $err = curl_error($ch);
  $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  @unlink($cookie);

  if ($raw === false) {
    return ['ok' => false, 'error' => 'curl: ' . $err];
  }

  $headers = substr($raw, 0, $headerSize);
  $body = substr($raw, $headerSize);

  if ($status < 200 || $status >= 400 || $body === '') {
    return ['ok' => false, 'error' => 'ดาวน์โหลด Drive ไม่สำเร็จ (HTTP ' . $status . ')'];
  }
  if (sci_drive_body_looks_like_html($body)) {
    return [
      'ok' => false,
      'error' => 'ไฟล์บน Drive เป็นแบบ private — รัน migrate_drive_auth.php ด้วยบัญชีเจ้าของ Form/Drive ก่อน',
    ];
  }

  $mime = 'application/octet-stream';
  if (preg_match('/^content-type:\s*([^\r\n;]+)/mi', $headers, $m)) {
    $mime = strtolower(trim($m[1]));
  }
  $filename = '';
  if (preg_match('/filename\*=UTF-8\'\'([^;\r\n]+)/i', $headers, $m)) {
    $filename = urldecode(trim($m[1]));
  } elseif (preg_match('/filename="([^"]+)"/i', $headers, $m)) {
    $filename = $m[1];
  }

  $tmp = tempnam(sys_get_temp_dir(), 'gdrv');
  if ($tmp === false || file_put_contents($tmp, $body) === false) {
    return ['ok' => false, 'error' => 'เขียนไฟล์ชั่วคราวไม่สำเร็จ'];
  }
  $mime = sci_drive_sniff_mime($tmp) ?: $mime;

  return [
    'ok' => true,
    'path' => $tmp,
    'mime' => $mime,
    'size' => strlen($body),
    'name' => $filename,
  ];
}

function sci_migrate_ext_for_mime(string $mime): string {
  return match ($mime) {
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'application/pdf' => 'pdf',
    default => 'bin',
  };
}

/** Reset rows that accidentally stored HTML login pages as objects. */
function sci_migrate_reset_html_objects(bool $dryRun): int {
  $st = sci_db()->query(
    "SELECT id, stored_path, mime_type FROM applicant_files
     WHERE stored_path LIKE 's3://%' AND (mime_type = 'text/html' OR stored_path LIKE '%.bin')"
  );
  $n = 0;
  foreach ($st->fetchAll() as $row) {
    // Only reset the known bad probe set: text/html or we verify via get
    $mime = (string)($row['mime_type'] ?? '');
    $path = (string)$row['stored_path'];
    $bad = ($mime === 'text/html');
    if (!$bad && str_ends_with($path, '.bin')) {
      if ($dryRun) {
        $bad = true;
      } else {
        $get = sci_s3_get_object($path);
        $bad = !empty($get['ok']) && sci_drive_body_looks_like_html((string)($get['body'] ?? ''));
      }
    }
    if (!$bad) continue;
    $n++;
    if ($dryRun) {
      mig_log("RESET DRY #{$row['id']} {$path}");
      continue;
    }
    $upd = sci_db()->prepare(
      "UPDATE applicant_files SET stored_path = 'legacy://drive', mime_type = NULL, size_bytes = 0 WHERE id = ?"
    );
    $upd->execute([(int)$row['id']]);
    mig_log("RESET #{$row['id']} → legacy://drive");
  }
  return $n;
}

/**
 * Upload one applicant_files row from Drive → MinIO.
 * @return array{ok:bool,skipped?:bool,error?:string,stored_path?:string}
 */
function sci_migrate_file_row(array $row, bool $dryRun): array {
  $id = (int)$row['id'];
  $stored = trim((string)($row['stored_path'] ?? ''));
  if (sci_s3_is_stored_path($stored)) {
    return ['ok' => true, 'skipped' => true];
  }

  $driveUrl = trim((string)($row['drive_url'] ?? ''));
  $driveId = $driveUrl !== '' ? sci_drive_id($driveUrl) : null;
  if (!$driveId) {
    return ['ok' => false, 'error' => 'ไม่มี drive_url / id'];
  }

  if ($dryRun) {
    return ['ok' => true, 'skipped' => false, 'stored_path' => 'dry-run://' . $driveId];
  }

  $dl = sci_drive_download_file($driveId);
  if (!$dl['ok']) {
    return ['ok' => false, 'error' => $dl['error'] ?? 'ดาวน์โหลดไม่สำเร็จ'];
  }

  $mime = (string)($dl['mime'] ?? 'application/octet-stream');
  if ($mime === 'text/html' || sci_drive_body_looks_like_html((string)file_get_contents((string)$dl['path'], false, null, 0, 64))) {
    @unlink((string)$dl['path']);
    return ['ok' => false, 'error' => 'ได้หน้า HTML แทนไฟล์จริง — ตรวจ OAuth / สิทธิ์ Drive'];
  }
  $ext = sci_migrate_ext_for_mime($mime);
  $fileType = preg_replace('/[^a-z_]/', '', strtolower((string)($row['file_type'] ?? 'other'))) ?: 'other';
  $eventId = (int)($row['event_id'] ?? 0);
  $roundId = (int)($row['round_id'] ?? 0);
  $applicantId = (int)($row['applicant_id'] ?? 0);
  $key = sprintf(
    'legacy/%d/%d/%d/%s_%s.%s',
    $eventId,
    $roundId,
    $applicantId,
    $fileType,
    $driveId,
    $ext
  );

  $put = sci_s3_put_file($key, (string)$dl['path'], $mime);
  @unlink((string)$dl['path']);
  if (!$put['ok']) {
    return ['ok' => false, 'error' => $put['error'] ?? 'อัปโหลด MinIO ไม่สำเร็จ'];
  }

  $original = trim((string)($row['original_name'] ?? ''));
  if ($original === '' && !empty($dl['name'])) $original = (string)$dl['name'];
  if ($original === '') $original = $fileType . '_' . $driveId . '.' . $ext;

  $st = sci_db()->prepare(
    'UPDATE applicant_files SET stored_path = ?, mime_type = ?, size_bytes = ?, original_name = ? WHERE id = ?'
  );
  $st->execute([
    $put['stored_path'],
    $mime,
    (int)($dl['size'] ?? $put['size'] ?? 0),
    $original,
    $id,
  ]);

  return ['ok' => true, 'stored_path' => $put['stored_path']];
}

function sci_migrate_push_local_uploads(bool $dryRun, int $limit, int $sleepMs): array {
  $st = sci_db()->query(
    "SELECT f.id, f.applicant_id, f.file_type, f.original_name, f.stored_path, f.mime_type,
            a.event_id, a.round_id
     FROM applicant_files f
     JOIN applicants a ON a.id = f.applicant_id
     WHERE f.stored_path LIKE 'uploads/%' AND f.stored_path NOT LIKE 's3://%'
     ORDER BY f.id"
  );
  $ok = 0;
  $fail = 0;
  $skip = 0;
  $n = 0;
  foreach ($st->fetchAll() as $row) {
    if ($limit > 0 && $n >= $limit) break;
    $n++;
    $rel = (string)$row['stored_path'];
    $abs = sci_vendor_absolute_path($rel);
    if ($abs === null || !is_file($abs)) {
      $fail++;
      mig_log("LOCAL FAIL #{$row['id']} missing {$rel}");
      continue;
    }
    if ($dryRun) {
      mig_log("LOCAL DRY #{$row['id']} {$rel}");
      $ok++;
      continue;
    }
    $mime = (string)($row['mime_type'] ?? '');
    if ($mime === '') $mime = sci_vendor_detect_mime($abs) ?: 'application/octet-stream';
    $put = sci_s3_put_file($rel, $abs, $mime);
    if (!$put['ok']) {
      $fail++;
      mig_log("LOCAL FAIL #{$row['id']} " . ($put['error'] ?? ''));
      continue;
    }
    $upd = sci_db()->prepare('UPDATE applicant_files SET stored_path = ?, mime_type = ?, size_bytes = ? WHERE id = ?');
    $upd->execute([$put['stored_path'], $mime, (int)filesize($abs), (int)$row['id']]);
    $ok++;
    mig_log("LOCAL OK #{$row['id']} → {$put['stored_path']}");
    if ($sleepMs > 0) usleep($sleepMs * 1000);
  }
  return compact('ok', 'fail', 'skip', 'n');
}

$opts = mig_args($argv);
mig_log('MinIO migrate Drive → bucket');

if (!sci_s3_configured()) {
  fwrite(STDERR, "ยังไม่ได้ตั้งค่า data/minio_config.php\n");
  exit(1);
}

$probe = sci_s3_head_bucket();
if (!$probe['ok']) {
  fwrite(STDERR, 'เชื่อมต่อ MinIO ไม่สำเร็จ: ' . ($probe['error'] ?? '') . "\n");
  exit(1);
}
mig_log('Connected to bucket `' . sci_s3_config()['bucket'] . '`');
if ($opts['dry_run']) mig_log('DRY RUN — ไม่เขียน MinIO / DB');

$driveCfg = sci_drive_migrate_config();
$hasDriveAuth = trim((string)$driveCfg['refresh_token']) !== '';

if ($opts['reset_bad']) {
  $n = sci_migrate_reset_html_objects($opts['dry_run']);
  mig_log("reset-bad done: {$n}");
}

if (!$hasDriveAuth && empty($opts['force_anonymous'])) {
  mig_log('ERROR: ยังไม่มี Drive refresh_token');
  mig_log('ไฟล์จาก Google Form เป็น private — ต้อง authorize ก่อน:');
  mig_log('  1) เปิด Google Drive API ในโปรเจกต์ Google Cloud');
  mig_log('  2) เพิ่ม redirect URI: http://localhost/sci_shop/migrate_drive_auth.php');
  mig_log('  3) เปิดเบราว์เซอร์: http://localhost/sci_shop/migrate_drive_auth.php');
  mig_log('  4) ล็อกอินด้วยบัญชีเจ้าของ Google Form / Drive แล้วอนุญาต drive.readonly');
  mig_log('  5) รัน migrate_drive_to_minio.php อีกครั้ง');
  if ($opts['local_too']) {
    mig_log('Pushing local uploads/ → MinIO (ไม่ต้องใช้ Drive auth)…');
    $loc = sci_migrate_push_local_uploads($opts['dry_run'], $opts['limit'], $opts['sleep_ms']);
    mig_log('Local done: ' . json_encode($loc, JSON_UNESCAPED_UNICODE));
  }
  exit(2);
}

if ($hasDriveAuth) {
  mig_log('Drive OAuth: refresh_token พร้อมใช้งาน');
} else {
  mig_log('WARNING: --force-anonymous เปิดอยู่ จะลองดาวน์โหลดแบบไม่ล็อกอิน (มักล้มเหลว)');
}

if ($opts['from_xlsx']) {
  mig_log('Re-scan Excel Drive URLs (ensure DB rows exist via migrate_import_2569 first)');
  $files = [
    1 => __DIR__ . DIRECTORY_SEPARATOR . 'แบบตอบรับ.xlsx',
    2 => __DIR__ . DIRECTORY_SEPARATOR . 'แบบตอบรับ69_รอบ2.xlsx',
    3 => __DIR__ . DIRECTORY_SEPARATOR . 'แบบตอบรับ69_รอบ3.xlsx',
  ];
  foreach ($files as $round => $path) {
    if (!is_file($path)) {
      mig_log("skip missing {$path}");
      continue;
    }
    mig_log("xlsx round {$round}: " . basename($path));
  }
}

$sql = "SELECT f.id, f.applicant_id, f.file_type, f.original_name, f.stored_path, f.drive_url, f.mime_type,
               a.event_id, a.round_id
        FROM applicant_files f
        JOIN applicants a ON a.id = f.applicant_id
        WHERE f.drive_url IS NOT NULL AND f.drive_url <> ''
          AND (f.stored_path LIKE 'legacy://%' OR f.stored_path = '' OR f.stored_path NOT LIKE 's3://%')
        ORDER BY f.id";
$st = sci_db()->query($sql);
$rows = $st->fetchAll();
mig_log('Drive rows to migrate: ' . count($rows));

$ok = 0;
$fail = 0;
$skip = 0;
$n = 0;
foreach ($rows as $row) {
  if ($opts['limit'] > 0 && $n >= $opts['limit']) break;
  $n++;
  $res = sci_migrate_file_row($row, $opts['dry_run']);
  if (!empty($res['skipped'])) {
    $skip++;
    continue;
  }
  if (!$res['ok']) {
    $fail++;
    $err = (string)($res['error'] ?? '');
    mig_log("FAIL #{$row['id']} {$row['file_type']} " . $err);
    if (stripos($err, 'Drive API') !== false && (stripos($err, 'disabled') !== false || stripos($err, 'has not been used') !== false)) {
      mig_log('ABORT: เปิด Google Drive API ก่อน แล้วรันใหม่');
      mig_log('https://console.developers.google.com/apis/api/drive.googleapis.com/overview?project=9221575730');
      break;
    }
  } else {
    $ok++;
    mig_log("OK #{$row['id']} {$row['file_type']} → " . ($res['stored_path'] ?? ''));
  }
  if ($opts['sleep_ms'] > 0) usleep($opts['sleep_ms'] * 1000);
}

mig_log("Drive done: ok={$ok} fail={$fail} skip={$skip} processed={$n}");

if ($opts['local_too']) {
  mig_log('Pushing local uploads/ → MinIO…');
  $loc = sci_migrate_push_local_uploads($opts['dry_run'], $opts['limit'], $opts['sleep_ms']);
  mig_log('Local done: ' . json_encode($loc, JSON_UNESCAPED_UNICODE));
}

mig_log('Finished.');
