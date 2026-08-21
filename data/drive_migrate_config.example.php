<?php
/**
 * Example Drive migrate OAuth config (optional overrides).
 * Prefer running migrate_drive_auth.php which writes data/drive_migrate_config.php.
 */
return [
  'refresh_token' => '',
  'access_token' => '',
  'access_token_expires_at' => 0,
  // Leave empty to reuse data/auth_secrets.php client_id/secret
  'client_id' => '',
  'client_secret' => '',
];
