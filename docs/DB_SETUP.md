# ฐานข้อมูล `sciweekshop`

## สิ่งที่มีแล้ว

1. **Schema** — `sql/001_schema.sql`
2. **Connection** — `db.php` (+ `data/db_config.example.php`)
3. **นำเข้าปี 2569 / 2568** — `migrate_import_2569.php` + `migrate_import_2568.php`
   - ปี 2568 จากประกาศ `Shop-Sci-Week 2025-signed.pdf` (`data/alumni_2568.json`) → event `sciweek-2568`
4. **API อ่าน/เขียน MySQL** — `db_data_lib.php` โหลดจาก `api.php`
   - `data` / `rebuild` / `save_status` / `save_payment` / `assign_shop` / `unassign_shop` / `shop_report` / export docx → MySQL
   - `upload` → นำเข้า Excel เข้า MySQL ของรอบปัจจุบัน (เก็บไฟล์สำรองด้วย)
   - ปิดชั่วคราว: ตั้ง env `SCI_USE_MYSQL=0` (กลับไป Excel/JSON)
5. **RBAC** — `rbac_lib.php`
   - สิทธิ์: `admin` / `committee` (กรรมการฝ่ายจัดหารายได้) / `finance` / `vendor`
   - เข้าใช้งานได้เฉพาะบัญชีที่ admin กำหนดสิทธิ์จาก eoffice
   - `committee` ทำงานได้เหมือน admin ยกเว้น จัดการผู้ใช้งาน และดูบันทึกการใช้งาน
   - ผู้ที่ไม่มีสิทธิ์จะเห็นข้อความ: ระบบนี้ใช้สำหรับกรรมการฝ่ายจัดหารายได้เท่านั้น…
6. **จัดการกิจกรรม/ล็อก (เฟส 2)** — `event_admin_lib.php` + แท็บ **กิจกรรม**
   - CRUD กิจกรรม / รอบ (วันเปิด–ปิด) / โซน / ล็อก
   - คัดลอกโครงสร้างจากปี/กิจกรรมอื่น
   - แก้ประเภทล็อก + สลับผู้ได้รับสิทธิ์ระหว่างล็อก
   - ตั้งกิจกรรมที่ใช้งาน (`is_active`)
7. **พอร์ทัลสมัคร Vendor (เฟส 3)** — `apply.php` + `apply_api.php` + `vendor_apply_lib.php`
   - สมัครออนไลน์ทีละขั้น + อัปโหลดไฟล์เข้า `applicant_files` (`data/uploads/…`)
   - เปิด/ปิดรับอัตโนมัติตาม `apply_open_at` / `apply_close_at` และธง `is_open`
   - ตรวจสถานะด้วยเบอร์โทร
   - เจ้าหน้าที่เปิดไฟล์ผ่าน `file_serve.php` (ต้อง login)
8. **แจ้งเตือนการเงิน (เฟส 4)** — unpaid → เตือนปีถัดไป
   - คัดเลือกแล้ว + สถานะชำระเงิน ถูกซิงก์เข้า `alumni_vendors.payment_status`
   - API `alumni_sync` + บันทึกชำระเงินอัตโนมัติอัปเดต alumni
   - UI: แบนเนอร์แจ้งเตือน / กรองเจ้าเดิมค้างชำระ / รายการในแท็บการเงิน
   - จับคู่ “เจ้าเดิม” เฉพาะ alumni ปีก่อนหน้ากิจกรรมที่เปิดอยู่
9. **MinIO / S3 เก็บไฟล์ภาพ** — `s3_lib.php` + `data/minio_config.php`
   - อัปโหลดจากพอร์ทัลสมัครรับเฉพาะ JPG/PNG/WEBP → bucket `sci-shop`
   - `file_serve.php` ดึงจาก MinIO (หรือ local fallback)
   - ย้ายไฟล์จาก Google Drive (Excel รอบ 1–3): `migrate_drive_to_minio.php`

## คำสั่ง schema / import

```bat
cd c:\xampp\htdocs\sci_shop
C:\xampp\php\php.exe apply_schema.php
C:\xampp\php\php.exe migrate_import_2569.php
C:\xampp\php\php.exe migrate_import_2568.php
```

## MinIO

1. คัดลอก `data/minio_config.example.php` → `data/minio_config.php` แล้วใส่คีย์
2. อัปโหลดใหม่จาก `apply.php` เป็นภาพเท่านั้น (JPG/PNG/WEBP) → bucket `sci-shop`
3. ย้ายไฟล์ Google Drive จาก Excel (รอบ 1–3) — ไฟล์ส่วนใหญ่เป็น private ต้อง OAuth บัญชีเจ้าของ Form:

```bat
REM ใช้ redirect URI เดียวกับ login (auth_callback.php) — ไม่ต้องเพิ่ม URI ใหม่
REM เปิด Google Drive API ในโปรเจกต์ Google Cloud ก่อน
REM แล้วเปิดเบราว์เซอร์:
REM   http://localhost/sci_shop/migrate_drive_auth.php
REM ล็อกอินด้วยบัญชีเจ้าของ Google Form / Drive

C:\xampp\php\php.exe migrate_drive_to_minio.php --reset-bad
C:\xampp\php\php.exe migrate_drive_to_minio.php
C:\xampp\php\php.exe migrate_drive_to_minio.php --local-too
```

## แผนขั้นถัดไป

| กลุ่ม | งาน |
|--------|------|
| ops | สำรองข้อมูล / ตรวจสิทธิ์ bucket `sci-shop` บน production |
