<?php
/**
 * Example Google OAuth secrets.
 * Copy to data/auth_secrets.php and fill in values.
 */
return [
  'client_id' => 'YOUR_CLIENT_ID.apps.googleusercontent.com',
  'client_secret' => 'YOUR_CLIENT_SECRET',
  'allowed_emails' => [],
  // Emergency only: emails here may bootstrap as admin if not yet granted.
  // Normal access requires an admin to add the person under แท็บผู้ใช้งาน.
  'allowed_domains' => [],

  // Optional: lock public URL per host (recommended on reverse-proxy / subdirectory installs)
  // Google OAuth treats localhost and 127.0.0.1 as DIFFERENT redirect URIs — register both.
  'public_base_urls' => [
    'localhost' => 'http://localhost/sci_shop',
    '127.0.0.1' => 'http://127.0.0.1/sci_shop',
    'sci.kku.ac.th' => 'https://sci.kku.ac.th/app/sciweekshop69',
  ],

  // Or force one base URL everywhere:
  // 'public_base_url' => 'https://sci.kku.ac.th/app/sciweekshop69',

  // Authorized redirect URIs to add in Google Cloud Console (exact match):
  //   http://127.0.0.1/sci_shop/auth_callback.php
  //   http://localhost/sci_shop/auth_callback.php
  //   https://sci.kku.ac.th/app/sciweekshop69/auth_callback.php
];
