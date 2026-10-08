# M6.6 Task 3 — classroom subject workspace

## Starting point and scope

- Verified clean `milestone/6-6-soft-government-ui` at `5463b7cd71972e883a3b54d1bbc6c42e8a82800e`, equal to `origin/milestone/6-6-soft-government-ui` (ahead/behind 0/0) before editing.
- This task changes the classroom subjects page, its contextual read and write adapters, limited expected-context checks in the existing assignment and component services, page assets, tests, and this note. It does not change the schema, permissions, Gradebook behavior, or legacy route contracts.

## Workspace and contextual workflow

`GET /workspaces/classrooms/{classroomId}/subjects` retains the authorized, classroom-scoped offering list. The Tabler page has a compact six-column table, one labeled icon action per offering, a classroom toolbar, and an optional selected-offering panel. The row action loads `GET /hx/workspaces/classrooms/{classroomId}/subjects/{offeringId}` into that panel. Its ordinary link opens the same selection via `?offering_id=` when JavaScript is unavailable. The panel separates offering details, active teachers, and score setup. Existing offering management, assignment history, full score setup, and Gradebook destinations remain linked where authorized.

The panel uses the live `ClassroomSubjectsReadService` projection and rechecks the selected offering's school, classroom, and academic year. `ClassroomOfferingContextReadService` loads component detail only for users allowed to set up components or open the Gradebook. Without setup permission it shows active components only and does not fetch history IDs or expose inactive details. Assignment and component controls are independently gated; year and offering state also govern mutation controls.

## Endpoint and service contracts

All new writes are POST under `/hx/workspaces/classrooms/{classroomId}/subjects/{offeringId}`:

| Suffix | Permission | Delegated operation |
| --- | --- | --- |
| `/assignments` | `TEACHING_ASSIGNMENT_MANAGE` | `TeachingAssignmentService::createAssignment` |
| `/assignments/{assignmentId}/status` | `TEACHING_ASSIGNMENT_MANAGE` | `TeachingAssignmentService::changeStatus` to `INACTIVE` |
| `/components` | `GRADEBOOK_COMPONENT_MANAGE` | `GradebookComponentService::createScoreItem` |
| `/components/{componentId}` | `GRADEBOOK_COMPONENT_MANAGE` | `GradebookComponentService::updateScoreItem` |
| `/components/{componentId}/status` | `GRADEBOOK_COMPONENT_MANAGE` | `GradebookComponentService::changeStatus` |

The controller supplies school and actor IDs from the session, verifies CSRF, checks that resource IDs belong to the selected live offering, and passes expected classroom and status values into the existing transactional services. These checks run again under the services' established school/year/offering/resource locks. A moved offering, changed state, or concurrent status change rejects the write atomically. Existing services continue to own validation and audit; the controller does not write data or create duplicate audit records. Other callers retain their old method semantics because the new expected-context parameters are optional.

Score item codes and ordering remain server assigned. Active counts and maxima come from the authoritative refreshed read model. A component with any score history keeps its maximum immutable, including after score clearing; the panel marks that field read-only and the domain service enforces the rule. Inactive component detail and history indicators are reserved for setup permission. Stopping a teacher assignment changes status and retains the assignment/audit history; it does not delete a record or silently replace another teacher.

## Responses, fallback, and accessibility

Successful HTMX writes return the refreshed subjects fragment (table, totals, and selected panel). Validation and stale-state failures return a sanitized refreshed fragment with HTTP 422; bad CSRF returns 419. Missing or out-of-scope offering/resource returns 404, and route permission failure returns 403. No failed write emits a success message. Ordinary POST success redirects to the subjects page with the offering selected; an ordinary POST error renders a full page with the error and preserved score inputs. Stop/status forms use explicit confirmation text. The page script manages focus after loading, errors, close, and Escape; its native confirmation fallback covers JavaScript running without HTMX. Native links, forms, and server rendering cover JavaScript disabled.

The table has a caption and labeled focusable scroll region. Icon actions have accessible names and titles, form fields have labels, result messages use alert/status semantics, and the panel heading accepts focus. Browser checks at 390, 768, 1024, 1280, and 1440 pixels found no document overflow; the table scrolls within its region on narrow screens. A live authenticated MAMP review at 390 and 1440 pixels confirmed panel loading, focus return, Escape close, and containment.

## Verification and fixture handling

- Focused PHPUnit before the last review-only test addition: 191 tests, 4,964 assertions passed. The updated workflow test: 9 tests, 84 assertions passed. The final full suite: 3,081 tests, 70,134 assertions passed.
- Chrome cross-screen matrix passed for normal, limited, empty, and read-only classroom subject states at all five widths, including mobile-to-desktop-to-mobile resize. Foundation, entry, navigation, administration, student workflow, confirmation, and Gradebook browser suites passed. Gradebook Registry validator passed all 124 accepted behaviors; Golden Journeys A–M passed.
- PHP lint, JavaScript syntax, and `git diff --check` passed. Legacy route and service coverage remains in the full PHPUnit and browser suites.
- Authenticated MAMP review used the existing `admintestsc` session and classroom 47. The offering `วิทยาการคำนวณ4` (ว14201, term 1) showed `testteach`, four score items, and an active total of 50.00. The review made no MAMP data mutations, so there was nothing to restore and no audit row was added. Write paths were exercised in isolated transactional database fixtures that roll back after each test.
- In a separate no-HTMX Chrome fixture, the stop action displayed its native confirmation once. Chrome blocked the subsequent local fixture POST with `ERR_BLOCKED_BY_CLIENT`, so that browser fixture does not establish native POST navigation. The feature test confirms the native POST redirect and selected-panel render on the server. No authenticated MAMP mutation was attempted because the existing records were not identified as disposable fixtures.
