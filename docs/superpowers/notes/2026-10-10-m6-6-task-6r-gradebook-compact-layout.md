# M6.6 Task 6R — Gradebook Compact Layout & Unified Score Summary

**IMPLEMENTED / TECHNICAL GATES PASS — Product-owner visual acceptance: PENDING.**

Starting SHA: `311ed7292d16e853e946ad292b6a1114989cdd08`; branch `milestone/6-6-soft-government-ui`. รายงานนี้รวมหลักฐานก่อน/หลังและประวัติรอบที่เคย BLOCKED; ผลล่าสุดอยู่หัวข้อ Network Evidence Only ด้านท้าย. อนุมัติหนึ่ง commit รวม Task 6R และ push เมื่อ technical gates ผ่านครบ; ไม่ reset/PR/merge/Milestone 7.

Product Owner อนุมัติให้ลบ overview/score heading/permanent range form, ย้าย help/Fill/Clear ไป compact context controls และรวม summary เป็นหนึ่งคอลัมน์. คำสั่ง Continue Task 6R อนุมัติซ่อม read-only live feedback, regression ทั้งสอง modes/no-range และ batch assertions โดยรักษา authoritative summary ครบทุกค่า.

**สถานะรอบก่อน (เก็บประวัติ):** ข้อกำหนด authenticated MAMP ฉบับเดิมต้องตรวจ read-only/historical rows ด้วย. Context จริงที่เข้าถึงได้มีเฉพาะ writable/current rows จึงยังพิสูจน์สองบริบทนี้บน MAMP ไม่ได้. Fixtures/HTTP tests ผ่านแต่ไม่ใช้แทน MAMP proof. Gate นี้ **BLOCKED** จึงหยุดโดยไม่มี commit/push ตาม stop condition.

**สถานะล่าสุด 2026-10-11:** isolated authenticated MAMP และ technical regression gates ผ่านครบ. หลังผู้ใช้วางหน้าต่าง Task 6R ด้านหน้าและอนุมัติเฉพาะ DevTools Network ตรวจ existing authenticated MAMP ผ่าน: GET หน้า Gradebook HTTP 200 รับข้อมูลผ่าน network จริง; PP5 POST 0 ระหว่าง native navigation/range/caret/Escape. ไม่มี required technical gate ที่ยัง FAIL/BLOCKED. Product-owner visual acceptance ยังคง PENDING.

## Dependency audit before implementation

- `context.php` owns the single H1, subject/classroom/year/term/status, authorized navigation, and `gradebook-components`. Workspace PHP/browser tests expect that disclosure. Replace those assertions with actual component-header name/max assertions; preserve setup navigation and bootstrap component data.
- `view.php` owns `gradebook-help`, `gradebook-guidance`, the score-region labels/descriptions, feedback and range controls. Help moves into context; guidance stays outside disclosures. Remove heading wrapper without touching the host/fallback region name.
- `gradebook-range-actions`, `gradebook-range-summary`, `gradebook-fill-value`, `gradebook-fill-submit`, `gradebook-clear-submit` are adapter listener contracts. `tableBuilt` currently unhides the form. Keep IDs, initialize the form inside a closed disclosure, reveal the trigger only after a successful build. Selection/render callbacks update controls without opening/closing it.
- `focusin` currently treats form descendants as Gradebook-owned; extend that ownership to its compact trigger. Keep `selectedPlan`, `runRange`, `submitBatch`, queue/uncertain/permission logic. Escape handling is local to the disclosure, distinct from grid/editor Escape.
- JS `rowData`, `validateBatch`, `summaryFromFragment`, `apply` preserve `total`, `max`, `count`, `complete`. `apply` has revision protection for row summaries; derived summary must be updated in that same protected update.
- Tabulator currently constructs four physical summary columns (112+105+128+104 = 449 px). Replace definitions with one read-only 112 px column. `fields`/`componentByField` define selection, navigation and clipboard boundaries independently of summaries.
- `row-summary.php` serves fallback cells and single-score OOB responses from `GradebookScoreController::store`; four `row-{offeringId}-{enrollmentId}-{suffix}` spans are required and validated by `summaryFromFragment`. Keep their exact values and OOB outerHTML IDs, embed hidden non-column metadata inside the one fallback summary cell, and send a derived display span in OOB responses. Batch JSON is unchanged.
- Existing PHP read/score/batch tests assert authoritative totals/count/completion and score IDs. Keep that independent validation; add fallback cell/header/colspan and formatting cases. Error/parity browser tests reference the old visible `total` cell and must target the projection while independently retaining response validation.
- Golden G operates Fill by trusted pointer/keys; add actual compact-trigger opening and unchanged-range checks. Golden H enters via Tab: adapt DOM traversal from the new trigger while still checking first score focus/selection/one roving stop. Race external-focus simulation must open the disclosure before focusing its input.
- `scoring-assets.php` and `selection-assets.php` already load versioned first-party CSS/JS. Shared CSS, renderer/vendor version, controllers, services, endpoints and arithmetic are outside this change.

## Baseline defect และหลักฐานก่อน/หลังซ่อม

ครั้งแรกหยุดโดยไม่มี commit/push เพราะ read-only parity fail: `FAIL after 12: GB-SEL-006 — Escape announces that the range is no longer selected`. ตรวจ baseline จาก `git archive HEAD` ที่ `/private/tmp/pp5-6r-baseline`, original router/tests/assets/views บน loopback18890; Task6R fixture บน 18889. ไม่ reset/เปลี่ยน branch. ทั้งสองเป็น disposable fixtures แยกจาก MAMP.

