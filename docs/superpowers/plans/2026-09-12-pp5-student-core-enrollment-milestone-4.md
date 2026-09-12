# PP5 Student Core + Enrollment Milestone 4 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a tenant-safe student identity, academic-year enrollment, classroom placement history, student lookup/history, and a safe canonical import workflow that is ready for a future DMC-specific adapter without guessing an unknown DMC source format.

**Architecture:** Extend the approved Milestone 3 SCHOOL context with a school-scoped `students` master, academic-year-scoped `student_enrollments`, and append-only classroom placement history. Student identity remains stable across years; enrollment records represent one student's participation in one academic year; classroom placement changes are separate history rows. All tenant relationships use school-aware repository queries and composite foreign keys, and all enrollment/placement mutations honor the academic-year lifecycle.

**Tech Stack:** PHP 8.2-compatible, FastRoute 1.3.x, PDO, MySQL 8 / MariaDB-compatible SQL, PHP Session, PHPUnit 11, server-rendered PHP views, PHP native CSV, MAMP locally, InfinityFree-compatible request-driven deployment.

**Spec:** `docs/superpowers/specs/2026-09-08-pp5-technical-architecture-v1.2-design.md`

## Source Baseline

This plan starts from the Milestone 3 merged baseline:

```text
main before this plan:
dc2c26f9fdbe04d10fd8e8646c8e1dd017378145

Milestone 1:
Foundation complete and merged

Milestone 2:
School Administration complete and merged

Milestone 3:
Academic Structure complete and merged

Automated reference baseline:
1,352 tests
25,153 assertions
```

The current academic structure already provides:

```text
School
→ Academic Year
→ Grade Level
→ Classroom
→ Subject
→ Subject Offering
```

Milestone 4 extends the chain to:

```text
School
→ Student Identity
→ Academic-Year Enrollment
→ Grade Level
→ Classroom Placement History
```

---

## Global Constraints

- Preserve all Milestones 1–3 authentication, authorization, tenant-isolation, CSRF, transaction, session, password, audit, XSS escaping, and error-safety behavior.
- Keep MVC-lite: Browser → Controller → Service → Repository → PDO → Database → View.
- SQL belongs in repositories; business invariants belong in services; controllers handle HTTP representation, CSRF, session context, render/redirect only.
- Production target remains InfinityFree. SQL must work on both MySQL 8 and MariaDB.
- Do not use Laravel, React/Vue SPA, Node backend, Redis, queue workers, cron, database triggers, microservices, stored procedures for business logic, or a public REST platform.
- School authorization context always comes from authenticated Session. Browser `school_id` never selects or changes a tenant.
- Browser student/year/classroom/enrollment/import-batch IDs are target references only and must be resolved through tenant-scoped repository queries.
- Every school-scoped row must identify its school directly or through a composite database relationship that proves the same school.
- Never fetch a school-scoped student/enrollment/placement globally by ID and then authorize in PHP.
- State-changing forms verify CSRF before business validation, repository mutation, staging mutation, or audit write.
- Important student/enrollment/import mutations must answer WHO / WHAT / WHEN / WHERE (School) through `audit_logs`.
- National ID must never be written raw to audit logs, error text, application logs, or list/search HTML.
- Multi-step writes use PDO transactions and roll back completely on Throwable.
- No hard delete endpoint for students, enrollments, or placement history.
- `NULL` remains semantically different from `0`, empty string, or an invented sentinel.
- Historical migrations from Milestones 1–3 are not edited. Milestone 4 adds new migration/seed files.
- Current SCHOOL authorization remains school-wide only. Non-NULL `user_role_assignments.academic_year_id` remains ignored by permission checks.
- No teacher/classroom/subject fine-grained permission scopes are introduced.
- `pp5_test` fixtures must restore the seeded baseline after each feature test.
- Each task uses genuine TDD: RED from missing behavior → minimal GREEN → focused regression → full regression → `php -l` → `git diff --check` → commit → push → stop for review.
- Every task stops after its own commit/push. Do not begin the next task until the previous task is approved.
- Do not create or merge a pull request until Task 8 is reviewed and approved.

---

## Milestone Boundary

### Included

```text
school-scoped student identity
student profile create/update/status
student code and optional national-ID uniqueness within school
academic-year enrollment
grade-level enrollment
optional classroom placement
classroom placement history and classroom moves
transfer-out / withdrawal enrollment states
student lookup and enrollment history
student/enrollment permissions
tenant-safe student/enrollment CRUD/state transitions
PII-safe audit behavior
canonical CSV import staging
import preview / confirm / cancel
duplicate/conflict detection
idempotent re-import protection
cross-resource tenant/isolation regression
MAMP smoke and documentation
```

### Explicitly Deferred

Do not implement in Milestone 4:

```text
native DMC XLSX/XLSB parser or DMC-column mapping without a real DMC sample
automatic cross-school student transfer/linking
multiple-school student identity
promotion workflow
repeat-year decision workflow
graduation workflow
annual-result generation
gradebook / scores
attendance
evaluations
competencies
activities
teacher assignment
homeroom-teacher assignment
academic-year/classroom/subject permission scopes
non-NULL academic_year_id role authorization
student/parent portal
guardian/contact/address subsystem
medical/sensitive health profile
student photo storage
email/SMS notifications
finalization / unlock / approval workflow
PP5 / PP6 report generation
mPDF output
HTMX autosave
soft-delete framework
deployment automation
```

A real DMC sample is still required before claiming native DMC compatibility. This milestone deliberately builds a canonical import engine so a later DMC adapter can map source columns into a stable internal row contract without changing student/enrollment business rules.

---

## Locked Design Decisions

### 1. Student identity is school-scoped and year-independent

`students` represents the person/identity known by one school.

It does **not** contain:

```text
academic_year_id
classroom_id
grade_level_id
term_no
```

Those belong to enrollment/placement.

One student row survives across multiple academic years.

Primary business identity rules:

```text
UNIQUE(school_id, student_code)
UNIQUE(school_id, national_id) when national_id is not NULL
```

The database row `id` is the stable internal identity. `student_code` is a school-issued attribute and may be corrected by authorized users without changing the row ID or losing history.

The same `student_code` or national ID may exist in another school because School is the tenant boundary.

### 2. Student master fields

Milestone 4 stores only fields needed for identity, matching, and PP5-oriented academic use:

```text
school_id
student_code
national_id        nullable
prefix_th
first_name_th
last_name_th
gender_code        nullable
birth_date         nullable
status             ACTIVE | INACTIVE
created_at
updated_at
```

Field constraints:

```text
student_code   max 50 Unicode chars, required
national_id    NULL or exactly 13 ASCII digits
prefix_th      max 50 Unicode chars, required
first_name_th  max 100 Unicode chars, required
last_name_th   max 100 Unicode chars, required
gender_code    NULL | MALE | FEMALE | OTHER
birth_date     NULL or strict ISO YYYY-MM-DD, not a future date
```

Text uses the Milestone 3 Unicode policy:

```text
valid UTF-8
Unicode-aware trim
multibyte length
reject \p{Cc} control characters
```

No national-ID checksum algorithm is introduced in this milestone because the existing PP5 docs do not define that policy.

### 3. Student status is not enrollment status

Student master status:

```text
ACTIVE
INACTIVE
```

Rules:

```text
create → ACTIVE
ACTIVE ↔ INACTIVE
same state → no-op
```

An INACTIVE student remains readable for history but cannot receive a new enrollment.

Changing a student to INACTIVE is denied while that student has an `ACTIVE` enrollment in any DRAFT or ACTIVE academic year in the same school.

A transfer-out does not automatically set the student master to INACTIVE. The person may later return to the school.

### 4. Enrollment is one student in one academic year

`student_enrollments` is academic-year-scoped.

Identity:

```text
UNIQUE(school_id, academic_year_id, student_id)
```

One student has at most one enrollment row per academic year within the school.

Fields:

```text
school_id
academic_year_id
student_id
grade_level_id
entry_date    nullable
exit_date     nullable
status
created_at
updated_at
```

Enrollment status:

```text
ACTIVE
TRANSFERRED_OUT
WITHDRAWN
```

Creation always starts `ACTIVE`.

Allowed transitions:

```text
ACTIVE → TRANSFERRED_OUT
ACTIVE → WITHDRAWN
same state → no-op only while the year is open
terminal state → any other state = deny
```

`TRANSFERRED_OUT` and `WITHDRAWN` require `exit_date`.

There is no enrollment cancel/delete correction path in Milestone 4. A mistaken enrollment remains a controlled future-correction case rather than being silently deleted or repurposed.

No reopen/reactivate workflow is included.

Promotion, repeat, and graduation are not encoded as enrollment states in this milestone. A future workflow creates the next academic-year enrollment based on annual results.

### 5. Grade level belongs to enrollment

Every enrollment has one `grade_level_id`.

