# ระบบ ปพ.5 — School Administration

ฐานที่พัฒนาครบ Milestone 1–5 ใช้ PHP 8.2-compatible, FastRoute, PDO และ PHP Session บน MAMP MySQL 8
โดย SQL รองรับ MariaDB ด้วย ไม่ใช้ Laravel, Node.js backend, Redis, queue, cron
หรือ database triggers

## Local setup

รันคำสั่งจาก project root ใช้ PHP CLI 8.2 ขึ้นไปจาก MAMP และ Composer
ตรวจ `php -v` และให้มี extension `pdo_mysql`

1. ใช้ branch ของ Milestone 5 ที่พัฒนาแล้ว:

   ```sh
   git checkout milestone/5-teaching-gradebook-core
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
   ตามด้วย `20260912_001_student_core_enrollment.sql` และ
   `20260912_001_student_core_permissions.sql` ของ Milestone 4
   และ `20260915_001_teaching_gradebook_core.sql` กับ
   `20260915_001_teaching_gradebook_permissions.sql` ของ Milestone 5

   Seeded baseline Milestone 5 มี **7 roles, 22 permissions และ 38 role-permission mappings**:
   SYSTEM_ADMIN ได้ 3 SYSTEM permissions เดิม ส่วน SCHOOL_ADMIN ได้ 6 SCHOOL
   administration permissions เดิมและ 5 academic permissions ใหม่;
   ACADEMIC_ADMIN ได้ 5 academic permissions เดียวกัน ได้แก่ `ACADEMIC_SETUP_VIEW`,
   `ACADEMIC_YEAR_MANAGE`, `CLASSROOM_MANAGE`, `SUBJECT_MANAGE`, `SUBJECT_OFFERING_MANAGE`
   HOMEROOM_TEACHER, SUBJECT_TEACHER, EXECUTIVE และ VIEWER ไม่ได้รับสิทธิ์ทั้งห้านี้
   Milestone 4 seed เพิ่ม `STUDENT_VIEW`, `STUDENT_MANAGE`, `ENROLLMENT_MANAGE`,
   `STUDENT_IMPORT` ให้ SCHOOL_ADMIN และ ACADEMIC_ADMIN เท่านั้น

   Milestone 5 เพิ่ม `TEACHING_ASSIGNMENT_MANAGE`, `GRADEBOOK_VIEW`,
   `GRADEBOOK_COMPONENT_MANAGE` และ `GRADEBOOK_SCORE_ENTER`
   โดย SCHOOL_ADMIN และ ACADEMIC_ADMIN ได้ทั้งสี่แบบ school-wide/unscoped;
   SUBJECT_TEACHER ได้ `GRADEBOOK_VIEW` และ `GRADEBOOK_SCORE_ENTER`
   แบบ `SUBJECT_OFFERING` scope; EXECUTIVE ได้ `GRADEBOOK_VIEW`
   แบบ school-wide read-only ส่วน HOMEROOM_TEACHER และ VIEWER
   ไม่มี Milestone 5 permission โดย default
   Global grade levels ยังคง 6 แถวเดิม

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
assignment ที่มี non-NULL academic year **ยังไม่มีผลต่อ authorization ใน Milestone 4**

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

## นักเรียนและการลงทะเบียน — Milestone 4

ข้อมูลแยกเป็น **Student Master → Academic-Year Enrollment → Classroom Placement History**
Student identity เป็นข้อมูลระดับโรงเรียน ไม่ผูกกับปีการศึกษา ห้องเรียน หรือระดับชั้น
`students.id` คงเดิมเมื่อแก้รหัสนักเรียน และประวัติของทุกปียังอ้างถึง identity เดิม

### Student master และ PII

| Field | Rule |
| --- | --- |
| `student_code` | จำเป็น, 1–50 Unicode characters, unique ภายในโรงเรียน |
| `national_id` | ไม่บังคับ; ว่างเป็น NULL หรือ 13 ASCII digits; non-NULL unique ภายในโรงเรียน |
| `prefix_th` | จำเป็น, 1–50 Unicode characters |
| `first_name_th`, `last_name_th` | จำเป็น, field ละ 1–100 Unicode characters |
| `gender_code` | NULL / MALE / FEMALE / OTHER |
| `birth_date` | NULL หรือ strict ISO `YYYY-MM-DD` ที่ไม่อยู่ในอนาคต |
| `status` | ACTIVE / INACTIVE; สร้างใหม่เป็น ACTIVE |

ข้อความต้องเป็น UTF-8 ใช้ Unicode trim และไม่รับ control characters
รหัส/เลขประจำตัวประชาชนเดียวกันใช้ต่างโรงเรียนได้ ไม่มีการเชื่อม identity ข้ามโรงเรียนอัตโนมัติ
Unique student code ใช้ `utf8mb4_unicode_ci`: ตัวพิมพ์ใหญ่–เล็กและอักขระที่เทียบเท่าตาม
collation ถือว่าซ้ำ การตรวจ duplicate ภายใน CSV ใช้การเปรียบเทียบเดียวกับฐานข้อมูล
ไม่มี national-ID checksum policy ใน Milestone 4

ไม่แสดง raw national ID ใน student list/search HTML, import preview, audit, error หรือ logs
ไม่ใช้ national ID เป็น GET query สำหรับค้นหา หน้ารายละเอียดสำหรับ `STUDENT_VIEW`
แสดงเฉพาะเลขท้ายสี่หลักพร้อม masking; หน้าแก้ไขที่ผ่าน `STUDENT_MANAGE` เท่านั้นจึงอ่าน/แก้ค่าเต็มได้
Audit profile เก็บชื่อ fields ที่เปลี่ยนและ safe metadata เช่น `has_national_id`
ไม่เก็บค่าชื่อ–นามสกุลหรือ raw national ID

Student status แยกจาก enrollment status: ACTIVE ↔ INACTIVE ได้ และสถานะเดิมเป็น no-op
INACTIVE ยังอ่านประวัติได้ แต่รับ enrollment ใหม่ไม่ได้ ห้าม inactivate ขณะมี ACTIVE enrollment
ในปี DRAFT/ACTIVE ใด ๆ ของโรงเรียน ส่วน enrollment ในปี CLOSED เพียงอย่างเดียวไม่ block
การ inactivate การย้ายออก/ลาออกไม่เปลี่ยน student master status อัตโนมัติ

### Enrollment และ placement history

หนึ่งนักเรียนมี enrollment ได้ไม่เกินหนึ่งแถวต่อโรงเรียน/ปี รวมแถวที่สิ้นสุดแล้ว
เลือก `grade_level_id` ตอนสร้างและแก้ grade ของ enrollment ไม่ได้ใน Milestone 4
Enrollment สร้างเป็น ACTIVE และอาจไม่มี classroom placement ได้

เปลี่ยนได้จาก ACTIVE → TRANSFERRED_OUT หรือ ACTIVE → WITHDRAWN โดยต้องมี `exit_date`
Terminal enrollment ห้าม reopen หรือเปลี่ยนเป็น terminal อีกแบบ สถานะเดิมเป็น no-op เฉพาะปีที่เปิด
`entry_date` ไม่บังคับ; entry/exit ต้องเป็น strict ISO date อยู่ในช่วงวันเริ่ม/สิ้นปีที่ระบุไว้
และ exit ต้องไม่ก่อน entry ไม่มี delete/cancel endpoint สำหรับแก้ enrollment ที่สร้างผิด

Placement แยกจาก enrollment และเก็บทุกแถวประวัติ มี ACTIVE ได้ไม่เกินหนึ่งแถวต่อ enrollment:

- **Place:** ไม่มีห้องเดิม → สร้าง ACTIVE placement
- **Move:** จบ placement เดิมด้วย ENDED/ended_at แล้วสร้าง ACTIVE placement ใหม่
- **Unassign:** จบ placement เดิมโดยยังเก็บ enrollment ไว้แบบไม่มีห้อง
- ห้องเดิมตรงเป้าหมายเป็น exact no-op ไม่สร้าง audit หรือประวัติซ้ำ

ห้องเป้าหมายต้อง ACTIVE และตรง school/year/grade ของ enrollment ทุกมิติ
ห้องเก่าที่ INACTIVE ไม่ขัดขวางการ move/unassign ออก เมื่อ transfer-out/withdraw
จะจบ current placement ใน transaction เดียวกัน ประวัติไม่ถูก hard delete

Enrollment/placement mutation และ import preview/apply ทำได้ในปี **DRAFT / ACTIVE** เท่านั้น
ปี **CLOSED เป็น read-only** รวม same-state mutation; ยังอ่านประวัติได้
Student master ยัง maintain ได้โดยแยกจาก lifecycle ของปี ไม่มี CLOSED override/unlock

### Student permissions และ navigation

| Permission | Access |
| --- | --- |
| `STUDENT_VIEW` | Student list/detail/history และ enrollment list |
| `STUDENT_MANAGE` | Student create/edit/update/status |
| `ENROLLMENT_MANAGE` | Enrollment create/edit/status และ place/move/unassign |
| `STUDENT_IMPORT` | Import index/preview/show/apply/cancel |

Seed ให้ทั้งสี่ permissions กับ **SCHOOL_ADMIN และ ACADEMIC_ADMIN** เท่านั้น
SYSTEM_ADMIN คง SYSTEM permissions เดิม; HOMEROOM_TEACHER, SUBJECT_TEACHER, EXECUTIVE
และ VIEWER ไม่ได้รับทั้งสี่โดย default Baseline ของ Milestone 4 ณ จุดนั้นคือ **7 roles / 18 permissions / 27 mappings / 6 grade levels**

Dashboard ใช้ `AuthorizationService` ตรวจ permission จริงทุกครั้ง:
“จัดการนักเรียน” → `/students` และ “การลงทะเบียนนักเรียน” → `/academic/enrollments`
อาศัย `STUDENT_VIEW`; “นำเข้านักเรียน” → `/academic/student-import` อาศัย `STUDENT_IMPORT`
ไม่ตรวจ role code/name หรือ username เพื่อ authorize UI การเพิ่ม/ถอด permission mapping มีผลทันที
ผู้มี STUDENT_VIEW อย่างเดียวอ่าน list/history ได้ แต่ create/edit/mutation enrollment ถูกปฏิเสธ
Navigation เป็น UI convenience; middleware เป็น security boundary

### Student, enrollment และ import routes

ทุก route ใช้ **Auth → SchoolContext → Permission → handler**; `{id}` รับตัวเลขเท่านั้น
ทุก POST ตรวจ CSRF ก่อน business/staging/audit write

| Method | Route | Permission |
| --- | --- | --- |
| GET | `/students` | STUDENT_VIEW |
| GET | `/students/create` | STUDENT_MANAGE |
| POST | `/students` | STUDENT_MANAGE |
| GET | `/students/{id}` | STUDENT_VIEW |
| GET | `/students/{id}/edit` | STUDENT_MANAGE |
| POST | `/students/{id}` | STUDENT_MANAGE |
| POST | `/students/{id}/status` | STUDENT_MANAGE |
| GET | `/academic/enrollments` | STUDENT_VIEW |
| GET | `/academic/enrollments/create` | ENROLLMENT_MANAGE |
| POST | `/academic/enrollments` | ENROLLMENT_MANAGE |
| GET | `/academic/enrollments/{id}/edit` | ENROLLMENT_MANAGE |
| POST | `/academic/enrollments/{id}/placement` | ENROLLMENT_MANAGE |
| POST | `/academic/enrollments/{id}/status` | ENROLLMENT_MANAGE |
| GET | `/academic/student-import` | STUDENT_IMPORT |
| POST | `/academic/student-import/preview` | STUDENT_IMPORT |
| GET | `/academic/student-import/{id}` | STUDENT_IMPORT |
| POST | `/academic/student-import/{id}/apply` | STUDENT_IMPORT |
| POST | `/academic/student-import/{id}/cancel` | STUDENT_IMPORT |

Student list รับ `q` สำหรับรหัส/ชื่อ ไม่ค้น national ID; enrollment list รับ
`academic_year_id`, `grade_level_id`, `classroom_id`, `status`, `q` โดย resolve filters ในโรงเรียนของ session
Target/parent IDs ไม่ใช่ authority: `school_id`, `user_id`, `actor_user_id`, role และ context
ที่ browser ส่งมาเปลี่ยน tenant/actor ไม่ได้ Backend ตรวจ user/membership/school ซ้ำ
Milestone 5 เพิ่ม `permission_scopes` แบบ `SUBJECT_OFFERING`
สำหรับ SUBJECT_TEACHER โดย permission และ scope ต้องอยู่บน role assignment
เดียวกัน จึงยืม scope จาก assignment อื่นไม่ได้ และการ revoke มีผลทันที
ส่วน non-NULL academic-year assignment ยังไม่ใช้เป็น school-wide authorization ทั่วไป
GET foreign/missing ให้ friendly 404 แบบไม่บอก existence; POST foreign/missing ให้ safe 422
(หรือ 403 เมื่อ context/permission gate ปฏิเสธก่อน) และ malformed/missing CSRF ให้ 419
โดยไม่เปลี่ยน business, staging หรือ audit ข้อมูล dynamic escape ก่อน render ทุกครั้ง

### Canonical CSV: Preview → Apply / Cancel

เลือกปีการศึกษาที่เปิด แล้ว upload field `student_file` เป็น UTF-8 CSV
รับ UTF-8 BOM, comma delimiter และ header ตามลำดับนี้เท่านั้น:

```text
student_code,national_id,prefix_th,first_name_th,last_name_th,gender_code,birth_date,grade_level_code,classroom_code,entry_date
```

ขนาดสูงสุด **2 MiB / 1,000 data rows** (ไม่นับ header) ต้องมีข้อมูลอย่างน้อยหนึ่งแถว
ใช้ validation student profile เดียวกับ manual form; `grade_level_code` จำเป็น
`classroom_code` และ `entry_date` ไม่บังคับ Grade ต้อง ACTIVE; classroom code ต้อง resolve
เป็น ACTIVE classroom ของโรงเรียน/ปี/grade ที่เลือก MIME จาก browser ไม่ใช่ security authority
ไม่มีการ copy raw upload ไปเก็บใน repository storage

Preview normalize/validate และเขียนเฉพาะ `student_import_batches` / `student_import_rows`
ไม่เปลี่ยน students/enrollments/placements และไม่มี business audit โดยจัดแต่ละแถวเป็น:

- **CREATE / CREATE:** ไม่พบ identity → สร้างนักเรียนและ enrollment ใหม่ตอน apply
- **MATCH / CREATE:** identity/profile ตรงของเดิม แต่ยังไม่มี enrollment ในปีนี้
- **MATCH / NOOP:** enrollment ACTIVE, grade และ current classroom/unplaced ตรงกัน
- **CONFLICT / ERROR:** ตั้ง actions เป็น NONE, แสดงข้อความปลอดภัย และ block apply

Matching แยกค้น national ID (ถ้ามี) กับ student code ภายในโรงเรียนเดียวกัน
ถ้าชี้คนละคน, national ID ตรงแต่รหัสต่าง, หรือรหัสตรงแต่ profile/national ID ไม่ตรง ให้ conflict
Import ไม่ update existing student profile, ไม่เปลี่ยน existing grade และไม่ move existing classroom
Existing terminal enrollment, grade/classroom ที่ต่างจากเดิม และ inactive student ไม่ผ่าน
รหัสนักเรียนหรือ non-NULL national ID ซ้ำในไฟล์เป็น ERROR; ไม่เผย raw national ID ในข้อความ/preview

Apply ต้องไม่มี error และมี enrollment ใหม่อย่างน้อยหนึ่งรายการ **all-NOOP batch apply ไม่ได้**
Service revalidate ทุกแถวใน transaction เดียว ไม่เชื่อ staging ว่าเป็นสิทธิ์หรือข้อมูลปัจจุบัน
ใช้ lock school/year/batch แล้ว matched students/enrollments ตาม ID ก่อน placements/classrooms
สร้างเฉพาะ missing entities, เขียน per-entity audits และ `STUDENT_IMPORT_APPLIED` summary
จากนั้น mark APPLIED และลบ row staging หากขั้นตอนใดล้มเหลว rollback business/audit/batch/staging ทั้งหมด

ไฟล์ที่ APPLIED แล้วห้ามนำเข้าซ้ำด้วย SHA-256 เดิมใน **school/year เดิม**
Hash เดิมต่างโรงเรียนหรือปีใช้ได้ แต่ยังต้องผ่าน validation ทั้งหมด; ไม่มี global student matching
Cancel ทำได้เฉพาะ PREVIEW ที่ยังไม่หมดอายุ → CANCELLED พร้อมลบ row staging และไม่มี business audit
APPLIED/EXPIRED cancel ไม่ได้

Preview มีอายุ **24 ชั่วโมง** Row staging อาจเก็บ raw national ID ระหว่าง live PREVIEW เท่านั้น
การเปิด import index/batch หรือสร้าง preview ใหม่เรียก cleanup ของโรงเรียนใน session:
PREVIEW ที่หมดอายุ → EXPIRED และลบ row staging โดยไม่เขียน business audit
Cleanup เป็น **request-driven ไม่มี cron**; ข้อมูลหมดอายุอาจยังอยู่จนมี request มาเรียก cleanup
แต่ apply/cancel ปฏิเสธทันทีเมื่อหมดอายุ แม้ cleanup ยังไม่ทำงาน

**Milestone 4 ยังไม่รองรับ native DMC XLSX/XLSB** Canonical CSV ไม่ใช่การ claim DMC compatibility
Future DMC adapter ต้องใช้ real approved DMC sample เพื่อกำหนด source mapping ก่อน

### Student audit และ transaction guarantees

ใช้ action codes เจ็ดรายการ:
`STUDENT_CREATED`, `STUDENT_UPDATED`, `STUDENT_STATUS_CHANGED`,
`STUDENT_ENROLLMENT_CREATED`, `STUDENT_ENROLLMENT_STATUS_CHANGED`,
`STUDENT_CLASSROOM_PLACEMENT_CHANGED`, `STUDENT_IMPORT_APPLIED`

ทุก mutation สำคัญบันทึก actor จาก session, school, entity type/ID และ timestamp
Student audit เก็บ safe metadata/changed field names; enrollment/placement เก็บ IDs, status และวันที่
Import summary เก็บ counts กับ source SHA-256 ไม่มี row PII หรือ password/hash ของรหัสผ่าน
Exact no-op ไม่สร้าง audit noise; transactions rollback เมื่อ repository/audit ล้มเหลว
Composite foreign keys บังคับ parent ให้ตรง tenant/year/grade เสริมจาก service validation

## การมอบหมายครูประจำวิชาและสมุดคะแนน — Milestone 5

Milestone 5 เพิ่ม subject-offering teaching assignment, offering-scoped authorization
สำหรับครูประจำวิชา, configurable term gradebook และ audited score entry
โดยยังใช้ Student Enrollment + current Classroom Placement จาก Milestone 4
เป็น roster authority และไม่สร้าง roster ซ้ำอีกชุดหนึ่ง

### Teaching assignment และ gradebook permissions

สิทธิ์ Milestone 5 มี `TEACHING_ASSIGNMENT_MANAGE`, `GRADEBOOK_VIEW`,
`GRADEBOOK_COMPONENT_MANAGE` และ `GRADEBOOK_SCORE_ENTER`

SCHOOL_ADMIN และ ACADEMIC_ADMIN ใช้สิทธิ์ทั้งสี่แบบ school-wide/unscoped
SUBJECT_TEACHER ได้ `GRADEBOOK_VIEW` และ `GRADEBOOK_SCORE_ENTER`
แบบ `SUBJECT_OFFERING` scope เท่านั้น ส่วน EXECUTIVE ได้ `GRADEBOOK_VIEW`
แบบ school-wide read-only

Generic school permission check ยอมรับเฉพาะ unscoped grants
ส่วน subject-offering authorization ใช้ resource-specific authorization
และ permission กับ scope ต้องมาจาก role assignment เดียวกัน
จึงไม่สามารถยืม scope จาก role assignment อื่นได้

Teaching assignment สร้างหรือ reactivate ได้เมื่อ school, membership,
SUBJECT_TEACHER role assignment, academic year และ offering
อยู่ในสถานะที่อนุญาตและ tenant ตรงกัน ไม่มี hard delete;
deactivate เก็บประวัติไว้ และการ revoke permission/scope/assignment
มีผลกับ authorization request ถัดไปทันที

ปี CLOSED ยังคงอ่าน teaching-assignment history ได้แต่ mutation ไม่ได้

### Gradebook components, roster และ NULL กับ zero

Gradebook component เป็น score column แบบ generic ของ subject offering
ไม่ hard-code ชื่อ column หรือตำแหน่ง cell จาก legacy Excel
แต่ละ component มี `code`, `name_th`, `max_score DECIMAL(7,2)`,
`sort_order` และสถานะ ACTIVE/INACTIVE

`code` ต้อง unique ภายใน offering ตาม database collation
และ `max_score` ต้องเป็น decimal ตั้งแต่ 0.01 ถึง 99999.99
โดยไม่ใช้ FLOAT/DOUBLE

เมื่อ component เคยมี score row แล้ว จะเปลี่ยน `max_score` ไม่ได้อีก
แม้ score row นั้นมีค่า NULL หรือ 0.00 แต่ยังแก้ metadata อื่น
หรือเปลี่ยน ACTIVE/INACTIVE ได้โดยไม่ลบ score history

Current roster ของ offering มาจาก ACTIVE enrollment ใน school/year เดียวกัน
ที่มี current ACTIVE classroom placement ตรงกับ classroom ของ offering
นักเรียนที่ไม่ใช่ current roster แต่เคยมี score row ของ offering
ยังคงปรากฏเป็น HISTORICAL และอ่านได้อย่างเดียว
เพื่อไม่ให้ประวัติคะแนนหายเมื่อย้ายห้องหรือสิ้นสุด enrollment

นักเรียนที่ไม่ใช่ current roster และไม่เคยมี score row จะไม่ปรากฏใน gradebook
ส่วน inactive component ไม่เป็น current score column และไม่รวมใน current totals
แต่ score history เดิมยังคงอยู่

Score ใช้ `DECIMAL(7,2) NULL` และต้องรักษาความแตกต่างนี้ทุกชั้นของระบบ:

```text
NULL = ยังไม่กรอก / ล้างคะแนน
0.00 = คะแนนศูนย์ที่กรอกจริง
```

ดังนั้น 0.00 นับเป็นคะแนนที่กรอกแล้ว ส่วน NULL ไม่นับ
configured maximum และ entered total คำนวณจาก active components
โดย completeness เป็น true ต่อเมื่อมี active component อย่างน้อยหนึ่งรายการ
และทุก active component มีคะแนน non-NULL

### Score mutation, audit และ HTMX autosave

Score mutation ใช้ school และ actor จาก authenticated Session
ส่วน offering, component และ enrollment เป็น target IDs จาก route
ค่าที่ browser ส่ง เช่น `school_id`, `user_id`, actor, role หรือ scope
ไม่ถูกใช้เป็น authorization authority

POST ตรวจ CSRF ก่อนประมวลผลคะแนน และช่องว่างถูกแปลงเป็น NULL
ก่อนเขียน service จะ lock/revalidate ACTIVE school, ปี DRAFT/ACTIVE,
ACTIVE offering, ACTIVE component, ACTIVE enrollment, current ACTIVE placement
ที่ตรงกับ classroom ของ offering และ live `GRADEBOOK_SCORE_ENTER` authorization

คะแนนรับเฉพาะ decimal ไม่ติดลบที่มีทศนิยมไม่เกิน 2 ตำแหน่ง
และต้องไม่เกิน `max_score` ของ component
การ clear score ที่มี row อยู่แล้วจะคง row identity เดิมและเปลี่ยน score เป็น NULL
เพื่อรักษาหลักฐาน historical roster ส่วนการ clear cell ที่ไม่เคยมี row
เป็น exact no-op จึงไม่สร้าง score row หรือ audit ใหม่

Score write และ `GRADEBOOK_SCORE_CHANGED` audit อยู่ใน transaction เดียวกัน
ถ้า write หรือ audit ล้มเหลว transaction ต้อง rollback ทั้งหมด
exact canonical no-op ไม่เขียน score ซ้ำและไม่สร้าง audit noise

หน้า gradebook โหลด HTMX 2.0.8 จาก local asset `/assets/vendor/htmx-2.0.8.min.js`
ร่วมกับ `/assets/gradebook.js` เฉพาะเมื่อผู้ใช้มีสิทธิ์บันทึกคะแนน
การ blur ช่องคะแนนเป็น POST boundary สำหรับ autosave

Enter ป้องกัน default behavior แล้วเลื่อนไปแถวถัดไปของ component เดิม
หรือ blur ช่องสุดท้ายเพื่อบันทึก ส่วน Tab/Shift+Tab และ caret keys ใช้ behavior ปกติของ browser
JavaScript ไม่คำนวณ totals เอง แต่ใช้ fragment และ row summary ที่ server render กลับมา

UI ยอมรับการบันทึกสำเร็จเมื่อ response เป็น HTTP 200
และมี header `X-Gradebook-Saved: 1` เท่านั้น
ถ้า transaction สำเร็จแล้วแต่ refresh read model ภายหลังล้มเหลว
server ตอบ 409 เพื่อไม่กล่าวอ้างผิดว่าการเขียนถูก rollback

## Historical MAMP smoke test และ cleanup — Milestone 1–4

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

### Historical Milestone 4 verification และ smoke checklist

ตรวจ migration chain บน `pp5_test` ที่เริ่มจาก schema ว่างและไม่มีข้อมูลที่ต้องเก็บ
ห้ามล้าง development `pp5` เพื่อทำขั้นตอนนี้ รันจาก project root:

```sh
php tools/migrate.php --database=pp5_test
php tools/seed.php --database=pp5_test
php tools/migrate.php --database=pp5_test
php tools/seed.php --database=pp5_test
```

รอบแรก apply 5 migration files / 3 seed files; รอบสองไม่ apply ซ้ำ
ตรวจ seed counts จากฐานข้อมูลให้เป็น 7 roles / 18 permissions / 27 mappings / 6 grade levels
จากนั้นรัน full PHPUnit, project syntax และ `git diff --check` ตาม Local setup

สำหรับ real MAMP smoke ใช้ `pp5`, login ผ่าน HTTP และ multipart upload จริง
สร้าง School A/B และผู้ใช้ชั่วคราวด้วย unique marker พร้อมบันทึก IDs ทุกชุด:

1. ตรวจ SCHOOL_ADMIN/ACADEMIC_ADMIN navigation และ direct URLs; VIEWER ถูกปฏิเสธ
   ทดลอง direct STUDENT_VIEW mapping ให้ role เดิมแล้วอ่าน list/history ได้แต่ mutation/import ไม่ได้
   ถอด mapping แล้วสิทธิ์ต้องหายทันที SYSTEM context เข้า SCHOOL student routes ไม่ได้
2. สร้าง Thai/Unicode student ทั้งมี national ID และเว้นว่าง; ตรวจ NULL, same-school duplicates,
   same identity ต่างโรงเรียน, แก้ code/profile โดย history คงเดิม, masked detail และ escaped HTML
   ตรวจ open active enrollment block inactivation, CLOSED history-only ไม่ block และ reactivate ได้
3. สร้าง DRAFT/ACTIVE enrollments รวม unplaced; place/move/unassign และ no-op
   ตรวจ wrong school/year/grade, inactive target, move-away จาก inactive old classroom,
   transfer-out/withdrawal พร้อมปิด placement, terminal reopen deny และ CLOSED read-only
4. Upload CSV แบบ CREATE/CREATE, MATCH/CREATE, MATCH/NOOP; preview เปลี่ยน staging เท่านั้น
   Apply สร้าง missing entities/audits ตามจำนวน, batch APPLIED และลบ staging
   ตรวจ same-hash/school/year deny และใช้ hash เดิมต่าง school/year ได้เมื่อข้อมูลถูกต้อง
5. ตรวจ profile/identity/grade/classroom conflicts, all-NOOP, duplicate codes ตาม DB collation,
   bad header/upload representation, foreign year/batch/classroom และ stale preview
   Rejected apply ต้องไม่มี partial entity/audit และ staging คงเดิม
6. Cancel PREVIEW แล้ว staging หายแต่ business/audit คงเดิม; APPLIED/EXPIRED/foreign cancel ไม่ได้
   ทดสอบหมดอายุโดยปรับเฉพาะ `expires_at` ของ recorded smoke batch ให้ผ่านเวลาแล้ว
   Apply/cancel ต้องปฏิเสธก่อน cleanup; GET import ต้อง mark EXPIRED และลบ staging
7. Missing/invalid CSRF ใน student create/update/status, enrollment create/status/placement,
   import preview/apply/cancel ต้อง 419 ก่อน write เทียบ full snapshots ของ
   `students`, `student_enrollments`, `student_classroom_placements`, `audit_logs`,
   `student_import_batches`, `student_import_rows` ก่อน/หลังทุก rejected POST
8. ตรวจ forged `school_id`, `user_id`, `actor_user_id`, role/context และ foreign parent/target IDs
   GET foreign/missing ไม่แยก existence; responses ไม่มี foreign secret markers, raw national ID,
   SQL/constraint/stack/path/credentials ตรวจ audit ทั้งเจ็ด codes และ exact no-op ไม่มี audit noise
9. ตรวจ private paths รวม `/app/Services/StudentImportService.php`,
   `/app/Support/CanonicalStudentCsvReader.php`, `/routes/web.php`, `/views/students/show.php`,
   `/views/academic/enrollments/edit.php`, `/views/academic/student-import/preview.php`,
   `/storage` และ subpaths ให้ 403 เช่นเดียวกับ private paths เดิม
10. Logout temporary sessions แล้ว cleanup เฉพาะ recorded IDs ของ run จากลูกไปแม่:
    import rows → batches → placements → enrollments → students → fixture audit rows
    → classrooms/years → role assignments/memberships → users → schools
    ถ้ามี temporary permission mapping ให้ลบเฉพาะคู่ที่เพิ่มเอง ตรวจข้อมูลทุกตารางกลับตรง
    baseline ก่อนสร้าง fixtures เก็บ migration/seed/reference data และข้อมูลจริงไว้ทั้งหมด
    ลบ temporary CSV/helpers/cookies; ห้าม commit fixtures, staging/PII/session dumps หรือ credentials

ผล Task 8 บน MAMP วันที่ **2026-09-15**: PHP CLI 8.3.14, PHPUnit 11.5.56,
Apache `localhost:8888`, MySQL 8.0.40; baseline **1,987 tests / 45,632 assertions**
พบและแก้ preview duplicate-code collation mismatch ด้วย regression RED ก่อนแก้ production
ครอบคลุม case/accent/Unicode composition/expansion, 1,000-code limit และ query-failure rollback
หลังแก้ **1,994 tests / 45,807 assertions**; syntax ผ่าน **126 PHP files** (ไม่นับ vendor)

Real MAMP smoke ผ่าน **3,823 checks** รวม response-leak checks และ snapshot invariants
ชุด mixed import สร้าง student/enrollment/placement ใหม่ **1/2/2** ตามที่คาด และ NOOP ไม่เขียนซ้ำ
Audit ทั้งเจ็ด codes ผ่าน, private paths ถูกปฏิเสธ, fixture cleanup คืนข้อมูลเดิมครบทุกตาราง
Focused import/domain/HTTP/isolation ผ่าน **144 tests / 5,070 assertions**
Oversized/>1,000 CSV rows และ injected write/audit failure rollback ยืนยันผ่าน automated regression
ไม่ได้ทำ destructive failure injection ใน development; MariaDB ไม่ได้รัน smoke ในเครื่อง MAMP นี้

## Milestone 5 verification และ MAMP smoke checklist

Task 8 verification ที่ยืนยันแล้วบน MAMP MySQL:

- Clean temporary database เริ่มจาก schema ว่าง: apply 6 migration files และ 4 seed files สำเร็จ
- Seed baseline หลัง M5 เป็น 7 roles / 22 permissions / 38 role-permission mappings / 6 grade levels
- Migration/seed runner รันซ้ำแล้วไม่มีไฟล์ถูก apply ซ้ำ และ temporary verification database ถูกลบจนเหลือศูนย์
- Focused score hardening หลังเพิ่ม regression coverage ผ่าน 90 tests / 2,457 assertions
- M5-focused regression ผ่าน 657 tests / 14,911 assertions
- Full PHPUnit baseline ก่อน Task 8 hardening ผ่าน 2,648 tests / 60,644 assertions
- PHP syntax ของ hardening tests ผ่าน และ `git diff --check` ผ่าน

Final verification หลังการแก้ Task 8 ทั้งหมด:

- Full PHPUnit บน `pp5_test` ผ่าน 2,651 tests / 60,718 assertions
- Project-wide PHP syntax ผ่าน 166 files
- First-party JavaScript syntax ผ่าน 2 files (`htdocs/assets/gradebook.js` และ `tests/Browser/gradebook-autosave.js`)
- `git diff --check` ผ่าน และไม่พบ untracked files
- Real MAMP smoke ของ Milestone 5 ผ่านครบ และ cleanup fixture เหลือศูนย์

การทดสอบ runtime ที่ยืนยันในเครื่องนี้เป็น MySQL 8; SQL ออกแบบให้ MariaDB-compatible

แต่ยังไม่ได้อ้างว่าได้รัน MariaDB runtime smoke จริง

### Real MAMP smoke — PASS

Milestone 5 real HTTP smoke รันกับ development database `pp5` บน MAMP MySQL 8
โดยแยกจาก automated tests ที่ใช้ `pp5_test` และใช้ temporary fixture ที่มี unique marker
ซึ่งไม่แก้ไขข้อมูลจริงเดิมของระบบ

ผลที่ยืนยันแล้ว:

- Login ผ่าน HTTP `POST /login` จริงด้วย temporary SUBJECT_TEACHER ใช้ cookie/session จริง
  และ CSRF token ที่ render จากระบบ ไม่มีการ inject session หรือข้าม authentication
- `GET /gradebook/{offeringId}` ตอบ HTTP 200 และ render current roster,
  gradebook CSRF, local HTMX 2.0.8 และ `gradebook.js`
- HTMX score POST ค่า `5.00` ตอบ HTTP 200 พร้อม `X-Gradebook-Saved: 1`;
  ฐานข้อมูลเก็บ `5.00`, server-rendered summary เปลี่ยนเป็น `5.00 / 20.00`,
  และสร้าง `GRADEBOOK_SCORE_CHANGED` audit ใน transaction
- เปลี่ยน `5.00 → 0.00` แล้วคง score row ID เดิมและเก็บ `0.00` เป็นคะแนนจริง
- ล้าง `0.00 → NULL` แล้วคง score row ID เดิม แต่ `score` เป็น NULL;
  ส่วนการล้าง cell ที่ไม่เคยมี score row เป็น no-op โดยไม่สร้าง score row หรือ audit
- เมื่อเปลี่ยน SUBJECT_OFFERING scope เป็น INACTIVE ระหว่าง session เดิม
  gradebook GET ถูกปฏิเสธทันทีและ score POST ตอบ 422 โดยไม่มี score/audit write;
  เมื่อเปิด scope กลับ session เดิมเข้าถึง gradebook ได้อีกครั้งโดยไม่ login ใหม่
- CLOSED academic year และ INACTIVE offering ยังอ่าน gradebook แบบ read-only ได้
  แต่ score POST ถูกปฏิเสธ; INACTIVE component ถูกตัดออกจาก current columns และเขียนไม่ได้
- เมื่อนักเรียนไม่มี active placement แต่ยังมี retained NULL score row
  นักเรียนยังปรากฏเป็น historical roster แบบ read-only และ direct score POST ถูกปฏิเสธ
- Rejected mutation responses ที่ตรวจไม่เผย unique fixture marker, username,
  SQLSTATE/PDO/constraint details, filesystem path, local config path,
  password hash หรือ session identifier
- หลัง smoke คืนสถานะ fixture ที่ทดสอบชั่วคราวแล้ว cleanup แบบ child → parent;
  ตรวจซ้ำด้วย IDs และ unique marker พบ school, user, membership, role assignment,
  academic data, student data, permission scope, gradebook components/scores และ audit logs
  เหลือศูนย์ทั้งหมด
- ลบ temporary cookie, CSRF, helper scripts, HTTP response artifacts และ smoke state แล้ว;
  ไม่มีไฟล์ดังกล่าวอยู่ใน repository หรือ `/tmp` หลัง cleanup

ห้าม commit cookie files, credentials, temporary helpers, smoke fixtures, generated logs หรือ local configuration

## ขอบเขต milestone ถัดไป

Milestone 1–5 ครอบคลุม SYSTEM/SCHOOL authentication, โรงเรียน/ผู้ใช้, โครงสร้างวิชาการ,
student identity, yearly enrollment, placement history, transfer-out/withdrawal,
canonical CSV import, subject-teacher assignment, `SUBJECT_OFFERING` permission scope,
configurable term gradebook, audited score entry และ per-cell HTMX autosave
พร้อม service, UI, permission และ audit การจบ milestone branch ยังต้องผ่าน review ก่อน PR/merge

Milestone 5 ยังเป็น gradebook core: เก็บคะแนนราย component และคำนวณ term totals/completeness
แต่ยังไม่ได้ implement grade symbol, term grade calculation, annual result, GPA,
subject finalization, unlock/approval หรือ promotion/repeat/graduation

ยังไม่ได้ implement: native DMC/XLSX/XLSB import, legacy Excel PP5 migration,
automatic cross-school transfer/linking, full staff profile subsystem,
homeroom-teacher assignment และ classroom-wide homeroom scope,
generic cross-domain scope editor, attendance, evaluations/competencies, activities,
PP5/PP6 reports, mPDF official report generation, bulk score import/export,
school chooser, user transfer, email invitation/reset, 2FA/SSO,
audit browsing UI และ deployment automation
