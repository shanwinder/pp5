# PP5 Teaching Assignment + Gradebook Core Milestone 5 Implementation Plan

> **For agentic workers:** Implement this plan task-by-task with genuine TDD. Each task ends with focused tests, full regression, commit, push, and review. Do not start the next task until the previous task is approved.

**Goal:** Add tenant-safe subject-teacher assignment, offering-scoped authorization, configurable term gradebooks, and audited score entry on top of the completed Milestones 1–4 baseline. This milestone establishes the score-entry foundation needed before attendance, evaluations, annual results, finalization, promotion, and PP5/PP6 reporting.

**Architecture:** Preserve the existing PHP MVC-lite flow. School/academic administrators manage teaching scopes and gradebook structure. A `SUBJECT_TEACHER` receives gradebook access only for subject offerings explicitly assigned to that role assignment. Gradebook components are generic configurable score columns owned by one subject offering. Scores use `DECIMAL`; `NULL` means not entered and is never treated as numeric zero. Gradebook totals are derived/read-only. Score mutation uses server-side authorization, CSRF, tenant-scoped repositories, transactions, and audit. HTMX autosave is introduced only for score cells after the server-side domain is complete.

**Tech Stack:** PHP 8.2-compatible, FastRoute 1.3.x, PDO, MySQL 8 / MariaDB-compatible SQL, PHP Session, PHPUnit 11, server-rendered PHP, Bootstrap-compatible markup, HTMX 2.x, Vanilla JS, MAMP locally, InfinityFree-compatible request-driven deployment.

**Spec:** `docs/superpowers/specs/2026-09-08-pp5-technical-architecture-v1.2-design.md`

**Baseline:** Milestones 1–4 are merged to `main` at merge commit `1c483af15c24cd76f905e28596fe7135eb4a02ec`. Milestone 4 verified **1,994 tests / 45,807 assertions**, 126 PHP files, MySQL 8.0.40 MAMP smoke, and deterministic seed baseline 7 roles / 18 permissions / 27 mappings / 6 grade levels.

---

## Global Constraints

- Preserve all Milestones 1–4 authentication, authorization, school isolation, CSRF, transaction, audit, error-safety, PII, and lifecycle guarantees.
- Keep MVC-lite: Browser → Controller → Service → Repository → PDO → Database → View.
- SQL belongs in repositories; business invariants and calculations belong in services; controllers handle HTTP only.
- Production target remains InfinityFree; SQL must work on both MySQL 8 and MariaDB.
- Do not use Laravel, React/Vue SPA, Node backend, Redis, queue workers, cron, database triggers, stored procedures for business rules, or a public REST platform.
- School authorization context always comes from authenticated Session. Browser `school_id`, `user_id`, role, scope, actor, classroom, subject, or offering fields never select authority.
- Every school-scoped query is tenant-scoped directly or through a relation whose foreign keys enforce the same tenant.
- Do not fetch a school-scoped gradebook entity globally by ID and authorize afterward in the controller.
- POST/HTMX mutations verify CSRF before any write, audit, cleanup, or scope mutation.
- Multi-step writes use PDO transactions and roll back completely on `Throwable`.
- Important mutations write audit rows with safe metadata. Exact no-op operations do not create audit noise.
- `NULL !== 0`. A missing score and an actual score of zero are distinct throughout DB, services, calculations, HTML, JSON/HTMX responses, and tests.
- Scores use `DECIMAL`; do not use FLOAT/DOUBLE for persisted score values or configured maxima.
- No hard-delete HTTP endpoints for teaching scopes, gradebook components, or scores. Use explicit status transitions / null score clearing while retaining audit history.
- Automated feature tests use `pp5_test` only and restore seeded baseline.
- Legacy PP5 Excel is a behavioral reference, not a schema template. Do not recreate 12 worksheets, fixed Excel cell coordinates, VBA macros, or ~39k formulas in the web application.
- The legacy workbook has subject-specific layouts and score allocations. Milestone 5 therefore uses generic gradebook components instead of hard-coding workbook labels such as `W`, `AQ`, or column letters whose business meaning is not locked by the approved architecture.
- Do not add DMC/XLSX/XLSB import in this milestone.

