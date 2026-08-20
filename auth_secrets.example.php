<?php
/**
 * Example Google OAuth secrets.
 * Copy to data/auth_secrets.php and fill in values.
 */
return [
  'client_id' => 'YOUR_CLIENT_ID.apps.googleusercontent.com',
  'client_secret' => 'YOUR_CLIENT_SECRET',
  'allowed_emails' => [],
  'allowed_domains' => [],

  // Optional: lock public URL per host (recommended on reverse-proxy / subdirectory installs)
  'public_base_urls' => [
    'localhost' => 'http://localhost/sci_shop',
    'sci.kku.ac.th' => 'https://sci.kku.ac.th/app/sciweekshop69',
  ],

  // Or force one base URL everywhere:
  // 'public_base_url' => 'https://sci.kku.ac.th/app/sciweekshop69',
];
