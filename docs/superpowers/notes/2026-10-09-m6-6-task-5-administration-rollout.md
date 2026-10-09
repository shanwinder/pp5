# M6.6 Task 5 — Dashboard and administration rollout

Engineering verification complete; product-owner visual acceptance **pending**. This wave does not start Task 6.

## Baseline and boundaries

- Repository: `/Applications/MAMP/htdocs/pp5`.
- Branch: `milestone/6-6-soft-government-ui`.
- Before editing, fetched origin, switched to this branch, verified clean working tree, HEAD and origin both `850302bea6c7cc2bfb76c526f5c737d66cf8c368`, ahead/behind `0 0`.
- Read the Tabler visual/interaction specification (including Task 1d decisions), contextual-workspace specification, Task 2/3/4 notes, M6.5 final verification, spreadsheet contract and Behavior Registry.
- PHP/server-rendered architecture, Tabler Core 1.6.1, pinned local vendors, current FastRoute/controllers/services/repositories remain intact. No schema, migration, permission code, mutation adapter, remote asset, build pipeline or framework added.
- Exactly one commit is intended: `style: roll out tabler administration surfaces`; push only the milestone branch. No PR or merge.

## Exact migrated templates

All paths below are relative to `htdocs/views/`:

| Domain | Templates |
| --- | --- |
| Dashboard, limited refinement | `dashboard/index.php` |
| School users | `admin/users/index.php`, `create.php`, `edit.php` |
| Academic years | `academic/years/index.php`, `create.php`, `edit.php` |
| Classrooms | `academic/classrooms/index.php`, `create.php`, `edit.php` |
| School subjects | `academic/subjects/index.php`, `create.php`, `edit.php` |
| Subject offerings | `academic/offerings/index.php`, `create.php`, `edit.php` |
| Teaching assignments | `academic/teaching-assignments/index.php` |
| System schools | `system/schools/index.php`, `create.php` |

Eighteen administration templates migrated, plus the existing dashboard refined. Shared changes are limited to `View::page()` stylesheet selection, administration selectors in `tabler-app.css`, and reuse of the existing native disclosure enhancement in `app.js`. Four test files change: `UiAdministrationTest.php`, `UiCrossScreenContractTest.php`, `ui-administration.php`, `ui-administration.js`.

Deferred templates/workflows are unchanged: `students/index.php`, `show.php`, `create.php`, `edit.php`; `academic/student-import/index.php`, `preview.php`, `steps.php`; `academic/enrollments/index.php`, `create.php`, `edit.php`; all classroom workspace templates; all Gradebook templates, grid/score setup/state machine/APIs; login/guest/error pages; future reports. Deferred pages retain their existing stylesheet choice. No registry or Golden Journey file changes.

## Presentation decisions

Dashboard uses its existing cards and authorized destinations. Hero padding and heading/section spacing are smaller; repeated descriptions and work-area subtitles are removed. School identity, classroom selector, teaching/Gradebook destinations and permission-composed management areas are preserved. No fabricated metrics or feeds.

Administration uses Tabler cards, card bodies/status accents, semantic `table table-vcenter table-hover`, native inputs/selects, form checks, alerts, badges, buttons and breadcrumbs. Blue accents mark general administration, cyan classrooms, amber years, violet subjects/teaching. Reading surfaces remain neutral.

List pages show title/context, one authorized primary action, optional compact year filter, table and contextual row actions. One action uses a compact named icon, with `title` plus the existing focus/hover tooltip. Offerings retain two compact icons for details and authorized score setup. Existing local `dots-vertical` and `notebook` SVGs are reused; no new vendor or icon dependency. Mobile icon targets are at least 44×44 px; desktop targets 36×36 px. Names identify the corresponding entity and SVGs remain decorative.

System-school and teaching-assignment status forms move into native `<details>` row disclosures. Closed rows expose one summary rather than a permanent form. Opening reveals the entity, consequence and actual POST controls. Enhanced disclosures are single-open; Escape closes and restores focus. Native Enter/Space toggling and authoritative forms survive failed enhancement. Teaching-assignment creation also uses a native disclosure, opened on server validation error; existing history, year filtering and reactivation endpoints remain intact.

