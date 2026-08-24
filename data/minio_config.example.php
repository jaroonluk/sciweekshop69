<?php
/**
 * Example MinIO / S3-compatible storage config (Laravel-style).
 * Copy to data/minio_config.php and fill in values (gitignored).
 *
 * Equivalent Laravel .env:
 *   MINIO_ENDPOINT=s3.sc.kku.ac.th
 *   MINIO_PORT=9000
 *   MINIO_USE_SSL=true
 *   MINIO_INSECURE_SKIP_VERIFY=true
 *   MINIO_ACCESS_KEY=
 *   MINIO_SECRET_KEY=
 *   MINIO_BUCKET=sci-shop
 *
 * Filesystem disk (path-style, skip TLS verify when self-signed):
 *   'endpoint' => (MINIO_USE_SSL ? 'https' : 'http').'://'.MINIO_ENDPOINT.':'.MINIO_PORT
 *   'use_path_style_endpoint' => true
 *   'http' => ['verify' => !MINIO_INSECURE_SKIP_VERIFY]
 */
return [
  'endpoint' => 's3.sc.kku.ac.th',
  'port' => 9000,
  'use_ssl' => true,
  'insecure_skip_verify' => true,
  'access_key' => 'YOUR_ACCESS_KEY',
  'secret_key' => 'YOUR_SECRET_KEY',
  'bucket' => 'sci-shop',
  'region' => 'us-east-1',
  'use_path_style_endpoint' => true,
  'prefix' => '',
];
