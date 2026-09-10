# PP5 Academic Structure Milestone 3 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add tenant-safe academic structure management so each school can configure academic years, grade levels, classrooms, subjects, and subject offerings before Student/DMC, enrollment, gradebook, attendance, evaluation, and reporting workflows are introduced.

**Architecture:** Extend the approved Milestone 2 SCHOOL context with school-scoped academic entities. Browser-supplied IDs identify target academic entities only; authorization `school_id` continues to come from the authenticated Session. Every academic read/write is tenant-scoped in repositories, business invariants and state transitions live in services, important mutations are audited, and all SCHOOL academic routes run `AuthMiddleware → SchoolContextMiddleware → PermissionMiddleware → handler`.

**Tech Stack:** PHP 8.2-compatible, FastRoute 1.3.x, PDO, MySQL 8 / MariaDB-compatible SQL, PHP Session, PHPUnit 11, server-rendered PHP views, MAMP locally, InfinityFree-compatible request-driven deployment.

**Spec:** `docs/superpowers/specs/2026-09-08-pp5-technical-architecture-v1.2-design.md`

## Global Constraints

- Preserve Milestones 1–2 authentication, authorization, tenant-isolation, CSRF, transaction, session, password, audit, and error-safety behavior.
- Keep MVC-lite: Browser → Controller → Service → Repository → PDO → Database → View.
- SQL belongs in repositories; business invariants belong in services; controllers handle HTTP only.
- Production target remains InfinityFree; SQL must work on both MySQL 8 and MariaDB.
- Do not use Laravel, React/Vue SPA, Node backend, Redis, queue workers, cron, database triggers, microservices, stored procedures for business logic, or a public REST platform.
- School authorization context always comes from authenticated Session. Never trust browser `school_id` for tenant selection.
- Academic entity IDs submitted by the browser are target references only and must be resolved through tenant-scoped repository queries.
- Every school-scoped entity must be attributable to one school directly or through a database relationship that enforces the same school.
- Do not fetch a school-scoped academic entity globally by ID and authorize afterward in a controller.
- State-changing forms use CSRF and must perform no mutation before CSRF succeeds.
- Important academic mutations must write audit rows that answer WHO / WHAT / WHEN / WHERE (School); no secret or raw SQL/stack details may reach audit or HTML.
- Multi-step writes use PDO transactions and roll back completely on Throwable.
- No hard delete endpoints for academic entities in this milestone; use explicit status/state transitions.
- `NULL` remains distinct from zero/empty values.
- `pp5_test` fixtures must restore the seeded baseline after each feature test.
- Each implementation task ends with focused tests, full regression, commit, push, and review. Do not start the next task until the previous task is approved.

---

## Milestone Boundary

### Included

```text
global grade-level reference
academic years
classrooms
school subject master
subject offerings by academic year + classroom + term
academic setup permissions
academic setup navigation
tenant-safe CRUD/state transitions
audit and regression hardening
```

### Explicitly Deferred

Do not implement in Milestone 3:

```text
students
student DMC import
enrollments
student transfer/promotion
staff_members profile subsystem
homeroom-teacher assignment
subject-teacher assignment
academic-year/classroom/subject permission_scopes
non-NULL academic_year_id role authorization
scores / gradebook
attendance
evaluations
competencies
activities
annual results
finalization/unlock/approval
ปพ.5 / ปพ.6 reports
mPDF report generation
XLSX import/export
HTMX autosave
school chooser or multiple ACTIVE memberships
```

The next milestone should consume this structure for Student Core and Enrollment. Fine-grained teacher scope is introduced only when teacher assignments and gradebook behavior are designed together.

---

## Locked Design Decisions

### 1. Global grade levels

Create a global `grade_levels` reference table. Milestone 3 seeds the primary-school levels used by the current PP5 MVP:

```text
P1  ประถมศึกษาปีที่ 1  sort_order 10
P2  ประถมศึกษาปีที่ 2  sort_order 20
P3  ประถมศึกษาปีที่ 3  sort_order 30
P4  ประถมศึกษาปีที่ 4  sort_order 40
P5  ประถมศึกษาปีที่ 5  sort_order 50
P6  ประถมศึกษาปีที่ 6  sort_order 60
```

The schema permits later grade-level additions without a migration. Milestone 3 does not add kindergarten or secondary rows.

### 2. Academic year state

`academic_years.status` uses:

```text
DRAFT
ACTIVE
CLOSED
```

Rules:

```text
create → DRAFT
DRAFT → ACTIVE
ACTIVE → CLOSED
same state → no-op
all other transitions → deny
```

- `year_be` is unique within each school and accepts 2400–2700.
- `start_date` and `end_date` are nullable while DRAFT.
- Activation requires both dates and `start_date <= end_date`.
- A school may have at most one ACTIVE academic year.
- Serialize activation by locking the owning `schools` row inside the transaction before checking for another ACTIVE year.
- DRAFT year details may be edited. ACTIVE and CLOSED year details are immutable in Milestone 3.
- CLOSED structure remains readable but cannot be mutated.
- No reopen workflow is included.

### 3. Classroom identity

A classroom belongs to exactly one school, academic year, and global grade level.

Fields:

```text
code       VARCHAR(50)   e.g. P4-1
name_th    VARCHAR(120)  e.g. ประถมศึกษาปีที่ 4/1
status     ACTIVE | INACTIVE
```