The grade level is selected when the enrollment is created and is immutable in Milestone 4.

This preserves a stable academic-year record and keeps ordinary classroom moves from changing the student's grade.

If an enrollment was created with the wrong grade, the correction workflow is not silently invented in Milestone 4. The normal UI and import preview must validate grade carefully before create; a controlled enrollment-correction workflow is deferred until its downstream effects are designed.

### 6. Classroom placement is separate history

Classroom placement is not stored directly on the student master.

Use `student_classroom_placements`:

```text
school_id
academic_year_id
grade_level_id
enrollment_id
classroom_id
status       ACTIVE | ENDED
started_at
ended_at
```

A student enrollment may exist without a classroom placement.

At most one ACTIVE placement is allowed per enrollment. This invariant is enforced transactionally by locking the enrollment and current placement before mutation because portable MySQL/MariaDB partial uniqueness is not used.

Placement change behavior:

```text
no current placement + classroom X
→ insert ACTIVE placement X

current X + classroom Y
→ end X
→ insert ACTIVE placement Y

current X + NULL
→ end X
→ student remains enrolled but unplaced

current X + X
→ exact no-op
```

The new classroom must be:

```text
same school
same academic year
same grade level as enrollment
ACTIVE
```

The old classroom does not need to remain ACTIVE in order to move/unassign away from it.

When an enrollment leaves ACTIVE status, any current placement is ended in the same transaction.

### 7. Academic-year lifecycle applies to enrollment/placement

Student master create/update/status is school-wide and independent of the academic-year state.

Enrollment and placement mutations are allowed only when the owning academic year is:

```text
DRAFT
ACTIVE
```

For `CLOSED` year:

```text
historical reads allowed
create denied
placement/move/unassign denied
status mutation denied
same-state mutation request denied
import preview/apply for that year denied
```

No CLOSED-year correction/override/unlock workflow is introduced.

`entry_date` and `exit_date` are strict ISO dates. If a year start date exists, enrollment dates cannot precede it; if an end date exists, enrollment dates cannot exceed it. When both entry/exit dates exist, `exit_date >= entry_date`.

### 8. Database-enforced tenant and academic relationships

Application checks are mandatory, and the database also rejects cross-tenant/cross-year/cross-grade combinations.

New candidate keys/FKs must include:

```text
students
  UNIQUE(id, school_id)

student_enrollments
  UNIQUE(id, school_id, academic_year_id, grade_level_id)

classrooms
  add UNIQUE(id, school_id, academic_year_id, grade_level_id)

student_classroom_placements
  (enrollment_id, school_id, academic_year_id, grade_level_id)
    → student_enrollments
  (classroom_id, school_id, academic_year_id, grade_level_id)
    → classrooms
```

This makes a placement with the wrong school, year, or grade impossible even if application validation fails.

### 9. Permission codes

Add exactly four permissions:

```text
STUDENT_VIEW
STUDENT_MANAGE
ENROLLMENT_MANAGE
STUDENT_IMPORT
```

Mappings after the new seed:

```text
SYSTEM_ADMIN      → existing SYSTEM permissions only
SCHOOL_ADMIN      → all existing school/academic permissions + all 4 Milestone 4 permissions
ACADEMIC_ADMIN    → existing 5 academic permissions + all 4 Milestone 4 permissions
HOMEROOM_TEACHER  → no Milestone 4 permission by default
SUBJECT_TEACHER   → no Milestone 4 permission by default
EXECUTIVE         → no Milestone 4 permission by default
VIEWER            → no Milestone 4 permission by default
```

Reason: student identity is PII and fine-grained classroom teacher scope is not implemented yet. Do not grant broad student access to teacher/viewer roles merely to make the UI convenient.

Seed baseline after Milestone 4:

```text
7 roles
18 permissions
27 role-permission mappings
6 grade levels
```

### 10. Student PII handling

National ID is the highest-sensitivity field introduced so far.

Rules:

```text
never write raw national_id to audit_logs
never include raw national_id in generic exception text
never include raw national_id in application logs
never show national_id in student list pages
never put national_id in GET query strings
student detail with STUDENT_VIEW shows a masked value only
student edit route with STUDENT_MANAGE may show/edit the full value
import preview does not render the full national_id
student_import_rows may hold national_id only while a PREVIEW batch is live
PREVIEW staging expires after 24 hours and row-level staging is deleted on apply/cancel/expiry
```

Audit for student create/update records field names and safe metadata rather than raw PII.

Example:

```json
{
  "student_code": "12345",
  "status": "ACTIVE",
  "has_national_id": true
}
```

For profile edits:

```json
{
  "changed_fields": [
    "first_name_th",
    "national_id"
  ]
}
```

### 11. Canonical import contract

Because no real DMC source file has been approved, Milestone 4 does **not** invent DMC column names.

The import engine accepts a PP5 canonical UTF-8 CSV with exact header order:

```text
student_code,national_id,prefix_th,first_name_th,last_name_th,gender_code,birth_date,grade_level_code,classroom_code,entry_date
```

Rules:

```text
school comes from Session
academic year is selected in the import screen
student_code required
national_id optional
prefix/name required
gender_code optional
birth_date optional
grade_level_code required
classroom_code optional
entry_date optional
UTF-8 BOM accepted
max upload size 2 MiB
max 1,000 data rows
comma delimiter
one header row
```

No raw uploaded file is copied into repository storage.

A future DMC adapter will transform a real approved DMC file into this canonical row structure. Native XLSX/XLSB support is not claimed by this milestone.

### 12. Import identity matching and conflict policy

For each normalized row, resolve within the current school only.

Matching order:

```text
1. If national_id is present, find by national_id.
2. Independently find by student_code.
3. If both resolve to different students → CONFLICT.
4. If national_id matches one student and student_code is new/different → CONFLICT.
5. If student_code matches one student and supplied national_id conflicts with that student's non-NULL national_id → CONFLICT.
6. If neither matches → CREATE student.
7. If one identity matches and all supplied profile fields agree → MATCH existing student.
8. Import never silently updates an existing student's profile.
```

Rows duplicated inside the same file by `student_code` or non-NULL `national_id` are errors.

Existing enrollment in the selected year:

```text
same student + same grade + same current classroom/unplaced state
→ NOOP

same student + different grade
→ CONFLICT

same student + different current classroom
→ CONFLICT

existing terminal enrollment
→ CONFLICT
```

The import is therefore safe and idempotent: it creates missing data, accepts exact matches as no-op, and never silently moves or overwrites an existing student.

### 13. Import staging and apply semantics

Use:

```text
student_import_batches
student_import_rows
```

Batch status:

```text
PREVIEW
APPLIED
CANCELLED
EXPIRED
```

PREVIEW batches expire 24 hours after creation. Expiration is request-driven because production has no cron: opening the import area or creating a new preview purges expired row-level staging for the current school and marks those batches `EXPIRED`. Expiry writes no business audit.

Preview:

```text
parse CSV
normalize/validate
resolve tenant-scoped references
classify CREATE/MATCH/NOOP/CONFLICT
persist normalized preview rows
write no business entity rows
write no audit rows
```

Apply is enabled only when:

```text
error_count = 0
and at least one business CREATE is required
```

Apply revalidates every row inside one transaction. Preview is not trusted as authorization or as a stale business decision.

Successful apply:

```text
locks school/year/batch
re-resolves rows deterministically
creates missing students
creates missing enrollments
creates initial placements when classroom_code is present
writes normal per-entity audit rows
writes STUDENT_IMPORT_APPLIED summary audit
marks batch APPLIED
deletes row-level staging records
commits
```

Any failure rolls back all entity writes, audits, batch state changes, and staging deletion.

Cancel:

```text
PREVIEW → CANCELLED
delete row-level staging records
no business audit
```

Applied or expired batches cannot be cancelled.

A previously APPLIED batch with the same:

```text
school_id
academic_year_id
source_sha256
```

is rejected as a duplicate re-import.

### 14. Audit action codes

Use stable action codes:

```text
STUDENT_CREATED
STUDENT_UPDATED
STUDENT_STATUS_CHANGED
STUDENT_ENROLLMENT_CREATED
STUDENT_ENROLLMENT_STATUS_CHANGED
STUDENT_CLASSROOM_PLACEMENT_CHANGED
STUDENT_IMPORT_APPLIED
```

No-op operations produce no audit.

Student profile audit must not contain raw national ID or full name values. Enrollment/placement audits may contain entity IDs, academic-year ID, grade-level ID, classroom ID, status, and entry/exit dates.

Import summary audit contains only counts and source hash, not row PII.

### 15. Lock ordering

Preserve deterministic lock order.

Student master mutation:

```text
school
→ student
→ open enrollment existence check when inactivating
```

Enrollment create:

```text
school
→ academic year
→ student
→ duplicate enrollment check
→ classroom if supplied
```

Enrollment status/placement mutation:

