# M6.5 Task 2 — Workspace shell and task navigation

## Baseline and scope

- Branch: `milestone/6-5-classroom-workspace`.
- Starting local/origin SHA after fetching: `e2c81f584e211e889651ec59bda5832e66110b65`.
- Starting tree clean. No branch creation, reset, PR, or main merge.
- Read the technical architecture, M6 shell/accessibility sections, M6.5 Task 2 and architecture sections, future assessment/reporting ledger, Task 1 inventory, and current implementation.
- Task 2 only. No new routes, dedicated roster, combined subject/teacher surface, score setup redesign, or future-domain placeholders.

## Architecture and authoritative context

`ClassroomWorkspaceReadService` retains Task 1's tenant-safe `findForSchool` lookup and live composition of existing authority. It now projects authorized switch targets, using the existing classroom repository for broad student/academic/teaching authority and the existing `GradebookReadService::listAccessibleOfferings` result for scoped readers. Targets are deduplicated by classroom, grouped by year in the presentation, and point to canonical classroom URLs. Historical accessible classrooms remain reachable.

`AppUiContextService` resolves an optional classroom locator under the authenticated session and asks the read service for authoritative context. It supplies a presentation model to `ClassroomWorkspaceNavigation`. `View::page` composes the reusable workspace shell before the page content inside the existing application layout. The global layout, middleware order, global active keys, logout form, and shell JavaScript remain unchanged.

The header displays school, academic year, classroom, and grade. Context is present on the overview, the existing student/subject/teacher list destinations, and authorized Gradebook pages. It is not sticky, so it does not cover focused controls.

The optional `workspace_classroom_id` query/GET-form field is only a locator. Each request resolves it again. School/user/permissions come from session/SchoolContext; year and grade come from the classroom relationship. The existing list controllers derive their filters from that context. Forged competing school/year/grade/classroom parameters cannot replace it. Invalid, missing, foreign, or inaccessible locators fail closed. Gradebook context comes from its authorized offering, regardless of browser workspace fields.

## Local navigation and entry points

- Overview links to the canonical classroom route.
- Student navigation links to the existing filtered enrollment list, retaining search/status filters and the revalidated classroom locator.
- Subject and teacher navigation uses separate, explicitly year-wide destinations: `รายวิชาในปีนี้` and `ครูผู้สอนในปีนี้`. Both pages explain that their data covers all classrooms in that year. They do not pretend to be Task 4's combined classroom surface.
- Scores links to the real accessible Gradebook list in the overview. Gradebook pages carry the same workspace header and return navigation.
- Only links produced by the authorized read model are rendered; no role-name checks or new permission codes.
- Dashboard exposes an authorized classroom/year list only when nonempty. Existing dashboard and Gradebook offering rows link back to their classroom workspace. The existing sidebar remains unchanged; no landing route was added.
- Local active state uses controller keys, supports descendant keys and `workspaces.classrooms.<implemented-section>.*`, and marks one visible item with `aria-current="page"`. It is independent of global navigation keys. No speculative routes are registered.

## Scoped access, performance and privacy

Gradebook-only readers see only classrooms represented by their live accessible offerings and receive overview/scores navigation only. Scope revocation is reflected on the next request, including partial revocation where another classroom remains accessible. Foreign, unassigned, and unrelated resources remain excluded; direct unauthorized workspace/Gradebook requests remain safe 404s. SYSTEM pages do not receive classroom navigation.

The switch projection performs no lookup per classroom. A test adds ten classrooms and confirms the query count stays constant for broad navigation. Scoped navigation retains Gradebook's existing per-offering live authorization checks; no alternate ACL or cached authority is introduced. Dashboard and workspace projections are request-local. There is no selection persistence.

Navigation does not load student rosters, score/components, national IDs, birth dates, classroom-wide counts, or teacher identity lists. Existing destination pages continue to use their own authorized services. Browser fixtures contain synthetic display data only. No workbook, real student data, credentials, session dumps, or generated logs are committed.

## Responsive and accessibility behavior

The native `details`/`summary` switcher works without JavaScript. Authorized links are grouped by year in a bounded scrolling region. Local navigation wraps; long names wrap. The existing focus styles remain visible, and the active item has bold/underline treatment as well as color. No animation or new production JavaScript is added; existing reduced-motion rules remain intact.

The browser matrix covers 1440×900, 1024×768, 768×1024, and 390×844, with broad-access, Gradebook-only, and legitimate empty classroom states. It checks document overflow, active semantics, control focus/occlusion, the last target in a long switcher, mobile menu/Escape/resize behavior, and POST+CSRF logout markup. A separate no-JS workspace case verifies the native disclosure. Native Enter, Space and Tab were also exercised in the browser with a visible focus outline. This is not manual screen-reader certification.

