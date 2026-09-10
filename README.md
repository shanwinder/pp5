# ระบบ ปพ.5 — School Administration

ฐาน Milestone 2 และ Milestone 3 Task 1 ใช้ PHP 8.2-compatible, FastRoute, PDO และ PHP Session บน MAMP MySQL 8
โดย SQL รองรับ MariaDB ด้วย ไม่ใช้ Laravel, Node.js backend, Redis, queue, cron
หรือ database triggers

## Local setup

รันคำสั่งจาก project root ใช้ PHP CLI 8.2 ขึ้นไปจาก MAMP และ Composer
ตรวจ `php -v` และให้มี extension `pdo_mysql`

1. สำหรับการเริ่ม milestone จากฐานที่อนุมัติแล้ว:

   ```sh
   git checkout main && git pull
   git checkout -b milestone/3-academic-structure
   ```

   สร้าง branch เพียงครั้งแรก หากมี branch นี้อยู่แล้วให้ checkout branch เดิม
   ขั้นตอนนี้เป็นคำแนะนำสำหรับ setup ไม่ใช่คำสั่งให้ merge milestone เข้าสู่ main

2. Start MAMP Apache/MySQL ตั้ง Apache Document Root เป็น `htdocs` ภายใน repository
   เปิด `mod_rewrite` และอนุญาต `.htaccess` เพื่อให้ routes และ private paths ทำงาน
   สร้างฐานข้อมูลใน phpMyAdmin:

   ```sql
   CREATE DATABASE pp5 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE pp5_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Copy local configuration และใส่ host, port, credentials ของ MAMP MySQL
   ตั้ง database เป็น `pp5` ใน `htdocs/config/local.php` ซึ่งถูก Git ignore:

   ```sh
   cp htdocs/config/local.example.php htdocs/config/local.php
   ```

   ห้าม commit local credentials

4. Install dependencies แล้ว migrate/seed development ตามด้วย test database:

   ```sh
   cd htdocs && composer install && cd ..
   php tools/migrate.php
   php tools/seed.php
   php tools/migrate.php --database=pp5_test
   php tools/seed.php --database=pp5_test
   ```

   คำสั่งที่ไม่ระบุ database ใช้ค่าจาก local config ซึ่งต้องตั้งเป็น `pp5`
   Runner บันทึกไฟล์ที่ apply แล้วใน `schema_migrations` และ `seed_migrations`
   จึงรันซ้ำได้ โดยต้องรัน migrations ก่อน seeds เสมอ ไฟล์ถูก apply ตามลำดับชื่อ
   รวม migration `20260910_001_academic_structure.sql` และ seed
   `20260910_001_academic_structure_reference.sql` หลังไฟล์ของ Milestone 1–2

   Seeded baseline มี **7 roles, 14 permissions และ 19 role-permission mappings**:
   SYSTEM_ADMIN ได้ 3 SYSTEM permissions เดิม ส่วน SCHOOL_ADMIN ได้ 6 SCHOOL
   administration permissions เดิมและ 5 academic permissions ใหม่;
   ACADEMIC_ADMIN ได้ 5 academic permissions เดียวกัน ได้แก่ `ACADEMIC_SETUP_VIEW`,
   `ACADEMIC_YEAR_MANAGE`, `CLASSROOM_MANAGE`, `SUBJECT_MANAGE`, `SUBJECT_OFFERING_MANAGE`
   HOMEROOM_TEACHER, SUBJECT_TEACHER, EXECUTIVE และ VIEWER ไม่ได้รับสิทธิ์ทั้งห้านี้

   Global grade levels มีเฉพาะ P1–P6 (ประถมศึกษาปีที่ 1–6), sort_order 10–60
   เพิ่มครั้งละ 10 และ status ACTIVE ทุกแถว Seed SQL รันซ้ำได้โดยไม่เพิ่มแถวซ้ำ

5. Bootstrap SYSTEM_ADMIN สำหรับเครื่อง local หลัง seed โดยผู้ดูแลระบุ credentials เอง
   แทนค่าตัวอย่างทั้งหมดก่อนรัน (`--email` ไม่บังคับ):

   ```sh
   php tools/bootstrap_system_admin.php \
     --username="<username>" \
     --display-name="<display name>" \
     --password="<password with at least 12 characters>" \
     --email="<optional email>"
   ```

   Bootstrap สร้างบัญชีใหม่พร้อม global SYSTEM_ADMIN แบบ transaction เดียว
   ไม่สร้าง school membership และไม่เชื่อมบัญชีเดิมเมื่อ username/email ซ้ำ
   ต้องมี ACTIVE SYSTEM_ADMIN role จาก seed ก่อน ชื่อผู้ใช้มี 3–100 ตัวอักษร
   ใช้ A-Z, a-z, 0-9, จุด, ขีดล่างหรือขีดกลาง ชื่อที่แสดงมี 1–190 ตัวอักษร
   email ถ้าระบุต้องถูกต้อง และรหัสผ่านอย่างน้อย 12 bytes โดยไม่ตัดช่องว่าง
   Output สำเร็จแสดงเพียง `Created SYSTEM_ADMIN user: <username>`

   ไม่มี default credentials หรือบัญชีเริ่มต้นที่ commit ไว้ใน repository
   ตัวเลือก `--database=pp5_test` มีไว้สำหรับ **TEST USE เท่านั้น** เพื่อแยกจาก `pp5`
   และต้องล้างบัญชีทดสอบหลังใช้

   Script เป็น **CLI-only**, อยู่ใน `tools/` นอก `htdocs` ไม่มี web bootstrap endpoint
   ห้ามย้ายเข้า web root หรือเปิดผ่าน HTTP ไม่สร้าง wrapper/symlink เพื่อเรียกผ่านเว็บ
   Bootstrap บน InfinityFree production จะดำเนินการภายหลังด้วยกระบวนการ
   phpMyAdmin/manual deployment ที่ได้รับอนุมัติ ไม่ใช้ HTTP bootstrap

6. Run automated tests แล้วตรวจ PHP syntax ทุกไฟล์ของ project ยกเว้น vendor:

   ```sh
   htdocs/vendor/bin/phpunit
   find . -path './htdocs/vendor' -prune -o -name '*.php' -type f -exec php -l {} \;
   git diff --check
   ```

   Automated feature tests ใช้ `pp5_test` เท่านั้น ต้องเปิด MAMP MySQL และ migrate/seed
   ก่อนรัน Tests ใช้ rollback หรือ cleanup เฉพาะ fixtures ที่ตนสร้าง

7. เปิด MAMP เช่น `http://localhost:8888/login` และทำ smoke test บน `pp5`
   ตามรายการด้านล่าง แยกจาก automated tests

