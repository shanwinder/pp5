# M6.5 Task 10 — งานสอนของฉัน

## Baseline and scope

- Started from clean `milestone/6-5-classroom-workspace` at `6522bef99eed285bd84700742c8c2d79c39e12e0`, matching fetched origin.
- Refined the existing `/gradebooks` route. No new route, write endpoint, migration, schema, seed, permission, or role-based production branch was added. Tasks 11 and 12 remain deferred.

## Read model and authorization

Before Task 10, `GradebookController::index()` asked `AppUiContextService::build('gradebooks', true)` for the already filtered offering list and rendered a flat technical table. This remains the single list query. `AppUiContextService` still builds the shell and performs live per-offering `GRADEBOOK_VIEW` checks. The controller passes only that authorized list to `GradebookReadService::teachingWork()`, which projects display capabilities. It does not load Gradebook rosters, scores, components, or teacher assignments. Direct Gradebook and setup routes independently revalidate their own permissions.

`canEnterScores()` centralizes the existing controller rule: ACTIVE offering, DRAFT or ACTIVE academic year, and live offering-scoped `GRADEBOOK_SCORE_ENTER`. It is used on both the landing and Gradebook view. Setup link visibility uses the same school permission as the canonical setup route, `GRADEBOOK_COMPONENT_MANAGE`, checked once for the request. It is independent of score entry. Closed-year and inactive setup pages are read-only, so the landing labels those links “ดูการเก็บคะแนน”. No capability result is cached across requests or trusted as a route gate.

The repository's deterministic order remains year descending, classroom code, subject code, term, offering ID. The view partitions current work first, then history, and groups each by academic year and classroom. DRAFT/ACTIVE years with ACTIVE offerings are current even for a personally read-only user. CLOSED years or inactive offerings appear under history. Term 1 and Term 2 retain separate rows and links to their own offering IDs. A scoped teacher sees only authorized offerings; broader authorized readers see their wider list. A zero-access SCHOOL user gets a 200 empty page, while the existing shell rule hides the teaching navigation item until an offering is accessible. SYSTEM context is still denied.

The page heading and shell link are “งานสอนของฉัน”. Row actions use “กรอกคะแนน” only when writable, otherwise “ดูคะแนน”; eligible current setup action is “ตั้งค่าการเก็บคะแนน”. “งานชั้นเรียน” uses the existing workspace route, whose resolver composes sections from live capabilities. Actions are native links with hidden classroom, subject, term, and year context. Section/year/classroom headings and text lifecycle states do not depend on color or JavaScript. The compact wrapping list is checked at 390, 768, 1024, and 1440 px.

## Verification and limits

- Focused landing tests cover scoped and multi-offering access, term separation, read-only current/history, setup/score independence, live revocation, direct denial, zero state, tenant scope, escaping, one offering-list query, and no roster/score/component/assignment reads.
- Focused landing PHPUnit: **16 tests / 304 assertions**. Final full PHPUnit: **3,063 tests / 69,806 assertions**.
- Synthetic entry browser matrix: **36 cases / 528 checks**, including current, multiple, read-only, historical, and zero-state landing variants at 390/768/1024/1440 px. Cross-screen matrix: **139 cases / 6,466 checks**. Both passed without document overflow or covered focus targets.
- Existing Gradebook browser regressions passed: navigation/range/copy/autosave **110 assertions**, paste **95**, fill **40**, fill limit **7**, read-only range **4**; Gradebook responsive matrix **44 cases / 740 checks**.
- PHP syntax: **217 files**, zero failures. JavaScript syntax: **16 files**, zero failures. `git diff --check` passed.
- Query pattern for the five-offering administrator fixture: one school offering list, five live VIEW checks, three live SCORE_ENTER checks for writable-lifecycle offerings, and one school setup permission check. There is no second offering list in the controller, no per-offering setup query, and no student-data query.
- Authenticated real-MAMP smoke requires credentials or a live authenticated session; synthetic browser fixtures do not establish that result.
- No manual screen-reader certification or multi-browser certification is claimed. Task 11 owns the broader cross-screen/accessibility/security hardening sweep, and Task 12 owns milestone finalization.