Root cause: `updateControls()` เดิม `if (!actions) return;` ก่อนอัปเดต `gradebook-range-status`. Read-only ไม่มี actions จึงไม่ประกาศ แม้ล้าง selection/focus ถูกต้อง. Defect **มีอยู่ใน baseline เดิม** ไม่ใช่ compact-layout regression. หลังอนุมัติจึงย้าย feedback ก่อน write-controls guard และติดตาม transition จากมีช่วงเป็นไม่มีช่วง; ไม่ประกาศตอนเริ่มหรือ Escape ซ้ำเมื่อไม่มีช่วง. ไม่เปลี่ยน active cell/focus/selection/permissions หรือส่ง mutation.

| Trusted pointer/keyboard | Selection หลัง Escape | Focus/editor | Live region |
| --- | --- | --- | --- |
| Baseline read-only | 1→0 | score_10 เดิม, ไม่มี editor | ว่างก่อน/หลัง — FAIL |
| Task6R ก่อนซ่อม | 1→0 | score_10 เดิม, ไม่มี editor | ว่างก่อน/หลัง — FAIL |
| หลังซ่อม read-only | 1→0 | same focused score, ไม่มี editor/request | `ยกเลิกการเลือกช่วงคะแนน` — PASS |
| หลังซ่อม writable | 1→0 | same focused score, ไม่มี editor/request | ข้อความเดียวกัน — PASS |
| Escape ซ้ำ/no-range ทั้งสอง modes | 0→0 | focus เดิม, requests0 | MutationObserver0: ไม่ประกาศซ้ำ |

Trusted `gradebook-feedback-real.js` ตรวจ isTrusted pointerup/keydown, actual activeElement/selected cells/editor absence/fetch count; readonly 6/writable 8 PASS. Synthetic parity readonly 27/writable 36 PASS. Registry/GB-SEL-006 meaning/mappings ไม่เปลี่ยน.

## Implementation และขอบเขต

- ลบ `gradebook-components` overview, `คะแนนรายหัวข้อ` heading/wrapper และ permanent `คะแนนสำหรับช่วงที่เลือก` strip. Actual component name/max อยู่ใน score headers; authorized `จัดการรายการคะแนน` route คงอยู่. H1/subject/classroom/year/term/status และ region names/descriptions คงอยู่.
- Help/`เครื่องมือช่วงคะแนน` อยู่ toolbar เดิม ใช้ native details ชื่อร่วม `gradebook-tools`, ปิดเริ่มต้น. Trigger เปิดหลัง adapter build; readonly ไม่มี trigger/form. Panel absolute anchored, viewport-contained, scroll ภายใน, targets44px. Selection ไม่ auto-open/grid shift. Native details containment เคยทำ popup แคบเท่า trigger ใน development test; แก้ scoped CSS แล้วตรวจซ้ำผ่านทุกขนาด.
- Form IDs/listeners และ `selectedPlan`/`runRange`/`submitBatch` pipeline เดิมคงอยู่. Pointer/Enter/Space/Tab/Shift+Tab/Escape ตรวจจริง; panel Escape คืน trigger preventScroll. Success ปิด panel เฉพาะเมื่อยังเป็นเจ้าของ focus แล้วคืน top-left; failure เปิดพร้อมเหตุผลแก้ไขได้. Late response ไม่ reclaim external focus.
- Fill/Clear หนึ่ง atomic batch; invalid/no-range/mixed historical ไม่ write; pending singles serialize ก่อน batch; uncertain lock ไม่ retry; immediate typing หลัง Fill ไม่ต้องคลิก.
- Tabulator มีหนึ่ง physical summary column `summaryDisplay`, heading `คะแนนที่บันทึก`, width112px. อีกสามคอลัมน์ออกจาก definitions ไม่ใช่ CSS ซ่อน. Summary readonly/excluded from component selection/navigation/clipboard.
- คง internal `total,max,count,complete`. Projection จาก authoritative server decimal strings ตัดเฉพาะ fractional trailing zeros; ไม่ float/sum/round/hardcode max. `42.00/50.00→42/50`, `42.50/50.00→42.5/50`, `25.75/35.50→25.75/35.5`, `0.00/50.00→0/50`, `0.00/0.00→0/0`. Integer trailing zeros/large exact strings ไม่เสีย precision. NULL cells ยังว่าง; total/max ไม่แทน completion.
- `apply()` สร้าง projection ใน revision guard เดิมของ authoritative row update ทั้ง single/batch. ไม่มี optimistic summary; stale revision test พิสูจน์ไม่ทับค่าทั้งสี่/summary ใหม่.
- Fallback หนึ่ง summary td: visible display + hidden aria-hidden non-column wrapper ของสี่ค่า. OOB คง original four `row-{offeringId}-{enrollmentId}-{total|max|count|complete}` IDs/values และเพิ่ม display span; fragment ไม่มี td. Empty colspan `3+active_component_count`; no-component0/0 พร้อม underlying completion เดิม.
- Actual fallback HTMX save บน disposable fixture: score5.00, visible19.25/35.5, internal19.25/35.50/1 of2/ยังไม่ครบ; หนึ่ง summarytd, ไม่มี Tabulator/range trigger.
- Batch obsolete-total assertion เปลี่ยนเป็นทั้งสองแถว 987.65/432.1 พร้อมตรวจ internal987.65/432.10/2 of2/ครบแยกกัน. Single ตรวจ 19.25/35.5 และสี่ค่าจริง. เพิ่ม missing-total/max/count/complete single/batch รวม 8 rejection scenarios; malformed ไม่ reconcile และเข้า uncertain lock. ไม่มีการลด authoritative assertions.
- ไม่เปลี่ยน controller/service/API/schema/permissions/tenant/CSRF/audit/lifecycle/formula, pinned Tabulator6.6.0/Tabler1.6.1/HTMX หรือ registry. Contract/Thai lock เพิ่มเฉพาะคำชี้แจง responsive ที่ได้รับอนุมัติในรอบต่อเนื่องด้านล่าง. ไม่มี framework/CDN/build pipeline ใหม่.

