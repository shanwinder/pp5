# M6.6 Task 6S — Clean Gradebook roster and repeating paste

Date: 2026-10-11. Branch: `milestone/6-6-soft-government-ui`.
Required baseline / local HEAD / verified remote branch: `f94a486c97679f4d1774004bb0aa40289ea06191`.

**Mandatory release gates: PASS.** Final authenticated MAMP Network proof closed the previous blocker: fresh Gradebook GET 200, fresh current Task 6S JS/CSS GET 200, and **zero POSTs** throughout the recorded native nonmutating actions. Actual DOM proof confirms the same explicit-editor input and caret 4→3→4, followed by Escape returning to its score cell. Production code is unchanged from the tested snapshot. The final staged review may now precede the user-authorized single Task 6S commit and push. Product-owner visual acceptance remains **PENDING**.

## Approved scope and implementation

The attached Product Owner request approves roster separation, removal of help/Fill/Clear UI, retained batch/keyboard Clear, repeating paste into larger selected rectangles, and complete rejection of insufficient multi-cell destinations. Those decisions override only conflicting prior roster/UI/paste assumptions.

Old visible columns: combined student identity, row type, unchanged score topics, total/max summary.
New visible columns in Tabulator and semantic fallback: `ลำดับ | เลขประจำตัว | ชื่อ-สกุล | [unchanged score topics] | คะแนนที่บันทึก`.

Ordinal is one-based display order, including virtual/historical rows; stable enrollment IDs remain write keys. Student codes are strings (leading zeros retained), and full Thai names/prefixes remain. CURRENT/HISTORICAL and enrollment-status metadata stay in the read model and DOM. Historical rows retain a quiet background and accessible `แถวประวัติ อ่านอย่างเดียว` row/score identity; eligibility, navigation, copy and server protection remain.

Only the first contiguous 56px ordinal column is frozen. Code/name scroll internally; fitColumns and all score topics remain. This leaves a small frozen block at 390px. The layout/resize matrices verify actual iframe CSS viewport widths, practical column minima, scrolling, score focus, selection and input/caret preservation. An attempted native viewport capability override did not change the authenticated tab's actual CSS width (1587px), so **no native 390px authenticated viewport PASS is claimed**.

Help/range-tool controls and the Fill/Clear form are absent rather than hidden keyboard stops. `tools.php` is comment-only and is no longer rendered by context. Visually hidden `gradebook-guidance` and live save/error/range feedback remain. Optional legacy Fill/Clear handlers and shared atomic batch infrastructure remain guarded by form presence; they provide no user-accessible Fill button. Delete/Backspace Clear remains. Dormant error text now describes keyboard Clear rather than a removed button.

`planPaste()` accepts explicit destination dimensions and reuses `planRectangle()` for writable/stable-ID/unique-target checks. One selected cell expands to the original source footprint once. Multi-cell targets must be at least as large in each dimension; valid targets are filled by `source[row % sourceHeight][column % sourceWidth]`, including partial right/bottom tiles. Complete expansion and the existing 2000-cell limit precede transport. Literal blanks and zero variants remain distinct. Exactly one existing atomic batch endpoint is used. Single-save serialization, server-authoritative validation/reconciliation, 409/uncertain locks and late-response focus ownership are retained. No schema, endpoints, permissions, scoring services, frameworks or renderer/vendor version changed.

## Behavior registry and history

The baseline comparison retains all 124 original IDs; **113 original records are byte-equivalent as parsed records**, including their mappings. No ID was removed. The approved changed records are:

| IDs | Disposition |
| --- | --- |
| GB-FILL-001–005 | `retired_ui`; full original accepted record/mapping preserved under `retirement.original`; current evidence proves retirement, never a button Fill workflow PASS |
| GB-PASTE-002/003 | Entire selected target receives the repeated matrix |
| GB-PASTE-011 | Existing limit applies to expanded destination |
| GB-SEL-004 / GB-NAV-006 | New ordinal/code/name non-score roster columns |
| GB-VIS-009 | Approved small frozen ordinal block |
| GB-PASTE-012–017 | Six new accepted tiling, insufficient-target, expanded-limit, focus and blank/zero/editor records |