```text
tenant-scoped pre-read enrollment to obtain immutable academic_year_id
→ BEGIN
→ school
→ academic year
→ enrollment
→ current placement
→ new classroom if supplied
```

Import apply:

```text
school
→ academic year
→ import batch
→ rows in row_no order
→ matched students in deterministic ID order
→ matched enrollments in deterministic ID order
→ placement/classroom targets
```

The pre-read is never an authorization shortcut; the row is reloaded/locked by `(school_id, id)` in the transaction.

### 16. HTTP/error behavior

All Milestone 4 routes are SCHOOL routes:

```text
AuthMiddleware
→ SchoolContextMiddleware
→ PermissionMiddleware
→ handler
```

GET foreign/missing target:

```text
friendly 404
```

POST foreign/missing target or parent:

```text
safe 422
zero business writes
zero audit writes
```

Malformed CSRF including missing/string mismatch/array/object:

```text
419
zero entity writes
zero staging writes
zero audit writes
```

Unexpected database Throwable becomes a safe `DomainException`; browser responses never expose SQLSTATE, PDOException, SQL, constraints, stack trace, credentials, or filesystem paths.

---

## Planned Routes

### Student identity

```text
GET  /students                         STUDENT_VIEW
GET  /students/create                  STUDENT_MANAGE
POST /students                         STUDENT_MANAGE
GET  /students/{id}                    STUDENT_VIEW
GET  /students/{id}/edit               STUDENT_MANAGE
POST /students/{id}                    STUDENT_MANAGE
POST /students/{id}/status             STUDENT_MANAGE
```

### Enrollment and placement

```text
GET  /academic/enrollments                         STUDENT_VIEW
GET  /academic/enrollments/create                  ENROLLMENT_MANAGE
POST /academic/enrollments                         ENROLLMENT_MANAGE
GET  /academic/enrollments/{id}/edit               ENROLLMENT_MANAGE
POST /academic/enrollments/{id}/placement          ENROLLMENT_MANAGE
POST /academic/enrollments/{id}/status             ENROLLMENT_MANAGE
```

### Import

```text
GET  /academic/student-import                      STUDENT_IMPORT
POST /academic/student-import/preview              STUDENT_IMPORT
GET  /academic/student-import/{id}                 STUDENT_IMPORT
POST /academic/student-import/{id}/apply           STUDENT_IMPORT
POST /academic/student-import/{id}/cancel          STUDENT_IMPORT
```

Use FastRoute numeric `{id:\d+}` variables.

---

## File Map

Expected new files:

```text
database/migrations/20260912_001_student_core_enrollment.sql
database/seeds/20260912_001_student_core_permissions.sql

htdocs/app/Repositories/StudentRepository.php
htdocs/app/Repositories/StudentEnrollmentRepository.php
htdocs/app/Repositories/StudentClassroomPlacementRepository.php
htdocs/app/Repositories/StudentImportBatchRepository.php
htdocs/app/Repositories/StudentImportRowRepository.php

htdocs/app/Services/StudentAdministrationService.php
htdocs/app/Services/EnrollmentAdministrationService.php
htdocs/app/Services/StudentImportService.php

htdocs/app/Validation/StudentProfileRules.php
htdocs/app/Support/CanonicalStudentCsvReader.php

htdocs/app/Controllers/StudentController.php
htdocs/app/Controllers/EnrollmentController.php
htdocs/app/Controllers/StudentImportController.php

htdocs/views/students/index.php
htdocs/views/students/create.php
htdocs/views/students/show.php
htdocs/views/students/edit.php

htdocs/views/academic/enrollments/index.php
htdocs/views/academic/enrollments/create.php
htdocs/views/academic/enrollments/edit.php

htdocs/views/academic/student-import/index.php
htdocs/views/academic/student-import/preview.php

tests/Feature/StudentCoreSchemaTest.php
tests/Feature/StudentPermissionSeedTest.php
tests/Feature/StudentAdministrationTest.php
tests/Feature/StudentHttpTest.php
tests/Feature/EnrollmentAdministrationTest.php
tests/Feature/EnrollmentHttpTest.php
tests/Feature/StudentIsolationTest.php
tests/Feature/StudentImportTest.php
tests/Feature/StudentImportHttpTest.php
```

Expected modified files:

```text
htdocs/app/Http/Request.php
htdocs/app/Application.php
htdocs/app/Controllers/DashboardController.php
htdocs/routes/web.php
htdocs/views/dashboard/index.php
tests/Feature/RolePermissionSeedTest.php
tests/Feature/DashboardAccessTest.php
README.md
```

---

### Task 1: Add Student/Enrollment Schema and Permissions

**Files:**
- Create: `database/migrations/20260912_001_student_core_enrollment.sql`
- Create: `database/seeds/20260912_001_student_core_permissions.sql`
- Create: `tests/Feature/StudentCoreSchemaTest.php`
- Create: `tests/Feature/StudentPermissionSeedTest.php`
- Modify: `tests/Feature/RolePermissionSeedTest.php`
- Modify: `README.md` only for seed-count baseline if the existing test/document requires it during this task

**Interfaces:**
- Produces `students`, `student_enrollments`, `student_classroom_placements`, `student_import_batches`, `student_import_rows`.
- Adds classroom candidate key `(id, school_id, academic_year_id, grade_level_id)`.
- Produces four Milestone 4 permission codes and exact mappings.
- Seed baseline becomes `7 roles / 18 permissions / 27 mappings / 6 grade levels`.

- [ ] **Step 1: Write RED schema tests**

`StudentCoreSchemaTest` must introspect columns, indexes, defaults, nullable behavior, FKs, and prove DB-level tenant integrity.

Required rejection probes:

```text
Student Enrollment school A + Student B → DB rejects
Student Enrollment school A + Academic Year B → DB rejects
Placement school A + Enrollment B → DB rejects
Placement school A + Classroom B → DB rejects
Placement same school/year + classroom from wrong grade → DB rejects
Import Batch school A + Academic Year B → DB rejects
Import Row school A + matched Student B → DB rejects
```

Also prove:

```text
same student_code in different schools → allowed
same national_id in different schools → allowed
duplicate student_code in same school → rejected
duplicate non-NULL national_id in same school → rejected
multiple NULL national_id rows in same school → allowed
one student/year enrollment → enforced
```

Run before migration:

```bash
htdocs/vendor/bin/phpunit tests/Feature/StudentCoreSchemaTest.php
```

Expected: genuine RED because the Milestone 4 tables/keys do not exist.

- [ ] **Step 2: Write RED permission seed tests**

Assert exact permission codes:

```text
STUDENT_VIEW
STUDENT_MANAGE
ENROLLMENT_MANAGE
STUDENT_IMPORT
```

Assert exact mappings:

```text
SCHOOL_ADMIN   → all 4
ACADEMIC_ADMIN → all 4
other roles    → none of the 4
```

Update deterministic count assertions to:

```text
roles = 7
permissions = 18
role_permissions = 27
grade_levels = 6
```

Run:

```bash
htdocs/vendor/bin/phpunit tests/Feature/StudentPermissionSeedTest.php tests/Feature/RolePermissionSeedTest.php
```

Expected: RED before the new seed.

- [ ] **Step 3: Add migration**

The migration must create the following structure:

