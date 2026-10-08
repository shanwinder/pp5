# M6.6 Task 4 — Gradebook contextual workspace

## Baseline and scope

Verified `milestone/6-6-soft-government-ui` after fetching origin: HEAD and origin both `402a768d98aaef2dd11ba79876b5af4627a16f22`, ahead/behind 0/0, clean working tree. This task changes Gradebook presentation and ordinary navigation only. Task 5 remains outside scope. Product-owner visual acceptance is **pending**.

Changed production files: `htdocs/app/Controllers/GradebookController.php`, `htdocs/views/gradebook/view.php`, new `htdocs/views/gradebook/context.php`, `htdocs/views/gradebook/scoring-assets.php`, `htdocs/views/gradebook/selection-assets.php`, and new `htdocs/assets/gradebook-workspace.css`.

Changed verification files: new `tests/Feature/GradebookWorkspaceTest.php`, `tests/Feature/UiGradebookTest.php`, `tests/Feature/ClassroomWorkspaceNavigationTest.php`, `tests/Feature/UiCrossScreenContractTest.php`, `tests/Browser/gradebook-autosave.php`, `tests/Browser/ui-cross-screen.php`, new `tests/Browser/gradebook-workspace.js`, and new `tests/Browser/gradebook-workspace-real.js`. Existing navigation/asset assertions now verify selected-offering identity and the isolated stylesheet; no accepted interaction assertion was removed.

## Context, quick view and navigation

The Gradebook has one compact Tabler card header with an amber status strip, subject H1, blue classroom/term/year identity, violet subject code, lifecycle status badges and explicit writable/read-only text. It replaces the generic title, repeated classroom shell and separate metadata block on this screen. The shared shell and other screens are unchanged. Assigned teachers remain in their existing disclosure after the grid.

The native score disclosure shows active names/maxima and the authoritative item count/configured maximum. All values come from the existing `GradebookReadService` projection; components are fetched once, with no extra component/history query. The help disclosure retains the original guidance verbatim and adds accurate selection, historical/read-only and pending-save explanations. Its introductory line distinguishes direct typing from explicit edit. Quick-view freshness is explicitly page-load freshness; normal navigation reloads the server projection. No polling, client total calculation or grid refresh was added.

The toolbar links to teaching work, the authorized classroom overview, selected-offering Classroom Subjects and (with independent generic component-manage permission) score management. The selected destination is `/workspaces/classrooms/{classroomId}/subjects?offering_id={offeringId}`. It is emitted only when the existing authorized classroom projection includes that offering, the academic year matches, and the projection supplies a Subjects link. `ClassroomSubjectsReadService` uses this same scoped projection to authorize/filter its list. The destination independently rechecks current authority and offering membership. No user-supplied return URL is accepted. Component management falls back to the existing authorized `/gradebook/{offeringId}/setup` route if no selected destination is available.

Offering-scoped view/score permissions remain distinct from generic component management. A scoped teacher receives only their existing authorized offering destinations and no setup action; other-school/other-offering access and revoked scope still return 404. No permission, tenant, CSRF, audit, authentication, schema, mutation service or route contract changed. Task 3 remains the only contextual component editor.

## Objective before/after workflow

The baseline already showed names/maxima in grid headers, so reading a fully visible header required zero page transitions and still does. Long names can be clipped by the accepted column widths, and inspecting all components on mobile can require holder scrolling. We do not claim a transition reduction for that existing path.

For the baseline's separate full setup path, an authorized manager needed one click/page transition to inspect full names/maxima and another click/page transition to return to scoring (2 clicks, 2 transitions). The new disclosure needs one click to inspect and one to collapse (2 clicks, 0 transitions); the grid stays mounted throughout. Scoped teachers now have the same full-name disclosure without setup authority. Teaching work remains one link away; classroom overview and the selected offering are explicit toolbar destinations. Mobile actions wrap and retain text; score details list all active items without scrolling the grid sideways.

The same authenticated MAMP offering was measured at 900 px viewport height with contextual disclosures collapsed. Values are document coordinates of the first score row, not elapsed-time estimates:

| Width | Baseline first row Y | Task 4 first row Y | Document overflow |
| --- | ---: | ---: | --- |
| 390 | 1511.88 px | 916.82 px | None |
| 768 | 1100.36 px | 664.17 px | None |
| 1024 | Not measured | 664.17 px | None |
| 1280 | Not measured | 667.41 px | None |
| 1440 | 996.73 px | 671.73 px | None |

Mobile → desktop → mobile reproduced the same 390 px result. The mobile grid remains below the initial fold because the accepted range controls remain above it. Opening a contextual disclosure intentionally adds height; normal score interactions never open/close it.

## Accessibility, CSS and grid protection

One H1, ordered H2 sections, labeled navigation, visible action text, decorative pinned local Tabler Icons, native keyboard-operable summaries and inherited visible focus indicators. Status meaning is carried by text. No focus trap, focus-moving presentation script or framework was added. The disclosure retained summary focus after Enter collapsed it; a score save retained the next score's focus. Existing live regions and `aria-describedby="gradebook-guidance"` remain.

`gradebook-workspace.css` is loaded only by the scoring/selection asset partials. All selectors begin with task-specific context, toolbar, overview, help, component-list or feedback classes. It changes no Tabulator, editor, holder, sticky-identity or score-cell rules. Tabler 1.6.1 remains the single authenticated CSS foundation; no Bootstrap CSS or Tabler JavaScript was added. Global `tabler-app.css` and `app-compat.css` are unchanged.