`code` is unique within `(school_id, academic_year_id)`.

Classroom creation/update/status mutation is allowed only while the academic year is DRAFT or ACTIVE. CLOSED years are immutable.

### 4. Subject identity

`subjects` is school-scoped because subject/course codes and names belong to each school's curriculum setup.

Fields:

```text
code       VARCHAR(50)
name_th    VARCHAR(190)
status     ACTIVE | INACTIVE
```

`code` is unique within a school, not globally. Subject codes may contain Thai characters; validate length and control characters rather than restricting to ASCII.

### 5. Subject offering identity

A `subject_offering` means one school subject offered to one classroom in one academic year and one term.

Fields:

```text
school_id
academic_year_id
classroom_id
subject_id
term_no     1 | 2
status      ACTIVE | INACTIVE
```

Unique identity:

```text
UNIQUE(school_id, academic_year_id, classroom_id, subject_id, term_no)
```

Creation/reactivation requires:

- academic year belongs to session school and is DRAFT or ACTIVE;
- classroom belongs to the same school and same academic year and is ACTIVE;
- subject belongs to the same school and is ACTIVE;
- term is exactly 1 or 2.

Offering mutation is denied once the academic year is CLOSED. Inactivation keeps the row for history; no delete endpoint.

### 6. Database-enforced tenant relationships

Application tenant checks are required, but schema relationships must also reject cross-school parent combinations.

Use composite candidate keys/FKs so a School A child cannot reference a School B parent even if an application bug passes the wrong ID.

Required candidate keys include:

```text
academic_years  UNIQUE(id, school_id)
classrooms      UNIQUE(id, school_id, academic_year_id)
subjects         UNIQUE(id, school_id)
```

`subject_offerings` references classroom with `(classroom_id, school_id, academic_year_id)` so classroom and offering cannot disagree on academic year.

Add the deferred integrity constraint to `user_role_assignments`:

```text
(academic_year_id, school_id)
→ academic_years(id, school_id)
```

Milestone 3 still does not treat non-NULL `academic_year_id` assignments as authorized permissions. Existing school permission queries remain `academic_year_id IS NULL` until fine-grained scope is implemented later.

### 7. Permission codes

Add exactly five permissions:

```text
ACADEMIC_SETUP_VIEW
ACADEMIC_YEAR_MANAGE
CLASSROOM_MANAGE
SUBJECT_MANAGE
SUBJECT_OFFERING_MANAGE
```

Mappings after the new seed:

```text
SYSTEM_ADMIN      → existing 3 SYSTEM permissions only
SCHOOL_ADMIN      → existing 6 School Admin permissions + all 5 academic permissions
ACADEMIC_ADMIN    → all 5 academic permissions
HOMEROOM_TEACHER  → none of these five
SUBJECT_TEACHER   → none of these five
EXECUTIVE         → none of these five
VIEWER            → none of these five
```

The seeded baseline becomes:

```text
7 roles
14 permissions
19 role-permission mappings
```

Do not edit an already-applied Milestone 2 seed to add the new permissions. Add a new idempotent seed file.

### 8. Audit action codes

Use stable action codes:

```text
ACADEMIC_YEAR_CREATED
ACADEMIC_YEAR_UPDATED
ACADEMIC_YEAR_STATUS_CHANGED
CLASSROOM_CREATED
CLASSROOM_UPDATED
CLASSROOM_STATUS_CHANGED
SUBJECT_CREATED
SUBJECT_UPDATED
SUBJECT_STATUS_CHANGED
SUBJECT_OFFERING_CREATED
SUBJECT_OFFERING_UPDATED
SUBJECT_OFFERING_STATUS_CHANGED
```

Audit old/new values contain only the business fields that changed. Actor is session user; school is session school. No audit is written for exact no-op updates/status changes.

### 9. HTTP strategy

Use server-rendered PHP forms first. Do not introduce HTMX for academic setup CRUD in this milestone.

All academic routes use:

```text
AuthMiddleware
→ SchoolContextMiddleware
→ PermissionMiddleware
→ handler
```

Browser `school_id` is never accepted as authorization context.

GET foreign/missing entity edit requests must return the same friendly 404 behavior. Foreign/missing POST targets must return the same safe failure and make no writes/audit rows.

---

## Planned Routes

```text
GET  /academic/years                         ACADEMIC_SETUP_VIEW
GET  /academic/years/create                  ACADEMIC_YEAR_MANAGE
POST /academic/years                         ACADEMIC_YEAR_MANAGE
GET  /academic/years/{id}/edit               ACADEMIC_YEAR_MANAGE
POST /academic/years/{id}                    ACADEMIC_YEAR_MANAGE
POST /academic/years/{id}/status             ACADEMIC_YEAR_MANAGE

GET  /academic/classrooms                    ACADEMIC_SETUP_VIEW
GET  /academic/classrooms/create             CLASSROOM_MANAGE
POST /academic/classrooms                    CLASSROOM_MANAGE
GET  /academic/classrooms/{id}/edit          CLASSROOM_MANAGE
POST /academic/classrooms/{id}               CLASSROOM_MANAGE
POST /academic/classrooms/{id}/status        CLASSROOM_MANAGE

GET  /academic/subjects                      ACADEMIC_SETUP_VIEW
GET  /academic/subjects/create               SUBJECT_MANAGE
POST /academic/subjects                      SUBJECT_MANAGE
GET  /academic/subjects/{id}/edit            SUBJECT_MANAGE
POST /academic/subjects/{id}                 SUBJECT_MANAGE
POST /academic/subjects/{id}/status          SUBJECT_MANAGE

GET  /academic/offerings                     ACADEMIC_SETUP_VIEW
GET  /academic/offerings/create              SUBJECT_OFFERING_MANAGE
POST /academic/offerings                     SUBJECT_OFFERING_MANAGE
GET  /academic/offerings/{id}/edit           SUBJECT_OFFERING_MANAGE
POST /academic/offerings/{id}                SUBJECT_OFFERING_MANAGE
POST /academic/offerings/{id}/status         SUBJECT_OFFERING_MANAGE
```

