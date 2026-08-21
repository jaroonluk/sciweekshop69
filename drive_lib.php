<?php
/**
 * Google Drive download helpers for migrating Excel/Form attachments.
 * Config: data/drive_migrate_config.php (see example).
 * Auth: php migrate_drive_auth.php
 */
require_once __DIR__ . '/auth_lib.php';
require_once __DIR__ . '/vendor_apply_lib.php';

function sci_drive_migrate_config_path(): string {
  return __DIR__ . '/data/drive_migrate_config.php';
}

function sci_drive_migrate_config(bool $reload = false): array {
  static $cfg = null;
  if ($reload) $cfg = null;
  if ($cfg !== null) return $cfg;
  $defaults = [
    'refresh_token' => '',
    'access_token' => '',
    'access_token_expires_at' => 0,
    'client_id' => '',
    'client_secret' => '',
  ];
  $path = sci_drive_migrate_config_path();
  $loaded = [];
  if (is_file($path)) {
    $tmp = include $path;
    if (is_array($tmp)) $loaded = $tmp;
  }
  $cfg = array_merge($defaults, $loaded);
  if ($cfg['client_id'] === '' || $cfg['client_secret'] === '') {
    $auth = sci_auth_config();
    if ($cfg['client_id'] === '') $cfg['client_id'] = (string)($auth['client_id'] ?? '');
    if ($cfg['client_secret'] === '') $cfg['client_secret'] = (string)($auth['client_secret'] ?? '');
  }
  return $cfg;
}

function sci_drive_migrate_save_config(array $cfg): void {
  $path = sci_drive_migrate_config_path();
  $export = [
    'refresh_token' => (string)($cfg['refresh_token'] ?? ''),
    'access_token' => (string)($cfg['access_token'] ?? ''),
    'access_token_expires_at' => (int)($cfg['access_token_expires_at'] ?? 0),
    'client_id' => (string)($cfg['client_id'] ?? ''),
    'client_secret' => (string)($cfg['client_secret'] ?? ''),
  ];
  $php = "<?php\n/** Auto-saved Drive migrate credentials — do not commit. */\nreturn "
    . var_export($export, true) . ";\n";
  if (file_put_contents($path, $php) === false) {
    throw new RuntimeException('เขียน data/drive_migrate_config.php ไม่สำเร็จ');
  }
  sci_drive_migrate_config(true);
}

function sci_drive_http_json(string $url, array $postFields = [], array $headers = []): array {
  $ch = curl_init($url);
  $hdrs = $headers;
  $opts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
  ];
  if ($postFields) {
    $opts[CURLOPT_POST] = true;
    $opts[CURLOPT_POSTFIELDS] = http_build_query($postFields);
  }
  if ($hdrs) $opts[CURLOPT_HTTPHEADER] = $hdrs;
  curl_setopt_array($ch, $opts);
  $raw = curl_exec($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  if ($raw === false) throw new RuntimeException('HTTP error: ' . $err);
  $json = json_decode($raw, true);
  if (!is_array($json)) {
    throw new RuntimeException('Invalid JSON (HTTP ' . $status . '): ' . substr($raw, 0, 200));
  }
  if ($status >= 400) {
    $msg = $json['error_description'] ?? ($json['error']['message'] ?? ($json['error'] ?? 'HTTP ' . $status));
    if (is_array($msg)) $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
    throw new RuntimeException((string)$msg);
  }
  return $json;
}