```sql
ALTER TABLE classrooms
  ADD UNIQUE KEY uq_classroom_id_school_year_grade
    (id, school_id, academic_year_id, grade_level_id);

CREATE TABLE students (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id BIGINT UNSIGNED NOT NULL,
    student_code VARCHAR(50) NOT NULL,
    national_id CHAR(13) NULL,
    prefix_th VARCHAR(50) NOT NULL,
    first_name_th VARCHAR(100) NOT NULL,
    last_name_th VARCHAR(100) NOT NULL,
    gender_code VARCHAR(20) NULL,
    birth_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_students_school_code (school_id, student_code),
    UNIQUE KEY uq_students_school_national_id (school_id, national_id),
    UNIQUE KEY uq_students_id_school (id, school_id),
    CONSTRAINT fk_student_school
      FOREIGN KEY (school_id) REFERENCES schools(id)
      ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_enrollments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    grade_level_id BIGINT UNSIGNED NOT NULL,
    entry_date DATE NULL,
    exit_date DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_enrollment_school_year_student
      (school_id, academic_year_id, student_id),
    UNIQUE KEY uq_student_enrollment_id_school_year_grade
      (id, school_id, academic_year_id, grade_level_id),
    KEY idx_student_enrollment_year_grade_status
      (school_id, academic_year_id, grade_level_id, status),
    CONSTRAINT fk_student_enrollment_year_school
      FOREIGN KEY (academic_year_id, school_id)
      REFERENCES academic_years(id, school_id)
      ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_student_enrollment_student_school
      FOREIGN KEY (student_id, school_id)
      REFERENCES students(id, school_id)
      ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_student_enrollment_grade
      FOREIGN KEY (grade_level_id)
      REFERENCES grade_levels(id)
      ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_classroom_placements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    grade_level_id BIGINT UNSIGNED NOT NULL,
    enrollment_id BIGINT UNSIGNED NOT NULL,
    classroom_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_student_placement_enrollment_status
      (school_id, enrollment_id, status),
    CONSTRAINT fk_student_placement_enrollment_scope
      FOREIGN KEY (enrollment_id, school_id, academic_year_id, grade_level_id)
      REFERENCES student_enrollments(id, school_id, academic_year_id, grade_level_id)
      ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_student_placement_classroom_scope
      FOREIGN KEY (classroom_id, school_id, academic_year_id, grade_level_id)
      REFERENCES classrooms(id, school_id, academic_year_id, grade_level_id)
      ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_import_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    source_name VARCHAR(190) NOT NULL,
    source_sha256 CHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PREVIEW',
    row_count INT UNSIGNED NOT NULL DEFAULT 0,
    create_student_count INT UNSIGNED NOT NULL DEFAULT 0,
    create_enrollment_count INT UNSIGNED NOT NULL DEFAULT 0,
    noop_count INT UNSIGNED NOT NULL DEFAULT 0,
    error_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    applied_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_import_batch_id_school_year
      (id, school_id, academic_year_id),
    KEY idx_student_import_school_year_status
      (school_id, academic_year_id, status),
    KEY idx_student_import_school_status_expiry
      (school_id, status, expires_at),
    KEY idx_student_import_applied_hash
      (school_id, academic_year_id, source_sha256, status),
    CONSTRAINT fk_student_import_batch_year_school
      FOREIGN KEY (academic_year_id, school_id)
      REFERENCES academic_years(id, school_id)
      ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_student_import_batch_user
      FOREIGN KEY (created_by) REFERENCES users(id)
      ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_import_rows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id BIGINT UNSIGNED NOT NULL,
    school_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    row_no INT UNSIGNED NOT NULL,
    student_code VARCHAR(50) NOT NULL,
    national_id CHAR(13) NULL,
    prefix_th VARCHAR(50) NOT NULL,
    first_name_th VARCHAR(100) NOT NULL,
    last_name_th VARCHAR(100) NOT NULL,
    gender_code VARCHAR(20) NULL,
    birth_date DATE NULL,
    grade_level_code VARCHAR(20) NOT NULL,
    classroom_code VARCHAR(50) NULL,
    entry_date DATE NULL,
    matched_student_id BIGINT UNSIGNED NULL,
    student_action VARCHAR(20) NOT NULL,
    enrollment_action VARCHAR(20) NOT NULL,
    error_code VARCHAR(50) NULL,
    error_message VARCHAR(500) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_import_row_batch_row (batch_id, row_no),
    KEY idx_student_import_row_batch (school_id, batch_id, row_no),
    CONSTRAINT fk_student_import_row_batch_scope
      FOREIGN KEY (batch_id, school_id, academic_year_id)
      REFERENCES student_import_batches(id, school_id, academic_year_id)
      ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_student_import_row_matched_student
      FOREIGN KEY (matched_student_id, school_id)
      REFERENCES students(id, school_id)
      ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Do not use DB triggers or CHECK constraints for business status transitions.

- [ ] **Step 4: Add idempotent permission seed**

Use `INSERT ... ON DUPLICATE KEY UPDATE` for permissions and `INSERT IGNORE ... SELECT` for mappings. Do not hard-code IDs.

- [ ] **Step 5: Migrate/seed and verify**

On `pp5_test`:

```bash
php tools/migrate.php --database=pp5_test
php tools/seed.php --database=pp5_test
php tools/seed.php --database=pp5_test
htdocs/vendor/bin/phpunit tests/Feature/StudentCoreSchemaTest.php tests/Feature/StudentPermissionSeedTest.php tests/Feature/RolePermissionSeedTest.php
htdocs/vendor/bin/phpunit
```

Second seed run applies nothing.

- [ ] **Step 6: Syntax/diff verification**

```bash
find . -path './htdocs/vendor' -prune -o -name '*.php' -type f -exec php -l {} \;
git diff --check
```

- [ ] **Step 7: Commit and push**

```bash
git add database/migrations/20260912_001_student_core_enrollment.sql \
        database/seeds/20260912_001_student_core_permissions.sql \
        tests/Feature/StudentCoreSchemaTest.php \
        tests/Feature/StudentPermissionSeedTest.php \
        tests/Feature/RolePermissionSeedTest.php README.md
git commit -m "feat: add student core enrollment schema"
git push -u origin milestone/4-student-core-enrollment
```

Stop for review.

---

### Task 2: Add Student Identity Domain Service

**Files:**
- Create: `htdocs/app/Validation/StudentProfileRules.php`
- Create: `htdocs/app/Repositories/StudentRepository.php`
- Create: `htdocs/app/Repositories/StudentEnrollmentRepository.php`
- Create: `htdocs/app/Services/StudentAdministrationService.php`
- Create: `tests/Feature/StudentAdministrationTest.php`

**Interfaces:**

```php
StudentProfileRules::normalize(
    string $studentCode,
    ?string $nationalId,
    string $prefixTh,
    string $firstNameTh,
    string $lastNameTh,
    ?string $genderCode,
    ?string $birthDate
): array

StudentRepository::listForSchool(int $schoolId, ?string $search = null): array
StudentRepository::findForSchool(int $schoolId, int $studentId): ?array
StudentRepository::lockForSchool(int $schoolId, int $studentId): ?array
StudentRepository::findByCodeForSchool(int $schoolId, string $studentCode): ?array
StudentRepository::findByNationalIdForSchool(int $schoolId, string $nationalId): ?array
StudentRepository::create(int $schoolId, array $profile): int
StudentRepository::update(int $schoolId, int $studentId, array $profile): void
StudentRepository::updateStatus(int $schoolId, int $studentId, string $status): void

StudentEnrollmentRepository::hasActiveInOpenYear(int $schoolId, int $studentId): bool

StudentAdministrationService::createStudent(
    int $schoolId,
    int $actorUserId,
    string $studentCode,
    ?string $nationalId,
    string $prefixTh,
    string $firstNameTh,
    string $lastNameTh,
    ?string $genderCode,
    ?string $birthDate,
    ?string $ipAddress = null
): int

StudentAdministrationService::updateStudent(
    int $schoolId,
    int $actorUserId,
    int $studentId,
    string $studentCode,
    ?string $nationalId,
    string $prefixTh,
    string $firstNameTh,
    string $lastNameTh,
    ?string $genderCode,
    ?string $birthDate,
    ?string $ipAddress = null
): void

StudentAdministrationService::changeStatus(
    int $schoolId,
    int $actorUserId,
    int $studentId,
    string $status,
    ?string $ipAddress = null
): void
```

- [ ] **Step 1: Write RED domain tests**

Cover:

```text
create student in own school
same student_code other school allowed
same national_id other school allowed
duplicate code same school denied
duplicate non-NULL national_id same school denied
blank national_id → NULL
national_id not exactly 13 ASCII digits denied
Thai/Unicode names accepted
invalid UTF-8/control chars denied
length boundaries
gender NULL/MALE/FEMALE/OTHER accepted; other codes denied
strict birth date; future birth date denied
foreign target update/status denied
student_code correction retains same row ID/history
profile exact no-op → no write/no audit
ACTIVE↔INACTIVE
INACTIVE→ACTIVE allowed
inactivate with ACTIVE enrollment in DRAFT/ACTIVE year denied
CLOSED historical enrollment does not by itself block student inactivation
transaction rollback on audit/repository failure
raw national_id absent from audit and thrown messages
```

- [ ] **Step 2: Implement `StudentProfileRules`**

Use one reusable normalizer for manual and import paths.

Rules:

```text
blank optional values → NULL
required values reject blank after Unicode trim
mb_check_encoding(..., 'UTF-8')
mb_strlen()
reject /\p{Cc}/u
national_id → 13 ASCII digits only
gender_code → NULL/MALE/FEMALE/OTHER
birth_date → strict YYYY-MM-DD and not future
```

Return normalized associative keys matching the `students` columns.

- [ ] **Step 3: Implement tenant-scoped repositories**

Every target SQL includes:

```sql
WHERE school_id = :school_id
  AND id = :student_id
