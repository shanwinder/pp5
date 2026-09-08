# Technical Architecture v1.2

## Web App ปพ.5 Multi-School

### 1. เป้าหมาย

สร้าง Web Application สำหรับจัดทำและบริหารข้อมูล ปพ.5 และงานประเมินผลของสถานศึกษา รองรับหลายโรงเรียนในระบบเดียว โดยใช้เทคโนโลยีที่สามารถพัฒนาใน MAMP และนำขึ้น InfinityFree ได้โดยไม่ต้องใช้ Laravel, Node.js Server, SSH, Queue Worker หรือ Cron Job

ระบบต้องสามารถใช้แทน Excel เดิมได้ตั้งแต่การจัดการนักเรียน การกรอกคะแนน เวลาเรียน การประเมิน กิจกรรม การสรุปผล ไปจนถึงสร้าง ปพ.5/ปพ.6 และ PDF

---

# 2. Technology Stack

## Backend

* PHP 8.2-compatible
* Custom MVC-lite
* FastRoute
* Composer
* PDO
* PHP Session

## Database

Local:

* MAMP
* MySQL 8

Production:

* InfinityFree
* MariaDB/MySQL-compatible

SQL ของโครงการต้องใช้เฉพาะความสามารถที่รองรับได้ทั้ง MySQL 8 และ MariaDB ที่ใช้บน Production

## Frontend

* HTML5
* Bootstrap 5.3
* HTMX 2.x
* Vanilla JavaScript
* CSS ของระบบเพิ่มเติม

ไม่ใช้ React, Vue, Angular หรือ SPA Framework ใน MVP

## Reports

* HTML/CSS Print Preview
* mPDF สำหรับ Official PDF

## Import / Export

* PHP native CSV
* PhpSpreadsheet สำหรับ XLSX เมื่อจำเป็น

## Development

* Composer
* PHPUnit
* Git
* GitHub

## Deployment

* FTP ไปยัง InfinityFree

---

# 3. Environment

## Local Development

```text
Mac
 ↓
MAMP
 ├── Apache
 ├── PHP 8.2-compatible
 └── MySQL 8
```

ใช้สำหรับ:

* เขียนโค้ด
* Debug
* Migration
* Seed
* PHPUnit
* Composer

## Production

```text
InfinityFree
 ├── Apache
 ├── PHP
 └── MariaDB
```

Production จะไม่มี dependency ต่อ:

* SSH
* Node.js
* npm build
* Cron
* Queue Worker
* Redis
* Database Trigger
* Stored Procedure ที่เป็นกฎธุรกิจหลัก

---

# 4. Application Architecture

ใช้:

```text
Browser
   ↓
Front Controller
   ↓
Router
   ↓
Middleware
   ↓
Controller
   ↓
Service
   ↓
Repository
   ↓
PDO
   ↓
Database
```

ขากลับ:

```text
Database
   ↓
Repository
   ↓
Service
   ↓
Controller
   ↓
PHP View
   ↓
HTML
   ↓
Browser
```

---

# 5. หน้าที่แต่ละ Layer

## Controller

รับ HTTP Request และส่ง Response

Controller ไม่ควรมี SQL หรือ Business Logic จำนวนมาก

## Service

เป็นที่อยู่ของกฎระบบ เช่น:

* ตรวจคะแนนเต็ม
* คำนวณคะแนน
* ตัดเกรด
* Finalize
* GPA
* Evaluation
* Completion Validation
* Promotion Workflow

## Repository

รับผิดชอบการอ่าน/เขียน Database ผ่าน PDO

SQL อยู่ใน Layer นี้เป็นหลัก

## View

สร้าง HTML ที่แสดงต่อผู้ใช้

View ไม่มี Business Logic สำคัญ

---

# 6. Routing

มี Front Controller เดียว:

```text
/index.php
```

URL ตัวอย่าง:

```text
/login
/dashboard

/students
/students/{id}

/academic/classrooms
/academic/subjects

/gradebook/{subject}

/attendance

/evaluations/attributes
/evaluations/reading
/evaluations/competencies

/activities

/results

/reports
/reports/pp5
/reports/pp6

/admin/users
/admin/settings
/admin/audit
```

HTMX endpoints แยก logical route group เช่น:

```text
/hx/gradebook/score
/hx/attendance/status
/hx/evaluations/score
```

---

# 7. Multi-School Architecture

ระบบใช้:

```text
Shared Database
+
Shared Schema
+
School-based Multi-Tenancy
```

หนึ่ง Application รองรับหลายโรงเรียน

```text
Platform
 ├── School A
 ├── School B
 └── School C
```