---

## Milestone Boundary

### Included

```text
subject-offering teaching assignment
SUBJECT_TEACHER offering scope
scope-aware gradebook authorization
gradebook permissions and navigation
configurable term score components
term gradebook roster/read model
score enter / edit / clear
NULL vs 0 semantics
read-only computed totals/completeness
per-cell HTMX autosave
score audit and isolation regression
MAMP hardening and documentation
```

### Explicitly Deferred

Do not implement in Milestone 5:

```text
full staff_members profile subsystem
homeroom-teacher assignment
classroom-wide homeroom permission scopes
generic cross-domain scope editor
student DMC / XLSX / XLSB import
learning-indicator/outcome master copied from legacy Excel
attendance
evaluations / competencies
activities
term grade calculation / grade symbols
annual result / GPA
subject finalization
unlock request / approval
promotion / repeat / graduation
PP5 / PP6 report rendering
mPDF official documents
bulk score CSV/XLSX import/export
attendance/evaluation HTMX
public API
```

Milestone 5 stores and validates term score facts and computed totals only. It does **not** decide annual grades or official results.

---

## Locked Design Decisions

### 1. Scope model for subject teachers

The architecture requires authorization to evaluate:

```text
authenticated user
+ school context
+ role / permission
+ scope
+ entity state
```

Milestone 5 introduces the first concrete `permission_scopes` implementation for `SUBJECT_OFFERING` scope.

Use existing `user_role_assignments` as the role-assignment authority. Do not create a second independent teacher identity system.

`SUBJECT_TEACHER` remains a SCHOOL-context role, but its gradebook permissions require an active scope row matching the target subject offering. SCHOOL_ADMIN and ACADEMIC_ADMIN receive school-wide gradebook permissions and do not require offering scope.

The scope row must reference the active `SUBJECT_TEACHER` role assignment and the target offering with same-school foreign-key integrity. Inactive role assignment, inactive scope, foreign offering, foreign membership, suspended school, or closed session context denies access immediately.

Do not infer scope from a browser-submitted subject/classroom ID.

### 2. Permissions

Add exactly these Milestone 5 permissions unless implementation evidence proves a split is required:

```text
TEACHING_ASSIGNMENT_MANAGE
GRADEBOOK_VIEW
GRADEBOOK_COMPONENT_MANAGE
GRADEBOOK_SCORE_ENTER
```

Seed baseline intent:

```text
SCHOOL_ADMIN     all four, school-wide
ACADEMIC_ADMIN   all four, school-wide
SUBJECT_TEACHER  GRADEBOOK_VIEW + GRADEBOOK_SCORE_ENTER, offering-scoped
EXECUTIVE        GRADEBOOK_VIEW, school-wide read-only
HOMEROOM_TEACHER no Milestone 5 gradebook permission by default
VIEWER           no Milestone 5 permission by default
```

Direct grant/revoke behavior remains immediate and must be tested. UI visibility is convenience only; backend authorization is authoritative.

### 3. Teaching assignment

A teaching assignment means an active scope from one active `SUBJECT_TEACHER` role assignment to one subject offering.

Business rules:

```text
same school only
subject offering must exist in same school
offering academic year must be DRAFT or ACTIVE when assignment is created/reactivated
target offering must be ACTIVE when assignment is created/reactivated
user must have one active school membership for the same school
user must have an active SUBJECT_TEACHER role assignment for the same school
same role-assignment/offering pair is unique
assignment can be deactivated/reactivated
no hard delete
CLOSED year assignment history remains readable but cannot be mutated
```

Deactivating a teaching assignment removes scoped gradebook access immediately. It does not delete scores or audits.

### 4. Gradebook component model

A gradebook component is one configurable score column for one subject offering (therefore one classroom + subject + term).

Suggested fields:

```text
id
school_id
academic_year_id
subject_offering_id
code             VARCHAR(50)
name_th          VARCHAR(190)
max_score        DECIMAL(7,2)
sort_order       SMALLINT UNSIGNED
status           ACTIVE | INACTIVE
created_at
updated_at
```

Rules:

