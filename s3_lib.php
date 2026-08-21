<?php
/**
 * Minimal S3-compatible client for MinIO (SigV4, path-style).
 * Config: data/minio_config.php (see minio_config.example.php).
 */

function sci_s3_config(): array {
  static $cfg = null;
  if ($cfg !== null) return $cfg;

  $defaults = [
    'endpoint' => '',
    'port' => 9000,
    'use_ssl' => true,
    'insecure_skip_verify' => false,
    'access_key' => '',
    'secret_key' => '',
    'bucket' => 'sci-shop',
    'region' => 'us-east-1',
    'prefix' => '',
  ];

  $path = __DIR__ . '/data/minio_config.php';
  $loaded = [];
  if (is_file($path)) {
    $tmp = include $path;
    if (is_array($tmp)) $loaded = $tmp;
  } else {
    $ex = __DIR__ . '/data/minio_config.example.php';
    if (is_file($ex)) {
      $tmp = include $ex;
      if (is_array($tmp)) $loaded = $tmp;
    }
  }

  $cfg = array_merge($defaults, $loaded);
  $cfg['endpoint'] = trim((string)$cfg['endpoint']);
  $cfg['port'] = (int)$cfg['port'];
  $cfg['use_ssl'] = !empty($cfg['use_ssl']);
  $cfg['insecure_skip_verify'] = !empty($cfg['insecure_skip_verify']);
  $cfg['bucket'] = trim((string)$cfg['bucket']);
  $cfg['region'] = trim((string)$cfg['region']) ?: 'us-east-1';
  $cfg['prefix'] = trim((string)$cfg['prefix'], '/');
  $cfg['access_key'] = (string)$cfg['access_key'];
  $cfg['secret_key'] = (string)$cfg['secret_key'];
  return $cfg;
}

function sci_s3_configured(): bool {
  $c = sci_s3_config();
  return $c['endpoint'] !== ''
    && $c['access_key'] !== ''
    && $c['secret_key'] !== ''
    && $c['bucket'] !== ''
    && $c['access_key'] !== 'YOUR_ACCESS_KEY';
}

function sci_s3_base_url(): string {
  $c = sci_s3_config();
  $scheme = $c['use_ssl'] ? 'https' : 'http';
  $host = $c['endpoint'];
  $port = (int)$c['port'];
  $default = $c['use_ssl'] ? 443 : 80;
  if ($port > 0 && $port !== $default) {
    return $scheme . '://' . $host . ':' . $port;
  }
  return $scheme . '://' . $host;
}

function sci_s3_host_header(): string {
  $c = sci_s3_config();
  $host = $c['endpoint'];
  $port = (int)$c['port'];
  $default = $c['use_ssl'] ? 443 : 80;
  if ($port > 0 && $port !== $default) {
    return $host . ':' . $port;
  }
  return $host;
}

/** Normalize object key (no leading slash). Applies optional config prefix. */
function sci_s3_object_key(string $key): string {
  $key = str_replace('\\', '/', $key);
  $key = ltrim($key, '/');
  if (str_starts_with($key, 's3://')) {
    $parsed = sci_s3_parse_stored_path($key);
    return $parsed['key'] ?? $key;
  }
  $prefix = sci_s3_config()['prefix'];
  if ($prefix !== '' && !str_starts_with($key, $prefix . '/')) {
    $key = $prefix . '/' . $key;
  }
  return $key;
}

/**
 * Store reference in DB as s3://bucket/key
 */
function sci_s3_stored_path(string $key): string {
  $c = sci_s3_config();
  $key = sci_s3_object_key($key);
  return 's3://' . $c['bucket'] . '/' . $key;
}

function sci_s3_is_stored_path(string $path): bool {
  return str_starts_with(trim($path), 's3://');
}

/** @return array{bucket:string,key:string}|null */
function sci_s3_parse_stored_path(string $path): ?array {
  $path = trim($path);
  if (!preg_match('#^s3://([^/]+)/(.+)$#', $path, $m)) return null;
  return ['bucket' => $m[1], 'key' => $m[2]];
}