## Changed files และ test migration

Production8: `htdocs/assets/gradebook.js`, `gradebook-grid.css`, `gradebook-workspace.css`; `htdocs/views/gradebook/context.php`, `view.php`, `row-summary.php`, ใหม่ `tools.php`; ใหม่ `htdocs/app/Support/GradebookSummaryPresentation.php`.

PHP: `GradebookScoreHttpTest.php` ตรวจ four OOB values+display/no td; `GradebookWorkspaceTest.php`/`UiGradebookTest.php` ตรวจ actual component headers/closed tools/accessibility/colspan; ใหม่ `GradebookSummaryPresentationTest.php` ตรวจ 7 exact cases/fallback/OOB/hidden completeness.

Browser: `gradebook-autosave.php`, `gradebook-tabulator.js`, `gradebook-tabulator-errors.js`, `gradebook-spreadsheet-parity.js`, `gradebook-tabulator-races.js`, `gradebook-workspace.js`, `gradebook-workspace-real.js`, `gradebook-layout.js`, `gradebook-golden-journeys.js`; ใหม่ `gradebook-compact.js`, `gradebook-feedback-real.js`. Diagnostics ของ row summaries/revision อยู่ fixture router เท่านั้น ไม่มี production hooks. Workspace แทน overview assertions ด้วย actual name/max. Golden G เพิ่ม pointer opening/rectangle/atomicFill+Clear/immediate typing; Hlegitimate Tab จาก closed trigger; Csame input/caret/noPOST.

## Mandatory verification รอบก่อน responsive — เก็บประวัติแยกหลักฐาน

| Gate | ผลจริง |
| --- | --- |
| Registry | PASS 124 unique accepted IDs/mappings; registry/contract diff ว่าง |
| Focused PHPUnit `--filter 'Gradebook|UiGradebook'` | PASS 726 tests/16,290 assertions |
| Full PHPUnit | PASS 3,105 tests/70,694 assertions |
| Synthetic Tabulator | writable 62/readonly 12 PASS |
| Synthetic parity | writable 36/readonly 27 PASS, รวม historical/range/clipboard/no-range feedback |
| Synthetic direct/explicit editor | 54 PASS, direct navigation vs explicitcaret/cancel/IME |
| Synthetic race/size | 29+5 PASS, pending singles/batch/focus/2,001-cell rejection |
| Error/uncertain-write | 27 scenarios PASS, รวม 8missing fields/queued409/permission/CSRF/network/login/unmarked/malformed/500 |
| Synthetic compact | 72 PASS ที่ 390/768/1024/1280/1440/390revisit |
| Synthetic workspace | writable18/readonly17 PASS |
| Visual stability | 64 PASS, Gradebook-attributed shifts0; incidental total0.00102556 |
| Workload | 35×20:16 PASS; 100×40:9 PASS |
| Layout | 60 cases PASS,12modes×5widths incl noJS/setup/readonly/historical/empty/no-components |
| Trusted Golden A–M | 13journeys/148final checks PASS, actualpointer/keyboard/nativeclipboard |
| Trusted feedback/compact keyboard | readonly 6/writable 8 PASS |
| Trusted workspace | readonly10/writable9 PASS |
| Actual fallback HTMX fixture | PASS,1request,score/display+four fields,1summarytd |
| Authenticated MAMP current writable | Non-mutating checks complete,0score POST |
| Authenticated MAMP readonly/historical | **BLOCKED**,ไม่มีบริบทที่เข้าถึงได้สำหรับสองกรณีนี้ |
| PHP/JS syntax | PASS 232 PHP / 24 JS,first-party htdocs/tests excluding vendor |
| git diff --check | PASS,no staging/commit/push |
| Product-owner visual acceptance | PENDING |

Golden counts A10 B10 C7 D13 E11 F7 G12 H11 I11 J14 K14 L14 M14. Native copy F exact TSV blank/0.00 then12.50/6.00; nativepaste2×2. J–M delay2000ms, pending singles1/1/3/3 ณ trusted Clear, selected9/editingfalse/readyRejections0, batch1 หลัง singles, final score_10 focus/9selected. Registryvalidator ไม่ใช่ browserproof.

PHPUnit ใช้ local isolated test DB ไม่ใช้ real student data. DB socket / fixture loopback sandbox permission สำหรับ test เท่านั้น. Full PHP suite ผ่านหลัง final production/PHP changes; browser coverage เพิ่มภายหลังไม่เปลี่ยน production bytes. Syntax ตรวจใหม่หลัง final browser edits.

## Focused requirements 1–30 coverage

