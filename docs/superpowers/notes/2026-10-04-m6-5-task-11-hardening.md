# M6.5 Task 11 — Cross-screen, responsive, accessibility, and security hardening

## Baseline and boundary

- Branch `milestone/6-5-classroom-workspace`; fetched local HEAD and origin both `afe9c9baa62241f58743eae0fe00ea9b083c76eb`, ahead/behind `0 0`, clean tree before editing.
- Read the M6.5 plan, Tabulator addendum, accepted spreadsheet contract, 124-ID behavior registry, 10.6f audit, 10.6 production note, current M6 browser tests, and architecture tenant/security rules before editing.
- Task 11 changes only focus and live-region presentation plus its focused browser assertion. No score, permission, persistence, schema, migration, or vendor change. Task 12 and M6.6 have not started; the softer visual redesign remains deferred to M6.6.

## Implemented screen inventory

All classroom locators are resolved against the authenticated session school. Year, grade and school are derived from the resolved classroom or offering. Legacy links remain resource-checked by their own routes and services.

| Current route / screen | Controller / view | Required authority | Primary table and responsive containment | POST commands reachable in the work path | Legacy/deep link |
| --- | --- | --- | --- | --- | --- |
| `GET /workspaces/classrooms/{id}` overview | `ClassroomWorkspaceController::show`; `workspaces/classroom/overview.php` and shell | Any authorized student, academic/assignment section, or live accessible Gradebook offering; 404 for unauthorized/wrong school | Accessible Gradebook table in `.pp5-table-scroll` | None on overview | Classroom admin `/academic/classrooms`; `/gradebook/{offeringId}` |
| `GET /workspaces/classrooms/{id}/students` | `ClassroomWorkspaceController::students`; `workspaces/classroom/students.php` | `STUDENT_VIEW`; contextual actions require `ENROLLMENT_MANAGE` / `STUDENT_IMPORT` | Semantic roster in `.pp5-table-scroll` | Legacy enrollment create, placement, terminal status; CSV preview/apply/cancel through linked flow | `/students`, `/academic/enrollments`, `/academic/student-import` |
| `GET /workspaces/classrooms/{id}/subjects` | `ClassroomWorkspaceController::subjects`; `workspaces/classroom/subjects.php` | Academic setup/offering or assignment permission, or scoped accessible Gradebook; rows filtered for scoped user | Semantic offering/teacher table in `.pp5-table-scroll` | In-page teaching assignment create/status; linked offering create/update/status | `/academic/subjects`, `/academic/offerings`, `/academic/teaching-assignments` |
| `GET /gradebook/{offeringId}/setup` score structure | `GradebookComponentController::setup`; `gradebook/setup.php` | `GRADEBOOK_COMPONENT_MANAGE`, school-scoped offering; write also checks lifecycle in service | Component sections and labeled forms; wrapping controls | Component create/update/status | Existing setup URL retained |
| `GET /gradebook/{offeringId}` scores | `GradebookController::show`; `gradebook/view.php` | Live `GRADEBOOK_VIEW` offering authorization; score writes additionally require `GRADEBOOK_SCORE_ENTER` and valid lifecycle | Pinned local Tabulator internal holder; semantic fallback table in `.pp5-table-scroll` | Single score POST and atomic batch POST through local adapter | Existing offering-specific Gradebook URL retained |
| `GET /gradebooks` My Teaching | `GradebookController::index`; `gradebook/index.php`, `teaching-work.php` | School context plus live accessible-offering projection; empty access is an empty state | Grouped teaching list with wrapping actions | None | Existing `/gradebooks` entry point retained |

The workspace shell uses a native classroom/year disclosure, authorized local links, and active-state mapping. Other linked legacy entity/admin screens remain available under their existing permissions; this task did not redirect or remove them. The score navigation anchor targets the implemented overview, not a future placeholder.

## Audit and corrections

1. **Stale range announcement.** In the accepted Gradebook adapter, Escape cleared the visible score range but left the assistive-technology status saying a range was selected. Repeated one-cell movement also rewrote identical live-region text. `gradebook.js` now announces cancellation after a prior selection and skips unchanged announcements. The `GB-SEL-006` parity assertion checks cancellation while retaining score focus. Save status height, quiet save, selection, and commands are unchanged.
2. **Focus visibility after pointer input.** The M6 cross-screen suite initially passed in keyboard modality, then reproducibly failed its visible-focus checks for 134 cases when rerun after native pointer journeys. The global ring applied only to `:focus-visible`; programmatic focus after a pointer command could therefore be invisible. `app.css` now also outlines focused non-Tabulator cells. Tabulator score cells retain their dedicated single boundary, and the editor input keeps its borderless override. The unchanged cross-screen checks then passed all cases in the same pointer modality.

No other confirmed material defect required a code change. The separate `1280` px browser sweep covered overview, students, subjects, setup, Gradebook and My Teaching: each had exactly one H1, `scrollWidth == clientWidth == 1280`, contained tables/grid, and reachable navigation/actions. The four-width matrix separately covered 390, 768, 1024 and 1440 px. Gradebook layout includes empty roster, no components, read-only/history, frozen identity, disabled hidden fallback, labels, internal scrolling and focus geometry.

## Security and architecture review

