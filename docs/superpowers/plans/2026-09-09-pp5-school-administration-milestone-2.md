# PP5 School Administration Milestone 2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add platform-level System Administration and tenant-safe School Administration so a SYSTEM_ADMIN can create/suspend schools and appoint the first SCHOOL_ADMIN, while each SCHOOL_ADMIN can create and manage only users, memberships, roles, and passwords within their own school.

**Architecture:** Extend Milestone 1 with two explicit authorization contexts: `SYSTEM` and `SCHOOL`. System routes run `AuthMiddleware → PermissionMiddleware → handler`; school administration routes run `AuthMiddleware → SchoolContextMiddleware → PermissionMiddleware → handler`. School-owned reads/writes stay tenant-scoped, multi-step mutations run in PDO transactions, and school/user/role/password-reset changes are written to `audit_logs` without secrets.

**Tech Stack:** PHP 8.2-compatible, FastRoute 1.3.x, PDO, MySQL 8 / MariaDB-compatible SQL, PHP Session, PHPUnit 11, server-rendered PHP views, MAMP locally, InfinityFree-compatible request-driven deployment.

**Spec:** `docs/superpowers/specs/2026-09-08-pp5-technical-architecture-v1.2-design.md`

## Global Constraints

- Keep MVC-lite: Browser → Controller → Service → Repository → PDO → Database → View.
- No Laravel, React/Vue SPA, Node backend, Redis, queue worker, cron dependency, database trigger, public REST platform, or public self-registration.
- Normal users never choose a school during login; `school_id` comes from DB/session context only.
- Normal users may have at most one ACTIVE `school_memberships` row, regardless of the related school's status.
- `SYSTEM_ADMIN` is platform-level with `school_id = NULL`; in Milestone 2 a SYSTEM_ADMIN account must have zero ACTIVE `school_memberships` rows, including rows in ACTIVE, SUSPENDED, or INACTIVE schools.
- School-scoped admin roles keep the existing `(user_id, school_id)` membership integrity.
- Implement only `SYSTEM` and `SCHOOL` scopes now. Academic-year/classroom/subject scopes are deferred until those entities exist.
- SCHOOL_ADMIN may manage only users belonging to the current session school.
- SCHOOL_ADMIN may assign only active `SCHOOL`-scope roles and never `SYSTEM_ADMIN`.
- SCHOOL_ADMIN may change membership only `ACTIVE ↔ SUSPENDED`; transfers and `INACTIVE` workflows are deferred.
- SCHOOL_ADMIN may not suspend their own membership or remove their own `SCHOOL_ADMIN` role.
- ACTIVE memberships must retain at least one ACTIVE school role; roleless ACTIVE membership is an invalid login state.
- Passwords use `password_hash()` / `password_verify()`; new/reset passwords are at least 12 characters.
- Password/plaintext/hash must never appear in audit logs, HTML responses, exceptions, or application logs.
- Every POST action is CSRF-protected.
- Important admin mutations must answer WHO / WHAT / WHEN / WHERE (School) through `audit_logs`.
- SQL lives in repositories; business invariants live in services; controllers handle HTTP only.
- Multi-step mutations use transactions and full rollback.
- `pp5_test` fixtures roll back to baseline after each feature test.
- Each task stops after test/review/commit/push. Do not start the next task automatically.

---

## Locked Design Decisions

### 1. Authorization contexts

Add session key:

```text
context_type = SYSTEM | SCHOOL
```

SCHOOL session:

```text
user_id
context_type = SCHOOL
school_id
school_membership_id
display_name
csrf_token
last_activity
```

SYSTEM session:

```text
user_id
context_type = SYSTEM
display_name
csrf_token
last_activity
```

SYSTEM sessions contain no `school_id` and no `school_membership_id`.

### 2. System Admin authentication

An ACTIVE user with an ACTIVE global `SYSTEM_ADMIN` assignment (`school_id IS NULL`, `academic_year_id IS NULL`) may login in SYSTEM context only if they have zero ACTIVE `school_memberships` rows. Count these rows independently of `schools.status`: an ACTIVE row in a SUSPENDED or INACTIVE school still makes this an invalid SYSTEM_ADMIN account configuration and must deny login rather than select a context.

Normal users require exactly one ACTIVE `school_memberships` row before checking the school. Only after that count is exactly one, verify that the row's membership and school are still ACTIVE and that the user has at least one ACTIVE SCHOOL-scope role for that school. Two ACTIVE rows must deny login even when one school is SUSPENDED or INACTIVE.

### 3. Permission codes

Seed exactly:

```text
SYSTEM_SCHOOL_VIEW
SYSTEM_SCHOOL_CREATE
SYSTEM_SCHOOL_STATUS_MANAGE

SCHOOL_USER_VIEW
SCHOOL_USER_CREATE
SCHOOL_USER_UPDATE
SCHOOL_MEMBERSHIP_STATUS_MANAGE
SCHOOL_ROLE_MANAGE
SCHOOL_PASSWORD_RESET
```