function sci_s3_hmac(string $key, string $data): string {
  return hash_hmac('sha256', $data, $key, true);
}

function sci_s3_signing_key(string $secret, string $dateStamp, string $region, string $service): string {
  $kDate = sci_s3_hmac('AWS4' . $secret, $dateStamp);
  $kRegion = sci_s3_hmac($kDate, $region);
  $kService = sci_s3_hmac($kRegion, $service);
  return sci_s3_hmac($kService, 'aws4_request');
}

/**
 * Low-level signed request.
 * @return array{ok:bool,status:int,headers:array<string,string>,body:string,error?:string}
 */
function sci_s3_request(string $method, string $key, string $body = '', array $extraHeaders = [], ?string $bucket = null): array {
  if (!sci_s3_configured()) {
    return ['ok' => false, 'status' => 0, 'headers' => [], 'body' => '', 'error' => 'ยังไม่ได้ตั้งค่า MinIO (data/minio_config.php)'];
  }
  if (!extension_loaded('curl')) {
    return ['ok' => false, 'status' => 0, 'headers' => [], 'body' => '', 'error' => 'ต้องการ PHP curl extension'];
  }

  $c = sci_s3_config();
  $bucket = $bucket ?: $c['bucket'];
  $key = ltrim(str_replace('\\', '/', $key), '/');
  $region = $c['region'];
  $service = 's3';

  $amzDate = gmdate('Ymd\THis\Z');
  $dateStamp = gmdate('Ymd');
  $payloadHash = hash('sha256', $body);
  $host = sci_s3_host_header();

  $canonicalUri = '/' . $bucket;
  if ($key !== '') {
    $parts = explode('/', $key);
    foreach ($parts as $p) {
      $canonicalUri .= '/' . str_replace('%7E', '~', rawurlencode($p));
    }
  }

  $headers = array_merge([
    'host' => $host,
    'x-amz-content-sha256' => $payloadHash,
    'x-amz-date' => $amzDate,
  ], $extraHeaders);

  // Lowercase header names for signing
  $norm = [];
  foreach ($headers as $hk => $hv) {
    $norm[strtolower($hk)] = trim((string)$hv);
  }
  ksort($norm);
  $signedHeaderKeys = array_keys($norm);
  $canonicalHeaders = '';
  foreach ($norm as $hk => $hv) {
    $canonicalHeaders .= $hk . ':' . $hv . "\n";
  }
  $signedHeaders = implode(';', $signedHeaderKeys);

  $canonicalRequest = implode("\n", [
    strtoupper($method),
    $canonicalUri,
    '', // query string
    $canonicalHeaders,
    $signedHeaders,
    $payloadHash,
  ]);

  $credentialScope = $dateStamp . '/' . $region . '/' . $service . '/aws4_request';
  $stringToSign = implode("\n", [
    'AWS4-HMAC-SHA256',
    $amzDate,
    $credentialScope,
    hash('sha256', $canonicalRequest),
  ]);

  $signingKey = sci_s3_signing_key($c['secret_key'], $dateStamp, $region, $service);
  $signature = hash_hmac('sha256', $stringToSign, $signingKey);
  $authorization = 'AWS4-HMAC-SHA256 Credential=' . $c['access_key'] . '/' . $credentialScope
    . ', SignedHeaders=' . $signedHeaders
    . ', Signature=' . $signature;

  $url = sci_s3_base_url() . $canonicalUri;
  $curlHeaders = ['Authorization: ' . $authorization];
  foreach ($norm as $hk => $hv) {
    if ($hk === 'host') continue;
    $curlHeaders[] = $hk . ': ' . $hv;
  }

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => strtoupper($method),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_HTTPHEADER => $curlHeaders,
    CURLOPT_TIMEOUT => 120,
  ]);
  if ($body !== '' || in_array(strtoupper($method), ['PUT', 'POST'], true)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
  }
  if (!empty($c['insecure_skip_verify'])) {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  }

  $raw = curl_exec($ch);
  $errno = curl_errno($ch);
  $err = curl_error($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  curl_close($ch);

  if ($raw === false) {
    return ['ok' => false, 'status' => 0, 'headers' => [], 'body' => '', 'error' => 'curl: ' . ($err ?: (string)$errno)];
  }

  $headerBlob = substr($raw, 0, $headerSize);
  $respBody = substr($raw, $headerSize);
  $respHeaders = [];
  foreach (explode("\r\n", $headerBlob) as $line) {
    if (str_contains($line, ':')) {
      [$hk, $hv] = explode(':', $line, 2);
      $respHeaders[strtolower(trim($hk))] = trim($hv);
    }
  }

  $ok = $status >= 200 && $status < 300;
  return [
    'ok' => $ok,
    'status' => $status,
    'headers' => $respHeaders,
    'body' => $respBody,
    'error' => $ok ? null : ('S3 HTTP ' . $status . ': ' . substr(preg_replace('/\s+/', ' ', $respBody), 0, 240)),
  ];
}