| ข้อ | หลักฐาน |
| --- | --- |
| 1–5 one column/heading/removed strips/closed form | compact/workspacePHP+browser/MAMP five widths |
| 6–9 read-only tool absence/writable accessibility/pointer+keyboard/Escape | parity/trustedfeedback/workspace/GoldenG,H/MAMP writable |
| 10–12 no-range/valid/mixed historical | compact/tabulator/parity/errors; invalid range: 0 writes |
| 13–17 pending→Fill/one Fill/one Clear/top-left/failure correction | compact/GoldenG,J–M/races/errors |
| 18–20 decimal/fraction/zero-max | PHP 7 cases/compact JS+fallback |
| 21–22 no-components/initial fallback parity | layout/compact/PHP |
| 23–25 single/batch/stale | tabulatorfour-field assertions/compact revision/missing-field errors |
| 26–27 summary selection/copy/paste exclusion | parity/GoldenD,E,F/tabulator |
| 28–30 colspan/overflow/selection layout shift | PHPlayout/60-case matrix/compact/visual/MAMP |

## Authenticated MAMP evidence และข้อจำกัด

Safe authenticated Chrome `/gradebook/12`: วิชาวิทยาการคำนวณ 4, ชั้นประถมศึกษาปีที่ 4, ภาคเรียน 1 ปี 2569; actual4components max10/10/15/15, server max50.00; initial5/50,8/50,5/50. Formatterfixtures พิสูจน์ max35.50/432.10/0/large strings เพิ่มเติม ไม่มี hardcode50.

Actual pointer/key nonmutation: selection/help/range opening/closing, doubleclick/Enter/F2existing-value editor,Left/Rightcaret,Escapecancel. Desktop inputIDs3/4/5 ตาม doubleclick/Enter/F2, caret4→3→4 ใน same input/same focus/same cell; Escape คืน cellID2/score_5, selected1,POST0. MobileinputID6caret4→3→4same input/focus; Escape คืน cell2/score_5,selected1,POST0. Scroll[documentX,Y,holderX,Y]desktop[0,0,0,0],mobile[0,127,128,0]คงเดิมผ่าน arrows/cancel.

Temporary passive observer บันทึก WeakMapidentity/caret/focus/POST/scroll; ถอดแล้วคืน adapterbytes ตรงกับก่อน probe ทุก byte. Final inspection ไม่มี probe/editor;ไม่มี observer ใน deliveredcode.

Locator auto-scroll artifact ตอนคลิก toolbar บน mobile127→0 ตรวจใหม่ด้วย nativepointer บน visibletrigger. Native pointer/key ทั้ง 5widths เปิดปิดรักษา scroll/grid Y/selection; Escape คืน trigger. Panelbounds390:29..361,768:359..743,1024:607..991,1280:863..1247,1440:1023..1407 อยู่ viewport/ไม่ holderclip. Mobile panel อ่าน/scroll ได้. H1 เดียว/region guidance/help keyboard คงอยู่.

Teaching-work list ที่เข้าถึงได้มี offering writable เดียว; actual สาม rows เป็น current ทั้งหมด. **ยังไม่ได้พิสูจน์ authenticated MAMP readonly/historical**. Fixtures/HTTP ที่ผ่านไม่ใช่หลักฐานแทน MAMP. ไม่สร้าง real data/เปลี่ยน lifecycle/permissions เพื่อ fabricate coverage. ต้องมี safe existing authenticated context หรือ explicit Product Owner decision เปลี่ยน gate ก่อนส่งมอบ. ไม่มี commit/push ระหว่าง blocked.

## Before/after measurements

Authenticated MAMP viewport height900; Y เป็น documentcoordinate,วัด header-to-gridgap แบบเดียวกันก่อน/หลัง.

| Width | First row Y ก่อน | หลัง | Header-to-grid gap ก่อน | หลัง |
| --- | ---: | ---: | ---: | ---: |
| 390 | 832.4375 | 555.65625 | 392.1796875 | 63.3984375 |
| 768 | 601.4765625 | 382.2890625 | 312.8828125 | 41.6953125 |
| 1024 | 601.4765625 | 382.2890625 | 312.8828125 | 41.6953125 |
| 1280 | 604.7109375 | 333.5234375 | 312.8828125 | 41.6953125 |
| 1440 | 609.03125 | 337.84375 | 312.8828125 | 41.6953125 |

Grid content1,137→800px ลด 337px(summary449→112). Physical summary4→1. Score width92,row height43,frozen identity190,summary112px ทุก width. Document horizontal overflow0. Mobile→desktop→mobilefirstrow555.65625 เดิม. Permanentoverview/heading/rangebar0; เหลือ operationalfeedback. Popup ไม่เปลี่ยน grid Y/scroll/tableidentity.

## Artifacts / การส่งมอบ

Local artifacts: `/private/tmp/pp5-6r-browser-evidence.json` มี development intermediate records ให้ใช้ final PASS ตาม query/journey ไม่กลบ initial failures; `/private/tmp/pp5-6r-layout.txt`, `/private/tmp/pp5-6r-mamp-popup-native.json`, `/private/tmp/pp5-6r-focused-final.log`, `/private/tmp/pp5-6r-full.log`, `/private/tmp/pp5-6r-syntax.log`. ภาพสุดท้ายที่ตรวจแล้ว: `/private/tmp/pp5-6r-mamp-final.jpg` (1440 px, full context). ไฟล์ภาพเก่าที่ชื่อมี 390 เมื่อเปิดตรวจกลับเป็นภาพ desktop จึงไม่ใช้เป็นหลักฐานภาพ mobile; หลักฐาน responsive ใช้ actual DOM viewport/geometry และ native pointer matrix ที่บันทึกไว้. ไม่ stage temporary artifacts.