Use FastRoute numeric `{id:\d+}` vars. Query/form IDs such as `academic_year_id`, `classroom_id`, and `subject_id` are target entity references and must be resolved in the session school.

---

### Task 1: Add Academic Structure Schema and Reference/Permission Seed

**Files:**
- Create: `database/migrations/20260910_001_academic_structure.sql`
- Create: `database/seeds/20260910_001_academic_structure_reference.sql`
- Create: `tests/Feature/AcademicStructureSchemaTest.php`
- Create: `tests/Feature/AcademicPermissionSeedTest.php`
- Modify: `tests/Feature/RolePermissionSeedTest.php`
- Modify: `README.md`

**Interfaces:**
- Produces tables `grade_levels`, `academic_years`, `classrooms`, `subjects`, `subject_offerings`.
- Adds FK `(user_role_assignments.academic_year_id, school_id) → academic_years(id, school_id)`.
- Produces P1–P6 global grade levels.
- Produces the five Milestone 3 permissions and exact SCHOOL_ADMIN/ACADEMIC_ADMIN mappings.

- [ ] **Step 1: Write RED schema and seed tests**

`AcademicStructureSchemaTest` must introspect all five tables, indexes, FKs, nullable/default behavior, and prove database-level tenant integrity with attempted cross-school inserts.

Required failure probes include:

```text
Classroom school_id=A + academic_year_id from School B → DB rejects
Offering school_id=A + classroom from School B → DB rejects
Offering academic_year=A + classroom from another year in School A → DB rejects
Offering school_id=A + subject from School B → DB rejects
Role assignment school_id=A + academic_year_id from School B → DB rejects
```

`AcademicPermissionSeedTest` must assert exact P1–P6 codes/names/order and exact five permission codes/mappings.

Run before migration/seed implementation:

```bash
htdocs/vendor/bin/phpunit tests/Feature/AcademicStructureSchemaTest.php tests/Feature/AcademicPermissionSeedTest.php
```

Expected: RED because tables/seed rows do not exist.

- [ ] **Step 2: Add the migration**

Use InnoDB, utf8mb4 and BIGINT UNSIGNED IDs. Do not use DB triggers or stored procedures. Do not use CHECK constraints for business status validation; services enforce allowed states.

Required structure:

```sql
CREATE TABLE grade_levels (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    name_th VARCHAR(100) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    PRIMARY KEY (id),
    UNIQUE KEY uq_grade_levels_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE academic_years (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id BIGINT UNSIGNED NOT NULL,
    year_be SMALLINT UNSIGNED NOT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academic_year_school_year (school_id, year_be),
    UNIQUE KEY uq_academic_year_id_school (id, school_id),
    CONSTRAINT fk_academic_year_school FOREIGN KEY (school_id) REFERENCES schools(id)
      ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE classrooms (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    grade_level_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(50) NOT NULL,
    name_th VARCHAR(120) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_classroom_school_year_code (school_id, academic_year_id, code),
    UNIQUE KEY uq_classroom_id_school_year (id, school_id, academic_year_id),
    CONSTRAINT fk_classroom_year_school FOREIGN KEY (academic_year_id, school_id)
      REFERENCES academic_years(id, school_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_classroom_grade_level FOREIGN KEY (grade_level_id)
      REFERENCES grade_levels(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subjects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(50) NOT NULL,
    name_th VARCHAR(190) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subject_school_code (school_id, code),
    UNIQUE KEY uq_subject_id_school (id, school_id),
    CONSTRAINT fk_subject_school FOREIGN KEY (school_id) REFERENCES schools(id)
      ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subject_offerings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    classroom_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    term_no TINYINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_offering_identity (school_id, academic_year_id, classroom_id, subject_id, term_no),
    CONSTRAINT fk_offering_classroom_school_year FOREIGN KEY (classroom_id, school_id, academic_year_id)
      REFERENCES classrooms(id, school_id, academic_year_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_offering_subject_school FOREIGN KEY (subject_id, school_id)
      REFERENCES subjects(id, school_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE user_role_assignments
  ADD CONSTRAINT fk_assignment_academic_year_school
  FOREIGN KEY (academic_year_id, school_id)
  REFERENCES academic_years(id, school_id)
  ON DELETE RESTRICT ON UPDATE CASCADE;
```

Do not add redundant standalone school FKs where the composite FK already enforces the same relationship unless MySQL/MariaDB requires an index for the declared composite constraint.

- [ ] **Step 3: Add the idempotent seed**

Use `INSERT ... ON DUPLICATE KEY UPDATE` for grade levels and permissions. Use `INSERT IGNORE ... SELECT` for role-permission mappings; never hard-code role/permission IDs.

Seed exactly P1–P6 and the five permission codes in Locked Design Decisions.