Role mapping:

```text
SYSTEM_ADMIN → all 3 SYSTEM_* permissions
SCHOOL_ADMIN → all 6 SCHOOL_* permissions
```

Seed all seven role codes from the architecture, but other roles receive no Milestone 2 permissions yet.

### 4. Role assignment strategy

Use status transitions, not delete:

```text
user_role_assignments.status = ACTIVE | INACTIVE
academic_year_id = NULL
```

Do not create `permission_scopes` yet. Finer scopes depend on academic/classroom/subject tables that do not exist yet.

### 5. New-user strategy

School Admin creates a brand-new account. If username/email already exists anywhere, return a friendly validation error and instruct the admin to contact System Admin. No linking or cross-school transfer in this milestone.

### 6. School creation strategy

SYSTEM_ADMIN creates School + first SCHOOL_ADMIN atomically:

```text
BEGIN
  create school
  create user
  create ACTIVE membership
  assign SCHOOL_ADMIN
  write audit rows
COMMIT
```

Any failure rolls everything back.

### 7. Audit actions

Use stable action codes:

```text
SCHOOL_CREATED
SCHOOL_STATUS_CHANGED
SCHOOL_ADMIN_CREATED
SCHOOL_USER_CREATED
SCHOOL_USER_UPDATED
MEMBERSHIP_STATUS_CHANGED
SCHOOL_ROLES_CHANGED
USER_PASSWORD_RESET
```

Never store password or hash in `old_value` / `new_value`.

---

## Planned Routes

SYSTEM:

```text
GET  /system/schools
GET  /system/schools/create
POST /system/schools
POST /system/schools/{id}/status
```

SCHOOL:

```text
GET  /admin/users
GET  /admin/users/create
POST /admin/users
GET  /admin/users/{id}/edit
POST /admin/users/{id}/profile
POST /admin/users/{id}/membership-status
POST /admin/users/{id}/roles
POST /admin/users/{id}/reset-password
```

No route accepts browser-supplied `school_id` as authorization context.

---

### Task 1: Add Idempotent Role and Permission Seeds

**Files:**
- Create: `database/seeds/20260909_001_roles_permissions.sql`
- Create: `tools/seed.php`
- Create: `tests/Feature/RolePermissionSeedTest.php`
- Modify: `README.md`

**Interfaces:**
- Produces the seven architecture role codes.
- Produces the nine permission codes above.
- Produces deterministic mappings for SYSTEM_ADMIN and SCHOOL_ADMIN.
- `tools/seed.php` supports `--database=pp5_test`.

- [ ] **Step 1: Write failing seed test**

Verify all seven roles, all nine permissions, and exact role-permission mappings.

```php
self::assertSame([
    'SYSTEM_SCHOOL_CREATE',
    'SYSTEM_SCHOOL_STATUS_MANAGE',
    'SYSTEM_SCHOOL_VIEW',
], $this->permissionCodesForRole('SYSTEM_ADMIN'));
```

Run:

```bash
htdocs/vendor/bin/phpunit tests/Feature/RolePermissionSeedTest.php
```

Expected: FAIL before seed rows exist.

- [ ] **Step 2: Create idempotent seed SQL**

Use `INSERT ... ON DUPLICATE KEY UPDATE` for roles/permissions and `INSERT IGNORE ... SELECT` for role-permission pairs so IDs are never hard-coded.

```sql
INSERT INTO roles (code, name_th, scope_type, status) VALUES
('SYSTEM_ADMIN', 'ผู้ดูแลระบบส่วนกลาง', 'SYSTEM', 'ACTIVE'),
('SCHOOL_ADMIN', 'ผู้ดูแลระบบโรงเรียน', 'SCHOOL', 'ACTIVE'),
('ACADEMIC_ADMIN', 'ผู้ดูแลงานวิชาการ', 'SCHOOL', 'ACTIVE'),
('HOMEROOM_TEACHER', 'ครูประจำชั้น', 'SCHOOL', 'ACTIVE'),
('SUBJECT_TEACHER', 'ครูประจำวิชา', 'SCHOOL', 'ACTIVE'),
('EXECUTIVE', 'ผู้บริหาร', 'SCHOOL', 'ACTIVE'),
('VIEWER', 'ผู้ดูข้อมูล', 'SCHOOL', 'ACTIVE')
ON DUPLICATE KEY UPDATE
  name_th = VALUES(name_th),
  scope_type = VALUES(scope_type),
  status = VALUES(status);
```

- [ ] **Step 3: Implement `tools/seed.php`**

Mirror `tools/migrate.php`: connect through `Database`, parse `--database=...`, create `seed_migrations`, sort `database/seeds/*.sql`, skip already-applied names, execute, record, print `Applied <seed>`.