Create/edit pages have a 48rem maximum width and grouped fields, explicit labels, required/optional meaning and concise return/save actions. User profile, membership status, roles and password reset remain four independent forms. Profile editing comes first. Read-only user views retain profile/membership/role information without unauthorized forms. System-school creation retains two fieldsets: school details and first administrator.

Routine explanations are removed. Essential constraints remain beside their controls: BE academic-year number versus Gregorian dates (BE−543), permitted date range and draft/activation rules; password minimum 12; unique school subject code; classroom/year relationship; offering year immutability. Thai `รายวิชาโรงเรียน` distinguishes definitions from classroom offerings. Status consequences stay visible before submission. Passwords are neither prefilled nor echoed.

## Permissions, forms and confirmations

Existing action URLs, methods, names/types/values, required/min/max/length rules, options, checkbox semantics, CSRF tokens and escaping remain unchanged. Existing HTTP/service tests cover valid/invalid/missing input, preserved safe values, lifecycle and read-only/revoked authority, tenant isolation and CSRF denial. Controllers independently enforce access; no role name is used to grant UI authority. Teaching status rendering explicitly checks the already-existing `TEACHING_ASSIGNMENT_MANAGE` permission as well as open year status.

The original system-school confirmation text remains unchanged. Year closure, classroom/subject/offering inactivation retain confirmation and visible consequence. Membership, role replacement, password reset and teaching status gain the existing `data-confirm` enhancement with specific static consequence text. The shared submit handler produces one prompt; it does not add another prompt on forms handled by existing HTMX confirmation. No optimistic success or new mutation occurs. With JavaScript unavailable the visible warning and native POST remain available; client confirmation cannot run, while server validation/permissions/CSRF still apply.

## CSS compatibility analysis

Authenticated layout order stays local Tabler foundation → PP5 tokens/presentation → compatibility CSS only when required. Migrated administration templates now join dashboard/classroom workspaces in the explicit `legacyStyles=false` list. No migrated page loads `app-compat.css` or a duplicate Bootstrap foundation.

| Category | Treatment |
| --- | --- |
| A: shared accessibility/layout | Keep tokens, skip link, shell/navigation, focus, tooltip, named scroll-region rules. |
| B: legacy compatibility | Keep `app-compat.css` for global students, import/preview, enrollments and legacy Gradebook bodies. |
| C: superseded administration styling | Migrated bodies replace PP5 form/surface/filter/alert presentation with Tabler. Do not delete shared legacy selectors still consumed by deferred screens. |
| D: domain-specific presentation | Preserve classroom workspace/import and Gradebook styles; scope administration density under `.pp5-admin-tabler`. |

`app-compat.css` is byte-for-byte unchanged: **20,409 → 20,409 bytes**. `tabler-app.css` is **9,295 → 11,021 bytes**, a 27-line addition (including spacing/comment), **1,726 bytes**. Zero global compatibility rules retired. No claimed CSS reduction percentage. Per-page compatibility dependency is reduced by the eighteen migrated templates; remaining selector/file retirement needs a separate proven migration.

The teaching history table keeps a 64rem minimum and readable teacher/classroom/subject columns inside its own scroll region. This avoids collapsing Thai text into very tall mobile rows. Other administration tables use a 40rem minimum. Actions and disclosures expand within the table, rather than floating over adjacent rows. All new density selectors are scoped to migrated bodies.

## Objective MAMP density measurements

Measured actual authenticated MAMP at viewport height 900 px, same account/data, baseline before editing and final presentation after. Numbers are CSS pixels, rounded to one decimal. First-row distance is from the viewport top (including the shared shell). Table scroll remains internal.

| Page | First row 390 before→after | First row 1440 before→after | Row height 390 before→after | Row height 1440 before→after | Action width 1440 before→after |
| --- | --- | --- | --- | --- | --- |
| Users | 284.2→257.8 | 267.8→265.4 | 46.6→53.0 | 46.6→45.0 | 292.7→60.0 |
| Years | 325.0→257.8 | 308.6→265.4 | 46.6→53.0 | 46.6→45.0 | 332.2→84.5 |
| Classrooms | 483.5→391.5 | 355.1→351.1 | 85.1→53.0 | 46.6→45.0 | 287.1→84.5 |
| Subjects | 284.2→257.8 | 267.8→265.4 | 46.6→53.0 | 46.6→45.0 | 442.4→84.5 |
| Offerings | 483.5→391.5 | 355.1→351.1 | 154.9→74.1 | 46.6→45.0 | 318.2→99.8 |
| Teaching history, unfiltered | 512.3→413.2 | 439.9→335.1 | 67.4→53.0 | 67.4→45.0 | 192.0→60.0 |

