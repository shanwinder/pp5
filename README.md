# ระบบ ปพ.5 — School Administration

ฐานที่พัฒนาครบ Milestone 1–3 ใช้ PHP 8.2-compatible, FastRoute, PDO และ PHP Session บน MAMP MySQL 8
โดย SQL รองรับ MariaDB ด้วย ไม่ใช้ Laravel, Node.js backend, Redis, queue, cron
หรือ database triggers

## Local setup

รันคำสั่งจาก project root ใช้ PHP CLI 8.2 ขึ้นไปจาก MAMP และ Composer
ตรวจ `php -v` และให้มี extension `pdo_mysql`

1. ใช้ branch ของ Milestone 3 ที่พัฒนาแล้ว:

   ```sh
   git checkout milestone/3-academic-structure
   ```

   การพัฒนาครบใน milestone branch ยังแยกจากการอนุมัติ review และ merge เข้า main

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

   Seeded baseline หลัง Milestone 4 Task 1 มี **7 roles, 18 permissions และ 27 role-permission mappings**:
   SYSTEM_ADMIN ได้ 3 SYSTEM permissions เดิม ส่วน SCHOOL_ADMIN ได้ 6 SCHOOL
   administration permissions เดิมและ 5 academic permissions ใหม่;
   ACADEMIC_ADMIN ได้ 5 academic permissions เดียวกัน ได้แก่ `ACADEMIC_SETUP_VIEW`,
   `ACADEMIC_YEAR_MANAGE`, `CLASSROOM_MANAGE`, `SUBJECT_MANAGE`, `SUBJECT_OFFERING_MANAGE`
   HOMEROOM_TEACHER, SUBJECT_TEACHER, EXECUTIVE และ VIEWER ไม่ได้รับสิทธิ์ทั้งห้านี้
   Milestone 4 seed เพิ่ม `STUDENT_VIEW`, `STUDENT_MANAGE`, `ENROLLMENT_MANAGE`,
   `STUDENT_IMPORT` ให้ SCHOOL_ADMIN และ ACADEMIC_ADMIN เท่านั้น โดย roles อื่นไม่ได้รับ
   สิทธิ์ทั้งสี่เพิ่ม และ global grade levels ยังคง 6 แถวเดิม

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

## โครงสร้างวิชาการ — Milestone 3

Dashboard แสดง “จัดการโครงสร้างวิชาการ” ไป `/academic/years` เมื่อ
AuthorizationService ให้ `ACADEMIC_SETUP_VIEW` และจากหน้าปีการศึกษาไปยังห้องเรียน
รายวิชาและการเปิดรายวิชาได้ การซ่อน navigation ไม่ใช่ขอบเขต authorization:
backend ตรวจ permission ทุกครั้ง แม้เปิด URL โดยตรง สิทธิ์จัดการผู้ใช้ยังแยกจากสิทธิ์วิชาการ

- **ปีการศึกษา:** `year_be` 2400–2700 ไม่ซ้ำภายในโรงเรียน สร้างเป็น `DRAFT`
  แล้วเปลี่ยนได้เฉพาะ `DRAFT → ACTIVE → CLOSED` ไม่มี reopen ใน Milestone 3
  วันที่เว้นว่างได้ขณะ DRAFT แต่ activation ต้องมีวันที่ถูกต้องทั้งคู่และ
  `start_date <= end_date` วันที่ใช้ ค.ศ. รูปแบบ `YYYY-MM-DD`: ปีของวันเริ่มต้นต้องเป็น
  `year_be - 543` และปีของวันสิ้นสุดเป็นปีเดียวกันหรือปีถัดไป
  โรงเรียนหนึ่งมี ACTIVE ได้ไม่เกินหนึ่งปี
  แก้รายละเอียดได้เฉพาะ DRAFT; ACTIVE/CLOSED แก้รายละเอียดไม่ได้
- **ห้องเรียน:** ผูกกับโรงเรียน + ปีการศึกษา + global grade level ที่แน่นอน
  `code` ไม่ซ้ำภายในโรงเรียน/ปีเดียวกัน ใช้ซ้ำต่างปีหรือต่างโรงเรียนได้
  มีสถานะ `ACTIVE / INACTIVE`; แก้ระดับชั้น/รหัส/ชื่อได้โดยไม่ย้ายปี
  ปี CLOSED ห้ามสร้าง แก้ไข หรือเปลี่ยนสถานะห้องเรียน
- **รายวิชา:** master data ของแต่ละโรงเรียน `code` ไม่ซ้ำภายในโรงเรียน
  รองรับรหัสไทย/Unicode ตรวจความยาวและห้ามอักขระควบคุม
  มีสถานะ `ACTIVE / INACTIVE`; ปิดใช้งานแล้วยังคงข้อมูลและประวัติการเปิดสอน