- [ ] **Step 4: Verify idempotency**

```bash
php tools/seed.php --database=pp5_test
php tools/seed.php --database=pp5_test
htdocs/vendor/bin/phpunit tests/Feature/RolePermissionSeedTest.php
```

First run applies one seed; second run applies nothing; test passes.

- [ ] **Step 5: Update README**

Document `php tools/seed.php` and `php tools/seed.php --database=pp5_test`. State that seeds never contain default credentials.

- [ ] **Step 6: Full regression and commit**

```bash
htdocs/vendor/bin/phpunit
git status --short
git add database/seeds tools/seed.php tests/Feature/RolePermissionSeedTest.php README.md
git commit -m "feat: seed administration roles and permissions"
```

Stop for review.

---

### Task 2: Add Authorization Repository, Service, and Permission Middleware

**Files:**
- Create: `htdocs/app/Repositories/AuthorizationRepository.php`
- Create: `htdocs/app/Services/AuthorizationService.php`
- Create: `htdocs/app/Middleware/PermissionMiddleware.php`
- Create: `tests/Feature/AuthorizationTest.php`

**Interfaces:**

```php
AuthorizationRepository::hasActiveSystemAdmin(int $userId): bool
AuthorizationRepository::hasActiveSchoolRole(int $userId, int $schoolId): bool
AuthorizationRepository::hasSystemPermission(int $userId, string $permissionCode): bool
AuthorizationRepository::hasSchoolPermission(int $userId, int $schoolId, string $permissionCode): bool
AuthorizationService::hasPermission(int $userId, string $contextType, ?int $schoolId, string $permissionCode): bool
PermissionMiddleware::handle(Request $request, callable $next): Response
```

- [ ] **Step 1: Write RED tests**

Cover ACTIVE/inactive system assignment, ACTIVE/inactive role, School A vs School B isolation, missing permission, SYSTEM/SCHOOL scope mismatch, suspended membership, suspended school.

- [ ] **Step 2: Implement tenant-aware repository queries**

SYSTEM permission requires `ura.school_id IS NULL`, `academic_year_id IS NULL`, ACTIVE assignment/role, `r.scope_type='SYSTEM'`.

SCHOOL permission joins matching membership/school and requires both ACTIVE plus `ura.school_id=:school_id`, `r.scope_type='SCHOOL'`.

Use positional or unique named placeholders because PDO native prepares are enabled.

- [ ] **Step 3: Implement AuthorizationService**

```php
return match ($contextType) {
    'SYSTEM' => $schoolId === null
        && $this->authorization->hasSystemPermission($userId, $permissionCode),
    'SCHOOL' => is_int($schoolId)
        && $this->authorization->hasSchoolPermission($userId, $schoolId, $permissionCode),
    default => false,
};
```

- [ ] **Step 4: Implement PermissionMiddleware**

Read `user_id`, `context_type`, optional `school_id` from Session. Missing identity redirects `/login`; denied permission renders friendly 403; allowed calls `$next`.

- [ ] **Step 5: Verify and commit**

```bash
htdocs/vendor/bin/phpunit tests/Feature/AuthorizationTest.php
htdocs/vendor/bin/phpunit
git add htdocs/app/Repositories/AuthorizationRepository.php htdocs/app/Services/AuthorizationService.php htdocs/app/Middleware/PermissionMiddleware.php tests/Feature/AuthorizationTest.php
git commit -m "feat: add role permission authorization"
```

Stop for review.

---

### Task 3: Add SYSTEM and SCHOOL Authentication Contexts

**Files:**
- Create: `htdocs/app/Support/AccessContext.php`
- Modify: `htdocs/app/Services/AuthenticationService.php`
- Modify: `htdocs/app/Repositories/SchoolMembershipRepository.php`
- Modify: `htdocs/app/Controllers/AuthController.php`
- Modify: `htdocs/app/Middleware/SchoolContextMiddleware.php`
- Modify: `tests/Feature/AuthenticationTest.php`
- Modify: `tests/Feature/SchoolIsolationTest.php`

**Interfaces:**

```php
AccessContext::SYSTEM = 'SYSTEM'
AccessContext::SCHOOL = 'SCHOOL'
SchoolMembershipRepository::findActiveRowsForUser(int $userId): array
SchoolMembershipRepository::isActiveMembership(int $membershipId, int $userId, int $schoolId): bool
AuthenticationService::attempt(string $username, string $password): array
```

`AuthenticationService::attempt` always returns `context_type`, `user_id`, `school_id`, `school_membership_id`, `display_name`; school fields are null for SYSTEM.

`findActiveRowsForUser` returns rows containing `id`, `user_id`, and `school_id` for the requested user where `school_memberships.status = 'ACTIVE'`, ordered by membership ID. It must not filter by `schools.status`. Use this method for membership cardinality; do not use the existing `findActiveForUser` for that count because it filters out non-ACTIVE schools. Keep `isActiveMembership` for the subsequent membership/school status check.