- **Authority/tenant:** `ClassroomWorkspaceReadService` resolves classroom by school before composing context. Roster requires `STUDENT_VIEW`; subjects filter offerings to accessible IDs for scoped readers. Gradebook reads and both write endpoints recheck offering scope. Feature tests cover broad admin, scoped teacher, read-only, unauthorized, wrong-school and live revocation. No browser-provided school, role, permission, year or classroom value is treated as authority. Direct denied requests remain denied.
- **CSRF and GET:** All reachable state changes use POST and their existing CSRF checks, including enrollment, offerings, teaching assignments, components, HTMX single score and JSON batch score. Feature matrices cover missing/invalid/expired tokens, no mutation, wrong-school and revoked cases. Route review found no GET mutation.
- **Confirmation:** Existing `data-confirm` use remains limited to high-impact school status, terminal student enrollment/status and import decisions. Ordinary score entry, safe edits and navigation have no new confirmation.
- **PII/bootstrap:** Roster projects student code/name/status only. Gradebook bootstrap contains offering/component IDs and labels, enrollment ID, student code/name, current/history flag, scores and server summaries; no national ID, credential, permission secret, audit internals or browser-selected school authority. JSON uses HTML-safe hex escaping. Source and static searches found no raw national ID in these work surfaces.
- **Errors/audit:** Cross-school/missing workspace reads use safe 404. Score/client errors are bounded text; server refresh uncertainty asks for reload and blocks retry. Generic Throwable handling does not render stack, SQL, paths or raw exceptions. Existing mutation service tests cover successful audit and no fake success audit on denied/invalid writes. No audit model change.
- **Local assets:** Runtime script/style tags reference local first-party and pinned vendor files; no CDN, remote CSS/JS/font, inline executable handlers or per-page style hacks were added. Reduced-motion CSS remains in `app.css`. No dialog/drawer was introduced; the native disclosure and mobile navigation escape/focus behavior are covered by the cross-screen suite.
- **Future screens:** No clickable Attendance, Evaluation, Competencies, Activities, Results or Reports placeholders in current workspace/Gradebook views.
- **Performance:** Roster uses one tenant-scoped list; subjects use batched component summaries and teaching-assignment lists; Gradebook matrix reads roster and scores in sets. Accessible-offering listing still performs a permission query per offering to preserve the existing scope semantics. It was not rewritten without a demonstrated load defect or a separately proven equivalent authorization query.
- **Vendor integrity:** Tabulator JS `b8c69d7e6b82b01979a6630fad847c3a303b6a59b74bfd04c27fa644c4721332`, CSS `ff598d8e961398e09a8e52ab21740e5ed952c84feebbdacec8f8a362dd3f73db`, LICENSE `191a2ee554684e1064c897b432f0e1bc6dfa714ca045d3f6ea2cf692cbd398b7`; all match the accepted pins. No vendor diff.
- **Server authority / ordering:** Single and batch endpoints retain CSRF, tenant/scope/lifecycle/max validation, NULL versus zero, transaction and audit boundaries, server totals/completeness, stale revocation and uncertainty lock. `GB-COMMAND-001` is covered by delayed journeys J–M; each observed one batch after pending single writes, zero dropped/retried commands.

## Verification evidence

Evidence types are separate; fixture browser checks are not authenticated MAMP checks or screen-reader certification.

| Gate | Result |
| --- | --- |
| Behavior registry | PASS, **124** accepted IDs, unique mappings and vendor pins |
| Real browser Golden Journeys | **A–M PASS**: A 10, B 10, C 5, D 13, E 11, F 7, G 6, H 11, I 11, J 14, K 14, L 14, M 14 checks; native pointer/keyboard/clipboard on isolated fixture, with no test-side focus repair. J/K had 1 pending single; L/M had 3; all had 1 batch after singles. |
| Synthetic Gradebook writable / read-only / parity / direct entry / visual / races | PASS: 60 / 12 / 33 / 54 / 64 / 29 checks, respectively; visual fixture reported zero Gradebook layout shifts. |
| Synthetic Gradebook errors / paste limit / workloads / layout | PASS: 19 error scenarios, 201 checks; 2,001-cell limit 5 checks; 35×20 16 checks; 100×40 9 checks; responsive layout 48 cases, 828 checks. |
| Synthetic M6 cross-screen | PASS: 138 cases, 6,386 checks, 0 failures at 390/768/1024/1440 px; mobile→desktop→mobile resize PASS. Same assertions passed after pointer-modality focus correction. |
| Synthetic confirmation convention | PASS: no-JS native submission 7 checks; enhanced high-impact confirmation 11 checks. |
| Focused PHPUnit | PASS: 872 tests, 19,864 assertions, 0 failures, using local test database. |
| Full PHPUnit | PASS: 3,063 tests, 69,866 assertions, 0 failures, using local test database. |
| Syntax | PASS: 215 first-party PHP files and 18 first-party JavaScript files; 0 failures. |
| Diff | `git diff --check` PASS. |

MAMP `/gradebook/12` redirected the available in-app browser session to `/login`; no authenticated disposable session was available, so this task makes **no claim of real-data MAMP editing**. Native Thai IME, screen reader, other browsers and manual OS reduced-motion verification were not performed. Task 12 retains final multi-persona MAMP workflow certification and final milestone documentation. Milestone 6.6 retains the requested softer visual redesign.