- `code` is unique within one subject offering, under the same database collation used by the unique key.
- `name_th` and `code` are UTF-8 text with control-character rejection and bounded lengths.
- `max_score` must be finite decimal text parseable without float semantics, `> 0`, and within a documented safe upper bound chosen for storage (for example 99999.99). Do not force the total component maximum to 100 in Milestone 5.
- Component setup mutation is allowed only for DRAFT/ACTIVE academic years and ACTIVE offerings.
- A component with score history may be marked INACTIVE but is not hard-deleted.
- Existing score values are not rescaled when `max_score` changes. Changing max after scores exist is therefore denied in Milestone 5; administrators may change label/sort order or deactivate instead.
- Reordering components is presentation metadata and must not alter scores.

### 5. Score model

A score belongs to exactly one:

```text
school
academic year
subject offering
student enrollment
score component
```

Suggested fields:

```text
id
school_id
academic_year_id
subject_offering_id
enrollment_id
component_id
score            DECIMAL(7,2) NULL
updated_by
created_at
updated_at
```

Unique identity:

```text
UNIQUE(subject_offering_id, enrollment_id, component_id)
```

Use composite foreign keys / additional unique keys as needed so tenant/year/offering/component/enrollment relationships cannot cross schools at DB level.

`score = NULL` means “not entered / cleared”. `score = 0.00` is a real zero.

Cell validation:

```text
NULL → clear / not entered
otherwise decimal >= 0
score <= component.max_score
no scientific notation
no NaN/Infinity
no locale comma guessing
```

Normalize accepted decimal input deterministically and persist decimal strings through PDO; do not calculate with binary floating point.

### 6. Gradebook roster/read model

Milestone 5 does not introduce a duplicated permanent class roster table.

For an offering, current writable roster rows are ACTIVE student enrollments in the same school/year whose current ACTIVE classroom placement matches the offering classroom.

Historical safety rule:

- A student who no longer has a current placement in the offering classroom but already has score rows for that offering remains visible in historical/read-only gradebook output so existing scores do not disappear.
- New score entry for a non-current student is denied.
- Existing historical scores for a moved-out/non-current student are read-only in Milestone 5.
- A student never matching the offering school/year/classroom and with no score rows must not appear.

This keeps placement history authoritative and avoids silent score migration when a student changes classroom.

### 7. Totals and completeness

For each gradebook student row compute read-only values in service/read-model code:

```text
configured_max_total = sum(max_score of ACTIVE components)
entered_score_total  = sum(non-NULL scores for ACTIVE components)
entered_component_count
active_component_count
complete = entered_component_count == active_component_count
```

Rules:

- `NULL` is skipped, never converted to zero.
- `0` participates as a real entered score.
- If active components are zero, `complete` is false and the gradebook is not grade-ready.
- Do not calculate grade level, grade symbol, GPA, annual percentage, pass/fail, or final result in Milestone 5.
- Inactive component score history remains queryable for audit/history but is excluded from current totals unless a future result policy explicitly says otherwise.

### 8. Mutation state

Score entry is allowed only when all are true:

```text
school ACTIVE
academic year DRAFT or ACTIVE
subject offering ACTIVE
component ACTIVE
student enrollment ACTIVE
student currently placed in offering classroom
user has GRADEBOOK_SCORE_ENTER
user is school-wide authorized OR has matching active SUBJECT_OFFERING scope
```

CLOSED academic year freezes component/assignment/score mutation.

Milestone 5 has no subject FINALIZED state yet. Finalization/unlock/approval is explicitly deferred; later milestones will add an additional state gate without changing tenant/scope semantics.

### 9. Audit

Use stable actions such as:

```text
TEACHING_ASSIGNMENT_CREATED
TEACHING_ASSIGNMENT_STATUS_CHANGED
GRADEBOOK_COMPONENT_CREATED
GRADEBOOK_COMPONENT_UPDATED
GRADEBOOK_COMPONENT_STATUS_CHANGED
GRADEBOOK_SCORE_CHANGED
```

Score audit may contain:

```json
{
  "subject_offering_id": 123,
  "enrollment_id": 456,
  "component_id": 789,
  "old_score": null,
  "new_score": "0.00"
}
```