Registry totals: **130 records, 125 accepted, five retired UI**. Validator checks required IDs, retired-record decision/history/replacements, focused mappings, Golden coverage and unchanged vendor hashes. Original retirement records were preserved rather than deleted. COPY/RANGE/CLEAR/DIRECT/EDIT/server/permission rules and unaffected mappings remain unchanged. The historical Task 6R clarification is retained; the explicit Task 6S section states its narrow supersession.

Golden G now tests removed UI, scalar copy/native repetition, reverse partial-edge repetition, NULL/zero patterns, insufficient-target rejection and immediate typing; it does not claim button Fill works. H follows the ordinary toolbar Tab order and preserves historical copy/navigation/denial. Other Golden journeys retain their interactions and assertions.

## Results and evidence classes

Evidence is local under `/private/tmp/pp5-6s-20261011/` and `/private/tmp/pp5-6s-e2e-20261011/logs/`. These are task-owned temporary artifacts, not a substitute for a checked-in release decision.

| Gate | Result |
| --- | --- |
| Registry | PASS — 130 IDs / 125 accepted / five retired; all original IDs retained |
| Synthetic focused/error/race/parity/workload/tiling/visual | PASS — 43 cases, **889 checks**, including 27 error cases / 312 checks |
| Synthetic layout | PASS — 60 mode/width cases, **1108 checks** (390/768/1024/1280/1440px; writable/read-only/setup/empty/no-components/no-JS states) |
| Synthetic live resize | PASS in isolated single-iframe runs — 0/1/2/4/20 components, **1099 checks** |
| Trusted Golden A–M | PASS — **157 checks** |
| Trusted additional native tiling | PASS — **43 checks** |
| Trusted workspace writable/read-only | PASS — 10 + 11 = **21 checks** |
| Trusted live feedback writable/read-only | PASS — 8 + 8 = **16 checks** |
| Focused PHPUnit | PASS — **727 tests / 16328 assertions** |
| Full PHPUnit | PASS — **3106 tests / 70732 assertions**; final rerun recorded below |
| PHP/JS syntax | PASS — 22 changed/new PHP/JS files; final rerun recorded below |
| git diff --check | PASS; final rerun recorded below |
| Authenticated disposable runtime | PASS for the recorded native write/rejection cases and actual DB/server evidence below |
| Existing authenticated MAMP | PASS — fresh GET 200 positive control; current assets delivered over network; continuous recording shows zero POSTs after native selection/range/copy/navigation/cancel; actual same-input/caret/DOM proof below |
| Native authenticated 390px viewport | Unverified — override did not change actual CSS viewport; synthetic 390px matrix is separately passing |

Golden counts: A 10, B 10, C 7, D 13, E 11, F 7, G 21, H 11, I 11, J 14, K 14, L 14, M 14. J/K observed one pending single at Clear; L/M observed three; each had nine selected cells and no editor at the command, one batch only after all prior singles resolved, zero ready rejections, final top-left focus and nine selected cells. Trusted copy read exact scalar `8.00` and 2×2 TSV; native paste covered 3×4 scalar, 4×5 partial-edge/forward/reverse, immediate typing, blank/zero, insufficient target with no request, and explicit-editor text paste without range batching.

Synthetic visual measurements: desktop 64 checks, layout shift 0.003920269298161009 (Gradebook sources 0.00023735639574759944); narrow 64 checks, layout shift 0.0044615619260789715 (Gradebook sources 0). These measurements belong to response fixtures, not existing MAMP.

Expanded-limit test selected virtual row 51 across 40 score columns (2040 target cells) and rejected a 1×1 source without POST; displayed ordinal 51 remained correct. An initial harness failure held a stale pre-render DOM element; re-resolving the rendered endpoint corrected the fixture without changing application behavior or lowering assertions.

