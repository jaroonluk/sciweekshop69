<?php
/**
 * Google OAuth + session helpers for SCI Shop Review.
 * Client secret stays server-side only (data/auth_secrets.php).
 */

function sci_auth_secrets_path(): string {
  return __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'auth_secrets.php';
}

function sci_auth_config(): array {
  static $cfg = null;
  if (is_array($cfg)) return $cfg;

  $defaults = [
    'client_id' => '',
    'client_secret' => '',
    'allowed_emails' => [],   // empty = allow any signed-in Google account
    'allowed_domains' => [],  // e.g. ['kkumail.com'] — empty = no domain filter
    'app_name' => 'SCI Shop Review',
    // Optional exact public base URL (no trailing slash), e.g. https://sci.kku.ac.th/app/sciweekshop69
    'public_base_url' => '',
    // Host => base URL map (used when public_base_url is empty)
    'public_base_urls' => [],
  ];

  $path = sci_auth_secrets_path();
  $loaded = [];
  if (is_file($path)) {
    $tmp = include $path;
    if (is_array($tmp)) $loaded = $tmp;
  }

  $cfg = array_merge($defaults, $loaded);
  $cfg['client_id'] = trim((string)($cfg['client_id'] ?? ''));
  $cfg['client_secret'] = trim((string)($cfg['client_secret'] ?? ''));
  $cfg['public_base_url'] = rtrim(trim((string)($cfg['public_base_url'] ?? '')), '/');
  $cfg['allowed_emails'] = array_values(array_filter(array_map(
    static fn($e) => strtolower(trim((string)$e)),
    (array)($cfg['allowed_emails'] ?? [])
  )));
  $cfg['allowed_domains'] = array_values(array_filter(array_map(
    static fn($d) => strtolower(trim((string)$d)),
    (array)($cfg['allowed_domains'] ?? [])
  )));
  $map = [];
  foreach ((array)($cfg['public_base_urls'] ?? []) as $host => $base) {
    $h = strtolower(trim((string)$host));
    $b = rtrim(trim((string)$base), '/');
    if ($h !== '' && $b !== '') $map[$h] = $b;
  }
  $cfg['public_base_urls'] = $map;
  return $cfg;
}

function sci_auth_configured(): bool {
  $c = sci_auth_config();
  return $c['client_id'] !== '' && $c['client_secret'] !== '';
}

function sci_auth_request_is_https(): bool {
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
  $proto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
  if ($proto === 'https') return true;
  $front = strtolower((string)($_SERVER['HTTP_FRONT_END_HTTPS'] ?? ''));
  if ($front === 'on' || $front === '1') return true;
  $port = (string)($_SERVER['HTTP_X_FORWARDED_PORT'] ?? '');
  if ($port === '443') return true;
  return false;
}