- [ ] **Step 4: Update old seed-count expectations**

Update Milestone 2 seed regression assertions and README from the old baseline `7 roles / 9 permissions / 9 mappings` to the new deterministic baseline `7 / 14 / 19` without weakening assertions for the original nine permissions.

- [ ] **Step 5: Verify migration and seed idempotency**

On a clean/rebuilt `pp5_test`:

```bash
php tools/migrate.php --database=pp5_test
php tools/seed.php --database=pp5_test
php tools/seed.php --database=pp5_test
htdocs/vendor/bin/phpunit tests/Feature/AcademicStructureSchemaTest.php tests/Feature/AcademicPermissionSeedTest.php tests/Feature/RolePermissionSeedTest.php
htdocs/vendor/bin/phpunit
```

Second seed run must apply nothing. Full suite must be green.

- [ ] **Step 6: Commit and push**

```bash
git add database/migrations/20260910_001_academic_structure.sql \
        database/seeds/20260910_001_academic_structure_reference.sql \
        tests/Feature/AcademicStructureSchemaTest.php \
        tests/Feature/AcademicPermissionSeedTest.php \
        tests/Feature/RolePermissionSeedTest.php README.md
git commit -m "feat: add academic structure schema and permissions"
git push -u origin milestone/3-academic-structure
```

Stop for review.

---

### Task 2: Add Academic Year Domain Service

**Files:**
- Create: `htdocs/app/Repositories/AcademicYearRepository.php`
- Create: `htdocs/app/Services/AcademicYearAdministrationService.php`
- Modify: `htdocs/app/Repositories/SchoolRepository.php`
- Create: `tests/Feature/AcademicYearAdministrationTest.php`

**Interfaces:**

```php
SchoolRepository::lockActiveById(int $schoolId): ?array
AcademicYearRepository::listForSchool(int $schoolId): array
AcademicYearRepository::findForSchool(int $schoolId, int $academicYearId): ?array
AcademicYearRepository::findActiveForSchool(int $schoolId): ?array
AcademicYearRepository::create(int $schoolId, int $yearBe, ?string $startDate, ?string $endDate): int
AcademicYearRepository::updateDraft(int $schoolId, int $academicYearId, int $yearBe, ?string $startDate, ?string $endDate): void
AcademicYearRepository::updateStatus(int $schoolId, int $academicYearId, string $status): void
AcademicYearAdministrationService::createYear(int $schoolId, int $actorUserId, int $yearBe, ?string $startDate, ?string $endDate, ?string $ipAddress = null): int
AcademicYearAdministrationService::updateYear(int $schoolId, int $actorUserId, int $academicYearId, int $yearBe, ?string $startDate, ?string $endDate, ?string $ipAddress = null): void
AcademicYearAdministrationService::changeStatus(int $schoolId, int $actorUserId, int $academicYearId, string $status, ?string $ipAddress = null): void
```

`findForSchool` and all writes must include both school and target ID in SQL. Do not fetch by year ID globally then compare school in PHP.

- [ ] **Step 1: Write RED domain tests**

Cover:

```text
create DRAFT in own school
same year_be allowed in another school
duplicate year_be in same school rejected
2400 and 2700 accepted; outside rejected
invalid ISO dates rejected
start > end rejected
blank dates normalize to NULL
foreign target update/status denied and unchanged
DRAFT details update succeeds
ACTIVE/CLOSED details update denied
activate requires both dates
only one ACTIVE year per school
School A ACTIVE year does not block School B
DRAFT→ACTIVE→CLOSED succeeds
invalid transition/reopen denied
same status is no-op with no audit
transaction rollback on audit/repository failure
audit actor/school/entity/old/new/IP correct
```

- [ ] **Step 2: Implement tenant-scoped repository operations**

Keep SQL only in repositories. Translate duplicate key failure to a friendly DomainException such as `ปีการศึกษานี้มีอยู่แล้ว` without SQLSTATE.

`SchoolRepository::lockActiveById` must use `SELECT ... FOR UPDATE` and return null unless the school is ACTIVE.

- [ ] **Step 3: Implement service validation/state machine**

Date values are either `NULL` or strict `Y-m-d`. Treat blank strings as null. Activation transaction:

```text
BEGIN
lock active school row
load target FOR UPDATE in current school
validate DRAFT + complete dates
check no other ACTIVE year in current school
update status ACTIVE
write audit
COMMIT
```

Closing requires current status ACTIVE. Do not reopen CLOSED.

- [ ] **Step 4: Verify and commit**

```bash
htdocs/vendor/bin/phpunit tests/Feature/AcademicYearAdministrationTest.php
htdocs/vendor/bin/phpunit
git add htdocs/app/Repositories/AcademicYearRepository.php \
        htdocs/app/Repositories/SchoolRepository.php \
        htdocs/app/Services/AcademicYearAdministrationService.php \
        tests/Feature/AcademicYearAdministrationTest.php
git commit -m "feat: add academic year administration service"
git push
```

Stop for review.

---

### Task 3: Add Academic Year HTTP Screens

**Files:**
- Create: `htdocs/app/Controllers/AcademicYearController.php`
- Create: `htdocs/views/academic/years/index.php`
- Create: `htdocs/views/academic/years/create.php`
- Create: `htdocs/views/academic/years/edit.php`
- Modify: `htdocs/app/Application.php`
- Modify: `htdocs/routes/web.php`
- Create: `tests/Feature/AcademicYearHttpTest.php`