- [ ] **Step 1: Add RED tests**

Cover SYSTEM_ADMIN with zero memberships, SYSTEM_ADMIN + ACTIVE membership denied, normal user with one membership + active school role, roleless membership denied, role in wrong school denied, inactive system assignment denied.

Add explicit RED cases for:

- SYSTEM_ADMIN + ACTIVE membership row in a SUSPENDED school → deny.
- SYSTEM_ADMIN + ACTIVE membership row in an INACTIVE school → deny.
- Normal user with two ACTIVE membership rows, one in an ACTIVE school and one in a SUSPENDED school → deny.
- Normal user with exactly one ACTIVE membership row in a SUSPENDED school → deny.

- [ ] **Step 2: Add `AccessContext` constants**

```php
final class AccessContext
{
    public const SYSTEM = 'SYSTEM';
    public const SCHOOL = 'SCHOOL';
    private function __construct() {}
}
```

- [ ] **Step 3: Extend AuthenticationService**

Decision order:

```text
valid credentials
→ findActiveRowsForUser(user_id): count ACTIVE membership rows without filtering schools.status
→ hasActiveSystemAdmin?
   yes: ACTIVE membership rows must be 0 → SYSTEM
        any ACTIVE row, regardless of school status → deny
   no: ACTIVE membership rows must be exactly 1; otherwise deny
       + isActiveMembership(row.id, user_id, row.school_id) must be true
         (recheck both membership and school are ACTIVE)
       + hasActiveSchoolRole(user, school) → SCHOOL
```

Only success updates `last_login_at`.

- [ ] **Step 4: Update AuthController**

Always store `user_id`, `context_type`, `display_name`, `last_activity`.

SCHOOL stores school keys and redirects `/dashboard`.

SYSTEM forgets school keys and redirects `/system/schools`.

- [ ] **Step 5: Harden SchoolContextMiddleware**

Require `context_type === AccessContext::SCHOOL` before trusting school keys. SYSTEM session with injected school IDs must still receive 403.

- [ ] **Step 6: Verify and commit**

```bash
htdocs/vendor/bin/phpunit tests/Feature/AuthenticationTest.php
htdocs/vendor/bin/phpunit tests/Feature/SchoolIsolationTest.php
htdocs/vendor/bin/phpunit
git add htdocs/app tests/Feature/AuthenticationTest.php tests/Feature/SchoolIsolationTest.php
git commit -m "feat: add system and school login contexts"
```

Stop for review.

---

### Task 4: Add System School Administration Domain Service and Audit

**Files:**
- Create: `htdocs/app/Repositories/RoleRepository.php`
- Create: `htdocs/app/Repositories/RoleAssignmentRepository.php`
- Create: `htdocs/app/Repositories/AuditLogRepository.php`
- Create: `htdocs/app/Services/SystemSchoolAdministrationService.php`
- Modify: `htdocs/app/Repositories/SchoolRepository.php`
- Modify: `htdocs/app/Repositories/UserRepository.php`
- Modify: `htdocs/app/Repositories/SchoolMembershipRepository.php`
- Create: `tests/Feature/SystemSchoolAdministrationTest.php`

**Interfaces:**

```php
SchoolRepository::all(): array
SchoolRepository::findById(int $schoolId): ?array
SchoolRepository::findByCode(string $schoolCode): ?array
SchoolRepository::create(string $schoolCode, string $nameTh): int
SchoolRepository::updateStatus(int $schoolId, string $status): void
UserRepository::findByUsername(string $username): ?array
UserRepository::findByEmail(string $email): ?array
UserRepository::create(string $username, ?string $email, string $passwordHash, string $displayName): int
SchoolMembershipRepository::create(int $userId, int $schoolId, ?int $createdBy): int
RoleRepository::findActiveByCode(string $code): ?array
RoleAssignmentRepository::assignSystemRole(int $userId, int $roleId, ?int $assignedBy = null): int
RoleAssignmentRepository::assignSchoolRole(int $userId, int $schoolId, int $roleId, int $assignedBy): int
AuditLogRepository::record(
    ?int $schoolId,
    ?int $userId,
    string $action,
    string $entityType,
    ?int $entityId,
    ?array $oldValue,
    ?array $newValue,
    ?string $reason,
    ?string $ipAddress
): void
SystemSchoolAdministrationService::createSchoolWithAdmin(
    string $schoolCode,
    string $schoolName,
    string $adminUsername,
    string $adminDisplayName,
    ?string $adminEmail,
    string $adminPassword,
    int $actorUserId,
    ?string $ipAddress = null
): array
SystemSchoolAdministrationService::changeSchoolStatus(
    int $schoolId,
    string $status,
    int $actorUserId,
    ?string $ipAddress = null
): void
```

