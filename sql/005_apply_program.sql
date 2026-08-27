-- Per-event apply program (SCiWEEK vs SCiSQUARE). Safe if already patched via db.php.
USE sciweekshop;

ALTER TABLE events
  ADD COLUMN IF NOT EXISTS apply_program VARCHAR(16) NOT NULL DEFAULT 'sciweek'
    COMMENT 'sciweek | scisquare';

ALTER TABLE applicant_files
  MODIFY COLUMN file_type VARCHAR(32) NOT NULL
    COMMENT 'id_card|house_reg|photo|food|company_cert|prop_menu|prop_mgmt|prop_ops|prop_exp|prop_extra|other';