- **การเปิดรายวิชา:** identity คือโรงเรียน + ปีการศึกษา + ห้องเรียน + รายวิชา + ภาคเรียน
  ภาคเรียนเป็น `1` หรือ `2` เท่านั้น และ identity ต้องไม่ซ้ำแม้แถวเดิม INACTIVE
  มีสถานะ `ACTIVE / INACTIVE`; สร้าง/เปิดใช้งานอีกครั้งต้องใช้ห้องเรียนและรายวิชา ACTIVE
  ในโรงเรียนเดียวกัน ห้องเรียนต้องอยู่ในปีที่เลือก และปีต้องเป็น DRAFT หรือ ACTIVE
  แก้ไขไม่ย้ายปี; CLOSED ห้ามสร้าง แก้ไข หรือเปลี่ยนสถานะการเปิดรายวิชา

โครงสร้างในปี CLOSED ยังอ่านประวัติได้ ไม่มี hard-delete endpoint สำหรับ academic entities
ทุกชนิด การปิดปีไม่เปลี่ยนสถานะรายวิชา master ซึ่งเป็นข้อมูลระดับโรงเรียน

### Academic routes และ permissions

ทุก route ต่อไปนี้อยู่ใน SCHOOL context และใช้
`Auth → SchoolContext → Permission → handler` ตามลำดับ `{id}` รับตัวเลขเท่านั้น

| Method | Route | Permission |
| --- | --- | --- |
| GET | `/academic/years` | `ACADEMIC_SETUP_VIEW` |
| GET | `/academic/years/create` | `ACADEMIC_YEAR_MANAGE` |
| POST | `/academic/years` | `ACADEMIC_YEAR_MANAGE` |
| GET | `/academic/years/{id}/edit` | `ACADEMIC_YEAR_MANAGE` |
| POST | `/academic/years/{id}` | `ACADEMIC_YEAR_MANAGE` |
| POST | `/academic/years/{id}/status` | `ACADEMIC_YEAR_MANAGE` |
| GET | `/academic/classrooms` | `ACADEMIC_SETUP_VIEW` |
| GET | `/academic/classrooms/create` | `CLASSROOM_MANAGE` |
| POST | `/academic/classrooms` | `CLASSROOM_MANAGE` |
| GET | `/academic/classrooms/{id}/edit` | `CLASSROOM_MANAGE` |
| POST | `/academic/classrooms/{id}` | `CLASSROOM_MANAGE` |
| POST | `/academic/classrooms/{id}/status` | `CLASSROOM_MANAGE` |
| GET | `/academic/subjects` | `ACADEMIC_SETUP_VIEW` |
| GET | `/academic/subjects/create` | `SUBJECT_MANAGE` |
| POST | `/academic/subjects` | `SUBJECT_MANAGE` |
| GET | `/academic/subjects/{id}/edit` | `SUBJECT_MANAGE` |
| POST | `/academic/subjects/{id}` | `SUBJECT_MANAGE` |
| POST | `/academic/subjects/{id}/status` | `SUBJECT_MANAGE` |
| GET | `/academic/offerings` | `ACADEMIC_SETUP_VIEW` |
| GET | `/academic/offerings/create` | `SUBJECT_OFFERING_MANAGE` |
| POST | `/academic/offerings` | `SUBJECT_OFFERING_MANAGE` |
| GET | `/academic/offerings/{id}/edit` | `SUBJECT_OFFERING_MANAGE` |
| POST | `/academic/offerings/{id}` | `SUBJECT_OFFERING_MANAGE` |
| POST | `/academic/offerings/{id}/status` | `SUBJECT_OFFERING_MANAGE` |

### Tenant, CSRF และ audit

`school_id` สำหรับ authorization มาจาก authenticated Session เท่านั้น
ID จาก browser เป็นเพียง target reference รวม `academic_year_id`, `classroom_id`,
`subject_id` ทั้ง parent และ target ต้องผ่าน tenant-scoped lookup/validation
การส่ง `school_id` ปลอมเปลี่ยนโรงเรียนไม่ได้ Foreign/missing edit ให้ friendly 404
และ mutation ที่ไม่ถูกต้องให้ safe 422 โดยไม่มี entity/audit เปลี่ยน
Composite FK บังคับโรงเรียนของ parent และบังคับปีของห้องเรียนให้ตรงกับ offering
รวม FK `(academic_year_id, school_id)` ของ `user_role_assignments`

School-wide permission checks ยังใช้เฉพาะ assignment ที่ `academic_year_id IS NULL`
assignment ที่มี non-NULL academic year **ยังไม่มีผลต่อ authorization ใน Milestone 3**

Academic POST ทุก mutation ตรวจ CSRF ก่อนเปลี่ยนข้อมูล; bad/missing token ได้ 419
ทุกการสร้าง แก้ไข และเปลี่ยนสถานะที่สำคัญบันทึก audit ใน transaction เดียวกัน
โดย actor/school มาจาก Session และ old/new เก็บเฉพาะ business fields ที่เปลี่ยน
exact no-op update/status ไม่สร้าง audit noise