The original multi-iframe resize harness produced failures in the one-component case (timeout at synthetic score-click focus, and one run lost editor focus while other browser actions were active). A single iframe with the **same test file and every original assertion** passed 269 checks. Each component count was then executed separately, preserving the complete width/selection/editor/save coverage: 0=44, 1=269, 2=269, 4=263, 20=254. Those isolated PASS results are the matrix evidence; the original combined harness failures are retained and not relabeled PASS. The sequential general suite also required exact frame URL matching to avoid reading a prior iframe's completed result.

## Authenticated disposable database proof

Runtime: Apache + MAMP PHP CGI on `127.0.0.1:18917`, isolated document root `/private/tmp/pp5-6s-e2e-20261011/htdocs`. Database `pp5_gradebook_e2e_6s_20261011`, restricted DB principal `pp5_6s_20261011@localhost`, separate session name/path, one synthetic school `6S-E2E`, five synthetic students and two synthetic users. No live student/school records are disposable fixtures.

Per-request isolation guard verifies the actual Application class path, configured/current database, principal and document root before HTTP application execution. The initial vendor symlink incorrectly resolved Application to the repository; no score writes were attempted then. It was replaced by a real vendor copy, Apache restarted, and class/database proof confirmed before browser writes. Missing copied vendor assets were repaired inside the owned runtime. No live data was modified for fixture setup.

Native authenticated actions produced exactly these score requests in owned Apache access logs:

| Operation | Requests / status |
| --- | --- |
| Scalar 8 → one cell | One batch POST, 200 |
| External 2×2 → single origin | One batch POST, 200 |
| 2×2 → selected 4×5 | One batch POST, 200 |
| Reverse 4×5 blank/zero pattern | One batch POST, 200 |
| Tiled source containing 21 above max 20 | One batch POST, 422; no partial display/DB change |
| Too-small 1×2 destination | No additional POST |
| Mixed current/historical destination | No additional POST |

Final server count: **five score POSTs, all batch; four 200 and one 422; zero single-score POSTs**. Browser retained full target and top-left focus after valid paste. The 4×5 numeric pattern yielded authoritative row totals 42/95.5, 41/95.5, 42/95.5, 41/95.5. After NULL/zero repetition the DB contained eight NULLs, ten actual `0.00` values and two `8.00` scores in current rows. Historical row five retained all five `1.50` scores. Browser totals were 0/95.5, 8/95.5, 0/95.5, 8/95.5, historical 7.5/95.5. The invalid batch preserved these values. Native explicit edit at offering 2 retained value `1.50`, score field and caret 3 during the attempted viewport override; no native narrow-width result is claimed.

## Existing MAMP nonmutating evidence and resolved Network blocker

Existing authenticated page: `http://localhost:8888/gradebook/12`. Updated roster headers and absence of help/range form were inspected. Native single click + F2 opened `1.00` at `score_5`; Left moved collapsed caret 4→3; Right restored 3→4 with one input and unchanged value/score field. Escape returned to a score, Right navigated to `score_6`, and native Copy returned `5.00`. Scores and total/max summaries remained unchanged (7/50, 9/50, 13/50). No scores were intentionally entered, cleared or pasted on this existing page.

Earlier attempts were incomplete: a zero-match POST filter preceded the complete sequence, and foreground-window changes prevented a final capture. Those attempts were not treated as PASS. The Product Owner subsequently authorized only the foreground, verified PP5 Gradebook window and confirmed readiness. The final proof below supersedes this blocker; the existing-score write prohibition remained in force.

## Git safety and delivery scope

At the completion of the test audit the index was empty and no commit/push had occurred. Delivery is limited to one Task 6S commit and one push after the final staged review. Only the named milestone branch is authorized; no PR/merge/next task is included. Remote baseline verification initially required approved network access because sandbox DNS was unavailable; the returned branch SHA matched the required baseline. The pre-existing untracked Task 5R report is preserved with SHA256 `e92361caa761f2da5a84ab9d6e79d933a50130a1f759abf52249b0757e49ad58` and must not be staged as Task 6S work.

