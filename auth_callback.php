<?php
require_once __DIR__ . '/auth_lib.php';

sci_auth_start_session();

try {
  if (!sci_auth_configured()) {
    throw new RuntimeException('config');
  }

  $error = (string)($_GET['error'] ?? '');
  if ($error !== '') {
    header('Location: ' . sci_auth_url('login.php') . '?error=' . rawurlencode($error === 'access_denied' ? 'access_denied' : $error));
    exit;
  }

  $state = (string)($_GET['state'] ?? '');
  $expected = (string)($_SESSION['oauth_state'] ?? '');
  if ($state === '' || $expected === '' || !hash_equals($expected, $state)) {
    throw new RuntimeException('state');
  }
  unset($_SESSION['oauth_state']);

  $code = (string)($_GET['code'] ?? '');
  if ($code === '') {
    throw new RuntimeException('ไม่พบรหัสยืนยันจาก Google');
  }

  $token = sci_auth_exchange_code($code);
  $access = (string)($token['access_token'] ?? '');
  if ($access === '') {
    throw new RuntimeException('ไม่ได้รับ access token จาก Google');
  }

  $info = sci_auth_fetch_userinfo($access);
  $email = strtolower(trim((string)($info['email'] ?? '')));
  if ($email === '') {
    throw new RuntimeException('Google ไม่ส่งอีเมลกลับมา');
  }
  if (!sci_auth_user_allowed($email)) {
    throw new RuntimeException('not_allowed');
  }

  sci_auth_set_user([
    'email' => $email,
    'name' => (string)($info['name'] ?? $email),
    'picture' => (string)($info['picture'] ?? ''),
    'sub' => (string)($info['sub'] ?? ''),
  ]);

  $next = (string)($_SESSION['oauth_next'] ?? 'index.php');
  unset($_SESSION['oauth_next']);
  header('Location: ' . sci_auth_sanitize_next($next));
  exit;
} catch (Throwable $e) {
  $msg = $e->getMessage();
  $known = ['state', 'not_allowed', 'config', 'access_denied'];
  $q = in_array($msg, $known, true) ? $msg : ('เข้าสู่ระบบไม่สำเร็จ: ' . $msg);
  header('Location: ' . sci_auth_url('login.php') . '?error=' . rawurlencode($q));
  exit;
}
