# Milestone 6.5 — Task 10.5: Grid engine evaluation spike

**Status:** isolated prototype comparison; human selection pending. Branch `spike/m6-5-spreadsheet-grid-evaluation` starts at accepted SHA `83d38139f166f7808730378d725d341d75597885`. No production migration, Task 10.6, Task 11, or Task 12 is included.

## Open and compare

MAMP is configured with `/Applications/MAMP/htdocs/pp5/htdocs` as its document root. Apache answered HTTP 200 on port 8888 during this spike.

- Comparison: <http://localhost:8888/spikes/grid-evaluation/>
- Tabulator: <http://localhost:8888/spikes/grid-evaluation/tabulator.html>
- Jspreadsheet CE: <http://localhost:8888/spikes/grid-evaluation/jspreadsheet.html>
- Append `?mode=stress` to either prototype for 100 rows × 40 score columns.

Manual sequence: (1) ลองคลิกและพิมพ์คะแนน 5, 0, 5.5; (2) ใช้ Enter, Shift+Enter, Tab, Shift+Tab และลูกศร; (3) ลากเลือก 3×3 รวมย้อนทิศ; (4) Cmd/Ctrl+C แล้วตรวจ TSV; (5) Cmd/Ctrl+V ข้อความ `5\t0\n\t12.5` ลงบริเวณคะแนน; (6) เลื่อนไปคอลัมน์ขวาสุด; (7) ดูว่าชื่อนักเรียนยังค้างด้านซ้ายหรือไม่; (8) ลองแก้ไข/วางที่แถว `ประวัติ ·`; (9) ย่อหน้าต่างเป็นมือถือ; (10) ตัดสินด้วยสายตาและความรู้สึกว่าตัวใดเหมือน Google Sheets / Excel Online มากกว่า. ช่องทดสอบพิมพ์ไทยอยู่ใต้กริด; ให้ลอง IME จริงใน browser ของผู้ใช้. ช่องว่างกับ `0.00` เป็นคนละความหมาย. ค่าที่เปลี่ยนในต้นแบบจะหายเมื่อ reload.

## Repository contracts read before implementation

Read the technical architecture v1.2, UI foundation M6, classroom workspace M6.5 Tasks 6–10, future assessment requirements, Gradebook view and JS/CSS, score-cell and row-summary fragments, read service, score controller, and score service. The present Gradebook is a server-rendered offering-specific matrix. Current and historical rows are both visible/selectable; only authorized current score cells have editable inputs. The read model provides `enrollment_id`, component `id`, nullable decimal score, and four server-calculated summary fields.

Single-cell write: `POST /hx/gradebook/{offeringId}/components/{componentId}/enrollments/{enrollmentId}/score` with `score` and CSRF token; successful UI state requires HTTP 200 plus `X-Gradebook-Saved: 1`, and the response supplies a score-cell fragment plus out-of-band row summary. Blank means `NULL`, distinct from `0.00`. An invalid typed value stays visible in the current UI. Batch write: `POST /hx/gradebook/{offeringId}/scores/batch` with CSRF and JSON `batch` containing exactly `component_ids`, `enrollment_ids`, `values`; server limit is 2,000 cells and 262,144 bytes. HTTP 200 plus `X-Gradebook-Batch-Saved: 1` and authoritative JSON cells/rows confirm success. A 409 after commit can mean the write succeeded but refresh failed; retry must not be automatic. Task 9 fill uses the same atomic batch service. Authorization, validation, lifecycle, audit, transaction, and totals stay exclusively on the PP5 server.

## Official source and license inventory

