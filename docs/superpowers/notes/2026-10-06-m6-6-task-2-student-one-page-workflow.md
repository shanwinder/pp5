# M6.6 Task 2 — Student one-page workflow

## Baseline and scope

- Branch: `milestone/6-6-soft-government-ui`.
- Verified before edits: local HEAD and `origin/milestone/6-6-soft-government-ui` were both `e55209e6495a80e0810901cd7024429449a50e40`, ahead/behind `0 0`, with a clean worktree.
- This change is limited to classroom roster student context, placement and enrollment status. It adds no schema, migration, or permission code. Student, enrollment and placement legacy routes remain available.

## Workflow and authority

The compact icon in each roster row loads a server-rendered contextual card below the roster. The card shows the student code, name, current enrollment, year and grade, placement history, prior enrollment history when present, and a link to the full student record. Placement targets are active classrooms in the same school, academic year and grade, excluding the current classroom. The status choices map to the existing `TRANSFERRED_OUT` and `WITHDRAWN` domain statuses and require an explicit exit date and confirmation.

The workspace endpoints are:

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/hx/workspaces/classrooms/{classroomId}/students/{enrollmentId}` | Render student context |
| POST | `/hx/workspaces/classrooms/{classroomId}/students/{enrollmentId}/placement` | Change placement |
| POST | `/hx/workspaces/classrooms/{classroomId}/students/{enrollmentId}/status` | End enrollment |

Routes require the existing authenticated school context and permissions. The read service starts with `ClassroomRosterReadService`, so only a currently listed enrollment in the authorized classroom can open. The POST adapter rechecks that context, `ENROLLMENT_MANAGE`, the CSRF token, and input. `EnrollmentAdministrationService` remains the sole writer and audit source. Its optional expected classroom parameter checks the active placement under the existing transaction locks; status submissions also reject an enrollment that became nonactive. The service still validates school, year, grade, target classroom, exit date, and closed-year lifecycle. Invalid requests return a generic local error; successful HTMX requests receive an authoritative fresh roster, count, and live announcement. A plain form POST redirects back to the workspace after success.

The panel's heading receives focus after opening. Escape or Close clears it and returns focus to the triggering row action. After a mutation, focus moves to the server-rendered success or error. Forms and alerts have labels and roles, and the panel's error is linked to both forms. Without JavaScript, the row icon follows its existing student detail or enrollment edit destination; the POST routes also support normal form responses. HTMX and the panel script load only in the student workspace; the shared shell script stays within its existing size and responsibility contract.

## Verification so far

- Focused `ClassroomRosterTest` and `EnrollmentAdministrationTest`: 116 tests, 3,114 assertions passed.
- Full PHPUnit after moving the panel behavior into its page-specific asset: 3,072 tests, 70,046 assertions passed. The first full run found two shared-shell asset contract failures; both were corrected before this passing rerun.
- `UiAssetTest`, `UiConfirmationTest`, and `UiTablerProductionTest`: 13 tests, 200 assertions passed.
- PHP lint of every changed PHP file, JavaScript syntax checks, and `git diff --check`: passed.
- Chrome cross-screen synthetic production-template matrix: passed at 390, 768, 1024, 1280, and 1440 px. Roster normal passed 59/59 checks at desktop widths and 63/63 at mobile widths; roster read-only passed 47/47 and 51/51 respectively. These checks cover contained table scroll, compact row action, panel containment, visible focus, and document overflow. The matrix also passed the other existing pages and no-JS navigation cases.
- Chrome UI foundation, entry, administration, student workflow, navigation, and confirmation/no-JS browser suites: all passed.
- Gradebook Behavior Registry validator: 124 accepted behaviors passed. Golden Journeys A–M: all passed with real browser pointer and keyboard input, including native copy/paste and pending-save clear for J–M. Gradebook layout, Tabulator 60, direct-entry 54, visual stability 64, spreadsheet parity 33, error 12, race 29, and workload 16 browser checks passed.
- Authenticated MAMP Chrome session (`admintestsc`): reloaded `/workspaces/classrooms/47/students` after the page-specific asset change, opened a real student's contextual panel, inspected history and forms, verified focus on the heading, Escape/focus return, focus tooltip, and no document overflow. All five viewport widths had been checked previously. The available MAMP context has no second eligible room in the same year and grade. No MAMP mutation was made: before, temporary, and final placement/status are identical, with no test-created audit rows to erase. The high-impact status transition cannot be reversed by the normal service; persistence and audit are covered by isolated DB feature fixtures.
- Remaining limitation: a successful authenticated MAMP move and terminal status mutation cannot be exercised and restored with the available fixture. Automated transactional fixtures cover their domain outcomes and audit records.

## Test infrastructure note

DB tests need sandbox escalation to reach the local MySQL socket. A temporary automatic approval reviewer usage limit interrupted an earlier attempt; the user reported a reset and the focused and full suites subsequently ran successfully through normal approval. Default sandbox DB access still returns `SQLSTATE[HY000] [2002] Operation not permitted`.