**Interfaces:** controller derives `school_id` and actor `user_id` from Session only and delegates writes to `AcademicYearAdministrationService`.

- [ ] **Step 1: Write RED HTTP tests**

Cover exact planned routes, unauthenticated redirect, SYSTEM context denied, missing permission 403, bad/missing CSRF 419, School A list never shows School B, foreign edit 404, foreign mutations unchanged, injected query/POST `school_id` ignored, output escaping, safe 422 validation, and no SQL/stack/filesystem leakage.

Create/edit forms must never contain `name="school_id"`.

- [ ] **Step 2: Register routes and wire controller**

Every route metadata uses SCHOOL context. GET list uses `ACADEMIC_SETUP_VIEW`; create/edit/mutations use `ACADEMIC_YEAR_MANAGE` exactly as Planned Routes.

- [ ] **Step 3: Implement views**

Index shows year, date range, and status. Create/edit use server-rendered forms with CSRF. CLOSED year edit page is read-only except navigation; do not render a mutation control that the service will always reject.

Escape all dynamic values with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

- [ ] **Step 4: Verify and commit**

```bash
htdocs/vendor/bin/phpunit tests/Feature/AcademicYearHttpTest.php
htdocs/vendor/bin/phpunit tests/Feature/SchoolIsolationTest.php
htdocs/vendor/bin/phpunit
git add htdocs/app/Controllers/AcademicYearController.php htdocs/app/Application.php \
        htdocs/routes/web.php htdocs/views/academic/years tests/Feature/AcademicYearHttpTest.php
git commit -m "feat: add tenant safe academic year screens"
git push
```

Stop for review.

---

### Task 4: Add Classroom Administration Domain and HTTP

**Files:**
- Create: `htdocs/app/Repositories/GradeLevelRepository.php`
- Create: `htdocs/app/Repositories/ClassroomRepository.php`
- Create: `htdocs/app/Services/ClassroomAdministrationService.php`
- Create: `htdocs/app/Controllers/ClassroomController.php`
- Create: `htdocs/views/academic/classrooms/index.php`
- Create: `htdocs/views/academic/classrooms/create.php`
- Create: `htdocs/views/academic/classrooms/edit.php`
- Modify: `htdocs/app/Application.php`
- Modify: `htdocs/routes/web.php`
- Create: `tests/Feature/ClassroomAdministrationTest.php`
- Create: `tests/Feature/ClassroomHttpTest.php`

**Interfaces:**

```php
GradeLevelRepository::listActive(): array
GradeLevelRepository::findActiveById(int $gradeLevelId): ?array
ClassroomRepository::listForSchool(int $schoolId, ?int $academicYearId = null): array
ClassroomRepository::findForSchool(int $schoolId, int $classroomId): ?array
ClassroomRepository::create(int $schoolId, int $academicYearId, int $gradeLevelId, string $code, string $nameTh): int
ClassroomRepository::update(int $schoolId, int $classroomId, int $gradeLevelId, string $code, string $nameTh): void
ClassroomRepository::updateStatus(int $schoolId, int $classroomId, string $status): void
ClassroomAdministrationService::createClassroom(int $schoolId, int $actorUserId, int $academicYearId, int $gradeLevelId, string $code, string $nameTh, ?string $ipAddress = null): int
ClassroomAdministrationService::updateClassroom(int $schoolId, int $actorUserId, int $classroomId, int $gradeLevelId, string $code, string $nameTh, ?string $ipAddress = null): void
ClassroomAdministrationService::changeStatus(int $schoolId, int $actorUserId, int $classroomId, string $status, ?string $ipAddress = null): void
```

- [ ] **Step 1: Write RED domain tests**

Cover active grade-level requirement, own-school/year creation, same code allowed in different school/year, duplicate same school/year rejected, DRAFT/ACTIVE year mutation allowed, CLOSED denied, foreign academic year/classroom denied, code/name trimming and length, only ACTIVE/INACTIVE statuses, no-op audit behavior, rollback, and exact audit values.

Classroom update does not move a classroom to another academic year in Milestone 3. Only grade level/code/name may change.

- [ ] **Step 2: Implement repositories/service**

Classroom target reads/writes include `school_id`. Resolve academic year through `AcademicYearRepository::findForSchool`. Resolve grade level through the global active reference repository. Business validation remains in service.

- [ ] **Step 3: Write RED HTTP tests and implement routes/controller/views**

List may filter by browser `academic_year_id`, but the referenced year must belong to session school. Browser `school_id` remains ignored. Foreign year/classroom IDs return safe failure and no data/audit changes.

Use exact permissions from Planned Routes and CSRF on all POSTs.

- [ ] **Step 4: Verify and commit**

```bash
htdocs/vendor/bin/phpunit tests/Feature/ClassroomAdministrationTest.php tests/Feature/ClassroomHttpTest.php
htdocs/vendor/bin/phpunit tests/Feature/SchoolIsolationTest.php
htdocs/vendor/bin/phpunit
git add htdocs/app/Repositories/GradeLevelRepository.php \
        htdocs/app/Repositories/ClassroomRepository.php \
        htdocs/app/Services/ClassroomAdministrationService.php \
        htdocs/app/Controllers/ClassroomController.php htdocs/app/Application.php \
        htdocs/routes/web.php htdocs/views/academic/classrooms \
        tests/Feature/ClassroomAdministrationTest.php tests/Feature/ClassroomHttpTest.php
git commit -m "feat: add classroom administration"
git push
```