`SchoolRepository::findById` returns `id`, `school_code`, `name_th`, and `status` regardless of school status, or null when absent, so status changes can load SUSPENDED/INACTIVE schools. `assignSystemRole` creates an ACTIVE global assignment with `school_id = NULL` and `academic_year_id = NULL`; `assignSchoolRole` creates an ACTIVE assignment for the supplied school with `academic_year_id = NULL`. Both return the assignment ID.

- [ ] **Step 1: Write RED service tests**

Cover atomic create, duplicate school code/admin username/admin email rollback, password minimum, empty email→NULL, first admin membership + SCHOOL_ADMIN role, `academic_year_id=NULL`, no secret in audit, ACTIVE↔SUSPENDED audit, invalid status denied.

- [ ] **Step 2: Implement repository writes**

Keep SQL in repositories. Translate duplicate-key PDO failures to friendly `DomainException` messages; never expose SQLSTATE to controller.

- [ ] **Step 3: Implement AuditLogRepository**

Encode arrays with `JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR`. Repository must never receive password data. Callers supply all arguments explicitly; pass null for absent old/new values, reason, or IP. The service actor is the audit `userId`; the target is identified by `entityType`/`entityId`.

- [ ] **Step 4: Implement transactional school creation**

```php
public function createSchoolWithAdmin(
    string $schoolCode,
    string $schoolName,
    string $adminUsername,
    string $adminDisplayName,
    ?string $adminEmail,
    string $adminPassword,
    int $actorUserId,
    ?string $ipAddress = null
): array
```

Validate school code/name, username/display name, email, password>=12. Transaction creates school→user→membership→SCHOOL_ADMIN assignment→audit; rollback on any Throwable.

- [ ] **Step 5: School status**

Allow `ACTIVE`, `SUSPENDED`, `INACTIVE`; no delete endpoint. Existing SchoolContextMiddleware must block suspended school on next request.

- [ ] **Step 6: Verify and commit**

```bash
htdocs/vendor/bin/phpunit tests/Feature/SystemSchoolAdministrationTest.php
htdocs/vendor/bin/phpunit
git add htdocs/app tests/Feature/SystemSchoolAdministrationTest.php
git commit -m "feat: add system school administration service"
```

Stop for review.

---

### Task 5: Add System Admin HTTP Screens and Permission Pipeline

**Files:**
- Create: `htdocs/app/Controllers/SystemSchoolController.php`
- Create: `htdocs/views/system/schools/index.php`
- Create: `htdocs/views/system/schools/create.php`
- Modify: `htdocs/app/Http/Request.php`
- Modify: `htdocs/app/Application.php`
- Modify: `htdocs/routes/web.php`
- Create: `tests/Feature/SystemAdminHttpTest.php`

**Interfaces:**

```php
Request::server(string $key, mixed $default = null): mixed
```

SYSTEM routes use `AuthMiddleware → PermissionMiddleware → handler`, never SchoolContextMiddleware.

- [ ] **Step 1: Write RED HTTP tests**

Cover system redirect/list/create form, school user forbidden, missing permission forbidden, CSRF 419, successful create, first admin later SCHOOL login, browser school_id ignored, suspend/reactivate school, output escaping, no SQL/stack leaks.

- [ ] **Step 2: Add `Request::server()`**

Use `REMOTE_ADDR` only for audit IP.

- [ ] **Step 3: Add permission metadata routes**

```text
GET  /system/schools                 SYSTEM_SCHOOL_VIEW
GET  /system/schools/create          SYSTEM_SCHOOL_CREATE
POST /system/schools                 SYSTEM_SCHOOL_CREATE
POST /system/schools/{id}/status     SYSTEM_SCHOOL_STATUS_MANAGE
```

Use FastRoute `{id:\d+}`.

- [ ] **Step 4: Extend Application dispatch**

Explicitly wire AuthorizationRepository/Service, PermissionMiddleware, SystemSchoolAdministrationService and SystemSchoolController. Capture FastRoute vars from `$routeInfo[2]`. Do not add a DI container.

- [ ] **Step 5: Implement SystemSchoolController**

```php
index(): Response
create(): Response
store(Request $request): Response
changeStatus(Request $request, int $schoolId): Response
```

Every POST verifies CSRF. DomainException returns friendly 422. Success redirects `/system/schools`.

- [ ] **Step 6: Implement views**

Create form fields: `school_code`, `name_th`, `admin_username`, `admin_display_name`, optional `admin_email`, `admin_password`. Never render password value back.

- [ ] **Step 7: Verify and commit**

```bash
htdocs/vendor/bin/phpunit tests/Feature/SystemAdminHttpTest.php
htdocs/vendor/bin/phpunit
git add htdocs/app htdocs/views/system htdocs/routes/web.php tests/Feature/SystemAdminHttpTest.php
git commit -m "feat: add system school administration screens"
```

