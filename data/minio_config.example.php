<?php
/**
 * Example MinIO / S3-compatible storage config.
 * Copy to data/minio_config.php and fill in values (gitignored).
 */
return [
  'endpoint' => 's3.sc.kku.ac.th',
  'port' => 9000,
  'use_ssl' => true,
  // true when MinIO uses a self-signed certificate
  'insecure_skip_verify' => true,
  'access_key' => 'YOUR_ACCESS_KEY',
  'secret_key' => 'YOUR_SECRET_KEY',
  'bucket' => 'sci-shop',
  // MinIO default region for SigV4
  'region' => 'us-east-1',
  // Optional path prefix inside the bucket
  'prefix' => '',
];