```

Search list may match:

```text
student_code
prefix_th + first_name_th + last_name_th
first_name_th
last_name_th
```

Do not search national ID through GET list search.

`hasActiveInOpenYear` joins `student_enrollments` to `academic_years` with matching `school_id` and counts only:

```text
enrollment.status = ACTIVE
year.status IN (DRAFT, ACTIVE)
```

- [ ] **Step 4: Implement StudentAdministrationService transactions**

For create/update/status:

```text
lock ACTIVE school
validate/normalize
resolve target inside school
write only if changed
audit in same transaction
commit
```

Translate unexpected Throwable to safe `DomainException`.

Use audit codes:

```text
STUDENT_CREATED
STUDENT_UPDATED
STUDENT_STATUS_CHANGED
```

Audit does not include names or raw national ID. `STUDENT_UPDATED` records `changed_fields`.

- [ ] **Step 5: Verify focused/full regression**

```bash
htdocs/vendor/bin/phpunit tests/Feature/StudentAdministrationTest.php
htdocs/vendor/bin/phpunit
find . -path './htdocs/vendor' -prune -o -name '*.php' -type f -exec php -l {} \;
git diff --check
```

- [ ] **Step 6: Commit and push**

```bash
git add htdocs/app/Validation/StudentProfileRules.php \
        htdocs/app/Repositories/StudentRepository.php \
        htdocs/app/Repositories/StudentEnrollmentRepository.php \
        htdocs/app/Services/StudentAdministrationService.php \
        tests/Feature/StudentAdministrationTest.php
git commit -m "feat: add student identity administration"
git push
```

Stop for review.

---

### Task 3: Add Tenant-Safe Student Screens

**Files:**
- Create: `htdocs/app/Controllers/StudentController.php`
- Create: `htdocs/views/students/index.php`
- Create: `htdocs/views/students/create.php`
- Create: `htdocs/views/students/show.php`
- Create: `htdocs/views/students/edit.php`
- Modify: `htdocs/app/Application.php`
- Modify: `htdocs/routes/web.php`
- Create: `tests/Feature/StudentHttpTest.php`

**Interfaces:**

```php
StudentController::index(Request $request): Response
StudentController::create(): Response
StudentController::store(Request $request): Response
StudentController::show(int $studentId): Response
StudentController::edit(int $studentId): Response
StudentController::update(Request $request, int $studentId): Response
StudentController::changeStatus(Request $request, int $studentId): Response
```

Controller derives `school_id` and actor `user_id` from Session only.

- [ ] **Step 1: Write RED HTTP tests**

Cover:

```text
guest → login
SYSTEM context → 403
missing permission → 403
STUDENT_VIEW list/show works
STUDENT_MANAGE create/edit/update/status works
viewer with only STUDENT_VIEW cannot mutate
School A list never shows School B
foreign student show/edit behaves like missing
foreign POST safe 422, unchanged entity/audit
browser school_id ignored
bad/missing/array CSRF → 419 before writes
malformed scalar inputs do not produce TypeError/500
HTML-like names escaped
list does not render raw national_id
show renders masked national_id
edit is STUDENT_MANAGE-only
no SQL/PDO/stack/filesystem leak
```

- [ ] **Step 2: Register exact routes**

Add the Student identity routes from Planned Routes with SCHOOL context metadata.

- [ ] **Step 3: Implement controller representation validation**

Validate that scalar fields are strings before calling the service. Reject arrays/objects with friendly 422.

For GET search:

```text
q absent → null
q must be scalar string
Unicode trim
max 100 chars
```

Do not accept `school_id`.

- [ ] **Step 4: Implement views**

Student list columns:

```text
student_code
full name
status
actions
```

Do not list national ID or birth date.

Show page may display:

```text
student_code
full name
masked national ID
gender
birth date
status
```

Mask national ID so only the last four digits are recognizable.

Edit page uses a full national-ID input only because the route is gated by `STUDENT_MANAGE`.

Escape every dynamic value.

- [ ] **Step 5: Verify**

```bash
htdocs/vendor/bin/phpunit tests/Feature/StudentHttpTest.php
htdocs/vendor/bin/phpunit tests/Feature/SchoolIsolationTest.php
htdocs/vendor/bin/phpunit
find . -path './htdocs/vendor' -prune -o -name '*.php' -type f -exec php -l {} \;
git diff --check
```

- [ ] **Step 6: Commit and push**

```bash
git add htdocs/app/Controllers/StudentController.php \
        htdocs/app/Application.php htdocs/routes/web.php \
        htdocs/views/students tests/Feature/StudentHttpTest.php
git commit -m "feat: add tenant safe student screens"
git push
```

Stop for review.

---

### Task 4: Add Enrollment and Classroom Placement Domain

**Files:**
- Modify: `htdocs/app/Repositories/StudentEnrollmentRepository.php`
- Create: `htdocs/app/Repositories/StudentClassroomPlacementRepository.php`
- Create: `htdocs/app/Services/EnrollmentAdministrationService.php`
- Create: `tests/Feature/EnrollmentAdministrationTest.php`

**Interfaces:**

```php
StudentEnrollmentRepository::listForSchoolYear(
    int $schoolId,
    int $academicYearId,
    ?int $gradeLevelId = null,
    ?int $classroomId = null,
    ?string $status = null,
    ?string $search = null
): array

StudentEnrollmentRepository::listForStudent(int $schoolId, int $studentId): array
StudentEnrollmentRepository::findForSchool(int $schoolId, int $enrollmentId): ?array
StudentEnrollmentRepository::lockForSchool(int $schoolId, int $enrollmentId): ?array
StudentEnrollmentRepository::findForStudentYear(
    int $schoolId,
    int $academicYearId,
    int $studentId
): ?array
StudentEnrollmentRepository::create(
    int $schoolId,
    int $academicYearId,
    int $studentId,
    int $gradeLevelId,
    ?string $entryDate
): int
StudentEnrollmentRepository::updateStatus(
    int $schoolId,
    int $enrollmentId,
    string $status,
    ?string $exitDate
): void

StudentClassroomPlacementRepository::listForEnrollment(
    int $schoolId,
    int $enrollmentId
): array
StudentClassroomPlacementRepository::findActiveForEnrollment(
    int $schoolId,
    int $enrollmentId
): ?array
StudentClassroomPlacementRepository::lockActiveForEnrollment(
    int $schoolId,
    int $enrollmentId
): ?array
StudentClassroomPlacementRepository::create(
    int $schoolId,
    int $academicYearId,
    int $gradeLevelId,
    int $enrollmentId,
    int $classroomId
): int
StudentClassroomPlacementRepository::end(
    int $schoolId,
    int $placementId
): void

EnrollmentAdministrationService::createEnrollment(
    int $schoolId,
    int $actorUserId,
    int $academicYearId,
    int $studentId,
    int $gradeLevelId,
    ?int $classroomId,
    ?string $entryDate,
    ?string $ipAddress = null
): int

EnrollmentAdministrationService::changePlacement(
    int $schoolId,
    int $actorUserId,
    int $enrollmentId,
    ?int $classroomId,
    ?string $ipAddress = null
): void

EnrollmentAdministrationService::changeStatus(
    int $schoolId,
    int $actorUserId,
    int $enrollmentId,
    string $status,
    ?string $exitDate,
    ?string $ipAddress = null
): void
```

- [ ] **Step 1: Write RED domain tests**

Cover:

```text
create enrollment for own ACTIVE student
one enrollment per student/year
same student may enroll in another year
foreign student/year denied
INACTIVE student denied
active grade level required
DRAFT and ACTIVE year allow create
CLOSED year denies create
entry date strict ISO
entry date respects any available year start/end boundary
exit date respects year boundaries and cannot precede entry date
unplaced enrollment allowed
initial active classroom placement allowed
classroom must same school/year/grade and ACTIVE
same school + wrong year classroom denied
same year + wrong grade classroom denied
placement move closes old and creates one active new row
placement unassign closes old and leaves no active placement
same classroom placement exact no-op
inactive old classroom does not prevent move away
inactive new classroom denied
change status ACTIVE→TRANSFERRED_OUT with exit date
ACTIVE→WITHDRAWN with exit date
transfer/withdraw without exit date denied
terminal status cannot reopen/change
terminal transition closes active placement
CLOSED year denies placement/status including same-state request
foreign enrollment target denied
rollback on placement/audit/status failures
no-op no audit
```

- [ ] **Step 2: Extend enrollment repository**

All list/read/write joins preserve:

```text
enrollment.school_id
enrollment.academic_year_id
student.school_id
year.school_id
placement.school_id/year/grade
classroom.school_id/year/grade
```

Do not join child/parent by `id` alone.

- [ ] **Step 3: Implement placement repository**

`lockActiveForEnrollment` uses `SELECT ... FOR UPDATE` and resolves only within supplied school/enrollment.

Ending a placement writes:

```text
status = ENDED
ended_at = CURRENT_TIMESTAMP
```

There is no delete.

- [ ] **Step 4: Implement EnrollmentAdministrationService**

For create:

```text
BEGIN
lock ACTIVE school
lock/open academic year
lock ACTIVE student in school
resolve ACTIVE grade level
ensure no enrollment for student/year
if classroom provided: resolve ACTIVE exact school/year/grade classroom
create enrollment ACTIVE
create placement if supplied
audit enrollment
audit placement change if supplied
COMMIT
```

For placement/status, pre-read the tenant-scoped enrollment only to learn its immutable academic year, then re-lock all authoritative rows inside the transaction following the lock order in this plan.

Audit codes:

```text
STUDENT_ENROLLMENT_CREATED
STUDENT_ENROLLMENT_STATUS_CHANGED
STUDENT_CLASSROOM_PLACEMENT_CHANGED
```

- [ ] **Step 5: Verify**

```bash
htdocs/vendor/bin/phpunit tests/Feature/EnrollmentAdministrationTest.php
htdocs/vendor/bin/phpunit
find . -path './htdocs/vendor' -prune -o -name '*.php' -type f -exec php -l {} \;
git diff --check
```

- [ ] **Step 6: Commit and push**

```bash
git add htdocs/app/Repositories/StudentEnrollmentRepository.php \
        htdocs/app/Repositories/StudentClassroomPlacementRepository.php \
        htdocs/app/Services/EnrollmentAdministrationService.php \
        tests/Feature/EnrollmentAdministrationTest.php