Stop for review.

---

### Task 5: Add Subject Administration Domain and HTTP

**Files:**
- Create: `htdocs/app/Repositories/SubjectRepository.php`
- Create: `htdocs/app/Services/SubjectAdministrationService.php`
- Create: `htdocs/app/Controllers/SubjectController.php`
- Create: `htdocs/views/academic/subjects/index.php`
- Create: `htdocs/views/academic/subjects/create.php`
- Create: `htdocs/views/academic/subjects/edit.php`
- Modify: `htdocs/app/Application.php`
- Modify: `htdocs/routes/web.php`
- Create: `tests/Feature/SubjectAdministrationTest.php`
- Create: `tests/Feature/SubjectHttpTest.php`

**Interfaces:**

```php
SubjectRepository::listForSchool(int $schoolId, bool $activeOnly = false): array
SubjectRepository::findForSchool(int $schoolId, int $subjectId): ?array
SubjectRepository::create(int $schoolId, string $code, string $nameTh): int
SubjectRepository::update(int $schoolId, int $subjectId, string $code, string $nameTh): void
SubjectRepository::updateStatus(int $schoolId, int $subjectId, string $status): void
SubjectAdministrationService::createSubject(int $schoolId, int $actorUserId, string $code, string $nameTh, ?string $ipAddress = null): int
SubjectAdministrationService::updateSubject(int $schoolId, int $actorUserId, int $subjectId, string $code, string $nameTh, ?string $ipAddress = null): void
SubjectAdministrationService::changeStatus(int $schoolId, int $actorUserId, int $subjectId, string $status, ?string $ipAddress = null): void
```

- [ ] **Step 1: Write RED domain tests**

Cover school-scoped uniqueness, Thai/Unicode subject codes, control-character rejection, trim/length, foreign target denial, ACTIVE/INACTIVE only, same state no-op, update without changing school, audit correctness, and rollback.

Existing historical offerings must not be deleted when a subject becomes INACTIVE. An INACTIVE subject cannot be selected for a new/reactivated offering in Task 6.

- [ ] **Step 2: Implement repository/service**

Every subject target operation includes supplied `school_id` and `subject_id`. Duplicate code failure is friendly and secret-safe.

- [ ] **Step 3: Write RED HTTP tests and implement routes/controller/views**

Verify SCHOOL_ADMIN and ACADEMIC_ADMIN permission paths, unauthorized school role 403, foreign IDs safe, browser school_id ignored, CSRF, XSS escaping, and inactive subject displayed as historical configuration but not removed.

- [ ] **Step 4: Verify and commit**

```bash
htdocs/vendor/bin/phpunit tests/Feature/SubjectAdministrationTest.php tests/Feature/SubjectHttpTest.php
htdocs/vendor/bin/phpunit tests/Feature/SchoolIsolationTest.php
htdocs/vendor/bin/phpunit
git add htdocs/app/Repositories/SubjectRepository.php \
        htdocs/app/Services/SubjectAdministrationService.php \
        htdocs/app/Controllers/SubjectController.php htdocs/app/Application.php \
        htdocs/routes/web.php htdocs/views/academic/subjects \
        tests/Feature/SubjectAdministrationTest.php tests/Feature/SubjectHttpTest.php
git commit -m "feat: add subject administration"
git push
```

Stop for review.

---

### Task 6: Add Subject Offering Administration Domain and HTTP

**Files:**
- Create: `htdocs/app/Repositories/SubjectOfferingRepository.php`
- Create: `htdocs/app/Services/SubjectOfferingAdministrationService.php`
- Create: `htdocs/app/Controllers/SubjectOfferingController.php`
- Create: `htdocs/views/academic/offerings/index.php`
- Create: `htdocs/views/academic/offerings/create.php`
- Create: `htdocs/views/academic/offerings/edit.php`
- Modify: `htdocs/app/Application.php`
- Modify: `htdocs/routes/web.php`
- Create: `tests/Feature/SubjectOfferingAdministrationTest.php`
- Create: `tests/Feature/SubjectOfferingHttpTest.php`

**Interfaces:**

```php
SubjectOfferingRepository::listForSchool(int $schoolId, ?int $academicYearId = null): array
SubjectOfferingRepository::findForSchool(int $schoolId, int $offeringId): ?array
SubjectOfferingRepository::create(int $schoolId, int $academicYearId, int $classroomId, int $subjectId, int $termNo): int
SubjectOfferingRepository::update(int $schoolId, int $offeringId, int $classroomId, int $subjectId, int $termNo): void
SubjectOfferingRepository::updateStatus(int $schoolId, int $offeringId, string $status): void
SubjectOfferingAdministrationService::createOffering(int $schoolId, int $actorUserId, int $academicYearId, int $classroomId, int $subjectId, int $termNo, ?string $ipAddress = null): int
SubjectOfferingAdministrationService::updateOffering(int $schoolId, int $actorUserId, int $offeringId, int $classroomId, int $subjectId, int $termNo, ?string $ipAddress = null): void
SubjectOfferingAdministrationService::changeStatus(int $schoolId, int $actorUserId, int $offeringId, string $status, ?string $ipAddress = null): void
```

Offering update never changes its academic year. To move an offering to another year, create a different offering; historical identity remains stable.