Scores are educational records but are not secret credentials; audit should record the exact changed score needed for accountability. Do not include raw national ID, submitted browser authority fields, SQL, stack traces, filesystem paths, cookies, or credentials.

Exact same normalized score is a no-op and writes no audit.

### 10. HTMX save contract

Server-rendered gradebook page is the source of initial state.

Introduce a logical HTMX endpoint such as:

```text
POST /hx/gradebook/{offeringId}/components/{componentId}/enrollments/{enrollmentId}/score
```

Request contains:

```text
_token
score
```

The URL IDs identify targets only. School/user/scope authority comes from Session and database authorization.

Response is a small escaped HTML fragment containing normalized cell value plus save status. Errors must be safe and must not echo untrusted HTML or raw SQL.

Autosave triggers on blur/Enter, not every keypress. Keyboard navigation may use unobtrusive Vanilla JS after server correctness is proven.

### 11. Legacy Excel relationship

Legacy PP5 workbook behavior informs Milestone 5 but is not copied structurally:

- multiple subject score sheets become one gradebook domain keyed by subject offering;
- fixed score columns become configurable `gradebook_components`;
- Excel formulas become service calculations;
- blank score cells map to `NULL`, not zero;
- calculated totals are readonly;
- macros are not ported;
- annual grade/formula behavior is deferred until a dedicated result-design milestone validates the intended rules.

---

# Task 1: Add Teaching Scope + Gradebook Schema and Permissions

**Files:**
- Create: `database/migrations/20260915_001_teaching_gradebook_core.sql`
- Create: `database/seeds/20260915_001_teaching_gradebook_permissions.sql`
- Create/Modify schema/seed tests as required

## RED tests

Cover at minimum:

```text
permission_scopes table exists and is tenant-safe
subject-offering scope cannot reference foreign role assignment/offering
scope pair uniqueness
component tenant/year/offering FK integrity
component code uniqueness under DB collation
score tenant/year/offering/component/enrollment FK integrity
score unique cell identity
score column is DECIMAL and nullable
seed adds exactly 4 permissions
seed role mappings match locked design
seed rerun idempotent
migration chain from clean DB succeeds
```

Add composite unique keys to existing parents only where required for safe FKs; do not weaken prior constraints.

Run focused schema/seed tests, full PHPUnit, syntax, `git diff --check`.

Commit suggestion:

```text
feat: add teaching and gradebook schema
```

Push and stop for review.

---

# Task 2: Add Teaching Assignment Domain + Scoped Authorization

**Files:**
- Create: repository/service classes for permission scopes / teaching assignments
- Modify: `AuthorizationRepository.php`
- Modify: `AuthorizationService.php`
- Add focused feature tests

Implement:

```text
list subject teachers for school
authorize school-wide gradebook role
resolve scoped SUBJECT_TEACHER access by offering
create/deactivate/reactivate teaching scope
CLOSED year mutation deny
foreign offering/user/role-assignment deny
role/membership/scope revocation takes effect immediately
```

Authorization must evaluate the actual role assignment granting the gradebook permission. Do not authorize a scoped teacher merely because another unrelated role assignment exists.

Tests must cover users with multiple active SCHOOL roles so permission/scope combination cannot be confused across assignments.

Commit suggestion:

```text
feat: add offering-scoped teaching authorization
```

Push and stop for review.

---

# Task 3: Add Teaching Assignment Screens and Navigation

**Files:**
- Create controller/views under `academic/teaching-assignments/`
- Modify routes/application wiring/dashboard navigation
- Add HTTP/isolation tests

Suggested routes:

```text
GET  /academic/teaching-assignments
POST /academic/teaching-assignments
POST /academic/teaching-assignments/{id}/status
```

Requirements:

- `TEACHING_ASSIGNMENT_MANAGE` required for all mutation routes.
- List/show choices are session-school scoped.
- UI may select academic year then offering and eligible SUBJECT_TEACHER user.
- Foreign/missing IDs use non-enumerating safe behavior.
- CSRF checked before mutation.
- Dashboard/navigation uses AuthorizationService, not role-code checks.
- No score/gradebook implementation is mixed into this task.