## Authentication และ authorization

บัญชีต้อง ACTIVE และตรวจรหัสผ่านด้วย `password_verify()`
ระบบนับ ACTIVE membership rows ก่อนตรวจสถานะโรงเรียน:

- ACTIVE SYSTEM_ADMIN แบบ global ต้องมี **ศูนย์ ACTIVE memberships** เท่านั้น
  ถ้ามี ACTIVE membership แม้โรงเรียน SUSPENDED/INACTIVE ก็ปฏิเสธ login
  Login สำเร็จได้ SYSTEM context และไป `/system/schools` โดยไม่มี school context
- ผู้ใช้โรงเรียนต้องมี **หนึ่ง ACTIVE membership row พอดี**, โรงเรียน ACTIVE
  และมี ACTIVE SCHOOL role สำหรับโรงเรียนนั้น หากมีสอง ACTIVE rows แม้อีกโรงเรียน
  ถูกระงับ ก็ปฏิเสธ login Login สำเร็จได้ SCHOOL context และไป `/dashboard`

ไม่มี school chooser และไม่รับ `school_id` จาก browser มาเลือก tenant
User, school และ membership ถูกตรวจซ้ำใน protected requests จึงระงับสิทธิ์ได้ทันที

SYSTEM routes ใช้ `Auth → Permission → handler` และไม่ผ่าน SchoolContextMiddleware
SCHOOL admin routes ใช้ `Auth → SchoolContext → Permission → handler`
Dashboard ใช้ AuthorizationService ตรวจ `SCHOOL_USER_VIEW` ก่อนแสดงลิงก์
“จัดการผู้ใช้” ไป `/admin/users` ส่วน PermissionMiddleware เป็นตัวบังคับสิทธิ์จริง
ผู้ไม่มีสิทธิ์เปิด URL โดยตรงจะได้ 403