Dashboard's first classroom content moves **610.1→510.1** at 390 and **617.6→517.6** at 1440, with the same classroom content and usable controls. Its permanent explanatory paragraphs drop **5→1**. Routine list introductory explanations are removed; essential empty/lifecycle/filter instructions remain when applicable.

Mobile single-icon rows can increase 46.6→53 px because 44 px touch targets are preserved. This is deliberate, not a claim that every row shrank. Closed rows retain one management action (offerings two); teaching status selection/submission is revealed only on demand. The available user list is not an artificially large data set; long hostile Thai strings/emails/roles are separately tested in fixtures.

Final create/edit form measurements on ten actual route journeys at both widths show **366 px at 390**, capped at **768 px at 1440**, labels associated, no document overflow. Baseline form widths were not captured; no measured before/after form-width claim. Information hierarchy, independence of user forms and final form containment are verified directly.

## Responsive and accessibility evidence

Administration browser matrix covers **390/768/1024/1280/1440**, normal, empty and read-only lists, all create/edit surfaces, read-only user detail and failed-enhancement school/teaching disclosures. All **175 cases / 2,215 checks pass**. Native no-script navigation is additionally covered by existing cross-screen fixtures; `noenhance=1` specifically removes `app.js`, not the browser assertion harness, so it is reported as failed enhancement rather than full JS-off testing.

Checks include one H1/main, unique IDs, associated labels, semantic scoped headers, named keyboard-reachable scroll regions, escaped long Thai strings, named icons, stylesheet isolation, reachable actions, no document overflow, internal table scrolling and native disclosure/focus behavior. Status meaning is written in Thai as well as colored badges. Existing focus rings remain visible; actual keyboard focus displays the row tooltip. No new color system is introduced. This is browser/contract review, not a claim of a complete external accessibility audit.

MAMP representative pages were reviewed at all five widths and mobile→desktop→mobile. The required 390 and 1440 captures cover dashboard, users, years, classrooms, subjects, offerings and teaching assignments. Final mobile teaching disclosure is 288 px wide, remains inside the horizontal table scroll region, and Escape restores SUMMARY focus. Actual protected Students/Subjects/Gradebook pages at 390 and 1440 each have one H1 and no document overflow.

## Regression results

| Gate | Result |
| --- | --- |
| Focused administration/domain/dashboard/layout/navigation/assets PHPUnit | PASS: 770 tests, 16,285 assertions |
| Focused UI contracts | PASS: 81 tests, 2,446 assertions |
| Full PHPUnit | PASS: 3,092 tests, 70,413 assertions |
| PHP syntax | PASS: 227 first-party PHP files across app/views/config/tests/bin |
| JavaScript syntax | PASS: 22 first-party assets/browser JS files |
| `git diff --check` | PASS |
| UI foundation | PASS: 5 cases / 66 checks |
| UI entry | PASS: 36 cases / 528 checks |
| UI navigation | PASS: 6 cases / 96 checks |
| Administration matrix | PASS: 175 cases / 2,215 checks |
| Cross-screen | PASS: 173 cases / 5,265 counted checks plus resize pass; all five widths, protected workspaces, no-JS navigation and mobile→desktop→mobile |
| Confirmation / failed enhancement | PASS: enhanced 11 + failed-enhancement 7 checks, real isolated POST receiver |
| Student workflow | PASS: normal/read-only/empty/error/context workflows |
| Subject workspace | PASS: normal/limited/empty/read-only/context loading, close/focus return through cross-screen matrix, plus existing feature/HTTP suites |

One full run encountered an unchanged `ClassroomWorkspaceNavigationTest` assertion that searches the entire HTML for `2570`: a generated offering ID `582570` matched that substring. It was not leaked next-year content. The unchanged navigation suite passed on rerun (29 tests / 212 assertions), and the final full suite passed. No test was weakened or changed for that incidental identifier collision. Remaining test debt: scope that assertion to semantic year context in a future test-maintenance task.