/**
 * @return array{ok:bool,stored_path?:string,key?:string,error?:string,etag?:string}
 */
function sci_s3_put_object(string $key, string $body, string $contentType = 'application/octet-stream'): array {
  $key = sci_s3_object_key($key);
  $res = sci_s3_request('PUT', $key, $body, [
    'content-type' => $contentType !== '' ? $contentType : 'application/octet-stream',
    'content-length' => (string)strlen($body),
  ]);
  if (!$res['ok']) {
    return ['ok' => false, 'error' => $res['error'] ?? 'อัปโหลดไป MinIO ไม่สำเร็จ'];
  }
  return [
    'ok' => true,
    'key' => $key,
    'stored_path' => sci_s3_stored_path($key),
    'etag' => $res['headers']['etag'] ?? null,
  ];
}

/**
 * Upload a local file path.
 * @return array{ok:bool,stored_path?:string,key?:string,error?:string,size?:int}
 */
function sci_s3_put_file(string $key, string $localPath, string $contentType = 'application/octet-stream'): array {
  if (!is_file($localPath)) {
    return ['ok' => false, 'error' => 'ไม่พบไฟล์ต้นทาง'];
  }
  $body = file_get_contents($localPath);
  if ($body === false) {
    return ['ok' => false, 'error' => 'อ่านไฟล์ไม่สำเร็จ'];
  }
  $put = sci_s3_put_object($key, $body, $contentType);
  if ($put['ok']) $put['size'] = strlen($body);
  return $put;
}

/**
 * @return array{ok:bool,body?:string,content_type?:string,error?:string,status?:int}
 */
function sci_s3_get_object(string $key, ?string $bucket = null): array {
  if (sci_s3_is_stored_path($key)) {
    $parsed = sci_s3_parse_stored_path($key);
    if (!$parsed) return ['ok' => false, 'error' => 'stored_path ไม่ถูกต้อง'];
    $key = $parsed['key'];
    $bucket = $parsed['bucket'];
  } else {
    $key = sci_s3_object_key($key);
  }
  $res = sci_s3_request('GET', $key, '', [], $bucket);
  if (!$res['ok']) {
    return ['ok' => false, 'error' => $res['error'] ?? 'ดาวน์โหลดจาก MinIO ไม่สำเร็จ', 'status' => $res['status']];
  }
  return [
    'ok' => true,
    'body' => $res['body'],
    'content_type' => $res['headers']['content-type'] ?? 'application/octet-stream',
    'status' => $res['status'],
  ];
}

function sci_s3_head_bucket(): array {
  $c = sci_s3_config();
  // GET /bucket/ (list with max-keys=0) as connectivity check
  $res = sci_s3_request('GET', '', '');
  if (!$res['ok'] && ($res['status'] ?? 0) === 404) {
    return ['ok' => false, 'error' => 'ไม่พบ bucket `' . $c['bucket'] . '`'];
  }
  return $res['ok']
    ? ['ok' => true, 'bucket' => $c['bucket']]
    : ['ok' => false, 'error' => $res['error'] ?? 'เชื่อมต่อ MinIO ไม่สำเร็จ'];
}
