# M6.5 Task 1 inventory and read boundary

Baseline: `milestone/6-5-classroom-workspace` at
`545508c0aec561434fc7a69dc5a96871f1d65553`; clean and synchronized after fetching origin.
Read the technical architecture, M6 plan, M6.5 plan, and future assessment/reporting ledger before implementation.

## Current implementation

All routes live in `htdocs/routes/web.php`; dependencies and handlers are wired in
`htdocs/app/Application.php`. Protected school requests run Auth, SchoolContext,
then PermissionMiddleware where a generic permission applies.

| Area | Existing paths and authority |
| --- | --- |
| Classroom / year / grade | `/academic/classrooms`, `ClassroomController`, `ClassroomAdministrationService`, `ClassroomRepository`; views under `academic/classrooms`. `findForSchool` joins the classroom's own year with school equality and its global grade level. Lists require `ACADEMIC_SETUP_VIEW`; edits require `CLASSROOM_MANAGE`. Year and grade repositories supply existing choices. |
| Student master | `/students`, `StudentController`, `StudentAdministrationService`, `StudentRepository`; views under `students`. Reads require `STUDENT_VIEW`, writes `STUDENT_MANAGE`. Detail lookup loads national ID and masks it; it is unsuitable for overview composition. |
| Enrollment / placement | `/academic/enrollments`, `EnrollmentController`, `EnrollmentAdministrationService`, `StudentEnrollmentRepository`, `StudentClassroomPlacementRepository`; views under `academic/enrollments`. List requires `STUDENT_VIEW` and supports year/grade/classroom filters. `listForSchoolYear` constrains school/year and joins current placement; placement history remains separate. Writes require `ENROLLMENT_MANAGE`, lifecycle checks, transactions and audit. |
| Subject / offering | `/academic/subjects`, `/academic/offerings`, corresponding controllers, administration services and repositories; views under `academic/subjects` and `academic/offerings`. Lists require `ACADEMIC_SETUP_VIEW`; mutations have separate manage permissions. Offering list filters by year, not classroom. Offering joins constrain school and classroom/year consistency. |
| Teaching | `/academic/teaching-assignments`, `TeachingAssignmentController`, `TeachingAssignmentService`, `TeachingAssignmentRepository`; views under `academic/teaching-assignments`. Requires `TEACHING_ASSIGNMENT_MANAGE`. Actual storage is **permission_scopes**, not a separate teaching_assignments table. History and live access are different concerns. |
| Gradebook | `/gradebooks`, `/gradebook/{offeringId}`, `GradebookController`, `GradebookReadService`, `GradebookRepository`. `listAccessibleOfferings` checks every offering through `AuthorizationService::hasSubjectOfferingPermission`. Live scope must belong to the same granting role assignment, school, year and offering. Closed years/inactive offerings remain readable when authorized. `getGradebook` also loads student rows/scores and is unnecessary for the overview. |
| Shell | `AppUiContextService::build` produces live permission/resource navigation and safe school identity. `View::page` wraps `layouts/app.php`; `View::render` remains fragment-only. Page title is passed by controllers. There is no shared breadcrumb component in the actual baseline. CSS, shell JS and status partials are local. |

## Task 1 decisions

- Add only `GET /workspaces/classrooms/{classroomId}`. Re-resolve the locator via
  `ClassroomRepository::findForSchool` using authenticated session context.
- A read service projects safe classroom/year/grade/school fields, existing
  permissions, and only the accessible Gradebook offerings belonging to this room.
- Overview access requires `ACADEMIC_SETUP_VIEW`, `STUDENT_VIEW`,
  `TEACHING_ASSIGNMENT_MANAGE`, or at least one accessible offering **in this room**.
  These existing routes already expose the relevant classroom context.
- Student, subject, teaching and score capabilities stay separate. No capability
  implies another. This route uses service-level composition authorization like
  Gradebook, since a single generic permission would exclude valid scoped users.
- Missing, foreign and unauthorized classrooms share the existing safe 404 page;
  context 403 responses are normalized to 404 as for direct Gradebook reads.
- Overview links may lead to existing authorized read pages. Student links include
  authoritative year/grade/classroom filters; offering/teaching links explicitly
  describe a year view because those legacy pages do not filter by classroom.
- No student query, roster, classroom-wide count, score/component load, teacher
  identity list, mutation form or new local section is needed. Offering links
  mean read access only; the existing Gradebook decides editability.
- Reuse the current shell without new sidebar entries or switching controls.
  Task 2 owns workspace navigation and entry-point integration.
- No schema, seed, permission, write-contract or domain-model change. Future
  assessment/versioned printable reference requirements remain deferred; this
  read projection adds no persistence or alternative student context.

## Verification approach

Reuse the real-MySQL transactional `GradebookReadFixtures` for service and HTTP
tests, including scope revocation, direct permission changes, foreign resources,
historical access, safe errors, escaped labels and SQL-level PII exclusion.
Run focused, related regression and full PHPUnit suites, first-party PHP lint,
the existing shell browser fixture, and diff/scope review before one commit.

## Verification results — 2026-09-27

- Before implementation, new tests failed because the route/service did not exist.
- Focused: `php htdocs/vendor/bin/phpunit tests/Feature/ClassroomWorkspaceTest.php`
  — **23 tests / 500 assertions passed**.
- Relevant regression: `php htdocs/vendor/bin/phpunit --filter
  'Classroom|Academic|Student|Enrollment|Subject|TeachingAssignment|Gradebook|Authorization|SchoolIsolation|Ui'`
  — **2,440 tests / 59,558 assertions passed**.
- Full: `php htdocs/vendor/bin/phpunit` — **2,861 tests / 66,187 assertions passed**
  on PHP 8.3.14 using the existing transactional `pp5_test` MySQL fixtures.
- First-party PHP syntax: **201 files passed** with PHP 8.2.26, excluding vendor.
- Browser: `php -S 127.0.0.1:18895 tests/Browser/ui-cross-screen.php`, opened in
  the Codex browser — **77 cases / 1,968 numeric checks**, plus the resize check,
  passed. This includes workspace admin/limited/empty views at 1440×900,
  1024×768, 768×1024 and 390×844. The first run caught mobile overflow from an
  absolutely positioned accessible label; using `aria-label` fixed it without
  a stylesheet or JavaScript change.
- Browser fixtures use synthetic display data. This is not a real MAMP Apache
  login/workflow smoke or manual screen-reader certification.
- JavaScript syntax was not rerun because no JavaScript file changed.
- Reviewed production and test diffs; `git diff --check` passed. No migrations,
  seeds, new permission codes, workbook/PII files, or Gradebook implementation
  changes. Existing route definitions retain their behavior.

Task 1 provides a direct overview URL. Sidebar integration, switching, dedicated
roster/subject workspaces, spreadsheet interactions and future assessment/reporting
remain deferred. No Task 2 code is included.