- [ ] **Step 1: Write RED domain tests**

Cover:

```text
own-school valid create
term 1 and 2 accepted; other values denied
duplicate identity denied
same subject/class/term allowed in another school
foreign academic year/classroom/subject denied
classroom from same school but different academic year denied
inactive classroom denied for create/reactivate
inactive subject denied for create/reactivate
DRAFT and ACTIVE year allow setup
CLOSED year denies create/update/status mutation
inactivation retains row
reactivation reuses same row when operating on existing target
foreign target update/status denied and unchanged
audit old/new and actor/school correct
transaction rollback on audit/write failure
```

- [ ] **Step 2: Implement repository/service**

All parent resolution is tenant-scoped. Do not rely only on foreign-key errors as business validation. The service must return friendly DomainException messages before write when a parent is foreign/inactive/closed.

- [ ] **Step 3: Write RED HTTP tests and implement routes/controller/views**

Create/edit forms offer only current-school parents:

```text
academic years: DRAFT or ACTIVE
classrooms: ACTIVE and matching selected/current academic year
subjects: ACTIVE
terms: 1 or 2
```

Server must revalidate all values regardless of form options. Forged browser IDs cannot cross tenant or academic year.

- [ ] **Step 4: Verify and commit**

```bash
htdocs/vendor/bin/phpunit tests/Feature/SubjectOfferingAdministrationTest.php tests/Feature/SubjectOfferingHttpTest.php
htdocs/vendor/bin/phpunit tests/Feature/SchoolIsolationTest.php
htdocs/vendor/bin/phpunit
git add htdocs/app/Repositories/SubjectOfferingRepository.php \
        htdocs/app/Services/SubjectOfferingAdministrationService.php \
        htdocs/app/Controllers/SubjectOfferingController.php htdocs/app/Application.php \
        htdocs/routes/web.php htdocs/views/academic/offerings \
        tests/Feature/SubjectOfferingAdministrationTest.php tests/Feature/SubjectOfferingHttpTest.php
git commit -m "feat: add subject offering administration"
git push
```

Stop for review.

---

### Task 7: Add Academic Navigation and Cross-Resource Isolation Regression

**Files:**
- Modify: `htdocs/app/Controllers/DashboardController.php`
- Modify: `htdocs/views/dashboard/index.php`
- Modify: `htdocs/app/Application.php` only if an additional existing dependency is required
- Create: `tests/Feature/AcademicIsolationTest.php`
- Modify: `tests/Feature/DashboardAccessTest.php`

**Interfaces:** Dashboard computes `ACADEMIC_SETUP_VIEW` through existing `AuthorizationService`; navigation visibility is not an authorization boundary.

- [ ] **Step 1: Write RED dashboard/navigation tests**

Verify:

```text
SCHOOL_ADMIN sees academic setup navigation
ACADEMIC_ADMIN sees academic setup navigation
role without ACADEMIC_SETUP_VIEW does not see it
removing permission mapping hides it immediately
manual academic URL still depends on PermissionMiddleware
browser school_id does not change navigation context
```

Render a single clear entry `จัดการโครงสร้างวิชาการ` linking to `/academic/years`, with links to classrooms/subjects/offerings reachable from academic pages.

- [ ] **Step 2: Add cross-resource tenant regression**

`AcademicIsolationTest` creates School A/B with academic years/classrooms/subjects/offerings and asserts School A can never read or mutate B by substituting any target or parent ID.

Include forged combinations that are easy to miss:

```text
A offering + B subject
A offering + B classroom
A offering + A classroom from wrong year
B academic_year_id in A classroom create
foreign IDs plus forged school_id=A/B browser field
```

Rejected operations leave entity rows and audit rows byte-for-byte unchanged.

- [ ] **Step 3: Implement navigation and verify**

Use `AuthorizationService::hasPermission(session user, SCHOOL, session school, 'ACADEMIC_SETUP_VIEW')`; never check role names in DashboardController.

Run:

```bash
htdocs/vendor/bin/phpunit tests/Feature/AcademicIsolationTest.php tests/Feature/DashboardAccessTest.php
htdocs/vendor/bin/phpunit tests/Feature/SchoolIsolationTest.php
htdocs/vendor/bin/phpunit
```

- [ ] **Step 4: Commit and push**

```bash
git add htdocs/app/Controllers/DashboardController.php htdocs/views/dashboard/index.php \
        htdocs/app/Application.php tests/Feature/AcademicIsolationTest.php \
        tests/Feature/DashboardAccessTest.php
git commit -m "feat: add academic setup navigation and isolation"
git push
```

If `Application.php` is unchanged, do not stage it.

Stop for review.

---

### Task 8: Milestone 3 Hardening, Documentation, and MAMP Smoke

**Files:**
- Modify: `README.md`
- Modify: tests only when a real regression discovered during hardening requires a test-first fix

- [ ] **Step 1: Update README to the completed Milestone 3 baseline**

Document:

```text
migrate + seed order
7 roles / 14 permissions / 19 mappings
P1–P6 grade levels
academic year DRAFT→ACTIVE→CLOSED rule
one ACTIVE academic year per school
classroom/subject/offering status behavior
academic routes and permissions
school_id session trust rule
tenant-safe parent-ID validation
Milestone 4 boundary: Student Core + Enrollment
```

Do not document students, DMC, gradebook, attendance, or teacher scopes as implemented.

