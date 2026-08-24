-- Per-round apply form flow. Safe if column already exists via db.php patch.
USE sciweekshop;

ALTER TABLE event_rounds
  ADD COLUMN IF NOT EXISTS apply_flow VARCHAR(32) NOT NULL DEFAULT 'zone_then_category'
    COMMENT 'zone_then_category | category_only';
