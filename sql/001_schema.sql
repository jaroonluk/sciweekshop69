-- SCI Week Shop — schema for database `sciweekshop`
-- Charset: utf8mb4 · Engine: InnoDB
-- Linked personnel source: eoffice.tbluser (same localhost)
-- Apply: mysql -u root --default-character-set=utf8mb4 < sql/001_schema.sql

SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE DATABASE IF NOT EXISTS sciweekshop
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE sciweekshop;

-- Drop in dependency order for clean re-apply
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS applicant_files;
DROP TABLE IF EXISTS applicants;
DROP TABLE IF EXISTS alumni_vendors;
DROP TABLE IF EXISTS slots;
DROP TABLE IF EXISTS zones;
DROP TABLE IF EXISTS event_rounds;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS settings;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- Roles & staff users (admin / finance) — pick people from eoffice.tbluser
-- ---------------------------------------------------------------------------
CREATE TABLE roles (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(32) NOT NULL,
  name_th VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (id, code, name_th) VALUES
  (1, 'admin', 'ผู้ดูแลระบบ'),
  (2, 'finance', 'เจ้าหน้าที่การเงิน'),
  (3, 'vendor', 'พ่อค้าแม่ค้า / ผู้สมัคร'),
  (4, 'committee', 'กรรมการฝ่ายจัดหารายได้');

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id TINYINT UNSIGNED NOT NULL,
  eoffice_username VARCHAR(10) NULL COMMENT 'link eoffice.tbluser.username',
  email VARCHAR(120) NULL,
  display_name VARCHAR(200) NOT NULL DEFAULT '',
  google_sub VARCHAR(64) NULL,
  password_hash VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_eoffice (eoffice_username),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_google (google_sub),
  KEY idx_users_role (role_id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Events (กิจกรรมรับสมัครร้านค้า) — many per year
-- ---------------------------------------------------------------------------
CREATE TABLE events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL,
  title VARCHAR(255) NOT NULL,
  year_be SMALLINT UNSIGNED NOT NULL COMMENT 'พ.ศ. เช่น 2569',
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_events_code (code),
  KEY idx_events_year (year_be)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_rounds (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id INT UNSIGNED NOT NULL,
  round_no TINYINT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  apply_open_at DATETIME NULL COMMENT 'เปิดรับสมัคร',
  apply_close_at DATETIME NULL COMMENT 'ปิดรับสมัคร',
  is_open TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_event_round (event_id, round_no),
  CONSTRAINT fk_rounds_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE zones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id INT UNSIGNED NOT NULL,
  code CHAR(1) NOT NULL COMMENT 'A/B/C/D/...',
  name_th VARCHAR(100) NOT NULL,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_zone_event_code (event_id, code),
  CONSTRAINT fk_zones_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE slots (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id INT UNSIGNED NOT NULL,
  zone_id INT UNSIGNED NOT NULL,
  code VARCHAR(10) NOT NULL COMMENT 'A8, B6, ...',
  category VARCHAR(255) NOT NULL,
  slot_limit TINYINT UNSIGNED NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slot_event_code (event_id, code),
  KEY idx_slots_zone (zone_id),
  CONSTRAINT fk_slots_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_slots_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Applicants
-- selection: รอพิจารณา | ได้รับการคัดเลือก | ไม่ได้รับการคัดเลือก
-- payment_status: unpaid | paid
-- ---------------------------------------------------------------------------
CREATE TABLE applicants (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id INT UNSIGNED NOT NULL,
  round_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL COMMENT 'vendor account if self-registered',
  legacy_excel_row INT NULL COMMENT 'row from old Excel import',
  applied_at DATETIME NOT NULL,
  name VARCHAR(200) NOT NULL,
  phone VARCHAR(40) NOT NULL DEFAULT '',
  zone_code CHAR(1) NOT NULL DEFAULT '',
  category VARCHAR(255) NOT NULL DEFAULT '',
  detail TEXT NULL,
  qualifications TEXT NULL,
  doc_status VARCHAR(40) NOT NULL DEFAULT 'รอตรวจสอบ',
  missing_detail TEXT NULL,
  review_note TEXT NULL,
  reviewed_at DATETIME NULL,
  selection VARCHAR(40) NOT NULL DEFAULT 'รอพิจารณา',
  assigned_slot_id INT UNSIGNED NULL,
  payment_status VARCHAR(16) NOT NULL DEFAULT 'unpaid',
  payment_at DATETIME NULL,
  payment_note VARCHAR(255) NULL,
  is_returning TINYINT(1) NOT NULL DEFAULT 0,
  alumni_year SMALLINT UNSIGNED NULL,
  alumni_slot VARCHAR(10) NULL,
  alumni_category VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_app_event_round (event_id, round_id),
  KEY idx_app_selection (selection),
  KEY idx_app_payment (payment_status),
  KEY idx_app_name (name),
  KEY idx_app_phone (phone),
  KEY idx_app_legacy (event_id, round_id, legacy_excel_row),
  CONSTRAINT fk_app_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_app_round FOREIGN KEY (round_id) REFERENCES event_rounds(id) ON DELETE CASCADE,
  CONSTRAINT fk_app_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_app_slot FOREIGN KEY (assigned_slot_id) REFERENCES slots(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE applicant_files (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  applicant_id INT UNSIGNED NOT NULL,
  file_type VARCHAR(16) NOT NULL COMMENT 'id_card|house_reg|photo|food|other',
  original_name VARCHAR(255) NOT NULL DEFAULT '',
  stored_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NULL,
  size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
  drive_url VARCHAR(500) NULL COMMENT 'legacy Google Drive link from Excel',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_files_app (applicant_id),
  KEY idx_files_type (applicant_id, file_type),
  CONSTRAINT fk_files_app FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alumni_vendors (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  year_be SMALLINT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  aliases TEXT NULL COMMENT 'JSON array of alternate names',
  slot_code VARCHAR(10) NULL,
  category VARCHAR(255) NULL,
  event_label VARCHAR(255) NULL,
  source_ref VARCHAR(255) NULL,
  payment_status VARCHAR(16) NOT NULL DEFAULT 'unknown',
  PRIMARY KEY (id),
  KEY idx_alumni_year (year_be),
  KEY idx_alumni_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  action VARCHAR(64) NOT NULL,
  entity_type VARCHAR(64) NULL,
  entity_id INT UNSIGNED NULL,
  detail_json JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user (user_id),
  KEY idx_audit_entity (entity_type, entity_id),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  setting_key VARCHAR(64) NOT NULL,
  setting_value TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value) VALUES
  ('upload_max_mb', '10'),
  ('upload_allowed_mimes', 'image/jpeg,image/png,image/webp,application/pdf');