## Verification

All tests used the existing transactional `pp5_test` MySQL fixtures, with PHP 8.3.14 for PHPUnit.

| Check | Result |
| --- | --- |
| Task 2 focused: `ClassroomWorkspaceNavigationTest.php` | **29 tests / 207 assertions passed** |
| Task 1 unchanged regression: `ClassroomWorkspaceTest.php` | **23 tests / 500 assertions passed** |
| Relevant regression: `--filter 'Classroom\|Academic\|Student\|Enrollment\|Subject\|TeachingAssignment\|Gradebook\|Authorization\|SchoolIsolation\|Ui\|Dashboard'` | **2,486 tests / 59,860 assertions passed** |
| Full PHPUnit | **2,890 tests / 66,403 assertions passed** |
| First-party PHP syntax with PHP 8.2.26 | **205 files passed**, vendor excluded |
| First-party JavaScript `node --check` | **11 files passed**; production JS unchanged |
| Final synthetic cross-screen browser matrix | **78 cases / 2,591 numeric checks passed**, plus resize check |
| Native no-JS switcher keyboard | Enter closes, Space opens, Tab reaches canonical classroom link with solid focus outline |
| Real MAMP | Login page only; authenticated workflow not performed |
| `git diff --check` and local diff/scope review | Passed |

The first regression run caught a Dashboard test constructing the old five-argument UI service. Its dependency wiring was updated; all assertions were retained. No test was removed or weakened.

New tests cover individual permission grants/revocations, scoped and broad switch sets, multiple academic years, foreign/forged/malformed locators, authoritative filters, partial and full live scope revocation, escaped names, real entry links, nested active keys, SYSTEM isolation, and query-count/PII boundaries. Existing Task 1 assertions remain unchanged. Browser assertions were extended with native disclosures and authorized-target checks; the existing focus/overflow assertions remain intact.

## Intentional limits

- Switching goes to the selected classroom's overview; no last-selection persistence.
- Subject and teaching lists remain year-wide. Dedicated classroom work surfaces remain Tasks 3–5.
- Existing create/edit/setup screens and mutation redirects retain their global workflows. Task 2 preserves context across the implemented read destinations; it does not move mutations into a workspace.
- MAMP Apache/PHP served the real login page successfully. No authenticated admin/teacher session or test credentials were available, so authenticated real-MAMP switching and logout smoke was not performed. Transactional MySQL feature tests and synthetic browser checks are reported separately.
- No schema, migration, seed, new business permission, or Gradebook write/keyboard semantic changes. `NULL != 0`, real zero, server totals, read-only history, blur save, saved header, invalid-value retention, Enter/Tab/arrows and live scope rules remain in their existing implementation.
- Task 3 has not started. Stop after the Task 2 commit/push/report.

## File inventory

### Added

- `docs/superpowers/notes/2026-09-27-m6-5-task-2-navigation.md`
- `htdocs/app/Support/ClassroomWorkspaceNavigation.php`
- `htdocs/views/workspaces/classroom/choices.php`
- `htdocs/views/workspaces/classroom/shell.php`
- `tests/Feature/ClassroomWorkspaceNavigationTest.php`

### Modified

- `htdocs/app/Application.php`
- `htdocs/app/Controllers/ClassroomWorkspaceController.php`
- `htdocs/app/Controllers/EnrollmentController.php`
- `htdocs/app/Controllers/GradebookController.php`
- `htdocs/app/Controllers/SubjectOfferingController.php`
- `htdocs/app/Controllers/TeachingAssignmentController.php`
- `htdocs/app/Services/AppUiContextService.php`
- `htdocs/app/Services/ClassroomWorkspaceReadService.php`
- `htdocs/app/Support/View.php`
- `htdocs/assets/app.css`
- `htdocs/views/academic/enrollments/index.php`
- `htdocs/views/academic/offerings/index.php`
- `htdocs/views/academic/teaching-assignments/index.php`
- `htdocs/views/dashboard/index.php`
- `htdocs/views/gradebook/offering-list.php`
- `htdocs/views/workspaces/classroom/overview.php`
- `tests/Browser/classroom-workspace.php`
- `tests/Browser/ui-cross-screen.js`
- `tests/Browser/ui-cross-screen.php`
- `tests/Browser/ui-entry.php`
- `tests/Feature/DashboardAccessTest.php`
- `tests/Feature/UiNavigationTest.php`
