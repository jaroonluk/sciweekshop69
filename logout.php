<?php
require_once __DIR__ . '/auth_lib.php';
sci_auth_clear();
header('Location: ' . sci_auth_url('login.php'));
exit;