git commit -m "feat: add student enrollment and placement domain"
git push
```

Stop for review.

---

### Task 5: Add Enrollment Screens and Student History

**Files:**
- Create: `htdocs/app/Controllers/EnrollmentController.php`
- Create: `htdocs/views/academic/enrollments/index.php`
- Create: `htdocs/views/academic/enrollments/create.php`
- Create: `htdocs/views/academic/enrollments/edit.php`
- Modify: `htdocs/app/Controllers/StudentController.php`
- Modify: `htdocs/views/students/show.php`
- Modify: `htdocs/app/Application.php`
- Modify: `htdocs/routes/web.php`
- Create: `tests/Feature/EnrollmentHttpTest.php`
- Modify: `tests/Feature/StudentHttpTest.php`

**Interfaces:**

```php
EnrollmentController::index(Request $request): Response
EnrollmentController::create(Request $request): Response
EnrollmentController::store(Request $request): Response
EnrollmentController::edit(int $enrollmentId): Response
EnrollmentController::changePlacement(Request $request, int $enrollmentId): Response
EnrollmentController::changeStatus(Request $request, int $enrollmentId): Response
```

- [ ] **Step 1: Write RED HTTP tests**

Cover:

```text
exact routes and permissions
foreign academic_year_id filter → friendly 404
browser school_id ignored
School A enrollment list never shows B
foreign student/year/classroom/enrollment targets safe
GET foreign enrollment edit = missing behavior
POST foreign enrollment mutation = safe 422
bad/malformed CSRF = 419, zero writes/audit
create form only shows current-school open years
create form only shows ACTIVE current-school students
classroom options are same year/grade and ACTIVE
closed-year edit page read-only
placement move/unassign
transfer-out/withdraw
dynamic output escaped
no national_id leakage
no SQL/stack/path leak
```

- [ ] **Step 2: Register routes**

Use Planned Routes exactly.

List route uses `STUDENT_VIEW`; create/edit/mutations use `ENROLLMENT_MANAGE`.

- [ ] **Step 3: Implement query filters**

Enrollment list supports:

```text
academic_year_id
grade_level_id
classroom_id
status
q
```

`academic_year_id` must resolve in session school.

`classroom_id`, when supplied, must resolve to the same school/year.

`q` searches student code/name, never national ID.

Malformed arrays/objects return safe 422/404 according to whether the input is a target/filter representation; never TypeError/500.

- [ ] **Step 4: Implement create/edit views**

Create:

```text
academic year
student
grade level
optional classroom
optional entry date
```

No `school_id`.

Edit page shows immutable:

```text
student
academic year
grade level
```

and offers:

```text
classroom placement / unassign
enrollment status transition
```

For CLOSED year, render history only with no mutation controls.

- [ ] **Step 5: Extend student detail history**

`/students/{id}` now shows all own-school enrollments ordered newest year first:

```text
year_be
grade level
enrollment status
entry/exit dates
current/last classroom
placement history
```

No foreign-school history may appear.

- [ ] **Step 6: Verify**

```bash
htdocs/vendor/bin/phpunit tests/Feature/EnrollmentHttpTest.php tests/Feature/StudentHttpTest.php
htdocs/vendor/bin/phpunit tests/Feature/SchoolIsolationTest.php
htdocs/vendor/bin/phpunit
find . -path './htdocs/vendor' -prune -o -name '*.php' -type f -exec php -l {} \;
git diff --check
```

- [ ] **Step 7: Commit and push**

```bash
git add htdocs/app/Controllers/EnrollmentController.php \
        htdocs/app/Controllers/StudentController.php \
        htdocs/app/Application.php htdocs/routes/web.php \
        htdocs/views/academic/enrollments htdocs/views/students/show.php \
        tests/Feature/EnrollmentHttpTest.php tests/Feature/StudentHttpTest.php
git commit -m "feat: add enrollment screens and student history"
git push
```

Stop for review.

---

### Task 6: Add Navigation and Student/Enrollment Isolation Regression

**Files:**
- Modify: `htdocs/app/Controllers/DashboardController.php`
- Modify: `htdocs/views/dashboard/index.php`
- Modify: `htdocs/app/Application.php` only if existing dependency wiring requires it
- Create: `tests/Feature/StudentIsolationTest.php`
- Modify: `tests/Feature/DashboardAccessTest.php`

**Interfaces:**
- Dashboard computes navigation from `AuthorizationService`; role names/codes are never used as UI authorization logic.
- Navigation visibility remains convenience only. Route middleware is the security boundary.

- [ ] **Step 1: Write RED navigation tests**

Verify:

```text
SCHOOL_ADMIN sees student/enrollment navigation
ACADEMIC_ADMIN sees student/enrollment navigation
role without STUDENT_VIEW does not see student/enrollment navigation
removing STUDENT_VIEW mapping hides navigation immediately
role with STUDENT_VIEW but without ENROLLMENT_MANAGE may read lists/history but not mutate
browser school_id cannot alter navigation context
```

Render:

```text
จัดการนักเรียน → /students
การลงทะเบียนนักเรียน → /academic/enrollments
```

Import navigation is added in Task 7 only when `STUDENT_IMPORT` is present.

- [ ] **Step 2: Add cross-resource isolation regression**

`StudentIsolationTest` creates School A/B, years, grades, classrooms, students, enrollments, placements and asserts School A cannot read or mutate School B by substituting any target/parent ID.

Required forged combinations:

```text
A enrollment + B student
A enrollment + B academic year
A enrollment + B classroom
A enrollment + A classroom wrong year
A enrollment + A classroom same year wrong grade
A placement target + B enrollment
A student URL + B student
foreign year/classroom filters
foreign IDs plus forged browser school_id=A/B
```

For every rejected operation snapshot these tables:

```text
students
student_enrollments
student_classroom_placements
audit_logs
```

and prove business/audit rows remain unchanged.

Use one own-school valid request as a control so the test proves the request reached the intended domain layer.

- [ ] **Step 3: Implement navigation and verify**

```bash
htdocs/vendor/bin/phpunit tests/Feature/StudentIsolationTest.php tests/Feature/DashboardAccessTest.php
htdocs/vendor/bin/phpunit tests/Feature/AcademicIsolationTest.php tests/Feature/SchoolIsolationTest.php
htdocs/vendor/bin/phpunit
find . -path './htdocs/vendor' -prune -o -name '*.php' -type f -exec php -l {} \;
git diff --check
```

- [ ] **Step 4: Commit and push**

```bash
git add htdocs/app/Controllers/DashboardController.php \
        htdocs/views/dashboard/index.php \
        tests/Feature/StudentIsolationTest.php \
        tests/Feature/DashboardAccessTest.php
git commit -m "feat: add student navigation and isolation"
git push
```

If `Application.php` is changed for an existing dependency only, include it; otherwise do not stage it.

Stop for review.

---

### Task 7: Add Canonical Student Import Preview and Apply

**Files:**
- Create: `htdocs/app/Support/CanonicalStudentCsvReader.php`
- Create: `htdocs/app/Repositories/StudentImportBatchRepository.php`
- Create: `htdocs/app/Repositories/StudentImportRowRepository.php`
- Create: `htdocs/app/Services/StudentImportService.php`
- Create: `htdocs/app/Controllers/StudentImportController.php`
- Create: `htdocs/views/academic/student-import/index.php`
- Create: `htdocs/views/academic/student-import/preview.php`
- Modify: `htdocs/app/Http/Request.php`
- Modify: `htdocs/app/Application.php`
- Modify: `htdocs/app/Controllers/DashboardController.php`
- Modify: `htdocs/views/dashboard/index.php`
- Modify: `htdocs/routes/web.php`
- Create: `tests/Feature/StudentImportTest.php`
- Create: `tests/Feature/StudentImportHttpTest.php`
- Modify: `tests/Feature/StudentIsolationTest.php`

**Interfaces:**

```php
Request::__construct(
    string $method,
    string $path,
    array $query,
    array $post,
    array $server,
    array $files = []
)