Stop for review.

---

### Task 6: Add School User Administration Domain Service

**Files:**
- Create: `htdocs/app/Services/SchoolUserAdministrationService.php`
- Modify: `htdocs/app/Repositories/UserRepository.php`
- Modify: `htdocs/app/Repositories/SchoolMembershipRepository.php`
- Modify: `htdocs/app/Repositories/RoleRepository.php`
- Modify: `htdocs/app/Repositories/RoleAssignmentRepository.php`
- Create: `tests/Feature/SchoolUserAdministrationTest.php`

**Interfaces:**

```php
SchoolMembershipRepository::findForSchoolUser(int $schoolId, int $userId): ?array
SchoolMembershipRepository::listForSchool(int $schoolId): array
SchoolMembershipRepository::updateStatus(int $membershipId, string $status): void
UserRepository::updateProfile(int $schoolId, int $userId, string $displayName, ?string $email): void
UserRepository::updatePasswordHash(int $schoolId, int $userId, string $passwordHash): void
RoleRepository::listActiveSchoolRoles(): array
RoleAssignmentRepository::activeSchoolRoleCodes(int $userId, int $schoolId): array
RoleAssignmentRepository::activateSchoolRole(int $userId, int $schoolId, int $roleId, int $assignedBy): void
RoleAssignmentRepository::deactivateSchoolRole(int $userId, int $schoolId, int $roleId): void
SchoolUserAdministrationService::createUser(
    int $schoolId,
    int $actorUserId,
    string $username,
    string $displayName,
    ?string $email,
    string $password,
    array $roleCodes,
    ?string $ipAddress = null
): int
SchoolUserAdministrationService::updateProfile(
    int $schoolId,
    int $actorUserId,
    int $userId,
    string $displayName,
    ?string $email,
    ?string $ipAddress = null
): void
SchoolUserAdministrationService::changeMembershipStatus(
    int $schoolId,
    int $actorUserId,
    int $userId,
    string $status,
    ?string $ipAddress = null
): void
SchoolUserAdministrationService::replaceRoles(
    int $schoolId,
    int $actorUserId,
    int $userId,
    array $roleCodes,
    ?string $ipAddress = null
): void
SchoolUserAdministrationService::resetPassword(
    int $schoolId,
    int $actorUserId,
    int $userId,
    string $password,
    ?string $ipAddress = null
): void
```

For these services, `schoolId` and `actorUserId` come from the authenticated session; `userId` is the target account. `roleCodes` is an array of role-code strings. Profile/password repository writes include the supplied school/user pair and require an ACTIVE or SUSPENDED membership, preserving the same tenant boundary as target reads. Services hash passwords before calling `updatePasswordHash` and pass null audit reasons when no reason is supplied by this milestone's workflow.

`activeSchoolRoleCodes` returns distinct ACTIVE SCHOOL-scope role codes for ACTIVE assignments in the supplied school with `academic_year_id = NULL`. `activateSchoolRole` reactivates an existing assignment or creates one if absent, recording `assignedBy`; `deactivateSchoolRole` marks matching assignments INACTIVE. Both operate only on the supplied user/school/role and `academic_year_id = NULL`.

- [ ] **Step 1: Write RED domain tests**

Cover own-school create, exact membership, requested roles, reject SYSTEM_ADMIN/system role/inactive role, duplicate rollback, require role, foreign target denied, profile only own active/suspended membership, blank email→NULL, self-suspend denied, ACTIVE↔SUSPENDED, INACTIVE denied, self SCHOOL_ADMIN removal denied, at least one role, password reset>=12, audit has no secret.

- [ ] **Step 2: Tenant-scoped target reads**

Every target operation begins with:

```sql
WHERE sm.school_id = :school_id
  AND sm.user_id = :user_id
```

Do not fetch target globally then authorize in controller.

- [ ] **Step 3: Implement `createUser()` transaction**

```php
public function createUser(
    int $schoolId,
    int $actorUserId,
    string $username,
    string $displayName,
    ?string $email,
    string $password,
    array $roleCodes,
    ?string $ipAddress = null
): int
```

Normalize/de-duplicate roles, require >=1 active SCHOOL role, create user+membership+roles+audit atomically.

- [ ] **Step 4: Profile and membership management**

Profile changes only `display_name`, `email`; username change is deferred. Membership accepts only ACTIVE/SUSPENDED. Reject self-suspension.

- [ ] **Step 5: `replaceRoles()`**

Only active SCHOOL-scope roles; SYSTEM_ADMIN rejected; >=1 desired role; self may add roles but not remove SCHOOL_ADMIN; removed roles become INACTIVE; inactive assignment may be reactivated. Audit old/new role arrays.

- [ ] **Step 6: Password reset**