### Gradebook full protection gate

- Behavior Registry validator: **124 unique accepted behaviors with test mappings**, PASS.
- Golden Journeys **A–M**, actual trusted pointer/keyboard/clipboard input: **13 journeys / 140 checks**, PASS. Counts: A10, B10, C5, D13, E11, F7, G6, H11, I11, J14, K14, L14, M14. Delayed J–M used `singleDelay=2000`, verified pending singles and queued batch completion with no rejected-ready state. A transient timing miss was rerun with live instruction/state checkpoints and passed; L's UI wait timed out but its eventual final PASS was observed.
- Clipboard distinction preserved: copied `\t0.00\n12.50\t6.00`; pasted `5\t0\n\t12.5`, verifying null versus explicit zero.
- Synthetic suites: editable 60; read-only 12; races 29; 2,001-cell batch-limit 5; parity 33; direct entry 54; visual stability 64; workload 35×20 16 and 100×40 9 — all PASS.
- Error suite: **19 scenarios / 201 checks**, PASS, including single/batch validation, unmarked/login/malformed responses, 409 and queued clear, 500, permission revocation, network loss and CSRF.
- Gradebook layout matrix: **48 cases**, PASS, writable/read-only/setup/error/empty/no-JS variants.
- Gradebook-attributed layout shifts: **0**. Overall fixture shift 0.006631 includes unrelated page layout, not attributed to Gradebook.
- No changes to Gradebook JS/CSS/templates, score endpoints, state machine, Tabulator vendor, registry or Golden Journey fixtures.

Browser fixtures are explicitly separate from authenticated MAMP. Matrix assertions may use synthetic events. Critical user-facing navigation/disclosure/filter/icon/keyboard journeys were additionally operated through real browser controls; synthetic checks alone are not claimed as trusted interaction evidence.

## Authenticated MAMP findings and limitations

Used the existing authenticated school administrator session (`admintestsc`) at `localhost:8888`. Dashboard→users→create, users→edit, four independent user sections, year list→edit, classroom/subject/offering list→create/edit and actual year-filter GET submissions were operated through links/controls. Offering create's actual year selection reveals the existing classroom/subject/term POST fields. Teaching creation/status disclosures, touch-width scrolling and keyboard tooltip/Escape behavior were verified. A membership confirmation was opened and cancelled; no mutation submitted.

No real user/school/assignment/enrollment/score data were created, changed or deleted for visual review. All mutation/CSRF/lifecycle testing uses isolated fixtures (PHPUnit transactional database fixtures or browser POST receiver), not MAMP live records. No password entered/reset.

No safe authenticated system account or separate read-only MAMP account was used. System-school status/create and read-only roles are verified in isolated production-template fixtures plus transactional HTTP tests; native keyboard failed-enhancement school disclosure was exercised directly. These are **not** presented as authenticated system/read-only MAMP evidence. A safe system account review remains a visual acceptance limitation, as permitted by the task.

During review the automated approval check rejected a broad browser-tab cleanup as potentially affecting unrelated user tabs. It was not executed. Only the specific isolated fixture tab created for review was later closed; other user tabs were retained. Responsive overrides are reset after review.

Local session evidence (temporary, not committed): `/private/tmp/pp5-task5-focused.log`, `pp5-task5-contracts.log`, `pp5-task5-full-final.log`, `pp5-task5-admin-browser-final.json`, `pp5-task5-mamp-after.json`, `pp5-task5-protected-smoke.json`, and `pp5-task5-mamp-*.jpg` captures. The final teaching row width fix supersedes its initial mobile measurement in the after JSON; final values are the table above and corrected teaching screenshots. No baseline screenshots were captured; baseline evidence is numerical measurement.

## Remaining migration and owner acceptance

Legacy student master/import/enrollment and Gradebook bodies still require compatibility styles. Retiring global legacy selectors, migrating deferred forms and reviewing contrast/visual preferences with school staff remain separate work. No additional rollout was attempted.

Record the product-owner feedback verbatim:

> Gradebook instructions and other nonessential explanations should not clutter daily work surfaces.

This is a **Task 6 final UX review follow-up**, not implemented inside Task 5. Gradebook help content remains unchanged. Product-owner visual acceptance of Task 5 is **pending**; passing engineering gates does not substitute for it.