The mandatory Network gate is now closed. Native 390px evidence remains unclaimed; the passing synthetic 390px matrix is separately identified. The original multi-iframe harness limitation remains documented and its isolated runs retain all assertions. Product-owner visual acceptance remains PENDING. No next task is started.

## Final checks and cleanup

Final full PHPUnit rerun after the last edits: **PASS, 3106 tests / 70732 assertions**, 1:41.867 (`logs/full-phpunit-final.txt`). Final syntax: **PASS, 22 files**. Final registry: **PASS, 130 IDs**. Every retired `retirement.original` compares exactly to its baseline record. Final `git diff --check`: PASS. Branch and HEAD remain exactly the required milestone branch and baseline SHA. Task 5R checksum remains unchanged.

Owned PHP fixture server and isolated Apache server stopped with successful process exits. Ownership-guarded cleanup removed only `pp5_gradebook_e2e_6s_20261011`, its restricted database principal, the one runtime session file, disposable credentials and private local configuration. Cleanup verified database removal and retained evidence logs. No live school/student records were fixture cleanup targets. Other prior task artifacts were preserved.

Screenshot of the updated authenticated synthetic roster: `/private/tmp/pp5-6s-20261011/browser/roster.jpg`. This shows synthetic names/IDs only. This is fixture visual evidence; final existing MAMP Network evidence is separately recorded below.


## Earlier Final Network Proof Only attempt — historical audit

Continued the existing working tree without changing production code or reopening fixture write tests. The initial foreground native Chrome title and URL matched PP5 Gradebook at `localhost:8888/gradebook/12`, and the visible roster was the Task 6S layout with retired tools absent. Only that verified foreground window was targeted for the DevTools shortcut. DevTools recording was not confirmed: the next observation showed the foreground had switched away from the authorized Gradebook. Chrome actions stopped immediately. No window/tab inventory, switching to another tab/window, HAR export, request payload inspection or secret capture was performed. The unexpected observation was not saved as evidence.

At the end of that attempt, a request to restore and keep the authorized Gradebook window in front was pending. **That attempt had no GET positive control and no measured POST count.** No pointer/keyboard scoring tests were performed in this continuation after the foreground changed, and no Paste, Delete, Fill or typing/commit was attempted on existing MAMP. This attempt cannot close the Network gate.

Independent final audit completed: all six modified production files match the SHA256 of the previously authenticated and tested isolated runtime; hashes are retained in `/private/tmp/pp5-6s-20261011/closure/production-hashes.json`. The expected first-party URLs for the live check are `/assets/gradebook.js?v=1791734877`, `/assets/gradebook-grid.css?v=1791733293` and `/assets/gradebook-workspace.css?v=1791733213`. These expected URLs were disk-derived; fresh delivery was subsequently confirmed in the final proof below.

Registry PASS: 130 IDs, 125 accepted, five retired UI; all 124 originals retained, 113 unaffected records unchanged, retired originals exact. Prior evidence files contain all 13 passing Golden A–M journeys, the trusted suites, all 43 passing general synthetic cases, all 60 passing layout cases and the five isolated resize runs. Focused PHPUnit prior result remains 727 tests / 16328 assertions. Full PHPUnit rerun for this continuation: **PASS, 3106 tests / 70732 assertions**, 1:30.561 (`closure/full-phpunit.txt`). Syntax PASS for 22 changed/new files; diff check PASS. Task 5R checksum remains `e92361caa761f2da5a84ab9d6e79d933a50130a1f759abf52249b0757e49ad58`, unmodified and untracked. The index remains empty, branch and HEAD remain the required milestone branch and baseline.

That attempt ended BLOCKED without commit/push. Its blocker is resolved by the final proof below; no PR/merge/next task is authorized. Product-owner visual acceptance PENDING.