Request::file(string $key, mixed $default = null): mixed

CanonicalStudentCsvReader::read(string $path, int $sizeBytes): array

StudentImportBatchRepository::create(
    int $schoolId,
    int $academicYearId,
    int $actorUserId,
    string $sourceName,
    string $sourceSha256
): int
StudentImportBatchRepository::findForSchool(int $schoolId, int $batchId): ?array
StudentImportBatchRepository::lockForSchool(int $schoolId, int $batchId): ?array
StudentImportBatchRepository::findAppliedByHash(
    int $schoolId,
    int $academicYearId,
    string $sourceSha256
): ?array
StudentImportBatchRepository::updatePreviewCounts(int $schoolId, int $batchId, array $counts): void
StudentImportBatchRepository::markApplied(int $schoolId, int $batchId): void
StudentImportBatchRepository::markCancelled(int $schoolId, int $batchId): void
StudentImportBatchRepository::expiredPreviewIdsForSchool(int $schoolId): array
StudentImportBatchRepository::markExpired(int $schoolId, int $batchId): void

StudentImportRowRepository::insert(int $batchId, int $schoolId, int $academicYearId, array $row): int
StudentImportRowRepository::listForBatch(int $schoolId, int $batchId): array
StudentImportRowRepository::deleteForBatch(int $schoolId, int $batchId): void

StudentImportService::preview(
    int $schoolId,
    int $actorUserId,
    int $academicYearId,
    string $sourceName,
    string $sourceSha256,
    array $rows,
    ?string $ipAddress = null
): int

StudentImportService::apply(
    int $schoolId,
    int $actorUserId,
    int $batchId,
    ?string $ipAddress = null
): void

StudentImportService::cancel(
    int $schoolId,
    int $actorUserId,
    int $batchId
): void

StudentImportService::expirePreviews(int $schoolId): void
```

- [ ] **Step 1: Write RED CSV-reader tests**

Cover:

```text
exact canonical header accepted
UTF-8 BOM accepted
wrong/missing/extra header denied
empty file denied
more than 1,000 data rows denied
size > 2 MiB denied
invalid UTF-8 denied
embedded control characters denied by downstream profile rules
CSV parse failure returns friendly DomainException
reader returns row_no starting at source data row 2
```

The reader does not write DB rows.

- [ ] **Step 2: Extend Request for file uploads**

Add optional `$files = []` after existing constructor parameters so all existing tests/callers remain source-compatible.

`fromGlobals()` passes `$_FILES`.

`file()` is a raw accessor; it does not normalize business data.

- [ ] **Step 3: Write RED import-domain tests**

Cover preview classification:

```text
new student + new enrollment → CREATE/CREATE
existing exact student + missing year enrollment → MATCH/CREATE
existing exact student + exact enrollment/classroom → MATCH/NOOP
national_id match + different student_code → CONFLICT
student_code match + conflicting national_id → CONFLICT
national_id and student_code match different rows → CONFLICT
duplicate student_code inside CSV → ERROR
duplicate non-NULL national_id inside CSV → ERROR
unknown/inactive grade level → ERROR
foreign/missing classroom code → ERROR
classroom wrong year/grade → ERROR
inactive classroom → ERROR
existing enrollment wrong grade/classroom → CONFLICT
terminal enrollment → CONFLICT
CLOSED year preview denied
same applied source hash/year/school denied
same source hash in another school/year allowed
24-hour preview expiry deletes staging rows and marks batch EXPIRED
preview writes staging only; business tables/audit unchanged
raw national_id absent from error text
```

Cover apply:

```text
error_count > 0 → apply denied
all NOOP → apply denied
stale preview is fully revalidated
one stale conflict → whole apply rollback
create students/enrollments/placements atomically
per-entity audit rows written
one STUDENT_IMPORT_APPLIED summary audit written
batch → APPLIED
row staging deleted
duplicate apply denied
audit failure → full rollback, batch remains PREVIEW, rows remain
```

Cover cancel:

```text
PREVIEW → CANCELLED
staging rows deleted
business/audit unchanged
APPLIED/EXPIRED batch cannot cancel
foreign batch denied
```

- [ ] **Step 4: Implement preview matching**

Process rows in source `row_no` order.

Normalize through `StudentProfileRules`.

Resolve:

```text
grade_level_code → active global grade
classroom_code → current-school selected-year classroom
student identity → current school only
existing enrollment → selected school/year/student
current placement → enrollment
```

Persist preview rows with:

```text
student_action = CREATE | MATCH | NONE
enrollment_action = CREATE | NOOP | NONE
error_code
error_message
```

Error messages mention row number/field/reason but never echo national ID.

No business audit on preview.

- [ ] **Step 5: Implement transactional apply**

Before writes:

```text
lock ACTIVE school
lock selected DRAFT/ACTIVE academic year
lock PREVIEW batch in same school/year
reject duplicate already-applied hash
load staging rows in row_no order
revalidate identity/enrollment/classroom decisions
```

Then create only missing entities.

Do not update existing student profile or move existing enrollment/classroom during import.

Write normal action audits plus:

```text
STUDENT_IMPORT_APPLIED
```

Summary `new_value`:

```json
{
  "row_count": 100,
  "created_students": 5,
  "created_enrollments": 7,
  "created_placements": 7,
  "noop_rows": 93,
  "source_sha256": "<64 hex chars>"
}
```

No row PII.

Mark APPLIED and delete row staging before commit.

- [ ] **Step 6: Write RED HTTP tests and implement controller/views**

Opening the import index first calls `expirePreviews()` for the session school so abandoned PREVIEW row data is removed without cron.

Upload form:

```text
academic_year_id
student_file (.csv)
CSRF
```

Controller validates `$_FILES` representation:

```text
array
UPLOAD_ERR_OK
tmp_name is scalar string
name is scalar string
size is integer/numeric scalar
```

Never trust browser MIME as security authority. Normalize `source_name` with `basename()`, require valid UTF-8, reject control characters, and cap it at 190 characters before persisting metadata.

Preview page shows:

```text
row number
student_code
full name
grade code
classroom code
student action
enrollment action
safe error
```

Do not show raw national ID.

Apply button appears only when `error_count = 0` and at least one CREATE exists.

Cancel available only for PREVIEW.

All batch routes resolve `(school_id, batch_id)`.

- [ ] **Step 7: Add import navigation**

Dashboard shows:

```text
นำเข้านักเรียน → /academic/student-import
```

only when `STUDENT_IMPORT` is allowed.

- [ ] **Step 8: Extend isolation regression**

Add:

```text
School A cannot read/apply/cancel School B batch
A batch cannot reference B year
A preview cannot resolve B classroom/student
forged browser school_id cannot move import tenant
foreign batch POST leaves business/audit unchanged
```

Staging rows created by an intentionally valid preview are allowed to change during preview-specific tests; business entity/audit snapshots must remain unchanged until apply.

- [ ] **Step 9: Verify**

```bash
htdocs/vendor/bin/phpunit tests/Feature/StudentImportTest.php tests/Feature/StudentImportHttpTest.php tests/Feature/StudentIsolationTest.php
htdocs/vendor/bin/phpunit
find . -path './htdocs/vendor' -prune -o -name '*.php' -type f -exec php -l {} \;
git diff --check
```

- [ ] **Step 10: Commit and push**

```bash
git add htdocs/app/Support/CanonicalStudentCsvReader.php \
        htdocs/app/Repositories/StudentImportBatchRepository.php \
        htdocs/app/Repositories/StudentImportRowRepository.php \
        htdocs/app/Services/StudentImportService.php \
        htdocs/app/Controllers/StudentImportController.php \
        htdocs/app/Http/Request.php htdocs/app/Application.php \
        htdocs/app/Controllers/DashboardController.php \
        htdocs/routes/web.php htdocs/views/dashboard/index.php \
        htdocs/views/academic/student-import \
        tests/Feature/StudentImportTest.php \
        tests/Feature/StudentImportHttpTest.php \
        tests/Feature/StudentIsolationTest.php
