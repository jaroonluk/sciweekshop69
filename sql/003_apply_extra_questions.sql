-- Extra apply-form questions (per round). Safe to re-run.
USE sciweekshop;

ALTER TABLE event_rounds
  ADD COLUMN IF NOT EXISTS ask_high_power TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'ถามการใช้ไฟฟ้ากำลังสูงในใบสมัคร',
  ADD COLUMN IF NOT EXISTS ask_ice_bucket TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'ถามการใช้ถังน้ำแข็งในใบสมัคร';

ALTER TABLE applicants
  ADD COLUMN IF NOT EXISTS need_high_power TINYINT(1) NULL
    COMMENT '1=ใช้ไฟฟ้ากำลังสูง 0=ไม่ใช้ NULL=ไม่ถาม',
  ADD COLUMN IF NOT EXISTS ice_bucket_count SMALLINT UNSIGNED NULL
    COMMENT 'จำนวนถังน้ำแข็ง NULL=ไม่ถาม 0=ไม่ใช้';