## Final authenticated MAMP Network proof — PASS

After the Product Owner confirmed readiness, the foreground native Chrome title was verified as the PP5 Gradebook and its address as `localhost:8888/gradebook/12`. No window/tab inventory or switching to another window/tab was used. DevTools was opened through that window's View → Developer menu. Network recording was positively confirmed ON and remained ON for the entire sequence; its log was not cleared after the positive control. Request headers, cookies, payloads, credentials and HAR exports were not inspected or saved.

With Disable cache checked, native Reload produced **12 requests, all GET 200**. The document `/gradebook/12` was GET 200 with 30.2 kB transferred over network. All three current first-party assets were GET 200 with real network transfer (no memory/disk-cache delivery):

| Asset | Transfer |
| --- | --- |
| `/assets/gradebook.js?v=1791734877` | 47.7 kB |
| `/assets/gradebook-grid.css?v=1791733293` | 5.4 kB |
| `/assets/gradebook-workspace.css?v=1791733213` | 1.1 kB |

The versions match the working-tree file modification times. The reloaded table shows ordinal/code/name separation, unchanged score topics and saved-score summary, with retired help/range controls absent. All six production SHA256 values still match the previously tested isolated runtime. No production file was changed during either continuation.

Native pointer/keyboard actions under continuous recording:

| Action | Focus/selection evidence | Score POSTs |
| --- | --- | --- |
| Single pointer click | One score selected and focused | 0 |
| ArrowRight outside editor | Focus moves to the next score topic | 0 |
| Shift+Down twice, Shift+Right | Native range status: 3 rows × 2 score topics | 0 |
| Cmd+C | Copy confirmation; focus retained; DOM active element DIV at `score_7`, row index 2; six selected cells, zero editors | 0 |
| Escape outside editor | Cancel-range confirmation; DOM DIV at `score_6`, row index 2; zero selected cells, zero editors | 0 |
| F2, ArrowLeft, ArrowRight, Escape | Same INPUT at `score_5`, caret `[4,4]` → `[3,3]` → `[4,4]`; one editor throughout; Escape restores DIV `score_5` and zero editors | 0 |

The explicit-editor identity comparison uses a temporary passive DOM sampler: it retains the first observed input reference locally, reads `document.activeElement`, score field, caret and selection/editor counts, and reports equality against that reference. It invokes no application APIs or event handlers and stops/removes its timer after 45 seconds. A shorter initial observation expired before the input actions and was not treated as caret proof. Directly moving into Console also dismissed an unchanged editor; that was not used as same-input evidence. No score value was typed, pasted, deleted or filled, and no Enter/Tab score commit was requested. Console commands and Network filter text were entered only in DevTools. The Console paste warning was left intact; read-only commands were typed through the real keyboard.

Final Network `method:POST` filter showed **0 / 12 requests**, with recording ON and cache disabled. Thus total POST count is **0**, including both single and batch score mutations. The positive GET control makes this a measured result rather than an empty/unverified recorder. No live score write operation was attempted; mutation coverage remains the previously passing disposable-fixture evidence.

Sanitized evidence: `/private/tmp/pp5-6s-20261011/closure/mamp-final-network-proof.json`. It contains only page/asset paths, GET status and transport, request counts and focus/selection/caret observations; no student identities, score matrix, clipboard content, request payloads or secrets. This file is distinct from the response fixtures and disposable-database logs.

Final closure review: registry PASS (all originals retained and unaffected mappings unchanged); Golden A–M and relevant focused browser suites retain their complete passing evidence; focused PHPUnit PASS 727 tests / 16328 assertions; latest full PHPUnit PASS 3106 tests / 70732 assertions; syntax PASS 22 files; production hashes unchanged; diff check PASS. The Task 5R file remains unchanged and excluded from staging. Mandatory gates PASS; only the authorized Task 6S commit/push remains, followed by STOP. Product-owner visual acceptance PENDING.