ไม่สร้าง Database ต่อโรงเรียน และไม่สร้าง Table ต่อโรงเรียน

---

# 8. School เป็น Tenant

ตารางหลัก:

```text
schools
```

ข้อมูลที่เป็นของโรงเรียนจะสามารถระบุ Tenant ได้ผ่าน `school_id` โดยตรงหรือผ่านความสัมพันธ์ที่ตรวจสอบได้อย่างชัดเจน

ตัวอย่าง School-scoped data:

* academic_years
* staff_members
* students
* classrooms
* subject_offerings
* enrollments
* scores
* attendance
* evaluations
* activities
* results
* reports
* audit logs

---

# 9. Global Data กับ School Data

## Global

ข้อมูลมาตรฐานที่ใช้ร่วมกัน เช่น:

* grade_levels
* permissions
* attendance status codes
* academic result codes
* activity type codes

## School-scoped

ข้อมูลที่เป็นของแต่ละโรงเรียน

ต้องถูกกรองตาม School Context เสมอ

---

# 10. User และ School Membership

`users` ไม่เก็บ `school_id` เป็นเจ้าของโดยตรง

ใช้ความสัมพันธ์:

```text
User
 ↓
School Membership
 ↓
Role Assignment
 ↓
Scope
```

ตารางหลัก:

```text
users
school_memberships
user_role_assignments
permission_scopes
```

---

# 11. การกำหนดโรงเรียนของ User

MVP ไม่มี Public Self-Registration

บัญชีผู้ใช้และสถานะถูกกำหนดโดยผู้ดูแลที่ได้รับสิทธิ์

Flow:

```text
SYSTEM ADMIN
 ↓
สร้าง School
 ↓
แต่งตั้ง SCHOOL ADMIN
 ↓
SCHOOL ADMIN
 ↓
สร้าง/จัดการ User
 ↓
กำหนด Membership
 ↓
กำหนด Role
 ↓
กำหนด Scope
```

User ทั่วไปไม่เลือกโรงเรียนเอง

---

# 12. Login Flow

```text
Username + Password
        ↓
Authenticate
        ↓
User ACTIVE?
        ↓
ACTIVE School Membership?
        ↓
Role Assignment
        ↓
Permission + Scope
        ↓
สร้าง Session
        ↓
Dashboard ของโรงเรียนนั้น
```

Session มีอย่างน้อย:

```text
user_id
school_id
school_membership_id
csrf_token
last_activity
```

`school_id` มาจาก Database ไม่ใช่ค่าที่ Browser ส่งมา

---

# 13. User หนึ่งคนกับหลายโรงเรียน

สำหรับ MVP:

> User ทั่วไปมี ACTIVE School Membership ได้สูงสุดหนึ่งโรงเรียนในเวลาเดียวกัน

กรณีย้ายโรงเรียน:

```text
School A membership
→ INACTIVE

School B membership
→ ACTIVE
```

ข้อมูลและ Audit เดิมของ School A ยังคงอยู่

---

# 14. Roles

Role หลัก:

```text
SYSTEM_ADMIN
SCHOOL_ADMIN
ACADEMIC_ADMIN
HOMEROOM_TEACHER
SUBJECT_TEACHER
EXECUTIVE
VIEWER
```

SYSTEM_ADMIN เป็น Platform-level role

SCHOOL_ADMIN และ Role อื่นอยู่ใน School Context

---

# 15. Authorization

ทุก Action สำคัญตรวจ:

```text
Authenticated User
+
School Context
+
Role
+
Permission
+
Scope
+
Entity State
=
ALLOW / DENY
```

ตัวอย่างครูประจำวิชา:

```text
GRADEBOOK_ENTER_SCORE = YES
subject อยู่ใน scope = YES
school ตรงกัน = YES
result ยังไม่ Finalized = YES

→ ALLOW
```

---

# 16. School Isolation Rule

ห้าม Query School-scoped entity โดย ID อย่างเดียวโดยไม่มีการตรวจ Tenant

ตัวอย่างที่ไม่อนุญาต:

```sql
SELECT *
FROM students
WHERE id = ?
```

ต้องตรวจ School Context ด้วยโดยตรงหรือผ่าน relation ที่เชื่อถือได้

เป้าหมายคือ:

> User โรงเรียน A ต้องไม่สามารถดูหรือแก้ข้อมูลโรงเรียน B ได้แม้เปลี่ยน URL หรือ HTTP Request เอง

---

# 17. Authentication & Security

ใช้:

* PHP Session
* `password_hash()`
* `password_verify()`
* Secure session handling
* CSRF Protection
* Prepared Statements
* Permission Middleware
* School Context Middleware
* Central Error Handler
* Audit Logging