function sci_drive_auth_url(string $redirectUri): string {
  $cfg = sci_drive_migrate_config();
  $params = [
    'client_id' => $cfg['client_id'],
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'https://www.googleapis.com/auth/drive.readonly',
    'access_type' => 'offline',
    'prompt' => 'consent',
    'include_granted_scopes' => 'true',
  ];
  return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function sci_drive_exchange_code(string $code, string $redirectUri): array {
  $cfg = sci_drive_migrate_config();
  return sci_drive_http_json('https://oauth2.googleapis.com/token', [
    'code' => $code,
    'client_id' => $cfg['client_id'],
    'client_secret' => $cfg['client_secret'],
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code',
  ]);
}

function sci_drive_refresh_access_token(): string {
  $cfg = sci_drive_migrate_config();
  $token = trim((string)$cfg['access_token']);
  $exp = (int)$cfg['access_token_expires_at'];
  if ($token !== '' && $exp > time() + 60) {
    return $token;
  }
  $refresh = trim((string)$cfg['refresh_token']);
  if ($refresh === '') {
    throw new RuntimeException('ยังไม่มี refresh_token — รัน migrate_drive_auth.php ก่อน (บัญชีเจ้าของไฟล์ใน Google Drive)');
  }
  $json = sci_drive_http_json('https://oauth2.googleapis.com/token', [
    'client_id' => $cfg['client_id'],
    'client_secret' => $cfg['client_secret'],
    'refresh_token' => $refresh,
    'grant_type' => 'refresh_token',
  ]);
  $access = (string)($json['access_token'] ?? '');
  if ($access === '') throw new RuntimeException('ไม่ได้รับ access_token จาก Google');
  $cfg['access_token'] = $access;
  $cfg['access_token_expires_at'] = time() + max(60, (int)($json['expires_in'] ?? 3600) - 30);
  if (!empty($json['refresh_token'])) {
    $cfg['refresh_token'] = (string)$json['refresh_token'];
  }
  sci_drive_migrate_save_config($cfg);
  return $access;
}

function sci_drive_body_looks_like_html(string $body): bool {
  $trim = ltrim($body);
  $head = strtolower(substr($trim, 0, 32));
  return str_starts_with($head, '<!doctype')
    || str_starts_with($head, '<html')
    || str_contains($head, '<html');
}

function sci_drive_sniff_mime(string $pathOrBytes, bool $isPath = true): string {
  if ($isPath) {
    $mime = sci_vendor_detect_mime($pathOrBytes);
    if ($mime !== '' && $mime !== 'text/html' && $mime !== 'application/octet-stream') {
      return $mime;
    }
    $bytes = (string)file_get_contents($pathOrBytes, false, null, 0, 32);
  } else {
    $bytes = substr($pathOrBytes, 0, 32);
    $tmp = tempnam(sys_get_temp_dir(), 'sniff');
    file_put_contents($tmp, $pathOrBytes);
    $mime = sci_vendor_detect_mime($tmp);
    @unlink($tmp);
    if ($mime !== '' && $mime !== 'text/html' && $mime !== 'application/octet-stream') {
      return $mime;
    }
  }
  $hex = bin2hex(substr($bytes, 0, 12));
  if (str_starts_with($hex, 'ffd8ff')) return 'image/jpeg';
  if (str_starts_with($hex, '89504e470d0a')) return 'image/png';
  if (str_starts_with($hex, '52494646') && str_contains(bin2hex(substr($bytes, 8, 4)), '5745')) return 'image/webp';
  if (str_starts_with($hex, '474946383')) return 'image/gif';
  if (str_starts_with($hex, '25504446')) return 'application/pdf';
  return $mime !== '' ? $mime : 'application/octet-stream';
}

/**
 * Download via Drive API v3 (requires migrate_drive_auth.php once).
 * @return array{ok:bool,path?:string,mime?:string,size?:int,error?:string,name?:string}
 */
function sci_drive_api_download(string $driveId): array {
  try {
    $access = sci_drive_refresh_access_token();
  } catch (Throwable $e) {
    return ['ok' => false, 'error' => $e->getMessage()];
  }

  $metaUrl = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($driveId)
    . '?fields=id,name,mimeType,size';
  $ch = curl_init($metaUrl);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access],
  ]);
  $metaRaw = curl_exec($ch);
  $metaStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $meta = json_decode((string)$metaRaw, true);
  if ($metaStatus >= 400 || !is_array($meta)) {
    $msg = is_array($meta) ? ($meta['error']['message'] ?? 'meta HTTP ' . $metaStatus) : 'meta failed';
    return ['ok' => false, 'error' => 'Drive API: ' . $msg];
  }

  $mediaUrl = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($driveId) . '?alt=media';
  $ch = curl_init($mediaUrl);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 180,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access],
  ]);
  $body = curl_exec($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
  $err = curl_error($ch);
  curl_close($ch);
  if ($body === false) return ['ok' => false, 'error' => 'curl: ' . $err];
  if ($status >= 400 || $body === '') {
    return ['ok' => false, 'error' => 'Drive media HTTP ' . $status];
  }
  if (sci_drive_body_looks_like_html($body)) {
    return ['ok' => false, 'error' => 'Drive API คืน HTML (โทเคน/สิทธิ์ไม่พอ)'];
  }

  $tmp = tempnam(sys_get_temp_dir(), 'gapi');
  if ($tmp === false || file_put_contents($tmp, $body) === false) {
    return ['ok' => false, 'error' => 'เขียนไฟล์ชั่วคราวไม่สำเร็จ'];
  }
  $mime = (string)($meta['mimeType'] ?? '');
  if ($mime === '' || $mime === 'application/octet-stream') {
    $mime = sci_drive_sniff_mime($tmp);
  }
  if ($ctype && !str_contains(strtolower($ctype), 'html') && $mime === 'application/octet-stream') {
    $mime = strtolower(trim(explode(';', $ctype)[0]));
  }

  return [
    'ok' => true,
    'path' => $tmp,
    'mime' => $mime,
    'size' => strlen($body),
    'name' => (string)($meta['name'] ?? ''),
  ];
}