Require own-school active/suspended membership and >=12 chars. Store new `password_hash`. Audit only `{ "password_reset": true }`.

- [ ] **Step 7: Verify and commit**

```bash
htdocs/vendor/bin/phpunit tests/Feature/SchoolUserAdministrationTest.php
htdocs/vendor/bin/phpunit
git add htdocs/app tests/Feature/SchoolUserAdministrationTest.php
git commit -m "feat: add school user administration service"
```

Stop for review.

---

### Task 7: Add School Admin HTTP Screens and Tenant Isolation

**Files:**
- Create: `htdocs/app/Controllers/SchoolUserController.php`
- Create: `htdocs/views/admin/users/index.php`
- Create: `htdocs/views/admin/users/create.php`
- Create: `htdocs/views/admin/users/edit.php`
- Modify: `htdocs/app/Application.php`
- Modify: `htdocs/routes/web.php`
- Create: `tests/Feature/SchoolAdminHttpTest.php`

**Interfaces:** SCHOOL admin routes always run `AuthMiddleware → SchoolContextMiddleware → PermissionMiddleware → handler`; controller derives actor/school from Session only.

- [ ] **Step 1: Write RED HTTP isolation tests**

Create School A/B. Verify School A list never shows B, browser school_id ignored, foreign edit/profile/role/password/status denied and unchanged, non-admin permission denied, SYSTEM context denied, bad CSRF 419, no school chooser, dynamic content escaped.

- [ ] **Step 2: Register routes**

```text
GET  /admin/users                             SCHOOL_USER_VIEW
GET  /admin/users/create                      SCHOOL_USER_CREATE
POST /admin/users                             SCHOOL_USER_CREATE
GET  /admin/users/{id}/edit                   SCHOOL_USER_VIEW
POST /admin/users/{id}/profile                SCHOOL_USER_UPDATE
POST /admin/users/{id}/membership-status      SCHOOL_MEMBERSHIP_STATUS_MANAGE
POST /admin/users/{id}/roles                  SCHOOL_ROLE_MANAGE
POST /admin/users/{id}/reset-password         SCHOOL_PASSWORD_RESET
```

- [ ] **Step 3: Implement SchoolUserController**

```php
index(): Response
create(): Response
store(Request $request): Response
edit(int $userId): Response
updateProfile(Request $request, int $userId): Response
changeMembershipStatus(Request $request, int $userId): Response
replaceRoles(Request $request, int $userId): Response
resetPassword(Request $request, int $userId): Response
```

Every POST verifies CSRF.

- [ ] **Step 4: Implement views**

Index shows own-school members/status/roles. Create form shows only SCHOOL roles. Edit page has profile, membership, role, and password reset forms. Never render password values and never include `name="school_id"`.

- [ ] **Step 5: Verify and commit**

```bash
htdocs/vendor/bin/phpunit tests/Feature/SchoolAdminHttpTest.php
htdocs/vendor/bin/phpunit tests/Feature/SchoolIsolationTest.php
htdocs/vendor/bin/phpunit
git add htdocs/app htdocs/views/admin htdocs/routes/web.php tests/Feature/SchoolAdminHttpTest.php
git commit -m "feat: add tenant safe school user administration"
```

Stop for review.

---

### Task 8: Bootstrap, Navigation, Audit Verification, and Milestone Hardening

**Files:**
- Create: `tools/bootstrap_system_admin.php`
- Create: `tests/Feature/SystemAdminBootstrapTest.php`
- Modify: `htdocs/app/Controllers/DashboardController.php`
- Modify: `htdocs/views/dashboard/index.php`
- Modify: `README.md`

**Interfaces:**

```text
php tools/bootstrap_system_admin.php --username=... --display-name=... --password=... [--email=...] [--database=pp5]
```

No web bootstrap endpoint.

- [ ] **Step 1: Write RED bootstrap tests**

Verify active user + global SYSTEM_ADMIN assignment, NULL school/year, duplicate safety, password>=12, seeded role required, no membership created, no password/hash output.

- [ ] **Step 2: Implement CLI bootstrap**

Require `PHP_SAPI === 'cli'`, parse flags, transactionally create user + global SYSTEM_ADMIN role. Output only `Created SYSTEM_ADMIN user: <username>`.

Resolve the seeded role with `RoleRepository::findActiveByCode(string $code): ?array`, create the account through `UserRepository::create(string $username, ?string $email, string $passwordHash, string $displayName): int`, then use `RoleAssignmentRepository::assignSystemRole(int $userId, int $roleId, ?int $assignedBy = null): int` with null `assignedBy` for bootstrap. Keep all SQL in repositories: the CLI tool must not contain direct SQL queries, including inserts into `user_role_assignments`. The CLI may coordinate the PDO transaction around repository calls.

- [ ] **Step 3: Add dashboard navigation**