- [ ] **Step 2: Full automated verification**

On `pp5_test`, migrate/seed and run:

```bash
php tools/migrate.php --database=pp5_test
php tools/seed.php --database=pp5_test
htdocs/vendor/bin/phpunit
find . -path './htdocs/vendor' -prune -o -name '*.php' -type f -exec php -l {} \;
git diff --check
```

All tests and syntax checks must pass. Record exact test/assertion counts.

- [ ] **Step 3: MAMP smoke on development `pp5`**

Create uniquely named temporary School A/B academic fixtures and verify end to end:

```text
SCHOOL_ADMIN and ACADEMIC_ADMIN permission behavior
create DRAFT academic year
cannot activate without dates
activate year with dates
second ACTIVE year in same school denied
School B may have its own ACTIVE year
create classroom with P1–P6 grade level
same classroom code in another school/year allowed
create Thai-code subject
create term 1 and term 2 offerings
foreign school IDs denied on every resource
wrong-year classroom/offering combination denied
browser school_id cannot switch tenant
bad CSRF rejected on every academic mutation
close academic year
closed-year classroom/offering mutation denied
all dynamic output escaped
```

- [ ] **Step 4: Inspect audit and cleanup**

Inspect audit rows for all 12 Milestone 3 action codes. Verify actor, school, entity, timestamps, old/new values and absence of secrets/SQL/stack traces.

Remove only smoke fixtures created in Step 3, respecting FK order. Keep migrations, seed rows, legitimate local data, and historical data not created by this smoke run.

- [ ] **Step 5: Recheck private paths and repository hygiene**

Private directories must remain 403 under MAMP. Confirm no migration/seed/admin utility is web-exposed outside the front controller.

Check:

```bash
git status --short
git diff --check
```

Ensure `htdocs/config/local.php`, `htdocs/vendor/`, PHPUnit cache, smoke dumps, credentials, temporary reports, and generated logs are not tracked.

- [ ] **Step 6: Acceptance checklist**

```text
[ ] old Milestone 1–2 security behavior still passes
[ ] P1–P6 global grade levels seed reproducibly
[ ] exactly 14 permissions and 19 mappings seed reproducibly
[ ] SCHOOL_ADMIN has all five academic permissions
[ ] ACADEMIC_ADMIN has all five academic permissions
[ ] other school roles receive none of the five by default
[ ] academic_year unique is school-scoped
[ ] academic year is DRAFT→ACTIVE→CLOSED only
[ ] activation requires valid dates
[ ] only one ACTIVE academic year per school
[ ] CLOSED year is immutable
[ ] classroom belongs to exact school + academic year
[ ] classroom code unique only within school/year
[ ] subject code unique only within school
[ ] Unicode subject codes work
[ ] offering belongs to exact school/year/classroom/subject
[ ] classroom year mismatch is rejected by service and DB FK
[ ] offering term is only 1 or 2
[ ] inactive parent cannot create/reactivate offering
[ ] no academic hard-delete endpoint exists
[ ] academic GET/POST routes use Auth→SchoolContext→Permission
[ ] school authorization context comes from Session only
[ ] browser school_id cannot switch tenant
[ ] foreign target/parent IDs cannot reveal or mutate another school
[ ] every academic POST mutation is CSRF-protected
[ ] every important academic mutation is audited
[ ] no-op writes do not create audit noise
[ ] audit contains no secrets/SQL/stack data
[ ] multi-step state changes roll back on failure
[ ] dashboard academic navigation follows ACADEMIC_SETUP_VIEW
[ ] navigation hiding is not the backend security boundary
[ ] user_role_assignments academic-year FK is installed
[ ] existing permission checks still ignore non-NULL academic_year_id
[ ] no students/enrollments/DMC/gradebook introduced
[ ] no teacher assignment/permission_scopes introduced
[ ] PHPUnit full suite passes
[ ] project-wide PHP syntax passes excluding vendor
[ ] MAMP smoke passes
[ ] local.php/vendor/phpunit cache remain ignored/untracked
[ ] no Laravel/Node/Redis/queue/cron/DB trigger introduced
```

- [ ] **Step 7: Final milestone commit and push**

```bash
git status --short
git add README.md
git commit -m "chore: verify academic structure milestone"
git push
```

If hardening required a real test-first defect fix, commit that fix separately before this documentation/final verification commit.

Stop. Do not create or merge a PR until final GitHub review confirms the whole milestone branch.

---

## Implementation Branch Setup

After this plan is present on `main` and the user is ready to implement:

```bash
git checkout main
git pull origin main
git checkout -b milestone/3-academic-structure
```

All Task 1–8 implementation commits belong on `milestone/3-academic-structure`.

Do not implement Milestone 3 directly on `main`.

---

## Milestone 3 Completion Result

After Task 8 is approved, PP5 will have this stable chain:

```text
SYSTEM_ADMIN
→ School
→ SCHOOL_ADMIN / ACADEMIC_ADMIN
→ Academic Year
→ Grade Level
→ Classroom
→ Subject
→ Subject Offering (Term 1/2)
```

This is the required structural base for the next milestone:

```text
Milestone 4 — Student Core and Enrollment
→ student identity within school
→ academic-year enrollment
→ classroom placement
→ DMC-oriented import workflow
→ tenant-safe student lookup/history
```

Scores, attendance, evaluations, competencies, activities, annual results and official PP5/PP6 reports remain downstream milestones.