Commit suggestion:

```text
feat: add teaching assignment management
```

Push and stop for review.

---

# Task 4: Add Gradebook Component Configuration

**Files:**
- Create repository/service/controller/views for gradebook components
- Modify routes/application/navigation as required
- Add domain/HTTP/isolation tests

Suggested route family:

```text
GET  /gradebook/{offeringId}/setup
POST /gradebook/{offeringId}/components
POST /gradebook/{offeringId}/components/{componentId}
POST /gradebook/{offeringId}/components/{componentId}/status
```

Requirements:

- `GRADEBOOK_COMPONENT_MANAGE` is school-wide for seeded admin roles.
- Offering/year/school state validated under locks before writes.
- Decimal max validation uses decimal-string semantics, not float comparison.
- Duplicate component code follows DB collation.
- Max score cannot change once score history exists.
- Inactive component remains historically readable.
- Exact no-op does not audit.
- CLOSED year freezes setup.

Commit suggestion:

```text
feat: add gradebook component setup
```

Push and stop for review.

---

# Task 5: Add Gradebook Read Model and Roster

**Files:**
- Create gradebook repository/read service
- Create gradebook controller/view
- Add domain/HTTP/isolation tests

Suggested route:

```text
GET /gradebook/{offeringId}
```

Render:

```text
offering identity: year / term / classroom / subject
teacher assignment context where appropriate
active components in sort order
current writable students
historical score-bearing moved-out rows read-only
per-cell score or blank for NULL
readonly entered total / configured max / completeness
```

Authorization:

- SCHOOL_ADMIN / ACADEMIC_ADMIN / EXECUTIVE with `GRADEBOOK_VIEW`: school-wide read.
- SUBJECT_TEACHER with `GRADEBOOK_VIEW`: only active scoped offering.
- Foreign/off-scope offering behaves safely and does not reveal existence/details.

Do not add score mutation yet.

Commit suggestion:

```text
feat: add scoped gradebook view
```

Push and stop for review.

---

# Task 6: Add Transactional Score Mutation and Audit

**Files:**
- Create/extend gradebook score repository/service
- Add focused domain and concurrency/failure tests

Service interface should express session-derived authority and target IDs, for example:

```php
setScore(
    int $schoolId,
    int $actorUserId,
    int $offeringId,
    int $componentId,
    int $enrollmentId,
    ?string $score,
    ?string $ipAddress = null
): array
```

Before write, lock/validate in stable order:

```text
ACTIVE school
DRAFT/ACTIVE academic year
offering
component
enrollment/current placement
scoped authorization dependencies as required
existing score cell
```

Then:

- normalize decimal / NULL;
- validate 0 <= score <= max;
- insert/update nullable score cell;
- exact normalized no-op returns without audit;
- write one `GRADEBOOK_SCORE_CHANGED` audit;
- commit atomically.

Failure in score write or audit must rollback fully.

Cover race/revalidation where student placement, offering status, component status, teacher scope, or year state changes between page render and score submit.

Commit suggestion:

```text
feat: add audited gradebook score entry
```

Push and stop for review.

---

# Task 7: Add HTMX Autosave + Gradebook Keyboard UX

**Files:**
- Create/modify `htdocs/routes/htmx.php` if the application does not already load it; otherwise follow existing route conventions
- Create HTMX gradebook endpoint/controller action
- Create score-cell partial
- Add minimal Vanilla JS for keyboard navigation
- Add HTTP/security/accessibility tests

Requirements:

```text
blur / Enter autosave
Tab / Shift+Tab native flow retained
Arrow-key behavior only where it does not break normal editing
visible saving / saved / error state
server-normalized value returned
NULL clear visually blank
0 remains visible as 0 / normalized decimal
readonly calculated totals refreshed safely
no request per keypress
```

CSRF, permission, scope, tenant, entity state, roster eligibility, and score range are rechecked server-side on every autosave.

Do not rely on disabled/hidden inputs for security.

Commit suggestion:

```text
feat: add gradebook autosave workflow
```

Push and stop for review.

---

# Task 8: Gradebook Isolation, Hardening, Documentation, and MAMP Smoke

