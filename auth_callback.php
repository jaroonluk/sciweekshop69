<?php
require_once __DIR__ . '/auth_lib.php';
require_once __DIR__ . '/rbac_lib.php';
require_once __DIR__ . '/drive_lib.php';

sci_auth_start_session();

try {
  if (!sci_auth_configured()) {
    throw new RuntimeException('config');
  }

  $error = (string)($_GET['error'] ?? '');
  if ($error !== '') {
    $driveMig = !empty($_SESSION['oauth_drive_migrate'])
      || ((string)($_SESSION['oauth_purpose'] ?? '') === 'drive_migrate');
    unset($_SESSION['oauth_drive_migrate'], $_SESSION['oauth_state'], $_SESSION['oauth_redirect_uri'], $_SESSION['oauth_purpose']);
    if ($driveMig) {
      header('Location: ' . sci_auth_url('migrate_drive_auth.php') . '?error=' . rawurlencode($error));
      exit;
    }
    header('Location: ' . sci_auth_url('login.php') . '?error=' . rawurlencode($error === 'access_denied' ? 'access_denied' : $error));
    exit;
  }

  $state = (string)($_GET['state'] ?? '');
  $consumed = sci_auth_oauth_consume($state);
  $driveMigrate = ($consumed['purpose'] === 'drive_migrate')
    || !empty($_SESSION['oauth_drive_migrate']);
  unset($_SESSION['oauth_drive_migrate']);

  // Ensure token exchange uses the same redirect_uri that started the flow
  if (!empty($consumed['redirect_uri'])) {
    $_SESSION['oauth_redirect_uri'] = $consumed['redirect_uri'];
  }

  $code = (string)($_GET['code'] ?? '');
  if ($code === '') {
    throw new RuntimeException('ไม่พบรหัสยืนยันจาก Google');
  }

  $token = sci_auth_exchange_code($code);
  $access = (string)($token['access_token'] ?? '');
  if ($access === '') {
    throw new RuntimeException('ไม่ได้รับ access token จาก Google');
  }

  // Drive migrate: save refresh_token only — do not require staff RBAC login
  if ($driveMigrate) {
    $cfg = sci_drive_migrate_config();
    $cfg['access_token'] = $access;
    $cfg['access_token_expires_at'] = time() + max(60, (int)($token['expires_in'] ?? 3600) - 30);
    if (!empty($token['refresh_token'])) {
      $cfg['refresh_token'] = (string)$token['refresh_token'];
    }
    if (trim((string)($cfg['refresh_token'] ?? '')) === '') {
      throw new RuntimeException('ไม่ได้รับ refresh_token — ถอนสิทธิ์แอปที่ Google Account แล้วลองใหม่ (ต้องได้ consent อีกครั้ง)');
    }
    if ($cfg['client_id'] === '') $cfg['client_id'] = (string)sci_auth_config()['client_id'];
    if ($cfg['client_secret'] === '') $cfg['client_secret'] = (string)sci_auth_config()['client_secret'];
    sci_drive_migrate_save_config($cfg);
    unset($_SESSION['oauth_next']);
    header('Location: ' . sci_auth_url('migrate_drive_auth.php') . '?done=1');
    exit;
  }

  $info = sci_auth_fetch_userinfo($access);
  $email = strtolower(trim((string)($info['email'] ?? '')));
  if ($email === '') {
    throw new RuntimeException('Google ไม่ส่งอีเมลกลับมา');
  }
  if (!sci_auth_user_allowed($email)) {
    throw new RuntimeException('not_allowed');
  }

  $name = (string)($info['name'] ?? $email);
  $picture = (string)($info['picture'] ?? '');
  $sub = (string)($info['sub'] ?? '');

  $staff = sci_rbac_resolve_login($email, $name, $sub);
  sci_auth_set_user(sci_rbac_session_payload($staff, [
    'email' => $email,
    'name' => $name,
    'picture' => $picture,
    'sub' => $sub,
  ]));

  $next = (string)($_SESSION['oauth_next'] ?? 'index.php');
  unset($_SESSION['oauth_next']);
  header('Location: ' . sci_auth_sanitize_next($next));
  exit;
} catch (Throwable $e) {
  $msg = $e->getMessage();
  $driveHint = !empty($_SESSION['oauth_drive_migrate'])
    || ((string)($_SESSION['oauth_purpose'] ?? '') === 'drive_migrate')
    || str_contains($msg, 'refresh_token')
    || str_contains((string)($_GET['state'] ?? ''), 'drive_migrate');
  if ($driveHint) {
    unset($_SESSION['oauth_drive_migrate'], $_SESSION['oauth_purpose']);
    header('Location: ' . sci_auth_url('migrate_drive_auth.php') . '?error=' . rawurlencode($msg));
    exit;
  }
  $known = ['state', 'not_allowed', 'config', 'access_denied', 'no_role', 'not_staff'];
  $q = in_array($msg, $known, true) ? $msg : ('เข้าสู่ระบบไม่สำเร็จ: ' . $msg);
  header('Location: ' . sci_auth_url('login.php') . '?error=' . rawurlencode($q));
  exit;
}