Use AuthorizationService to compute `SCHOOL_USER_VIEW`; render “จัดการผู้ใช้” only when allowed. Middleware remains the security boundary.

- [ ] **Step 4: Update README**

Document local order:

```text
git checkout main && git pull
create branch milestone/2-school-administration
composer install
php tools/migrate.php
php tools/seed.php
php tools/migrate.php --database=pp5_test
php tools/seed.php --database=pp5_test
bootstrap local SYSTEM_ADMIN
run PHPUnit
MAMP smoke test
```

State: no default credentials; InfinityFree production bootstrap will be handled later through approved phpMyAdmin/manual deployment; never expose bootstrap tool as HTTP.

- [ ] **Step 5: Full automated verification**

```bash
htdocs/vendor/bin/phpunit
```

Then `php -l` all project PHP excluding `htdocs/vendor`.

- [ ] **Step 6: Final MAMP smoke test on `pp5`**

Verify:

```text
seed roles/permissions
bootstrap SYSTEM_ADMIN
SYSTEM_ADMIN login → /system/schools
create School A + first School Admin
create School B + first School Admin
suspend/reactivate School B
School A admin login → /dashboard
School A admin → /admin/users
create teacher with SUBJECT_TEACHER role
teacher login succeeds; /admin/users = 403
School A admin reset teacher password
old password fails; new password succeeds
suspend teacher membership → next protected request 403
reactivate teacher
School A admin cannot access School B user IDs
browser school_id cannot switch tenant
School A admin cannot assign SYSTEM_ADMIN
School A admin cannot suspend self
School A admin cannot remove own SCHOOL_ADMIN role
bad CSRF rejected on every mutation
logout clears session and regenerates ID
```

Inspect `audit_logs` for expected action codes, correct school/actor, and no password/hash. Remove smoke fixtures.

- [ ] **Step 7: Recheck private paths**

```text
/config/database.php → 403
/app/Application.php → 403
/views/admin/users/index.php → 403
/views/system/schools/index.php → 403
/vendor/autoload.php → 403
/tools/bootstrap_system_admin.php → outside htdocs and not web reachable
```

- [ ] **Step 8: Acceptance checklist**

```text
[ ] seven roles and nine permissions seed reproducibly
[ ] no default credentials committed
[ ] SYSTEM_ADMIN authenticates only with zero ACTIVE membership rows, regardless of school status
[ ] SYSTEM_ADMIN + ACTIVE membership in ACTIVE/SUSPENDED/INACTIVE school is denied
[ ] SYSTEM_ADMIN creates school + first SCHOOL_ADMIN atomically
[ ] SYSTEM_ADMIN suspends/reactivates schools
[ ] normal login counts exactly one ACTIVE membership row before verifying its school is ACTIVE; no chooser
[ ] two ACTIVE membership rows are denied even when one school is SUSPENDED
[ ] exactly one ACTIVE membership row in a SUSPENDED school is denied
[ ] normal user requires at least one ACTIVE school role
[ ] PermissionMiddleware enforces backend authorization
[ ] SCHOOL_ADMIN lists/creates/updates only own-school users
[ ] SCHOOL_ADMIN manages own-school ACTIVE/SUSPENDED membership
[ ] SCHOOL_ADMIN manages only SCHOOL-scope roles
[ ] SCHOOL_ADMIN cannot grant SYSTEM_ADMIN
[ ] SCHOOL_ADMIN cannot suspend self or remove own SCHOOL_ADMIN
[ ] SCHOOL_ADMIN securely resets another own-school password
[ ] foreign-school IDs cannot reveal/mutate another tenant
[ ] browser school_id never selects tenant
[ ] school/user/membership suspension blocks next protected request
[ ] every admin mutation is CSRF protected
[ ] important mutations are audited
[ ] audit contains no password/password_hash
[ ] multi-step operations roll back on failure
[ ] PHPUnit passes
[ ] MAMP smoke passes on pp5
[ ] local.php/vendor/phpunit cache remain ignored/untracked
[ ] no Laravel/Node backend/Redis/cron/queue/DB trigger introduced
```

- [ ] **Step 9: Final Milestone commit**

```bash
git status --short
git add .
git commit -m "chore: verify school administration milestone"
git push -u origin milestone/2-school-administration
```

Stop. Do not merge to `main` until final GitHub review and PR verification.

---

## Explicitly Deferred Beyond Milestone 2

Do not implement:

```text
user transfer between schools
multiple ACTIVE memberships
school chooser/context switcher
academic year creation
classrooms/staff academic setup
academic-year/classroom/subject permission scopes
teacher subject assignments
student/DMC workflows
email invitations/password-reset email
2FA
SSO/OAuth
parent/student accounts
full audit-log browsing UI
soft-delete framework
InfinityFree deployment automation
```

After this milestone is merged and verified, proceed to Student Core / Academic Setup according to the broader PP5 roadmap.