function sci_auth_request_host(): string {
  $xf = trim((string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''));
  if ($xf !== '') {
    // may be "host1, host2"
    $xf = trim(explode(',', $xf)[0]);
    if ($xf !== '') return $xf;
  }
  return (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/**
 * Infer app directory from the current request path.
 * Handles reverse-proxy installs under /app/sciweekshop69.
 */
function sci_auth_detect_app_dir(): string {
  $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
  $scriptBase = $script !== '' ? basename($script) : '';

  $candidates = [];
  if ($script !== '') $candidates[] = $script;

  $uriPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
  if ($uriPath !== '') $candidates[] = $uriPath;

  $phpSelf = str_replace('\\', '/', (string)($_SERVER['PHP_SELF'] ?? ''));
  if ($phpSelf !== '') $candidates[] = $phpSelf;

  foreach ($candidates as $path) {
    $path = '/' . ltrim($path, '/');
    if ($scriptBase !== '' && str_ends_with($path, '/' . $scriptBase)) {
      $dir = substr($path, 0, -strlen($scriptBase) - 1);
      $dir = rtrim($dir, '/');
      return $dir === '/' ? '' : $dir;
    }
    // path already a directory-like prefix
    if (!str_contains(basename($path), '.')) {
      $dir = rtrim($path, '/');
      return $dir === '/' ? '' : $dir;
    }
  }

  if ($script !== '') {
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($dir === '/' || $dir === '\\' || $dir === '.') return '';
    return $dir;
  }
  return '';
}

function sci_auth_base_url(): string {
  $cfg = sci_auth_config();

  if ($cfg['public_base_url'] !== '') {
    return $cfg['public_base_url'];
  }

  $host = sci_auth_request_host();
  $hostKey = strtolower(preg_replace('/:\d+$/', '', $host) ?: $host);
  if (!empty($cfg['public_base_urls'][$hostKey])) {
    return $cfg['public_base_urls'][$hostKey];
  }
  // also allow full host:port keys
  if (!empty($cfg['public_base_urls'][strtolower($host)])) {
    return $cfg['public_base_urls'][strtolower($host)];
  }

  $scheme = sci_auth_request_is_https() ? 'https' : 'http';
  $dir = sci_auth_detect_app_dir();
  return $scheme . '://' . $host . $dir;
}

function sci_auth_redirect_uri(): string {
  $cfg = sci_auth_config();
  $forced = trim((string)($cfg['redirect_uri'] ?? ''));
  if ($forced !== '') return rtrim($forced, '/');
  return sci_auth_base_url() . '/auth_callback.php';
}

/** Absolute URL under the public app base (always use this for Location headers). */
function sci_auth_url(string $path = 'index.php'): string {
  $path = trim($path);
  if ($path === '' || $path === '/') {
    $path = 'index.php';
  }
  // If a full path under this host was passed, keep only the file name when possible
  if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
    return sci_auth_base_url() . '/index.php';
  }
  $path = ltrim(str_replace('\\', '/', $path), '/');
  // Drop query query for building; caller can append if needed
  $qpos = strpos($path, '?');
  $query = '';
  if ($qpos !== false) {
    $query = substr($path, $qpos);
    $path = substr($path, 0, $qpos);
  }
  $baseName = basename($path);
  if ($baseName === '' || $baseName === '.' || $baseName === '..') {
    $baseName = 'index.php';
  }
  return sci_auth_base_url() . '/' . $baseName . $query;
}

/**
 * Normalize post-login destination. Rejects wrong absolute paths like /sciweekshop69/.
 */
function sci_auth_sanitize_next(?string $next): string {
  $home = sci_auth_url('index.php');
  $next = trim((string)$next);
  if ($next === '' || $next === '/' || str_contains($next, '://') || str_starts_with($next, '//')) {
    return $home;
  }

  $path = (string)(parse_url($next, PHP_URL_PATH) ?: $next);
  $path = str_replace('\\', '/', $path);
  $appPath = (string)(parse_url(sci_auth_base_url(), PHP_URL_PATH) ?: '');
  $appPath = rtrim($appPath, '/'); // e.g. /app/sciweekshop69

  // Absolute site path
  if (str_starts_with($path, '/')) {
    if ($appPath !== '' && ($path === $appPath || str_starts_with($path, $appPath . '/'))) {
      $rel = ltrim(substr($path, strlen($appPath)), '/');
      if ($rel === '' || $rel === '/') return $home;
      $file = basename($rel);
      if (preg_match('/^(index|login)\.php$/i', $file)) {
        return sci_auth_url(strtolower($file) === 'login.php' ? 'index.php' : $file);
      }
      return $home;
    }
    // Wrong roots e.g. /sciweekshop69 or /sciweekshop69/
    return $home;
  }

  $file = basename($path);
  if (preg_match('/^index\.php$/i', $file)) {
    return $home;
  }
  return $home;
}

function sci_auth_redirect(string $pathOrUrl): void {
  if (str_starts_with($pathOrUrl, 'http://') || str_starts_with($pathOrUrl, 'https://')) {
    header('Location: ' . $pathOrUrl);
  } else {
    header('Location: ' . sci_auth_sanitize_next($pathOrUrl));
  }
  exit;
}

function sci_auth_start_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  session_name('sci_shop_sess');
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

function sci_auth_user(): ?array {
  sci_auth_start_session();
  $u = $_SESSION['sci_auth_user'] ?? null;
  return is_array($u) && !empty($u['email']) ? $u : null;
}

function sci_auth_logged_in(): bool {
  return sci_auth_user() !== null;
}

function sci_auth_set_user(array $user): void {
  sci_auth_start_session();
  session_regenerate_id(true);
  $_SESSION['sci_auth_user'] = [
    'email' => (string)($user['email'] ?? ''),
    'name' => (string)($user['name'] ?? ''),
    'picture' => (string)($user['picture'] ?? ''),
    'sub' => (string)($user['sub'] ?? ''),
    'login_at' => date('c'),
  ];
}

function sci_auth_clear(): void {
  sci_auth_start_session();
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'] ?? '/', $p['domain'] ?? '', !empty($p['secure']), !empty($p['httponly']));
  }
  session_destroy();
}

