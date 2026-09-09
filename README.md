# ระบบ ปพ.5 — Multi-School Foundation

Foundation Milestone 1 ใช้ PHP 8.2-compatible, FastRoute, PDO และ PHP Session
บน MAMP MySQL 8 โดย SQL รองรับ MariaDB ด้วย ไม่ใช้ Laravel, Node.js server,
Redis, cron หรือ database triggers

## Local setup

ใช้ PHP CLI 8.2 ขึ้นไปจาก MAMP และ Composer โดยรันคำสั่งจาก project root
ตรวจด้วย `php -v` ว่า terminal ใช้ PHP รุ่นที่ต้องการและมี `pdo_mysql`

1. Start MAMP Apache/MySQL ตั้ง Apache Document Root เป็นโฟลเดอร์ `htdocs`
   ภายใน repository นี้ เปิดใช้ `mod_rewrite` และอนุญาต `.htaccess`
   เพื่อให้ routing และ private-path protection ทำงาน
2. สร้างฐาน `pp5` และ `pp5_test` ใน phpMyAdmin โดยใช้ `utf8mb4`:

   ```sql
   CREATE DATABASE pp5 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE pp5_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Copy local configuration:

   ```sh
   cp htdocs/config/local.example.php htdocs/config/local.php
   ```

4. ใส่ host, port และ credentials ของ MAMP MySQL ใน `htdocs/config/local.php`
   โดยตั้ง database เป็น `pp5` ไฟล์นี้ถูก Git ignore ห้าม commit credentials
5. Install Composer dependencies:

   ```sh
   cd htdocs && composer install && cd ..
   ```

6. Run migrations สำหรับ development และ automated tests:

   ```sh
   php tools/migrate.php
   php tools/migrate.php --database=pp5_test
   ```

   คำสั่งแรกใช้ฐานจาก local config; เมื่อตั้งเป็น `pp5` จะเทียบเท่ากับ
   `php tools/migrate.php --database=pp5` ตัว runner บันทึกไฟล์ที่ทำแล้วไว้ใน
   `schema_migrations` จึงรันซ้ำได้โดยไม่ apply migration เดิมซ้ำ

7. Run seed SQL เมื่อมีการเพิ่ม role/permission seed ปัจจุบันยังไม่มี seed
   หรือบัญชีเริ่มต้นใน repository อย่าสมมติว่ามี default login
8. Run PHPUnit:

   ```sh
   htdocs/vendor/bin/phpunit
   ```

   Feature tests ใช้ `pp5_test` และ rollback fixtures หลังแต่ละ test
   ต้องเปิด MAMP MySQL และ migrate `pp5_test` ก่อน
9. เปิด MAMP site ที่ตั้งไว้ เช่น `http://localhost:8888/` และ `/login`
   การทดสอบผ่าน browser ใช้ฐาน `pp5` จาก local config

## Login และ school context

บัญชีที่จะ login ต้องเป็น `ACTIVE` มี password hash ที่สร้างด้วย
`password_hash()` และมี `ACTIVE` membership ในโรงเรียนที่ `ACTIVE` เพียงหนึ่งแห่ง
ระบบใช้ `password_verify()` และกำหนด school จากฐานข้อมูลโดยอัตโนมัติ
ไม่มี school chooser และไม่รับ school identifier จาก browser มาใช้เลือก tenant
หากไม่มี membership ที่ใช้งานได้หรือมีมากกว่าหนึ่งแห่ง ระบบจะปฏิเสธ login

`GET /dashboard` ผ่าน `AuthMiddleware → SchoolContextMiddleware → DashboardController`
และตรวจ user, membership และ school status ใหม่ทุก protected request
การระงับบัญชีหรือ membership จึงมีผลกับ request ถัดไป
Dashboard แสดงชื่อโรงเรียนที่ได้รับมอบหมาย ชื่อผู้ใช้ และ logout form ที่มี CSRF
Logout ใช้ `POST /logout` เท่านั้น

Session ID เปลี่ยนหลัง login/logout; cookie มี HttpOnly, SameSite=Lax และ Path=/
ตั้ง Secure เมื่อ request เป็น HTTPS เพื่อให้ local HTTP ยังทำงานได้
Application ตอบ friendly 403/404 และข้อความทั่วไปเมื่อเกิด exception
โดยไม่ส่ง SQL, credentials, stack trace หรือ filesystem paths ให้ browser

## การตรวจผ่าน MAMP

ใช้บัญชีและโรงเรียนทดสอบในฐาน `pp5`: สร้าง School A/B แล้วให้ user มี ACTIVE
membership เฉพาะ A ตรวจ login, dashboard ของ A, logout และการปฏิเสธเมื่อแก้
session ไป B หรือระงับ membership/user ลบเฉพาะ fixtures ที่สร้างทดสอบหลังจบ
Automated tests ยังคงใช้ `pp5_test` แยกจากการตรวจ HTTP

Private directories `app`, `config`, `routes`, `views`, `storage`, `vendor`
ต้องตอบ HTTP 403 เมื่อเรียกตรง เช่น `/config/database.php`, `/app/Application.php`,
`/views/dashboard/index.php` และ `/vendor/autoload.php`

Milestone นี้ยังไม่มีหน้าจัดการโรงเรียน/บัญชีผู้ใช้ ข้อมูลวิชาการ และรายงาน
ส่วนดังกล่าวเป็นงาน milestone ถัดไป