git commit -m "feat: add student import preview workflow"
git push
```

Stop for review.

---

### Task 8: Milestone 4 Hardening, Documentation, and MAMP Smoke

**Files:**
- Modify: `README.md`
- Modify tests only if hardening reveals a real defect; fix it test-first in a separate commit before the final documentation commit

- [ ] **Step 1: Update README to Milestone 4 baseline**

Document:

```text
student master vs enrollment separation
student fields and PII policy
student ACTIVE/INACTIVE rule
one enrollment per student/year
enrollment status transitions
grade-level immutability
classroom placement history
DRAFT/ACTIVE vs CLOSED mutation rule
4 Milestone 4 permissions and mappings
7 roles / 18 permissions / 27 mappings / 6 grade levels
student/enrollment/import routes
canonical CSV header and limits
preview/apply/cancel behavior
import identity matching/conflict rules
native DMC file support is NOT yet claimed
real DMC sample is required for a future adapter
tenant/session school rule
audit action codes
```

Do not document promotion, gradebook, attendance, reports, or native DMC compatibility as implemented.

- [ ] **Step 2: Clean migrate/seed verification**

On `pp5_test`:

```bash
php tools/migrate.php --database=pp5_test
php tools/seed.php --database=pp5_test
php tools/migrate.php --database=pp5_test
php tools/seed.php --database=pp5_test
```

Second runner pass applies nothing.

Confirm deterministic seed baseline:

```text
roles = 7
permissions = 18
role_permissions = 27
grade_levels = 6
```

- [ ] **Step 3: Full automated verification**

```bash
htdocs/vendor/bin/phpunit
find . -path './htdocs/vendor' -prune -o -name '*.php' -type f -exec php -l {} \;
git diff --check
```

Record exact PHPUnit test/assertion counts and number of PHP files checked.

- [ ] **Step 4: MAMP smoke — permissions and navigation**

Use uniquely marked temporary fixtures.

Verify:

```text
SCHOOL_ADMIN sees students/enrollments/import
ACADEMIC_ADMIN sees students/enrollments/import
role without STUDENT_VIEW cannot list/show students
role without ENROLLMENT_MANAGE cannot mutate enrollment
role without STUDENT_IMPORT cannot access import
SYSTEM context cannot access SCHOOL student routes
browser school_id cannot change tenant
```

- [ ] **Step 5: MAMP smoke — student identity**

Verify:

```text
create Thai/Unicode student
optional national_id
same student_code same school denied
same national_id same school denied
same code/national_id different school allowed
edit code/name/PII fields
raw national_id absent from list HTML/audit
show page masks national_id
inactivate student with active open-year enrollment denied
student with only historical CLOSED-year enrollment may be inactivated
reactivate student
```

- [ ] **Step 6: MAMP smoke — enrollment/placement**

Verify:

```text
create enrollment in DRAFT year
create enrollment in ACTIVE year
duplicate student/year denied
foreign student/year denied
unplaced enrollment allowed
place into matching active classroom
move classroom in same school/year/grade
unassign classroom
wrong-year classroom denied
wrong-grade classroom denied
inactive target classroom denied
transfer-out with exit date closes placement
withdraw with exit date
terminal enrollment cannot reopen
CLOSED year historical read works
CLOSED year enrollment/placement mutations denied
```

- [ ] **Step 7: MAMP smoke — canonical import**

Prepare a temporary canonical UTF-8 CSV containing:

```text
one new student
one exact existing student needing a new enrollment
one exact existing enrollment no-op
```

Verify:

```text
preview classifies rows correctly
preview changes staging only
apply creates expected missing data atomically
apply writes expected audits
row staging removed after apply
same file hash cannot be applied again to same school/year
same hash may be used in another school/year
conflict file disables apply
bad header denied
oversized/too-many-row protections exercised with automated tests
foreign batch/parent IDs denied
cancel removes preview rows without business audit
```

- [ ] **Step 8: CSRF/error/XSS smoke**

Exercise create/update/status/placement/import POST routes with bad/missing CSRF.

Expected:

```text
419 every request
business entity snapshot unchanged
staging snapshot unchanged when CSRF fails before preview
audit snapshot unchanged
```

Inject HTML-like payloads into allowed text fields and confirm escaped output.

Confirm no browser response contains:

```text
SQLSTATE
PDOException
raw SQL
constraint names
stack trace
filesystem path
database credentials
raw national_id from an error
```

- [ ] **Step 9: Audit inspection**

Verify all seven action codes appear when exercised:

```text
STUDENT_CREATED
STUDENT_UPDATED
STUDENT_STATUS_CHANGED
STUDENT_ENROLLMENT_CREATED
STUDENT_ENROLLMENT_STATUS_CHANGED
STUDENT_CLASSROOM_PLACEMENT_CHANGED
STUDENT_IMPORT_APPLIED
```

Check:

```text
actor
school
entity type
entity ID
timestamp
safe old/new values
no password/hash
no raw national_id
no SQL/stack/path
```

Exact no-op requests create no audit noise.

- [ ] **Step 10: Private-path and hygiene recheck**

Confirm private paths remain blocked under MAMP, including new views/classes.

Confirm no uploaded CSV, staging dump, national-ID dump, cookies, credentials, generated logs, `local.php`, vendor files, or PHPUnit cache are tracked.

Cleanup smoke fixtures child → parent by recorded IDs only:

```text
student_import_rows
student_import_batches
student_classroom_placements
student_enrollments
students
audit rows created by smoke
school fixtures if they were smoke-created
```

Do not use broad DELETE statements against legitimate local data.

- [ ] **Step 11: Acceptance checklist**

Milestone 4 is approved only when:

```text
[ ] old Milestone 1–3 security/regression behavior still passes
[ ] new migration/seed chain is reproducible
[ ] 7 roles / 18 permissions / 27 mappings / 6 grade levels
[ ] student identity is school-scoped and not year-scoped
[ ] student_code unique only within school
[ ] non-NULL national_id unique only within school
[ ] raw national_id never appears in audit/errors/list pages
[ ] student master status is separate from enrollment status
[ ] inactive student cannot receive new enrollment
[ ] student with open ACTIVE enrollment cannot be inactivated
[ ] exactly one enrollment per student/year
[ ] enrollment belongs to exact school/year/student/grade
[ ] grade level is immutable after enrollment creation
[ ] enrollment may be unplaced
[ ] at most one ACTIVE classroom placement per enrollment
[ ] placement history is retained; no hard delete
[ ] classroom move preserves school/year/grade
[ ] old inactive classroom does not block moving away
[ ] CLOSED year freezes enrollment/placement mutations
[ ] student master remains independently maintainable
[ ] transfer-out / withdrawal terminal states work
[ ] terminal enrollment cannot reopen in Milestone 4
[ ] promotion/repeat/graduation workflow is not introduced
[ ] all SCHOOL routes use Auth→SchoolContext→Permission
[ ] browser school_id never selects tenant
[ ] foreign/missing behavior is non-enumerating
[ ] every POST mutation is CSRF-protected before writes
[ ] malformed array/object inputs do not produce 500
[ ] all important mutations audited
[ ] exact no-op creates no audit
[ ] multi-step operations roll back on failure
[ ] dashboard navigation follows permission service, not role names
[ ] canonical CSV preview/apply/cancel works
[ ] import never silently updates existing student profile
[ ] import never silently changes existing grade/classroom
[ ] duplicate/conflict rows block apply
[ ] import apply is all-or-nothing
[ ] applied source hash is idempotency-protected per school/year
[ ] successful apply deletes row-level staging
[ ] PREVIEW staging expires after 24 hours through request-driven cleanup
[ ] native DMC XLSX/XLSB compatibility is not claimed without a real sample
[ ] PHPUnit full suite passes
[ ] project-wide PHP syntax passes excluding vendor
[ ] git diff --check passes
[ ] MAMP smoke passes on pp5
[ ] local/private/generated artifacts remain untracked
[ ] no Laravel/Node/Redis/queue/cron/DB trigger introduced
```

- [ ] **Step 12: Final milestone commit and push**

If hardening finds a real defect, fix it test-first in its own commit before the final documentation commit.

Then:

```bash
git status --short
git add README.md
git commit -m "chore: verify student core enrollment milestone"
git push
```

Stop. Do not create or merge a PR until final GitHub review confirms the full milestone branch.

---

## Implementation Branch Setup

This plan file belongs on `main` before implementation begins.

After this plan is committed and approved:

```bash
git checkout main
git pull origin main
git checkout -b milestone/4-student-core-enrollment
```

All Task 1–8 implementation commits belong on:

```text
milestone/4-student-core-enrollment
```

At Task kickoff, record the exact approved `main` SHA containing this plan and require Codex preflight:

```text
working tree clean
current branch = milestone/4-student-core-enrollment
local branch base = approved main SHA
remote main = approved main SHA
no rebase
no merge main
no amend/rewrite of approved commits
```

Do not implement Milestone 4 directly on `main`.

---

## Milestone 4 Completion Result

After Task 8 is approved, PP5 will have this stable chain:

```text
SYSTEM_ADMIN
→ School
→ SCHOOL_ADMIN / ACADEMIC_ADMIN
→ Academic Year
→ Grade Level
→ Classroom
→ Subject
→ Subject Offering
→ Student Identity
→ Student Enrollment
→ Classroom Placement History
→ Canonical Student Import
```

The system will then be structurally ready for downstream work such as:

```text
teacher/classroom/subject assignments and fine-grained scope
gradebook / scores
attendance
evaluations
competencies
activities
annual results
promotion/repeat/graduation workflow
finalization/unlock/approval
PP5 / PP6 reports
native DMC adapter after a real source sample is approved
```

Those are not part of Milestone 4.