Task5R `docs/superpowers/notes/2026-10-09-m6-6-gradebook-explicit-editor-regression.md` ยังคง untracked ไม่แก้/ลบ/stage; SHA256 `e92361caa761f2da5a84ab9d6e79d933a50130a1f759abf52249b0757e49ad58`ตรงก่อนงาน. Task6R existingwork ไม่ถูกทิ้ง. Viewport override reset,ไม่ปิด unrelated user tabs.

ซ่อม defect ที่อนุมัติแล้ว แต่ **release ยัง BLOCKED/no commit/push** เพราะ authenticated MAMP readonly/historical coverage ขาด. Final SHA เท่า starting SHA. Product-owner visual acceptance **PENDING**. ไม่ PR/merge/Milestone7.

## ต่อเนื่อง 2026-10-11 — Authorization และ isolated MAMP / responsive width

เจ้าของผลิตภัณฑ์อนุมัติ temporary isolated MAMP database/accounts และ responsive column geometry เพิ่มเติม โดยรักษางาน Task 6R เดิมทั้งหมด. ตรวจก่อนแก้: branch `milestone/6-6-soft-government-ui`, HEAD/origin `311ed7292d16e853e946ad292b6a1114989cdd08`, ahead/behind 0/0; working tree เป็นงาน Task 6R ที่ตั้งใจยังไม่ commit. Task 5R SHA256 ยังคง `e92361caa761f2da5a84ab9d6e79d933a50130a1f759abf52249b0757e49ad58` และไม่ stage.

### Runtime และหลักฐาน isolation

- Run-owned directory `/private/tmp/pp5-6r-e2e-20261011`, Apache MAMP 2.4.62 / PHP CGI 8.3.14, bind เฉพาะ `127.0.0.1:18916`; document root สำเนา working-tree `htdocs` ของ Task 6R. ไม่เปลี่ยน config ของ site เดิม.
- ฐานข้อมูลใหม่ `pp5_gradebook_e2e_6r_20261011`; DB user ใหม่มี grant เฉพาะฐานข้อมูลนี้. คำขอเว็บ `/login` ก่อน migration/provision ยืนยัน `SELECT DATABASE()` และ `CURRENT_USER()` จาก PHP CGI จริง ไม่ใช่เพียง environment variable. ใช้ migration/seed CLI เดิมหลัง proof นี้.
- Cookie name `PP5_6R_E2E_20261011`, session directory แยก; test-only credentials สุ่ม เก็บ permission 0600 ภายนอก Git. ไม่เข้าถึง session/profile ของผู้ใช้เดิมเพื่อรัน E2E.
- โรงเรียนสังเคราะห์หนึ่งแห่ง, enrollment ห้าราย, offering 0/1/2/4/20 หัวข้อ. บัญชี `EXECUTIVE` read-only และ `SCHOOL_ADMIN` writable เข้าผ่าน `/login` เดิม. คะแนนเริ่มต้นผ่าน GradebookScoreService เดิม; enrollment สุดท้ายเปลี่ยน placement ผ่าน EnrollmentAdministrationService เดิม จึงเป็น historical จริง.
- Read-only: select/Arrow/Shift+Arrow/Escape รักษา active/focus, ประกาศ `ยกเลิกการเลือกช่วงคะแนน`, Escape ซ้ำไม่มี live mutation, Enter/F2/typing/paste/Delete ไม่เปิด editor และไม่ส่ง mutation.
- Historical: native copy ได้ `1.50`, Enter/F2/direct entry ไม่เปิด editor; mixed current/historical selection ปิด Fill/Clear, Delete ส่ง 0 request. Escape รักษา score focus.
- Writable: direct `5` → Right ส่ง single POST หนึ่งครั้ง HTTP 200; summary จาก server `8.00`, `75.50`, `3 / 4`, `ยังไม่ครบ` แสดง `8/75.5`. Fill `6` สองช่องส่ง batch หนึ่งครั้ง แสดง `15/75.5`, count `4 / 4`, `ครบ`; Clear สองช่องส่ง blank batch หนึ่งครั้ง แสดง `3/75.5`, count `2 / 4`, `ยังไม่ครบ`. Restore focus top-left ถูกต้อง.
- Provisioning, lifecycle helper และ passive diagnostics อยู่เฉพาะ temporary runtime; ไม่มี endpoint bootstrap, ไม่มีการเปลี่ยน permissions/service/API/schema/vendor ใน repository.

### Responsive strategy และ corrective history

Native `fitColumns` ของ pinned local Tabulator 6.6.0 ใช้ identity min 190 / grow 2, row-type min 130 / grow 1, scores min 92 / grow 1 และ summary min อย่างน้อย 112 / grow 1, cap 192 หรือ min ที่ต้องการหาก literal ยาวกว่า. วัด summary จาก font จริงและ server literals ครั้งเดียวตอน build (รวม max/max เพื่อ reserve ขนาด) ไม่คำนวณคะแนนและไม่เปลี่ยน width ตาม response ระหว่างกรอก. ไม่มี max cap identity/row-type เพราะกรณี 0 components ต้องเติมพื้นที่ได้ด้วย. Header ไทย wrap ด้วย selector ที่มี specificity ตรงกับ vendor.