The view suffix starting at the empty-component message through range controls, JSON bootstrap, Tabulator host, fallback table, score cells, row summaries and teachers is byte-identical to baseline. Preserved IDs include `gradebook-guidance`, `gradebook-range-status`, `gradebook-batch-status`, `gradebook-csrf`, `gradebook-grid-data`, `gradebook-tabulator`, `gradebook-range-actions`, `gradebook-range-summary`, `gradebook-fill-value`, `gradebook-fill-submit`, and `gradebook-clear-submit`. All `data-grid-*` markup remains. The grid stays outside disclosures and is never hidden/mounted/reinitialized by the new context. `gradebook.js`, `gradebook-grid.css`, vendor files, score endpoints/services, registry and Golden Journey expectations are unchanged.

## Regression evidence

- Focused Gradebook/read/authorization/Classroom Subjects PHPUnit: **125 tests, 2,514 assertions passed**. Updated navigation/cross-screen contracts: **69 tests, 1,425 assertions passed**. New workspace feature cases cover context, lifecycle, component counts/maxima/names, empty roster/components, escaping, single component query, scoped destinations, permission revocation, historical cells and DOM invariants.
- Final full PHPUnit: **3,089 tests, 70,300 assertions passed**. The first full run exposed three old navigation/asset shape assertions; semantic replacements passed the focused gate and the final full run.
- Registry validator: **124 accepted behaviors with unique IDs and unchanged mappings passed**. This is traceability validation, not a substitute for browser evidence.
- Chrome synthetic Gradebook suites: Tabulator 60 checks; read-only 12; races 29; batch-size limit 5; parity 33; direct entry 54; visual stability 64 (zero Gradebook-attributed layout shifts); workload 35×20 16 and 100×40 9. All **19 single/batch error scenarios passed**, including malformed/uncertain responses, revoked permission, CSRF, connection loss and pending-single queued clear.
- Gradebook layout matrix: **48 cases passed**, including writable/read-only, active/error, setup variants, empty roster/components and no-JS fallback at 390/768/1024/1440.
- **Golden Journeys A–M passed, 140 trusted browser checks** using actual pointer clicks/drags, keyboard and clipboard shortcuts. J–M used 2,000 ms fixture single-save response delay and confirmed serialization while writes were pending. F's independently read browser clipboard was exactly `\t0.00\n12.50\t6.00`.
- New focused trusted context journey: writable **9 checks** and read-only **10 checks passed**. It opens score details by pointer, collapses with Enter, verifies complete names/maxima, selects/navigates scores, writes/saves in the isolated writable fixture, reads save status, and checks unchanged page/holder/row/column geometry and target focus. The read-only run independently copied `0.00` and showed no setup action/editor. The separate synthetic context suite passed 11 checks per mode.
- Foundation, navigation, entry, administration, student workflow, confirmation and cross-screen browser suites passed. The cross-screen matrix includes classroom overview, roster, Subjects normal/limited/empty/read-only states, five-width coverage, no-JS variants and mobile → desktop → mobile. Confirmation passed enhanced and no-enhancement modes. Classroom/subject mutation/security tests remain in full PHPUnit.
- All first-party PHP lint (232 files), JS syntax (22 files), registry recheck and `git diff --check` passed. No test expectation was weakened to accommodate a spreadsheet regression.

Local reproducible evidence: `/private/tmp/pp5-task4-focused.log`, `/private/tmp/pp5-task4-contracts.log`, `/private/tmp/pp5-task4-full-final.log`, `/private/tmp/pp5-task4-browser-evidence.json`, `/private/tmp/pp5-task4-trusted-context.txt`, and `/private/tmp/pp5-task4-responsive.json`. Browser fixtures use the repository's `tests/Browser/*.php` PHP-server routers. The new context suites are selectable through `tests=workspace` and `tests=workspace-real` with `mode=editable` or `mode=readonly`.

## Authenticated MAMP review and limitations

Used the existing `admintestsc` session at `http://localhost:8888/gradebook/12`, classroom 47, วิทยาการคำนวณ4 (ว14201), term 1, year 2569. The page showed four active items totaling 50.00, with maxima 10.00/10.00/15.00/15.00 and the existing assigned teacher. Inspected 390, 768, 1024, 1280 and 1440 px; all context/toolbars stayed contained. Keyboard opened/collapsed score details. The management link successfully navigated to the existing selected-offering Task 3 panel. At 1440 px, adjacent score navigation kept document scroll at 203 px, holder scroll at 0/0 and all three row heights at 43 px.

No authenticated MAMP score or component mutation was attempted: existing records were not established as disposable. No second authenticated historical/read-only MAMP session was available. Writable mutations, read-only copy/navigation and historical handling were exercised in isolated browser fixtures and transactional `pp5_test` fixtures; those are explicitly separate evidence. No MAMP data/audit restoration was needed. Screenshots are `/private/tmp/pp5-task4-mamp-390.jpg`, `...-768.jpg`, `...-1440.jpg`, and `...-overview.jpg`.

Remaining UX constraints: accepted grid headers can truncate long names; the new disclosure supplies complete names. Mobile range controls retain their accepted vertical footprint. Score-structure changes require normal page navigation/reload. Engineering gates passed; **product-owner visual acceptance remains pending**.