| Package | Exact source | Browser files retained | License/dependencies |
|---|---|---|---|
| Tabulator 6.6.0 | [Official release/tag `6.6.0`](https://github.com/tabulator-tables/tabulator/releases/tag/6.6.0), [official npm tarball](https://registry.npmjs.org/tabulator-tables/-/tabulator-tables-6.6.0.tgz), release commit `0ea06fa` | `vendor/tabulator/tabulator.min.js` 453,888 B; `tabulator.min.css` 28,383 B; `LICENSE` 1,082 B | [MIT](https://github.com/tabulator-tables/tabulator/blob/master/LICENSE); no runtime companion dependency used |
| Jspreadsheet **CE** 4.6.0 | [Official CE release/tag `v4.6.0`](https://github.com/jspreadsheet/ce/releases/tag/v4.6.0), [official npm tarball](https://registry.npmjs.org/jspreadsheet-ce/-/jspreadsheet-ce-4.6.0.tgz), release commit `4270c10` | `vendor/jspreadsheet-ce/jexcel.js` 561,932 B; `jexcel.css` 19,958 B; `LICENSE` 1,073 B | [MIT CE repository notice](https://github.com/jspreadsheet/ce/blob/master/LICENSE); requires jSuites 4.x. The npm tarball omits a standalone LICENSE, so the JS MIT header is preserved and the repository MIT notice is included alongside it. |
| jSuites 4.17.7 | [Official npm tarball](https://registry.npmjs.org/jsuites/-/jsuites-4.17.7.tgz) | `vendor/jsuites/jsuites.js` 404,895 B; `jsuites.css` 67,651 B; `LICENSE` 1,088 B | [MIT](https://github.com/jsuites/jsuites/blob/master/LICENSE) |

Total runtime vendor JS/CSS: Tabulator **482,271 B**; CE plus jSuites **1,054,436 B** (uncompressed, before HTTP transfer compression). Global objects: `Tabulator`; `jexcel`/`jspreadsheet` and `jSuites`. Both accept plain browser script/CSS without a production build or framework. npm was used temporarily only to obtain pinned official tarballs; no `node_modules`, package cache, formula package, or permanent Node requirement was added. Runtime assets are local.

The current npm CE 5.0.4 package declares `@jspreadsheet/formula` as a dependency. Because the [separate formula repository](https://github.com/jspreadsheet/formula) discusses premium licensing, and formulas do not matter for PP5, this spike deliberately pins the official CE 4.6.0 release whose npm dependency is only `jsuites:^4.0.0`. This is a **version scope limit**: observations do not establish CE 5 behavior. No Pro package, Pro extension, trial/evaluation license, paid formula engine, paid persistence, paid collaboration, key, or certificate was used. Any such capability is **NOT AVAILABLE IN THE EVALUATED FREE EDITION** and receives no decision credit. Formula and multi-sheet features are outside the decision.

## Fixture and presentation

Both pages load the same `fixture.js` generator. Normal: 30 current + 5 historical = 35 rows, 20 score columns, 4 read-only summary columns. Stress: 95 current + 5 historical = 100 rows, 40 score columns, 4 summaries. All student codes/names are synthetic Thai examples. Score items have short/long Thai names and varying maximum scores. Fixtures include blanks, literal `0.00`, integers, decimals, nearly complete rows, and sparse rows. Summary values are static fixture presentation values, not authoritative calculations after editing. Edits are browser memory only; reload restores the fixture.

The same PP5-like spacing, 30 px rows, thin rules, blue active outline, selected fill, fixed student identity, internal scrolling, distinct summary treatment, and textual `ประวัติ ·` cue are used in both. One standalone comparison page links to each. The same manual checklist and local-only diagnostics are on both pages. Tabulator uses a virtualized div grid. CE uses a table DOM and jSuites. Neither page links from production navigation.

## Neutral scorecard

| Criterion | Tabulator 6.6.0 | Jspreadsheet CE 4.6.0 |
|---|---|---|
| Spreadsheet visual feel | **Manual user decision** | **Manual user decision** |
| Frozen student identity | Yes; stayed at 19 px while internal scrollLeft reached 1,637 px | Yes (`freezeColumns:1`); stayed at 19 px while scrollLeft reached 1,801 px |
| Frozen header | Yes inside fixed-height grid | Yes with `tableOverflow` |
| Active cell / rectangular range | Visible outline/fill; drag 2×2 and keyboard right navigation observed | Visible outline/fill; drag 2×2 and keyboard right navigation observed |
| Reverse selection / 3×3 | Available for manual test; not separately automated | Available for manual test; not separately automated |
| Enter, Shift+Enter, Tab, Shift+Tab | Enter committed a local edit; full workflow requires human trial | Grid keyboard navigation observed; full workflow requires human trial |
| Copy | Public `copyToClipboard('range')` emitted exact 2×2 TSV (`1.5\t8.5\n5\t1.5`) through its `clipboardCopied` event. Automation's synthetic Cmd+C emitted `copy` without preceding keydown, so Tabulator's keybinding did not arm; manual shortcut verification required | Chromium synthetic Cmd+C returned exact 2×2 TSV (`5\t0\n\t12.5`) |
| Paste interception | Custom public paste action receives parsed row objects before mutation; prototype validates target types then mutates in memory | `onbeforepaste` receives raw TSV and coordinates; returning false blocked mutation on historical/summary targets |
| Read-only score cells | `editable(cell)` predicate blocks historical editor; paste action must also guard full target rectangle | `.readonly` cells block editor; `onbeforepaste` guards whole target rectangle |
| Summary columns | Four visible; editor off; paste guard rejects targeting | Four visible; `readOnly` columns; paste guard rejects targeting |
| Blank vs zero | 2×2 paste rendered `5`, `0`, blank, `12.5` in correct geometry | Same result; clipboard preserved geometry; fixture also retains literal `0.00` |
| Thai IME | Thai text field supplied; actual composition session not exercised | Same; actual composition session not exercised |
| Single-cell authoritative adapter | `cellEdited` fires **after** local mutation; snapshot/reconcile or custom editor needed | `onbeforechange` can transform value but is not a clean async cancel; `onchange` fires after local mutation; snapshot/reconcile needed |
| Atomic paste adapter | Public parser/action separation can defer mutation and send PP5 batch; moderate mapping work | `onbeforepaste` can stop native mutation and expose raw TSV/coords; strong precommit hook, but CE 4.6 rendering/reconciliation needs care |
| Fill | `selectableRangeFill:false` explicitly disables new 6.6 fill handle | Corner hidden, `autoIncrement:false`; Task 9 would remain an explicit PP5 batch action |
| Normal initialization, 1440 px | One observed run ~47 ms; ~775 rendered cells, virtual rows | One observed run ~39 ms; ~900 table cells |
| Stress initialization, 1440 px | One observed run ~37 ms; ~1,395 rendered cells | One observed run ~505 ms; ~4,545 table cells |
| Responsive | 390/768/1024/1440 px: no document horizontal overflow; internal grid scroll | Same; at 390 px the row-number gutter leaves less width for scores |
| Accessibility implications | `role=grid`/gridcell DOM and focusable cells; virtual rows require careful ARIA counts, labels and announcements | Native table DOM but headers are `td` and selected-range semantics are mostly visual; focus/labels need work |
| Vendor size | 482,271 B JS/CSS | 1,054,436 B JS/CSS including jSuites |
| Custom integration size | 59 adapter JS LOC; 17 engine CSS rules/lines + 36 shared CSS lines | 51 adapter JS LOC; 23 engine CSS lines + 36 shared CSS lines |
| Paid-feature dependency | None in evaluated package | None in evaluated 4.6.0 package; CE 5.x dependency/license ambiguity intentionally outside scope |

Initialization values are `performance.now()` snapshots, not a formal benchmark. No material conclusion follows from small normal-case differences. Stress CE made a full table; Tabulator rendered a viewport-sized subset. No obvious interaction lag was noticed in short manual navigation, but sustained scroll profiling was not done. The normal classroom case should dominate the decision.

## Future Task 10.6 adapter implications (only if chosen later)

The library must remain renderer + interaction engine: **grid → PP5 adapter → existing endpoints/services → authorization, validation, transaction, audit and database**. Neither prototype calls an endpoint. Client-side maximums, totals, lifecycle, and NULL normalization must never gain authority.

| Integration area | If Tabulator is selected | Risk | If CE 4.6.0 is selected | Risk |
|---|---|---|---|---|
| Bootstrap/read model | Render authorized server JSON into row objects; preserve fallback semantic table | Medium | Render same matrix to CE arrays; preserve fallback semantic table | Medium |
| Stable IDs | Keep `enrollment_id` on row and `component.id` on column, independent of visual index | Low | Maintain row/column ID maps outside CE coordinate model | Medium |
| Renderer initialization | Configure frozen identity, fixed height, virtual rows and score columns | Low | Configure jSuites, overflow, frozen identity and fixed columns | Medium |
| Single-cell edit | Capture old/typed value via custom editor or `cellEdited`; send existing single endpoint, await 200/header | Medium | `onchange` occurs after local mutation; capture previous value, send endpoint and reconcile | High |
| Authoritative response | Replace cell with server score and all four server summary fields; handle post-commit uncertain state | Medium | Use `setValue`/read-only summary updates without recursive change events; handle uncertain state | High |
| Validation errors | Preserve typed value in editor/overlay while presenting server error and focus | High | Same, likely custom editor or overlay due immediate CE mutation | High |
| Range and copy | Use range component; map only score columns; validate clipboard TSV, blanks and zero | Medium | Use CE coordinates/selection; map through stable ID arrays | Medium |
| Atomic batch paste | Public custom paste action can avoid speculative mutation; send Task 8 exact ID matrix, then apply server JSON | Medium | `onbeforepaste` can return false; parse raw matrix, send Task 8, then apply server JSON | Medium |
| Task 9 fill | Leave built-in fill disabled; explicit selected-range batch command | Low | Keep corner disabled; explicit selected-range batch command | Medium |
| Historical/read-only | Selection remains possible; `editable` plus paste guard, revalidate on server | Medium | `.readonly` plus pre-paste rectangle guard; revalidate on server | Medium |
| Summary columns | Noneditable, server-response-only writes | Low | `readOnly` columns, server-response-only writes | Medium |
| Focus/accessibility | Add labels, focus escape, state announcements, virtual row ARIA verification | High | Add semantic headers/labels, focus escape and range announcements | High |
| Fallback/read-only strategy | Keep server semantic table for JS failure/read-only | Medium | Keep server semantic table for JS failure/read-only | Medium |
| Test migration | Port Task 6–9 browser tests against adapter, retain service/HTTP tests | High | Same, with CE 4.6 and jSuites compatibility tests | High |

Both candidates optimistically mutate local state for ordinary single-cell edits. Tabulator's public paste action and CE's `onbeforepaste` can stop a paste before speculative UI mutation. A future adapter must not simply rely on `editable`/`readonly` for atomicity: it must reject the entire rectangle if any target is invalid, preserve typed input on validation error, and reconcile cells/summaries solely from authoritative server output. Stable enrollment/component ID mapping is straightforward in Tabulator data fields and possible with explicit CE coordinate maps. Server permission checks must still run at command time.

## Verification and limits

- Browser tested: Codex in-app **Chromium** at 390, 768, 1024 and 1440 px. Normal and stress pages loaded; no JS exceptions observed on final loads. Real Chrome, Safari, Firefox and Edge were not tested. No screen reader or WCAG certification was performed.
- Same fixture dimensions, visible values, 5 historical rows, four summaries, editable/current and non-editable/history were checked. CE rendered 35 rows; Tabulator virtualized rows, so only visible historical DOM rows are counted at a time. Double-click on history opened no editor in either engine.
- A 2×2 paste of `5\t0\n\t12.5` updated current rows in memory with the intended geometry in both engines. The same paste at a historical target left values unchanged. CE copy returned exact TSV; Tabulator's public copy API emitted exact 2×2 TSV. Tabulator synthetic shortcut limitation is stated in the scorecard; manual browser verification remains needed.
- Horizontal keyboard navigation scrolled both internal grids to summaries while the identity cell remained fixed. The header remained aligned. The document itself did not overflow at any tested viewport. Mobile simple score cells remained inspectable; touch editing was not tested.
- Local MAMP comparison URL and the six Tabulator/eight CE runtime resource URLs were verified; all resource URLs had the `localhost:8888` origin. Static source scan found no external script/link/import/font runtime tags. A literal network-disconnected browser run was not available, so offline operation is supported by local assets but not separately exercised with the network interface disabled. No production API request appears in prototype source; no such request was observed in the browser check.
- Thai text is present in headers/names and a separate text input accepts Thai; a genuine OS IME composition sequence was **not** exercised. Focus escape with Tab/Shift+Tab from deep inside the grid was not conclusively verified; both engines require Task 10.6/11 focus review. Range state is mostly visual and needs screen-reader design.
- Representative screenshots were visually captured in the in-app browser for both engines at 390, 768 and 1440 px and for a horizontal-scrolled/selected state. The browser tool did not provide a repository file export, so screenshots are **not committed or linked as local image artifacts**. The comparison page remains available for manual inspection.
- `node --check` passed for the three spike JS files; `git diff --check` and final Git checks are recorded at commit. No production/shared PHP, Gradebook JS/CSS, route, endpoint, service, schema, permission, audit, or navigation file was changed. No database, real score write, migration, test user, or PII was used. Full PHPUnit was not run because production files were untouched. No browser profile, npm cache, archive, or `node_modules` is committed.

**Decision remains with the user.** The visual/interaction preference after manually opening both pages determines whether either engine merits Task 10.6 before Task 11. This spike makes no overall numerical ranking or winner selection.

---

# Task 10.5b — Normalized PP5 Interaction

**Status:** second isolated spike on `spike/m6-5-spreadsheet-grid-evaluation`; human evaluation pending. The first-spike observations above remain intact. Starting spike SHA `169b61355aaff46099113d089c8a4a81aea75b13`; both local and origin production milestone refs were checked at `83d38139f166f7808730378d725d341d75597885` before changes.

## Why another comparison was necessary

A human found that the original Tabulator page required double-click or Enter before typing, while CE accepted typing after selecting. The first Tabulator adapter deliberately set `editTriggerEvent:'dblclick'`, so that observation conflated our setup with library capability. Both original HTML/JS pages remain available as **baseline round 1**; the comparison page now directs the human to separate **normalized round 2** pages. Neither library is selected here.

## Shared teacher interaction contract

- Click one score cell to select it without opening a caret. Printable typing replaces the old value and opens an editor immediately; `0` remains zero, blank remains blank. The display keeps the user's literal input in both pages; no local two-decimal formatting is imposed.
- Double-click, Enter or F2 opens the current value for caret editing. Inside the editor, Left/Right and Shift+Left/Right retain native caret/text-selection behavior. Up/Down commits and moves to the previous/next current student in the same score column. Enter/Shift+Enter commits down/up. Tab/Shift+Tab commits right/left. When there is no eligible score at the Tab edge, focus goes to the IME field (right) or comparison link (left). Escape cancels the transient edit.
- When selected but not editing, unmodified arrows navigate current editable score cells; Enter opens the current value; Tab moves score columns and exits at an edge. Shift+Arrow remains the engine's range extension. Pointer rectangular selection, including reverse 3×3, remains available. Pointer selection can include identity, summaries or history, but those cells are not writable. A click collapses the range; typing after a multi-cell range uses its active end and then the editor targets one cell.
- Historical score cells remain selectable/copyable but cannot start an edit or receive paste. Four summaries and student identity are read-only. Rapid vertical entry skips historical rows. Both pastes validate the entire target rectangle before local mutation; a target outside current score cells blocks the whole paste.
- Plain Cmd+C/Ctrl+C copies an explicitly selected range. Plain Cmd+V/Ctrl+V accepts the `5\t0\n\t12.5` matrix and preserves blank and zero. Ctrl/Cmd, Alt, Meta, navigation keys, function keys, Enter and IME composition are excluded from replace-style type-to-edit. No optimistic invalid-target paste is allowed.

## Official API and CE 5 research

- [Tabulator 6.x range documentation](https://www.tabulator.info/docs/6.x/range/) describes one-cell focus, Shift+Arrow extension, double-click editing and the edit/navigation conflict. [Editing docs](https://www.tabulator.info/docs/6.x/edit/) document the `editTriggerEvent` modes and public custom-editor `success`/`cancel` callbacks. [Cell component docs](https://www.tabulator.info/docs/6.x/components/) document `cell.edit()`, `cancelEdit()`, `navigateUp/Down/Left/Right`, `getStructuredCells()`, `addRange()` and range removal. [Keybinding docs](https://www.tabulator.info/docs/6.x/keybindings/) list navigation/copy defaults and binding overrides. In the **vendored 6.6.0 runtime**, `range.getBounds()` supplied internal bound objects rather than the cell components described on the docs page. The normalized adapter therefore uses documented `getStructuredCells()` and no internal object access. The public `navigate*` functions move focus among editable cells but open editors under the library's editing model; this adapter uses explicit score-column/row lookup and public `addRange()` to keep selected and editing states distinct. The vendor prints its existing frozen-column/range-header compatibility warning; it did not stop the tested interaction.
- The [official CE repository package manifest](https://github.com/jspreadsheet/ce/blob/master/package.json) identifies current CE **5.0.4**, with MIT in repository source and dependencies `@jspreadsheet/formula:^2.0.2` and `jsuites:^5.12.0`. The [official CE upgrade guide](https://bossanova.uk/jspreadsheet/docs/upgrade-from-v4-to-v5) describes the workbook/worksheet split and selection/event signature changes. [CE 5 editor docs](https://bossanova.uk/jspreadsheet/docs/editors) describe `openEditor` and `closeEditor`; [events](https://jspreadsheet.com/docs/v5/events) describe edition, before-change/change, selection and pre-paste hooks. Core CE plain-script examples do not require Pro, but v5 would require a separate adapter for its worksheet instance architecture.
- The [Formula Basic source repository](https://github.com/jspreadsheet/formula-basic) calls Formula Basic MIT, while the separate [Formula Premium repository](https://github.com/jspreadsheet/formula) describes a domain license for its premium build. Crucially, exact published npm `@jspreadsheet/formula@2.0.2` metadata has **no `license` field**; `npm pack --dry-run --json` lists only `dist/index.js` and `package.json`, with no license notice. The source repository manifest found during research is 2.0.1, not the exact 2.0.2 artifact. That gap prevents an unambiguous redistribution finding for the required CE 5 dependency. No CE 5 runtime or formula package was vendored or used. The normalized CE candidate remains the pinned CE **4.6.0** plus jSuites **4.17.7**, whose MIT notices and exact local assets are inventoried in round 1. No Pro, trial, key, premium formula, commercial persistence or server package was used. The exact CE 5 package license/dependency question remains open for any future version upgrade, independent of the human interaction choice.
- [CE 4 quick reference](https://bossanova.uk/jspreadsheet/v4/docs/quick-reference) documents `openEditor`, `closeEditor`, `updateSelectionFromCoords`, `resetSelection`, copy and paste events; the adapter uses those public methods. The vendored CE 4 keyboard source was inspected as behavior evidence: it uses a document-level key handler, naturally opens a text editor on printable input, and handles unmodified arrow/Enter/Tab itself. Our capture listener only takes the PP5-specific transitions before that handler. The editor input is real DOM focus.

## Normalized implementation findings

| Topic | Tabulator 6.6.0 | Jspreadsheet CE 4.6.0 |
|---|---|---|
| Type-to-edit | Custom capture handler for printable keys calls public `cell.edit()`; custom input starts with the triggering character. `editTriggerEvent:'dblclick'` remains solely for **explicit** existing-value editing and no longer determines whether typing works after single selection. | Native printable-key edit after click; only diagnostics are added for this path. |
| Explicit edit | Native double-click/Enter plus public `cell.edit()` for selected Enter/F2; custom editor retains the current value and caret. | Native double-click; public `openEditor(cell,false)` for Enter/F2. |
| Vertical edit navigation | Custom editor key handler invokes `success`, then public range selection of the adjacent eligible current score cell. | Capture handler invokes public `closeEditor(cell,true)` then `updateSelectionFromCoords`. |
| Left/Right while editing | Custom input leaves these keys untouched. | Capture handler leaves these keys to the native input. |
| Enter/Shift+Enter | Custom editor commit down/up. Selected Enter opens current value. | Public close/select down/up. Selected Enter opens current value. |
| Tab/Shift+Tab | Custom editor commit and move right/left; at score boundary focus explicitly exits. | Public close/select and the same explicit focus exit. |
| Escape | Custom-editor `cancel()` restores old value; selected Escape clears range. | Public `closeEditor(cell,false)` restores old value; selected Escape resets range. |
| Range and copy | Built-in pointer/Shift+Arrow range remains. A root `copy` listener writes TSV from public `getStructuredCells()` so real keyboard Cmd/Ctrl+C works, including blank cells. | Built-in range and keyboard clipboard behavior remain. |
| Paste | Public `clipboardPasteParser:'range'` and custom paste action inspect all target rows/fields via public components before local `row.update()`. | `onbeforepaste` examines raw TSV and coordinates and returns `false` for any invalid target before native mutation. |
| IME | `compositionstart/end` and `isComposing` guard input/grid keys; editor key handler declines Enter/arrows during composition. | Grid capture handler declines its own work and stops CE's document-level Enter/arrow processing while an editor is composing, without cancelling browser composition. |

The Tabulator editor is a deliberately small local `<input>` supplied through its documented custom-editor contract, not a vendor patch. The CE adapter does query the library-rendered `td[data-x][data-y]` to invoke `openEditor` for selected Enter/F2 and uses `cell.dataset` from the public edition event to locate a target; that is a DOM structure dependency. Tabulator's adapter queries only its own editor/input and public component/range objects for interaction. Neither normalized adapter modifies or monkey-patches vendor files, calls an undocumented library method, or uses Pro functionality. There is no event-bus/internal-module access in normalized code. Both intercept DOM `keydown` in capture phase because native edit navigation conflicts with PP5's Down-to-next-student contract; this event suppression is an integration cost, especially for future IME and accessibility review.

**Adapter size** (`wc -l`, includes logging, formatting, and baseline prototype setup): Tabulator baseline **59** → normalized **145** LOC; CE baseline **51** → normalized **118** LOC. Tabulator has two custom keydown handlers (grid and custom editor), one custom copy handler, and eight subscribed library events (`tableBuilt`, `rangeAdded`, `rangeChanged`, `cellClick`, `cellEditing`, `cellEdited`, `cellEditCancelled`, `clipboardPasted`). CE has one custom grid keydown handler and seven configured lifecycle/data hooks (`onselection`, `oneditionstart`, `oneditionend`, `onchange`, `oncopy`, `onbeforepaste`, `onpaste`), plus `updateTable` styling. Both have composition listeners and the shared IME test field. LOC alone is not a library decision.

## Browser verification and limits

The Codex **in-app Chromium** browser exercised both normalized pages on local MAMP Apache (HTTP 200). Results observed:

- Click existing score → `5` immediately opened an editor with `5`; Down committed and selected the row below. Repeated `5 ↓ 6 ↓ 7 ↓ 8 ↓` filled four adjacent students in one score column in both engines, with focus remaining usable. Typing `5.5` in an editor produced literal `5.5`; typing and committing `0` produced visible `0` in both, distinct from blank.
- Double-click retained an existing value for caret editing in both. Left/Right did not navigate out of the editor; Tab committed and selected the right score. Selected Enter opened the current value; Shift+Enter committed and moved up. Escape cancelled an attempted replacement and restored the old value. Right boundary Tab focused the IME field for both; left boundary Shift+Tab focused the comparison link for both in a fresh normal-view check.
- Shift+Right then Shift+Down produced a 2×2 range. Real browser Cmd+C returned exact TSV `1.5\t8.5\n5\t1.5` in both. Pointer 3×3 and reverse-direction selection copied `5\t\t6.5\n6\t8.5\t10\n7\t1.5\t3`, preserving the blank. Browser Cmd+V of `5\t0\n\t12.5` produced the exact 2×2 geometry in both. A historical target blocked typing and whole-matrix paste in both, leaving its value unchanged; a summary target also remained unchanged.
- Normal page loads had 35 rows × 20 score columns; stress pages 100 × 40. One observed stress load gave Tabulator ~39 ms with ~1,170 rendered cells and CE ~416 ms with ~4,545 DOM cells. Twelve repeated type/Down cycles completed in each stress page without visible delay or lost focus. These are observational snapshots, not formal benchmarks. Horizontal scroll and sticky student identity remained visible in visual checks. At 390/768/1024/1440 px, document horizontal overflow was false for both; the wide matrix stayed in internal scrolling.
- Fresh final normal-page browser console checks showed zero JS errors for both. During development, a Tabulator `getBounds()` assumption caused errors and was fixed by switching to public `getStructuredCells()`; those pre-fix console entries are not counted as final verification. The vendor's frozen-column/range warning remains. The browser was the Codex in-app Chromium environment; real Chrome, Safari, Firefox, Edge, touch, screen reader and WCAG certification were not tested.
- JS syntax checks, HTTP 200 on comparison/normalized pages and assets, local-only script/style scan, and Git diff checks are part of the final verification gate below. The spike source contains no fetch/XHR/HTMX/Gradebook endpoint invocation. No production API request was observed during browser interaction. No DB or real score writes occurred. Static summaries intentionally remain fixture values after edits; only the future PP5 server may authoritatively recalculate them.
- Thai composition safeguards are implemented and instrumented (`ACTIVE`, `EDITING`, `COMPOSING`) but **a genuine macOS Thai IME session was not exercised**. Browser key synthesis cannot establish IME correctness. A human should switch macOS input source to Thai, select a score cell, open the editor, type Thai in the grid and dedicated IME field, press composition-relevant keys, and confirm no premature commit/navigation. The prototype does not claim actual Thai IME success. IME text is a transient interaction test, not a valid production numeric score.

## Future PP5 adapter implications and open decision points

Interaction normalization does not change the production contract. Future Task 10.6, only if separately approved after human comparison, must map a visual score cell to stable `enrollment_id` and `component.id`; use existing single/batch endpoints; wait for HTTP success and authoritative response; reconcile server score and all summaries; preserve invalid typed text without treating it as saved; avoid automatic retry of ambiguous 409; intercept the whole paste atomically; maintain selected-but-not-writable history and permission revalidation. Tabulator's data rows carry stable IDs directly and public paste action can defer mutation. CE 4 coordinates require explicit stable ID arrays, while `onbeforepaste` can stop mutation. Ordinary single edits in both remain local optimistic prototype edits and would require a new async state layer. Neither formula engine nor worksheet tabs carry decision weight for PP5.

The remaining human decision points are: actual teacher feel for continuous entry and existing-value edits; manual Thai IME behavior; usability of range selection and copy/paste in the user's real browser; focus and screen-reader semantics; and whether the CE 5 dependency license can later be established for exact artifacts. Do not choose a winner or start Task 10.6, 11 or 12 from this spike alone.

### Round 2 URLs

- Comparison: <http://localhost:8888/spikes/grid-evaluation/>
- Tabulator normalized: <http://localhost:8888/spikes/grid-evaluation/tabulator-normalized.html>
- CE 4.6 normalized: <http://localhost:8888/spikes/grid-evaluation/jspreadsheet-normalized.html>
- Add `?mode=stress` to either normalized URL for 100 × 40. Original `tabulator.html` and `jspreadsheet.html` remain labeled baseline pages from round 1.