**Files:**
- Add/extend isolation and HTTP regression tests
- Modify `README.md`
- Production fixes only when a real defect is reproduced with a failing test first

Hardening matrix must include:

```text
School A offering + School B teacher/scope/component/enrollment
School A component + School B offering
School A score target + School B enrollment
same-school wrong year
same-school wrong classroom/current placement
inactive offering
inactive component
inactive teacher scope
revoked role permission
CLOSED year
forged school_id/user_id/actor/role/scope/offering IDs
malformed arrays/objects
missing/bad CSRF
```

Rejected mutations snapshot at least:

```text
permission_scopes
gradebook_components
gradebook_scores
audit_logs
```

plus relevant existing tables where the test exercises placement/year/offering state.

Verify no response leaks:

```text
foreign student markers
raw national ID
SQL / SQLSTATE
constraint names
PDO exception
stack trace
filesystem paths
credentials/session values
```

MAMP smoke should exercise real login and real HTTP/HTMX score posts on `pp5`, with unique temporary fixtures and child→parent cleanup.

Documentation must:

- update README baseline to Milestone 5;
- remove the now-stale instruction to checkout deleted `milestone/4-student-core-enrollment`;
- document teaching assignment + gradebook permissions;
- document NULL vs 0;
- document generic score components and term-only totals;
- document scoped SUBJECT_TEACHER behavior;
- explicitly state that grade calculation, finalization, annual results, attendance, evaluations, reports, DMC/XLSX/XLSB remain deferred.

Final verification:

```bash
htdocs/vendor/bin/phpunit
find . -path './htdocs/vendor' -prune -o -name '*.php' -type f -exec php -l {} \;
git diff --check
```

If a production defect is found during smoke, fix it in a separate test-first `fix:` commit before the final documentation/hardening commit.

Final documentation commit suggestion:

```text
docs: complete milestone 5 verification
```

Push and stop for pre-PR review. Do not create or merge PR automatically unless explicitly instructed.

---

## Milestone 5 Acceptance Checklist

```text
[ ] Milestones 1–4 full regression remains green
[ ] migration/seed chain remains idempotent and MariaDB-compatible
[ ] four new permissions seeded deterministically
[ ] teaching scopes are tenant-safe and role-assignment specific
[ ] SUBJECT_TEACHER cannot access an unassigned offering
[ ] revoking scope/role/permission removes access immediately
[ ] admins can manage all school offerings without teacher scope
[ ] executive gradebook access is read-only
[ ] component code uniqueness matches DB collation
[ ] component max uses DECIMAL-safe validation
[ ] max score cannot change after score history exists
[ ] score NULL is distinct from zero
[ ] score cannot exceed component maximum
[ ] gradebook current roster follows active placement
[ ] moved-out score-bearing students remain historically visible/read-only
[ ] foreign/off-scope entities are non-enumerating
[ ] CLOSED year freezes teaching/component/score mutation
[ ] score writes are transactional and audited
[ ] exact score no-op creates no audit noise
[ ] audit failure rolls score write back
[ ] HTMX autosave verifies CSRF + permission + scope + state every request
[ ] keyboard UX does not bypass server validation
[ ] raw national ID never appears in gradebook/audit/error surfaces
[ ] no annual grade/GPA/finalization behavior introduced
[ ] no attendance/evaluation/activity/report behavior introduced
[ ] no DMC/XLSX/XLSB compatibility claimed
[ ] README no longer references the deleted Milestone 4 branch
```

---

## Expected End State

After Milestone 5:

```text
School/Academic Admin
→ assign SUBJECT_TEACHER to subject offerings
→ configure term score components
→ view/edit all school gradebooks

Subject Teacher
→ login
→ see only assigned offerings
→ enter/clear scores with autosave
→ see readonly totals/completeness

Executive
→ view gradebooks read-only

System
→ preserves tenant isolation
→ distinguishes NULL from zero
→ audits score changes
→ retains historical scores across classroom moves
```

The next milestone can then build attendance/evaluation or result/finalization behavior on top of a stable student + academic structure + scoped gradebook foundation without importing legacy Excel formulas directly.