-- Add committee role (safe to re-run)
USE sciweekshop;
INSERT INTO roles (id, code, name_th) VALUES
  (4, 'committee', 'กรรมการฝ่ายจัดหารายได้')
ON DUPLICATE KEY UPDATE name_th = VALUES(name_th);
