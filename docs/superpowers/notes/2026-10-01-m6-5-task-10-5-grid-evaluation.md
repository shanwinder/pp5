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