function sci_auth_user_allowed(string $email): bool {
  $email = strtolower(trim($email));
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

  $cfg = sci_auth_config();
  $emails = $cfg['allowed_emails'];
  $domains = $cfg['allowed_domains'];

  if ($emails && !in_array($email, $emails, true)) return false;
  if ($domains) {
    $domain = substr(strrchr($email, '@') ?: '', 1);
    if (!in_array(strtolower($domain), $domains, true)) return false;
  }
  return true;
}

function sci_auth_require_login(bool $json = false): void {
  if (sci_auth_logged_in()) return;

  if ($json) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
      'ok' => false,
      'error' => 'กรุณาเข้าสู่ระบบด้วย Google ก่อน',
      'login_url' => sci_auth_url('login.php'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  header('Location: ' . sci_auth_url('login.php') . '?next=' . rawurlencode('index.php'));
  exit;
}

function sci_auth_google_authorize_url(string $state): string {
  $cfg = sci_auth_config();
  $redirect = sci_auth_redirect_uri();
  // Keep the exact redirect_uri for the token exchange step
  $_SESSION['oauth_redirect_uri'] = $redirect;
  $params = [
    'client_id' => $cfg['client_id'],
    'redirect_uri' => $redirect,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'access_type' => 'online',
    'include_granted_scopes' => 'true',
    'prompt' => 'select_account',
    'state' => $state,
  ];
  return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function sci_auth_http_json(string $url, array $opts = []): array {
  $method = strtoupper((string)($opts['method'] ?? 'GET'));
  $headers = (array)($opts['headers'] ?? []);
  $body = $opts['body'] ?? null;

  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 25,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
      throw new RuntimeException('เชื่อมต่อ Google ไม่สำเร็จ: ' . $err);
    }
  } else {
    $headerStr = implode("\r\n", $headers);
    $ctx = stream_context_create([
      'http' => [
        'method' => $method,
        'header' => $headerStr,
        'content' => $body ?? '',
        'timeout' => 25,
        'ignore_errors' => true,
      ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
      $code = (int)$m[1];
    }
    if ($raw === false) {
      throw new RuntimeException('เชื่อมต่อ Google ไม่สำเร็จ (file_get_contents)');
    }
  }

  $data = json_decode((string)$raw, true);
  if (!is_array($data)) {
    throw new RuntimeException('คำตอบจาก Google ไม่ใช่ JSON (HTTP ' . $code . ')');
  }
  if ($code >= 400) {
    $msg = (string)($data['error_description'] ?? $data['error'] ?? ('HTTP ' . $code));
    throw new RuntimeException('Google OAuth error: ' . $msg);
  }
  return $data;
}

function sci_auth_exchange_code(string $code): array {
  $cfg = sci_auth_config();
  sci_auth_start_session();
  $redirect = trim((string)($_SESSION['oauth_redirect_uri'] ?? ''));
  if ($redirect === '') $redirect = sci_auth_redirect_uri();
  unset($_SESSION['oauth_redirect_uri']);
  return sci_auth_http_json('https://oauth2.googleapis.com/token', [
    'method' => 'POST',
    'headers' => ['Content-Type: application/x-www-form-urlencoded'],
    'body' => http_build_query([
      'code' => $code,
      'client_id' => $cfg['client_id'],
      'client_secret' => $cfg['client_secret'],
      'redirect_uri' => $redirect,
      'grant_type' => 'authorization_code',
    ]),
  ]);
}

function sci_auth_fetch_userinfo(string $accessToken): array {
  return sci_auth_http_json('https://www.googleapis.com/oauth2/v3/userinfo', [
    'method' => 'GET',
    'headers' => ['Authorization: Bearer ' . $accessToken],
  ]);
}