School Admin จัดการเฉพาะผู้ใช้ในโรงเรียนจาก session: สร้างผู้ใช้, แก้ชื่อที่แสดง/email,
ระงับหรือเปิด membership, เปลี่ยน SCHOOL roles และตั้งรหัสผ่านใหม่
Username เปลี่ยนไม่ได้ ห้ามมอบ SYSTEM_ADMIN, ระงับตนเอง หรือนำ SCHOOL_ADMIN ของตนเองออก
Role ที่นำออกเปลี่ยนเป็น INACTIVE รวม assignment ที่ role definition ไม่ active แล้ว
หรือเปลี่ยน scope ไม่ลบประวัติและไม่กระทบ assignment รายปี

POST mutations ทุก route มี CSRF การบันทึกหลายขั้นตอนเป็น transaction และมี audit
รหัสผ่านเก็บเป็น hash เท่านั้น Audit ตั้งรหัสผ่านใหม่บันทึกเพียงเหตุการณ์
`{"password_reset": true}` ไม่มี plaintext/hash ใน audit หรือข้อความ error
Session ID เปลี่ยนหลัง login/logout; cookie มี HttpOnly, SameSite=Lax, Path=/
และ Secure เมื่อเป็น HTTPS Logout ใช้ `POST /logout`
Application ไม่ส่ง SQL, credentials, stack trace หรือ filesystem paths ให้ browser

## MAMP smoke test และ cleanup

ใช้ชื่อ fixtures ชั่วคราวที่ไม่ชนข้อมูลจริง จด user/school IDs และเก็บ baseline ก่อนเริ่ม:

1. Seed roles/permissions และ bootstrap SYSTEM_ADMIN ผ่าน CLI
2. SYSTEM login ไป `/system/schools`; สร้าง School A/B พร้อม admin คนแรก
3. ระงับ School B ตรวจว่าบัญชี B ถูกปฏิเสธ แล้วเปิดโรงเรียนกลับ
4. A admin login ไป dashboard เห็นลิงก์จัดการผู้ใช้และเข้า `/admin/users`
5. สร้างครู SUBJECT_TEACHER: login ได้ แต่ไม่มีลิงก์จัดการผู้ใช้และเข้า URL นี้ได้ 403
6. ตั้งรหัสผ่านครูใหม่: รหัสเดิมใช้ไม่ได้ รหัสใหม่ใช้ได้
7. ระงับ membership ครู: protected request ถัดไปได้ 403; เปิดกลับแล้วใช้งานได้
8. ลอง School B user ID ใน edit/profile/roles/password/membership ของ A:
   ต้องถูกปฏิเสธและไม่มีข้อมูล/audit เปลี่ยน แม้แทรก `school_id` ของ B
9. ตรวจห้าม grant SYSTEM_ADMIN, ห้าม suspend ตนเอง/ถอด SCHOOL_ADMIN ของตนเอง
   และ bad CSRF ในทุก admin mutation ต้องได้ 419 โดยไม่มี writes
10. ตรวจ logout ว่า session หมดสิทธิ์และ session ID เปลี่ยน
11. ตรวจ audit action, actor, entity, school, เวลา และ safe old/new values
    โดยไม่มี plaintext/hash แล้วลบเฉพาะ smoke fixtures ตามลำดับ FK
    คง migrations, seeds และข้อมูลจริงทั้งหมดไว้ ตรวจ baseline หลัง cleanup

Private paths ต้องตอบ HTTP 403 เช่น `/config/database.php`, `/app/Application.php`,
`/views/admin/users/index.php`, `/views/system/schools/index.php`, `/vendor/autoload.php`
`/tools/bootstrap_system_admin.php` ต้องไม่ web reachable เพราะอยู่นอก Document Root

Milestone 3 Task 1 เพิ่มเฉพาะ schema/reference infrastructure: `grade_levels`,
`academic_years`, `classrooms`, `subjects`, `subject_offerings` และ composite FK
ที่ป้องกัน parent ข้ามโรงเรียน/ข้ามปี รวม FK ของ `user_role_assignments`
ไปยังปีการศึกษาของโรงเรียนเดียวกัน การตรวจสิทธิ์เดิมยังใช้ `academic_year_id IS NULL`
ยังไม่มี academic setup UI หรือ service สำหรับจัดการโครงสร้างวิชาการ

Milestone 2 ครอบคลุม SYSTEM/SCHOOL authentication และการจัดการโรงเรียน/ผู้ใช้
ยังไม่มี school chooser, user transfer, academic setup, student/DMC workflow,
email invitation/reset, 2FA/SSO, audit browsing UI หรือ deployment automation
