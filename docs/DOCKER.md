# รัน SCI Shop ด้วย Docker

## ความต้องการ

- Docker Desktop / Docker Engine + Compose v2

## เริ่มต้นเร็ว

```bat
cd c:\xampp\htdocs\sci_shop
copy .env.example .env
docker compose up -d --build
```

เปิดเบราว์เซอร์:

| หน้า | URL |
|------|-----|
| พอร์ทัลสมัคร | http://localhost:8088/apply.php |
| ระบบ Admin | http://localhost:8088/login.php |
| MySQL (host) | `127.0.0.1:3308` user `sci` / `sci` db `sciweekshop` |

ค่าเริ่มต้นเก็บไฟล์อัปโหลดที่ `data/uploads` ใน volume (ไม่บังคับ MinIO)

## บริการใน compose

- **app** — PHP 8.5.12 + Apache
- **db** — MariaDB 11.4 (โหลด `sql/001_schema.sql` … `005_*.sql` ตอนสร้าง volume ครั้งแรก)
- **minio** (optional profile) — เปิดด้วยคำสั่งด้านล่าง

```bat
set SCI_ENABLE_MINIO=1
docker compose --profile minio up -d --build
```

## Admin login (Google OAuth)

1. ใส่ `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` ใน `.env`
2. ใน Google Cloud Console เพิ่ม Authorized redirect URI:
   - `http://localhost:8088/auth_callback.php`
3. ใส่ email ใน `SCI_ALLOWED_EMAILS` (คั่นด้วย comma) เพื่อ bootstrap เป็น admin ครั้งแรก
4. `docker compose up -d --force-recreate app`

ถ้ายังไม่มี OAuth — หน้าสมัคร (`apply.php`) ใช้ได้ตามปกติ แต่เข้า Admin ไม่ได้

## คำสั่งที่ใช้บ่อย

```bat
docker compose logs -f app
docker compose exec app php apply_schema.php
docker compose down
docker compose down -v
```

`down -v` จะลบข้อมูล DB / MinIO / uploads ใน volume

## โครงสร้างที่เกี่ยวกับ Docker

- `Dockerfile` — image PHP Apache
- `docker-compose.yml` — orchestration
- `docker/entrypoint.sh` — รอ DB, สร้าง config, ใส่ schema สำรอง
- `db.php` — อ่าน `SCI_DB_*` จาก environment