ศึกษาพฤติกรรมจาก local vendor และ [Tabulator layout documentation](https://www.tabulator.info/docs/6.x/layout/). ใช้ native ResizeObserver/layout ไม่เพิ่ม resize loop หรือสร้าง table ใหม่. เพิ่มเฉพาะ renderStarted/renderComplete guard จับ score focus ก่อน native virtual-row redraw เมื่อ host width เปลี่ยน แล้วคืน focus; reveal หลัง synchronous native scroll restoration ผ่าน microtask โดยคง input/caret และไม่แย่ง focus จากภายนอก.

ก่อน guard: range เดิมและ active cell คงอยู่หลัง resize แต่ browser focus หลุดไป document. หลัง guard: focus/active/rectangle เดิมคงอยู่ทุกขนาด และ score ที่ถูกโฟกัสอยู่ข้าง frozen identity. Explicit input เดิม (`5.00`, caret 3/3) ผ่าน 1440→1024→768→390→1440 และ 390→1440→390 โดย request count คงเดิม; ไม่ save/cancel จาก resize.

คำชี้แจง contract/Thai lock ที่อนุมัติระบุว่า width ปรับเมื่อ viewport/container เปลี่ยนได้ แต่ที่ available width คงเดิม scoring/navigation/status/reconciliation/selection/tool ต้องคง width และ row height. Registry 124 IDs และ mappings ไม่เปลี่ยน; ไม่เคยกำหนดว่า width numeric ต้องคงตลอด window resize.

### Authenticated pixel measurements — offering 4 หัวข้อ

| Viewport | Available/outer | Column content | Identity | Score แต่ละช่อง | Summary | Holder client/scroll | Document client/scroll | Row height |
|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| 1440 | 1158 | 1158 | 288 | 144 | 150 | 1158/1158 | 1440/1440 | 43 |
| 1280 | 998 | 998 | 248 | 124 | 124 | 998/998 | 1280/1280 | 43 |
| 1024 | 742 | 800 | 190 | 92 | 112 | 742/800 | 1024/1024 | 58 |
| 768 | 750 | 800 | 190 | 92 | 112 | 750/800 | 768/768 | 58 |
| 390 | 364 | 800 | 190 | 92 | 112 | 364/800 | 390/390 | 43 |

ก่อน responsive ที่ 1440: outer 1158, content 800, พื้นที่ว่างขวา 358 px. หลัง: content 1158, ว่าง 0 px. ทดสอบ authenticated 25 combinations ของห้าขนาดและ 0/1/2/4/20 components; evidence JSON เก็บตัวเลขเต็ม. ความสูงแถวในตารางนี้เป็นค่าหลัง resize; fixed-viewport edit/save stability ตรวจแยก.

### Release gate รอบใหม่ — ผลที่เก็บระหว่าง verification

- Registry PASS 124; focused PHPUnit PASS 726 / 16,290; full PHPUnit PASS 3,105 / 70,694.
- Synthetic engine 62/12, parity 36/27, direct/explicit 54, races 29 + limit 5, visual 64 ทั้ง narrow/desktop, workload 35×20 16 / 100×40 9, workspace 18/17; 27 error scenarios ผ่าน รวม missing single/batch summary ทั้งสี่ค่าครบ (ไม่ลด assertion).
- Trusted Golden A–M PASS 148 checks: A10 B10 C7 D13 E11 F7 G12 H11 I11 J14 K14 L14 M14. J–M delay 2000 ms; pending singles 1/1/3/3, batch หลัง singles, 0 command rejection. Native clipboard F ได้ exact TSV `\t0.00\n12.50\t6.00`.
- Trusted feedback read-only 6 / writable 8 PASS. Compact synthetic 72 PASS.
- Syntax first-party PHP 235 / JS 25 ผ่าน, 0 failures; temporary helper syntax นับแยกจาก production.
- Evidence รอบนี้อยู่ `/private/tmp/pp5-6r-e2e-20261011/logs/`: DB/web identity, migrations, provisioning, authenticated actions/geometry, synthetic-browser, trusted-golden/feedback, PHPUnit และ syntax. ไม่มี secret ใน evidence หรือ Git.
- สถานะนี้ยังไม่ยืนยัน final gate/commit/push; ต้องเติม responsive container, layout matrix, lifecycle/read-only copy, cleanup และ final diff verification. Product-owner visual acceptance **PENDING**.

### Verification status ก่อน Network authorization — เก็บประวัติ STOP / ไม่มี commit หรือ push

| Gate | ผลรอบสุดท้าย |
|---|---|
| Registry | PASS 124 unique IDs + mappings; registry bytes ไม่เปลี่ยน |
| Focused PHPUnit รวม Gradebook HTTP / permissions / historical / summary | PASS 726 tests / 16,290 assertions |
| Full PHPUnit | PASS 3,105 tests / 70,694 assertions |
| Trusted Golden A–M | PASS 13 journeys / 148 checks |
| Read-only/writable synthetic parity | PASS 27 / 36 checks |
| Engine/read-only, direct/explicit, races/limit | PASS 62/12, 54, 29/5 |
| Errors / 409 / uncertain-write / summary validation | PASS 27 scenarios / 312 checks; missing total/max/count/complete ทั้ง single และ batch ยังตรวจครบ |
| Visual stability | PASS 64 checks ที่ narrow และ desktop; Gradebook-source cumulative shift 0.0007107221 / 0.0006348251 ตามลำดับ (ไม่อ้างว่าเป็น 0) |
| Workload / virtual rendering | PASS 35×20:16, 100×40:9 |
| Layout/fallback matrix | PASS 60 cases / 1,148 checks |
| Responsive container suite | PASS 1,095 checks: 0 components44, 1:268, 2:268, 4:262, 20:253 |
| Compact tool | PASS 72 checks แต่ละขนาด 390/768/1024/1280/1440 |
| Trusted live feedback | PASS read-only6 / writable8 |
| Trusted contextual workspace | PASS writable9 / read-only10; read-only native clipboard `0.00` |
| Authenticated isolated MAMP | PASS scenarios A–D: EXECUTIVE copy `1.50`, navigation/range/Escape/no repeated announcement; historical copy/edit/atomic denial; writable real single/Fill/Clear; inactive offering lifecycle read-only copy/no editor/no tool/0 mutation |
| Trusted authenticated resize | PASS read-only range round trip 7 resizes, same active/focus/range and 0 requests; explicit input round trip 7 resizes, same input/value/caret and no additional requests |
| Authenticated existing MAMP | **PARTIAL / BLOCKED network proof** — DOM geometry ทั้งห้าขนาดผ่าน, explicit caret 4→3→4, same focused input during resize, Escape removes editor and returns score focus. ไม่อ้าง 0 POST จาก DOM เพียงอย่างเดียว |
| Syntax / whitespace | PASS first-party PHP235 / JS25 / `git diff --check` |
| Cleanup | PASS servers stopped; dedicated DB/user removed; test credentials/private runtime config removed; 2 test session files removed; viewport override reset |
| Product-owner visual acceptance | **PENDING** |

Existing MAMP `/gradebook/12` ใช้ authenticated context เดิมสำหรับ non-mutating check แยกจาก E2E; ไม่ login/logout หรือเปลี่ยน existing account/session/profile และไม่กรอกค่าคะแนนใหม่. Browser DOM evaluation ไม่มี resource timing API และ Apache เดิมปิด access log จึงยังไม่สามารถยืนยัน network ของการกระทำรอบนี้ได้. ไม่แก้ Apache/app config เดิมเพื่อเพิ่ม logging.

Automatic approval review หมดเวลาสองครั้งขณะ logout isolated test site (ไม่ใช่การตัดสิน unsafe); cleanup ทำผ่าน run-owned server/session resources แทนและตรวจแล้วว่าไม่เหลือ listener ที่ 18916/18889. Existing MAMP URL เปิดได้แยกต่างหาก. ต่อมา native Chrome binding เลือกหน้าต่างอื่น จึงหยุดก่อนสั่งงานใด; การขอ inventory เพื่อหา task window ถูก automatic approval review **ปฏิเสธ** เพราะจะเปิดเผยหน้าต่างส่วนตัวที่ไม่เกี่ยวกับ Gradebook. ไม่มีการ bypass/workaround ไปอ่านข้อความหรือควบคุมหน้าต่างเหล่านั้น. ต้องมี task-scoped DevTools Network proof ก่อนปิด gate ที่เหลือ; หลักฐาน DOM/fixture ไม่ใช้แทน.

Temporary database ownership ตรวจจาก create-before-use proof, restricted grants, manifest และ school fixture ก่อนลบ. ไม่ลบ/ใช้ `pp5` หรือ `pp5_test` เพื่อ browser provisioning/cleanup; PHPUnit ใช้กลไก test DB เดิมตาม repository. Evidence และสำเนา runtime ที่ไม่มี secret ยังคงที่ `/private/tmp/pp5-6r-e2e-20261011`, อาจลบได้ภายหลังตามนโยบาย temporary files. Safe JSON `logs/cleanup.json` เก็บผล cleanup. ภาพยืนยันขนาดจริง: [1440px](/private/tmp/pp5-6r-e2e-20261011/logs/gradebook-1440.png), [390px](/private/tmp/pp5-6r-e2e-20261011/logs/gradebook-390.png).

ไม่มี final Task 6R commit SHA: HEAD/origin ยัง `311ed7292d16e853e946ad292b6a1114989cdd08`, ahead/behind 0/0, staging ว่าง. Task 6R คงใน working treeครบ 27 intended files (production presentation8, tests16, report/contract/lock3); Task 5R ยังคง untracked และ SHA256 เดิม. ไม่มี PR/merge/Milestone7. หยุดตาม mandatory gate ที่ยัง BLOCKED.


## ต่อเนื่อง 2026-10-11 — Network Evidence Only / mandatory technical gates PASS

ผู้ใช้อนุญาตให้ตรวจ DevTools Network เฉพาะหน้าต่าง Task 6R ที่นำมาด้านหน้า. Native Chrome binding ยืนยัน title เป็นสมุดคะแนนและ URL `http://localhost:8888/gradebook/12` ก่อนทำงาน; มีเฉพาะแท็บนี้ในหน้าต่างที่อ่าน. ไม่ inventory หรือเข้าถึงหน้าต่าง/แท็บอื่น ไม่อ่าน cookies/passwords/tokens ไม่เปิด request headers/payload/response และไม่แก้ config เดิม. ใช้ Network UI และ read-only DOM เฉพาะ Gradebook สำหรับ focus/caret evidence.

### Network capture positive control และ zero-mutation proof

- Network recording เปิดตลอด (Stop recording toggle = 1), All request types, ไม่มี invert/ชนิด request ที่ซ่อน POST. Clear log หนึ่งครั้งก่อนเริ่มและ reload Gradebook ผ่านปุ่ม Network; ไม่ clear อีกตลอดชุด interactions.
- ตาราง Method/Status แสดง **GET `/gradebook/12` HTTP 200**, transferred **33,463 bytes ผ่าน network** (resource 33.0 kB, 262 ms). นี่เป็น positive control ว่าจับ request จริงได้ ไม่ใช่เพียง log ว่าง. Gradebook JS/CSS และ local vendor assets เป็น GET 200 เช่นกัน (assets จาก disk cache แยกจาก document ที่ผ่าน network).
- Native filter `url:http://localhost:8888/` แสดง **12 PP5 requests ทั้งหมดเป็น GET**. Log รวม 13 มี extension image GET หนึ่งรายการที่ถูกกรองออก; ไม่นับเป็น PP5 และไม่บันทึกเนื้อหาของ extension.
- หลัง interactions เปลี่ยนเพียง filter ของ log เดิมเป็น `url:http://localhost:8888/ method:POST`: **0 / 13 requests**, transferred 0.0 kB. ดังนั้น PP5 POST ทั้งหมดและ score mutation POST เป็น **0** ใน observation window นี้. Recording ยังเปิด; ไม่ resend/fetch/save คะแนนเพื่อทดลอง capture.

### Trusted non-mutating interactions บน authenticated existing MAMP

| การกระทำจริง | หลักฐาน DOM/focus | Network |
|---|---|---|
| Pointer เลือกช่องแรก → ArrowRight | focused score_6, 1 selected cell, ไม่มี input | PP5 POST 0 |
| Shift+Right → Shift+Down | score_7 focused, rectangle 2×2 (score_6/score_7 สองแถว), live `เลือก 2 แถว × 2 หัวข้อคะแนน` | PP5 POST 0 |
| Escape นอก editor | range 4→0, score_7 focus เดิม, live `ยกเลิกการเลือกช่วงคะแนน` | PP5 POST 0 |
| Escape ซ้ำเมื่อไม่มีช่วง | range 0, focus/live text เดิม, ไม่มี editor; no-repeat MutationObserver regression ตรวจแยกใน trusted feedback ทั้งสอง modes | PP5 POST 0 |
| F2 explicit editor | ค่าเดิม `2.00`, activeElement INPUT, caret 4/4 | PP5 POST 0 |
| ArrowLeft → ArrowRight | same input AX identity 135, focused INPUT เดิม, caret 4→3→4, value `2.00` เดิม, parent score_7 เดิม | PP5 POST 0 |
| Escape ใน editor | input ถูกปิด, activeElement DIV score_7, ไม่มีช่วงค้าง | PP5 POST 0 |

ไม่พิมพ์ printable value, ไม่ Enter/Tab commit, ไม่ paste/Delete/Fill/Clear ใน existing MAMP. Writes ของ mandatory E2E ตรวจไว้เฉพาะ isolated disposable database ที่ cleanup แล้ว. Existing authenticated site ในรอบนี้ไม่มีคะแนนถูกแก้จริง.

หลักฐาน local ที่เก็บเฉพาะ Network panel/ค่าที่จำเป็น: `logs/existing-mamp-network-get.txt`, `logs/existing-mamp-network-post.txt`, `logs/existing-mamp-network-interactions.json`; ภาพ [GET/Method/Status](/private/tmp/pp5-6r-e2e-20261011/logs/existing-mamp-network-get.png), [POST 0](/private/tmp/pp5-6r-e2e-20261011/logs/existing-mamp-network-post.png). ไม่ export HAR ที่อาจบรรจุข้อมูลเกินขอบเขต. ข้อจำกัด Network รอบก่อนถูกปิดด้วย authorization นี้; ประวัติ BLOCKED ด้านบนไม่ใช่สถานะปัจจุบัน.

### Final regression และ release decision

Production presentation ทั้ง 8 files มี SHA256 ตรงกับสำเนา runtime ที่ใช้ authenticated isolated verification (`logs/network-production-hashes.json`). รอบ Network นี้เปลี่ยนเพียงรายงาน ไม่มี production/test edit เพิ่ม จึงยังใช้ผล trusted Golden A–M, synthetic, responsive, layout/workload, parity/error/race และ isolated MAMP จาก production bytes เดียวกันได้. ตรวจ registry, focused/full PHPUnit, syntax และ whitespace ซ้ำก่อน commit ตามผลด้านล่าง.

- Registry **PASS 124 accepted unique IDs/mappings**, registry ไม่มี diff; contract/lock clarification จำกัดตาม authorization.
- Focused PHPUnit **PASS 726 tests / 16,290 assertions** (`logs/network-focused-phpunit.txt`).
- Full PHPUnit **PASS 3,105 tests / 70,694 assertions** (`logs/network-full-phpunit.txt`).
- PHP/JS syntax **PASS PHP235 / JS25, failures0** (`logs/network-syntax.json`); `git diff --check` **PASS**.
- Mandatory technical gates ในตารางก่อนหน้า **PASS ทั้งหมด** รวม existing authenticated MAMP Network ที่ตอนนี้ผ่าน; ไม่มี FAIL/BLOCKED ค้าง.
- Task 5R ยังคง untracked ไม่ stage/แก้/ลบ, SHA256 `e92361caa761f2da5a84ab9d6e79d933a50130a1f759abf52249b0757e49ad58`; Task 6R เดิมครบและไม่มี reset.
- พร้อมรวม **27 intended Task 6R files ในหนึ่ง commit** และ push เฉพาะ `origin/milestone/6-6-soft-government-ui` ตาม authorization. Final commit SHA ตรวจจาก Git ของ commit นี้และรายงานผลส่งมอบ.
- **Product-owner visual acceptance: PENDING**. Technical gate PASS ไม่ใช่ visual acceptance; ไม่สร้าง PR/merge หรือเริ่ม Milestone 7.
