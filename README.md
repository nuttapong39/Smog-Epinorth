# Smog-Epinorth

ระบบดึงข้อมูลผู้ป่วยกลุ่มโรคที่เกี่ยวข้องกับหมอกควัน (ICD-10 กลุ่ม I, J และอื่น ๆ) จากฐานข้อมูล **HOSxP (PostgreSQL)** แล้วเข้ารหัสส่งไปยัง **MOPH Epinorth API** — รองรับทั้งการส่งอัตโนมัติรายวันและส่งเองย้อนหลัง พร้อม Dashboard ติดตามผลและแจ้งเตือนผ่าน LINE (MOPH ALERT)

รันบน **XAMPP (Apache + PHP)** บน Windows

## คุณสมบัติ
- 🧙 **First-run setup wizard** — ตั้งค่าการเชื่อมต่อผ่านหน้าเว็บ เก็บเป็น `config.json`
- ⏰ **ส่งอัตโนมัติรายวัน** ผ่าน Windows Task Scheduler → `cron.php` + ปุ่ม "ตั้งทดสอบส่งอีก N นาที"
- ✋ **ส่งเอง** เลือกช่วงวันที่ได้ (โหมด manual / catch-up)
- 📊 **Dashboard** — สถิติ, กราฟรายวัน, สัดส่วน success/failed, ประวัติ 50 รายการ
- 📖 **หน้าวิธีการใช้งาน** พร้อมสถานะระบบแบบเรียลไทม์
- 🔔 แจ้งเตือนผลการส่งเข้า LINE ผ่าน MOPH ALERT

## การติดตั้ง
1. วางโปรเจกต์ไว้ใน `C:\xampp\htdocs\Smog-Epinorth`
2. คัดลอกไฟล์ตั้งค่าต้นแบบ แล้วใส่ค่าลับ (encryption key / hash salt / MOPH keys):
   ```bash
   cp config.example.php config.php
   ```
3. เปิด `http://localhost/Smog-Epinorth/` — ครั้งแรกจะเด้งไปหน้า **ตั้งค่าระบบ** ให้กรอกการเชื่อมต่อ PostgreSQL และ MOPH API (`USERNAME` = รหัสสถานบริการ ขึ้นต้นด้วย `u`) ระบบจะบันทึกเป็น `config.json`
4. ไปหน้า **ตั้งค่า** เพื่อกรอกชื่อโรงพยาบาล / MOPH keys และตั้งเวลาส่งอัตโนมัติ (คัดลอกคำสั่ง Windows Task Scheduler ไปวางใน Command Prompt แบบ Run as Administrator)

## โครงสร้างสำคัญ
| ไฟล์ | หน้าที่ |
|------|---------|
| `config.example.php` | ต้นแบบตั้งค่า (คัดลอกเป็น `config.php`) |
| `config_setup.php` | หน้า setup wizard ครั้งแรก / แก้ไขการเชื่อมต่อ |
| `index.php` | ส่งข้อมูลเอง (เลือกช่วงวันที่) |
| `dashboard.php` | Dashboard สถิติ + กราฟ |
| `settings.php` | ตั้งค่า รพ. / MOPH keys / ตารางเวลา + ทดสอบ |
| `guide.php` | คู่มือการใช้งานพร้อมสถานะระบบ |
| `cron.php` | endpoint สำหรับ Task Scheduler เรียกทุก 1 นาที |
| `includes/` | โค้ดหลัก: sync, notify, layout, scheduled runner |

## ⚠️ ความปลอดภัย
ไฟล์ต่อไปนี้ **ไม่ถูก commit** (อยู่ใน `.gitignore`) เพราะมีข้อมูลลับ — ต้องตั้งค่าเองในแต่ละเครื่อง:
- `config.php` — encryption key, hash salt, MOPH keys, รหัส DB
- `config.json` — รหัสผ่าน DB จริง (สร้างจากหน้า setup)
- `*.db` — `sync_log.db` (settings, cron token)
- `*.log`

Apache จะบล็อกการเข้าถึง `config.json` / `*.db` / `*.log` ผ่าน HTTP ด้วย `.htaccess`