Audit action codes มี 12 รายการ: `ACADEMIC_YEAR_CREATED`, `ACADEMIC_YEAR_UPDATED`,
`ACADEMIC_YEAR_STATUS_CHANGED`, `CLASSROOM_CREATED`, `CLASSROOM_UPDATED`,
`CLASSROOM_STATUS_CHANGED`, `SUBJECT_CREATED`, `SUBJECT_UPDATED`,
`SUBJECT_STATUS_CHANGED`, `SUBJECT_OFFERING_CREATED`, `SUBJECT_OFFERING_UPDATED`,
`SUBJECT_OFFERING_STATUS_CHANGED` พร้อม actor, school, entity และเวลา
ไม่มี password/plaintext/hash, SQL หรือ stack/internal path ใน audit/error
Dynamic HTML ใช้ output escaping

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

สำหรับ Milestone 3 ใช้ development `pp5` และสองโรงเรียนชั่วคราวที่มี unique marker
จด ID ทุกแถวทันที ไม่ใช้หรือแก้ไขข้อมูลจริง:

1. Login SCHOOL_ADMIN/ACADEMIC_ADMIN ตรวจ academic navigation และ direct URLs;
   VIEWER ต้องไม่เห็น navigation และ backend ปฏิเสธ ส่วน ACADEMIC_ADMIN ไม่มีสิทธิ์จัดการผู้ใช้
2. สร้าง DRAFT ตรวจห้าม activate ไร้วันที่ จากนั้นใส่วันที่และ activate
   ปี ACTIVE ที่สองในโรงเรียนเดียวกันต้องถูกปฏิเสธ แต่ School B activate ของตนได้
3. สร้างห้องเรียน ทดสอบ duplicate ภายในโรงเรียน/ปี และใช้รหัสเดิมต่างปี/โรงเรียน
   สร้างรายวิชารหัสไทย ทดสอบ duplicate ภายในโรงเรียนและ ACTIVE/INACTIVE
4. สร้าง offering term 1/2 ทดสอบ duplicate identity, term ผิด, inactive parents,
   foreign parent/target, ห้องเรียนผิดปี และ `school_id` ปลอม
5. ทดสอบ bad/missing CSRF กับ create/update/status ของทั้งสี่ resource ให้ได้ 419
   เปรียบเทียบ entity และ audit snapshots ทุกคำขอที่ถูกปฏิเสธ รวม business validation
6. ตรวจ no-op update/status, audit ทั้ง 12 action codes และ escaping ของค่าชั่วคราว
   ปิดปีแล้วตรวจ CLOSED freeze ของห้องเรียน/offering และยังอ่านประวัติได้
7. Cleanup เฉพาะ IDs ของ run ที่ยืนยัน unique marker แล้ว ตาม FK จากลูกไปแม่:
   audit ของ fixtures ตามแนวทาง local cleanup → offerings → classrooms → subjects
   → years → role assignments → memberships → users → schools
   เก็บ audit/ข้อมูลจริงและ migration/seed/reference data ไว้ทั้งหมด
   ตรวจ marker เหลือศูนย์และ baseline กลับเท่าเดิม แล้วลบ helper/cookie/credentials ชั่วคราว
8. รัน migrate/seed บน `pp5_test`, full PHPUnit, PHP syntax และ `git diff --check` ซ้ำ
   ห้าม commit smoke artifacts, local.php, vendor, PHPUnit cache หรือ generated logs

Failure-injection tests ที่มีอยู่เป็นหลักฐาน rollback เมื่อ write/audit/transaction ล้มเหลว
ส่วน MAMP smoke ตรวจพฤติกรรมคำขอจริงและ rejected snapshots; ไม่จำลอง DB failure
แบบทำลายข้อมูลใน development

Private paths ต้องตอบ HTTP 403 เช่น `/config/database.php`, `/config/local.php`,
`/app/Application.php`, `/views/dashboard/index.php`, `/views/admin/users/index.php`,
`/views/system/schools/index.php`, `/views/academic/years/index.php`,
`/views/academic/classrooms/index.php`, `/views/academic/subjects/index.php`,
`/views/academic/offerings/index.php`, `/vendor/autoload.php`
`/tools/bootstrap_system_admin.php` ต้องไม่ web reachable เพราะอยู่นอก Document Root

## ขอบเขต milestone ถัดไป

Milestone 1–3 ครอบคลุม SYSTEM/SCHOOL authentication, โรงเรียน/ผู้ใช้ และโครงสร้างวิชาการ
พร้อม service, UI, permission และ audit ฐานนี้เตรียมสำหรับ
**Milestone 4 — Student Core + Enrollment** ซึ่งยังไม่ได้เริ่ม

ยังไม่ได้ implement: students, DMC import, enrollments, transfer/promotion,
staff profile subsystem, homeroom teacher assignments, subject teacher assignments,
fine-grained `permission_scopes`, non-NULL academic-year authorization,
gradebook/scores, attendance, evaluations, competencies, activities, annual results,
finalization, PP5/PP6 reports, XLSX import/export และ HTMX autosave
รวมถึง school chooser, user transfer, email invitation/reset, 2FA/SSO,
audit browsing UI และ deployment automation