Backend ต้องตรวจ Authorization ทุกครั้ง

การซ่อนปุ่มใน Frontend ไม่ถือว่าเป็น Security

---

# 18. Database Strategy

ใช้:

```text
MySQL/MariaDB
InnoDB
utf8mb4
```

Primary Key หลัก:

```text
BIGINT UNSIGNED AUTO_INCREMENT
```

Foreign Keys ใช้จริง

Default delete behavior ของข้อมูลสำคัญ:

```text
RESTRICT / NO ACTION
```

ไม่ใช้ Cascade Delete อย่างกว้างขวางกับข้อมูลทางการ

---

# 19. Data Types

คะแนนและค่าทศนิยม:

```text
DECIMAL
```

ไม่ใช้ FLOAT สำหรับคะแนน/เกรด

วันที่:

```text
DATE
DATETIME
```

ปีการศึกษา:

```text
year_be = 2569
```

วันที่จริงใน Database ใช้ ค.ศ./ISO date

---

# 20. NULL Rule

หลักสำคัญของระบบ:

```text
NULL ≠ 0
```

เช่น:

```text
score = NULL
→ ยังไม่ได้กรอก

score = 0
→ นักเรียนได้ศูนย์จริง
```

ใช้หลักเดียวกันกับ Evaluation และ Result อื่น ๆ

---

# 21. Unique Constraints

ข้อมูลที่เป็นของโรงเรียนต้องคิด Tenant ด้วย

ตัวอย่าง:

```text
UNIQUE(school_id, student_code)

UNIQUE(school_id, year_be)
```

ไม่ใช้ `student_code` unique ทั้ง Platform

---

# 22. Transaction

ใช้ Transaction กับ operation หลายขั้นตอน เช่น:

* Import DMC
* Bulk scores
* Finalize subject
* Annual result
* Promotion
* Unlock
* Approval

Flow:

```text
BEGIN
 ↓
Business operation
 ↓
Audit
 ↓
COMMIT
```

หากล้มเหลว:

```text
ROLLBACK
```

---

# 23. Migration

ใช้ SQL Migration Files เช่น:

```text
database/migrations/

20260908_001_create_schools.sql
20260908_002_create_users.sql
...
```

มี:

```text
schema_migrations
```

Local สามารถมี Migration Runner ผ่าน PHP CLI

Production MVP ใช้ phpMyAdmin Import Migration SQL

ไม่สร้าง public `/migrate` endpoint

---

# 24. Seed

แยก:

```text
database/seeds/
```

สำหรับข้อมูลตั้งต้น เช่น:

* Roles
* Permissions
* Grade Levels
* Attendance Status
* Activity Types
* Evaluation Frameworks
* สมรรถนะ 5 ด้าน
* รายการประเมินสมรรถนะ

---

# 25. Frontend Strategy

ใช้:

```text
Server-rendered PHP
+
Bootstrap
+
HTMX
+
Vanilla JS
```

หลักการ:

```text
Server-rendered first

HTMX
→ interaction ที่ต้องคุยกับ Server

Vanilla JS
→ behavior ภายใน Browser
```

---

# 26. Save Strategy

## Form ทั่วไป

ใช้ปุ่ม:

```text
บันทึก
```

## Gradebook / Attendance / Evaluation

ใช้ Autosave แบบมีจังหวะ

เช่น:

```text
กรอกคะแนน
 ↓
Blur / Enter
 ↓
Validate
 ↓
HTMX Request
 ↓
Save
 ↓
✓
```

ไม่ยิง request ทุก keypress

---

# 27. Gradebook UX

รองรับ Keyboard Navigation:

* Enter
* Tab
* Shift+Tab
* Arrow keys ตามความเหมาะสม

แสดงสถานะ:

```text
กำลังบันทึก
บันทึกแล้ว
ผิดพลาด
```

คะแนนรวมและเกรดที่ระบบคำนวณเป็น readonly

---

# 28. Attendance UX

ใช้:

```text
มาเรียนทั้งหมด
```

เป็น action เริ่มต้น

แล้วครูเปลี่ยนเฉพาะ:

* ขาด
* ป่วย
* ลา
* สาย

เพื่อลดจำนวนคลิก

---

# 29. Evaluation UX

รองรับสอง Mode:

### Class Mode

ประเมินหัวข้อหนึ่งให้ทั้งห้อง

### Student Mode

ประเมินนักเรียนหนึ่งคนหลายรายการ

รองรับ Bulk Action แต่การ overwrite จำนวนมากต้อง Confirm

---

# 30. Responsive

ระบบเป็น Desktop-first สำหรับ Data Entry จำนวนมาก

แต่ต้อง Responsive

Mobile เหมาะกับ:

* Attendance
* Evaluation
* Student Lookup
* Dashboard

Gradebook บนหน้าจอเล็กใช้ Horizontal Scroll แทนการบีบคอลัมน์จนอ่านไม่ได้

---

# 31. Reports

ใช้ Report Data Service กลาง

```text
Finalized Data
 ↓
Report Data Service
 ↓
HTML Template
 ├── Browser Print Preview
 └── mPDF
       ↓
     PDF
```

Business Logic ไม่อยู่ใน Report Template

---

# 32. Draft และ Official PDF

```text
DRAFT
```

สามารถ Preview ก่อนข้อมูล Final

ส่วน:

```text
OFFICIAL
```

ต้องผ่าน Requirement และ Finalization ตามที่กำหนด

Official Report รองรับ:

* Revision
* Signatory Snapshot
* Audit
* Generated Document Record

---

# 33. Application Structure

```text
htdocs/
│
├── index.php
├── .htaccess
├── composer.json
├── composer.lock
│
├── app/
│   ├── Controllers/
│   ├── Services/
│   ├── Repositories/
│   ├── Models/
│   ├── Middleware/
│   ├── Validation/
│   └── Support/
│
├── routes/
│   ├── web.php
│   └── htmx.php
│
├── config/
│
├── views/
│   ├── layouts/
│   ├── components/
│   ├── auth/
│   ├── dashboard/
│   ├── students/
│   ├── academic/
│   ├── gradebook/
│   ├── attendance/
│   ├── evaluations/
│   ├── activities/
│   ├── results/
│   ├── reports/
│   └── admin/
│
├── database/
│   ├── migrations/
│   └── seeds/
│
├── storage/
│   ├── schools/
│   ├── logs/
│   └── temp/
│
├── assets/
│   ├── css/
│   ├── js/
│   ├── images/
│   └── vendor/
│
└── vendor/
```

Private directories ต้องถูกป้องกัน direct HTTP access

---

# 34. Environment Configuration

Local:

```text
APP_ENV=development
APP_DEBUG=true
```

Production:

```text
APP_ENV=production
APP_DEBUG=false
```

Database password และ secrets ห้าม Commit เข้า GitHub

---

# 35. Audit

Action สำคัญต้องสามารถตอบได้ว่า:

```text
WHO
WHAT
WHEN
WHY
WHERE (School)
```

โดยเฉพาะ:

* Score
* Attendance
* Evaluations
* Finalize
* Unlock
* Promotion
* Approval
* User/Role changes

---

# 36. Finalization

Workflow มาตรฐาน:

```text
DRAFT
 ↓
IN_PROGRESS
 ↓
READY
 ↓
FINALIZED
```

Finalized data ไม่สามารถแก้ตรง ๆ

ต้อง:

```text
Unlock Request
 ↓
Approval
 ↓
Edit
 ↓
Finalize Again
```

---

# 37. สิ่งที่ไม่ใช้ใน MVP

* Laravel
* React/Vue
* Node backend
* Microservices
* Redis
* Queue Worker
* Cron jobs
* Database triggers
* Public REST API platform
* Mobile native app
* Digital signature
* Parent portal
* Student portal

---

# 38. Definition of Done ด้าน Technical Architecture

Architecture นี้ถือว่าสำเร็จเมื่อสามารถรองรับ flow:

```text
System Admin
→ สร้างโรงเรียน

School Admin
→ สร้างผู้ใช้
→ กำหนด Role/Scope

Teacher
→ Login
→ เข้าโรงเรียนของตนอัตโนมัติ
→ จัดการข้อมูลตาม Permission

Academic Workflow
→ นักเรียน
→ คะแนน
→ Attendance
→ Evaluation
→ Activities
→ Annual Results
→ Promotion
→ Approval

Report
→ ปพ.5
→ ปพ.6
→ PDF
```

โดยข้อมูลโรงเรียนแต่ละแห่งถูกแยกจากกันอย่างถูกต้องใน Application เดียว

---

# 39. Technical Baseline

ให้ถือว่า Architecture หลักของระบบคือ:

```text
PHP 8.2-compatible
+
Custom MVC-lite
+
FastRoute
+
PDO
+
MySQL 8 local
+
MariaDB production
+
Shared-schema Multi-School
+
PHP Session
+
Bootstrap
+
HTMX
+
Vanilla JS
+
mPDF
+
PhpSpreadsheet
+
Git/GitHub
+
InfinityFree
```

สถานะ:

**TECHNICAL ARCHITECTURE v1.2 — READY FOR IMPLEMENTATION PLANNING**
