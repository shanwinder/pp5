# M6.6 Task 6 — Final UX Polish & Consistency

วันที่ตรวจ: 2026-10-10 · Repository `/Applications/MAMP/htdocs/pp5` · Branch `milestone/6-6-soft-government-ui`

Engineering validation: **PASS สำหรับ mandatory gates ที่ระบุด้านล่าง**. Product-owner visual acceptance: **PENDING**. การผ่าน tests ไม่ใช่การอนุมัติภาพรวม M6.6 แทนเจ้าของผลิตภัณฑ์

## Baseline และการรักษางานเดิม

ก่อนแก้ไข fetch origin แล้วตรวจ current branch, HEAD, origin SHA, ahead/behind และ working tree: HEAD และ origin ตรงกับ `836788d0ac2e6d42f41b366fb79cc836b58ca4c7`, ahead/behind `0 0`, ไม่มี tracked modifications. อ่าน AGENTS.md, owner decisions, registry, interaction contract/lock, Golden A–M, focused tests, Tabler visual system, contextual pattern และ Task 1d–5/5R notes ตามลำดับก่อนแก้ Gradebook

ไฟล์ untracked เดิมที่พบเพียงรายการเดียว:

`docs/superpowers/notes/2026-10-09-m6-6-gradebook-explicit-editor-regression.md`

รักษาไว้ ไม่เขียนทับ ไม่ลบ ไม่ stage. SHA256 ก่อนและหลังตรวจเท่ากัน:

`e92361caa761f2da5a84ab9d6e79d933a50130a1f759abf52249b0757e49ad58`

Task 6 ใช้ explicit staging เท่านั้น. ไม่มี `git add -A`, reset, clean, PR หรือ merge. Final commit SHA/push proof บันทึกในข้อความส่งมอบหลังสร้าง commit เดียว เพื่อไม่ฝัง self-referential commit hash ในเอกสารนี้

## Before audit, classification และขอบเขต

ตรวจ rendered authenticated MAMP ก่อนแก้: Dashboard, Classroom Overview/Students/Subjects, Gradebook, School Users, Academic Years, Classrooms, School Subjects, Offerings, Teaching Assignments ที่ 390/1440 px; ตรวจ Gradebook เพิ่ม 768/1024/1280 px. ตรวจ create/edit forms ตัวแทนและ legacy students/enrollments/import โดยไม่ submit mutation

| Finding | Class | Decision / result |
| --- | --- | --- |
| Gradebook มี disclosure ช่วยเหลือเต็มแถว พร้อม shortcut paragraph และ initial paste instructions ซ้ำ | B: presentation ต้องผ่าน Gradebook gate | เหลือ help native disclosure เดียวข้างหัวข้อคะแนน ตัด paragraph ซ้ำ และย้าย paste instructions เข้า help |
| `gradebook-guidance` อยู่ภายใน collapsed disclosure | B: accessibility ต้องตรวจ | ย้าย description ที่มี ID เดิมออกจาก disclosure เป็น visually-hidden; คำนวณ accessible description และตรวจ native AX text |
| สถานะเริ่มต้นสั้นลงอาจทำให้ status reservation ของ adapter สั้นลงและ grid กระโดดเมื่อเซฟ | B | CSS เฉพาะ Gradebook รักษาพื้นที่หนึ่งบรรทัด desktop/สองบรรทัด mobile; ไม่แก้ state machine |
| Students hero/roster note อธิบายปุ่มตัวเลือกซ้ำ | A | ตัดคำอธิบายปุ่ม ย่อ note เหลือความหมายรายชื่อปัจจุบันและคำแนะนำ scroll บนมือถือ |
| Subjects hero อธิบายปุ่มตัวเลือกซ้ำ | A | เหลือข้อความสำคัญว่าแต่ละภาคเรียนเป็นรายการแยกกัน |
| หน้าบริหาร Task 5 มี dense rows, compact named icons และ disclosures ที่ทำงานดีแล้ว | A: audit, ไม่จำเป็นต้องแก้ | รักษา 18 templates และ stylesheet isolation เดิม |
| Range actions และ context navigation ยังใช้พื้นที่ก่อนตารางคะแนน | C: ต้องตัดสินใจ interaction/DOM lock ก่อนเปลี่ยน | ไม่ยุบ/ย้าย/ซ่อน controls หรือเปลี่ยน contract ใน Task 6 |
| Overview มี workflow zones; legacy enrollment/import ยังใช้ compatibility styles และพื้นที่แนวตั้งมาก | D | เป็น migration/review debt แยกต่างหาก ไม่ redesign ในงานนี้ |
| ชื่อ role codes ใน administration ยังเป็นรหัสเดิม | D | คง terminology/domain mapping; ไม่เพิ่มระบบแปล role ใหม่ |
| Year assertion ค้น `2570` ใน raw HTML จึงชน offering ID เช่น `582570` | B: narrow test maintenance | ตรวจ semantic context และ display text แทน พร้อม collision/actual-year fixtures |

ไม่มี document overflow ในหน้าตัวแทนที่ตรวจ. ไม่พบเหตุให้เปลี่ยน business logic, endpoint, permission, schema หรือ vendor assets

## ไฟล์ Task 6 (8 files รวมรายงาน)

1. `htdocs/views/gradebook/view.php` — compact help, accurate shared guidance และ short idle status
2. `htdocs/assets/gradebook-workspace.css` — heading/help placement และ scoped live-status reservation
3. `htdocs/views/workspaces/classroom/students.php` — ลดข้อความอธิบายซ้ำ
4. `htdocs/views/workspaces/classroom/subjects.php` — ลดข้อความอธิบายซ้ำ
5. `tests/Feature/GradebookWorkspaceTest.php` — writable/read-only help, description และ visible live feedback assertions
6. `tests/Feature/ClassroomWorkspaceNavigationTest.php` — semantic year-context assertion และสาม counterexamples
7. `tests/Browser/gradebook-workspace.js` — compact help, disclosure containment, guidance และ status assertions
8. เอกสารนี้

ไม่มี controller/service/repository/migration/runtime JS เปลี่ยนแปลง

## Gradebook help, feedback และ locked contracts

หลักที่ใช้: **Show the work first. Explain only when needed.** `วิธีกรอกคะแนน` เป็น native `<details>` ที่ปิดเริ่มต้น อยู่ข้าง `คะแนนรายหัวข้อ`; เปิดด้วย pointer และ Enter ได้ มี summary สูง 44 px. ไม่เพิ่ม JS หรือ help card

`gradebook-guidance` มี ID เดียว เป็น `.visually-hidden` อยู่นอก disclosure ไม่มี `hidden`/`aria-hidden`. Grid และ semantic fallback คง `aria-describedby="gradebook-guidance"`. ข้อความ on-demand ใช้ guidance เดียวกัน ระบุ direct entry, explicit caret editing, Escape ใน/นอก editor, keyboard/clipboard และ NULL versus `0.00` ให้ชัดเจน. คำแนะนำการวาง blank และ pending/uncertain state ยังเข้าถึงได้ใน help

ตรวจ accessible description ด้วย `dom-accessibility-api@0.7.1` จาก npm ใน instrumentation ชั่วคราวเท่านั้น: writable description ไม่ว่าง (519 ตัวอักษร), เท่ากันทั้ง live grid และ fallback; read-only description เท่ากันและไม่มีคำสั่ง write. Full Chrome native AX tree แสดง guidance text ครบเมื่อ help ปิด. นี่เป็น computed AccName/description test ร่วมกับ native AX evidence ไม่ใช่การรับรอง VoiceOver ทุกเวอร์ชัน

Initial `gradebook-batch-status` เหลือ `พร้อมกรอกคะแนน`. Region ยังเป็น visible `role=status`, `aria-live=polite`, `aria-atomic=true` อยู่นอก help. Error, permission loss, pending, success, uncertain และ range feedback ใช้ handler เดิม. พื้นที่ status ไม่ collapse เมื่อข้อความสั้น/ว่าง: 21.695 px desktop, 43.398 px mobile. Mobile trusted direct save และ 2×2 batch paste รักษาความสูงนี้; desktop visual-stability suite ผ่าน และ Gradebook-attributed layout shifts = 0. ข้อผิดพลาดยาวยังขยายเพื่อให้อ่านได้ ไม่ clip/hide เพื่อทำคะแนน density

DOM ตั้งแต่ components-empty notice ลงไป รวม range form, bootstrap JSON, Tabulator host, semantic fallback และ teacher disclosure เทียบ baseline แล้ว byte-identical. IDs `gradebook-range-status`, `gradebook-batch-status`, `gradebook-range-actions`, `gradebook-range-summary`, `gradebook-fill-value`, `gradebook-fill-submit`, `gradebook-clear-submit` คงเดิม

`gradebook.js`, `gradebook-grid.css`, Tabulator vendor, `gradebook/context.php`, scoring/selection assets, registry/approved mappings, interaction contract และ Golden Journey expectations ไม่เปลี่ยน. Renderer/version, score normalization, queues, endpoints, CSRF และ audit/server validation ไม่เปลี่ยน. Task 5R ยังคง **UNCONFIRMED**, ไม่มี speculative keyboard fix. Lock เป็น binding review/test rule ไม่ได้อ้างว่าเป็น branch protection

## Density ที่วัดจริง

MAMP offering 12, viewport height 900 px, disclosures ปิด, first-row Y เป็น document coordinate. Context/holder/rows/columns เหมือนเดิมทุก width

| Width | First row before → after | Earlier | Context height | Holder W × H | Status before → after |
| --- | --- | --- | --- | --- | --- |
| 390 | 916.820 → 832.438 | 84.383 px | 300.258 | 364 × 516 | 43.391 → 43.398 |
| 768 | 664.172 → 601.477 | 62.695 px | 172.594 | 750 × 516 | 21.695 → 21.695 |
| 1024 | 664.172 → 601.477 | 62.695 px | 172.594 | 742 × 516 | 21.695 → 21.695 |
| 1280 | 667.406 → 604.711 | 62.695 px | 175.828 | 998 × 516 | 21.695 → 21.695 |
| 1440 | 671.727 → 609.031 | 62.695 px | 180.148 | 1158 × 516 | 21.695 → 21.695 |

Score rows **43 px**, score columns **92 px** ก่อน/หลัง. Permanent help controls **1 → 1**, full-row help กลายเป็น affordance ใน heading row. Visible instructional paragraphs นอก help **2 → 0** (shortcut hint และ initial paste instructions); visible operational idle status ยังมีอยู่. ไม่มี document overflow. Mobile→desktop→mobile กลับมา first row Y **832.438**, holder **364×516**, rows/columns เดิม ไม่ reinitialize เป็น mobile cards

| Contextual workspace | First row before → after | Row height before/after | Additional measurement |
| --- | --- | --- | --- |
| Students 390 | 950.172 → 877.086 | 51.391 | Hero 231.086 → 179.695 |
| Students 1440 | 751.938 → 722.242 | 47.391 | After hero 123.695 |
| Subjects 390 | 917.086 → 895.391 | 58.891 | Hero 279.086 → 257.391 |
| Subjects 1440 | 714.242 → 714.242 | 56.070 | ข้อความสั้นลง แต่ไม่ได้อ้าง pixel saving |

Workspace row actions ยังคง 44 px mobile / 32 px desktop. Current classroom/year, badges, read-only warnings, empty states และข้อควรตรวจปี/ห้องก่อนเพิ่ม/นำเข้ายังแสดงตามเดิม

Administration measurements ก่อน/หลังเท่ากัน: desktop rows 45 px / actions 36 px; mobile ส่วนใหญ่ rows 53 px / actions 44 px, offering mobile rows 74.086 px. First-row Y: users/years/subjects 257.820 mobile / 265.375 desktop; classrooms/offerings 391.516 / 351.070; teaching 413.211 / 335.070. ไม่อ้างว่า Task 6 ทำให้ admin rows แน่นขึ้นเมื่อไม่มีการเปลี่ยนจริง

## Compatibility CSS และ test maintenance

`gradebook-workspace.css`: **1,260 → 1,629 bytes (+369)**. มีการแก้ scoped rules เท่านั้น; ไม่มี global `.table`, `.btn`, `.tabulator`, `.form-control` override. `tabler-app.css` **11,021 bytes**, `app-compat.css` **20,409 bytes**, `View.php` ไม่เปลี่ยน. ไม่ retire global compatibility selectors จาก text search และไม่ย้อน stylesheet exclusion ของ 18 administration templates. Legacy student/import/enrollment และ Gradebook fallback ยังคง dependency เดิม

Year test เดิมห้าม substring `2570` ทุกตำแหน่งใน HTML แม้เป็น endpoint/resource ID. ใหม่ assert ปี 2569 ใน Gradebook context แล้วห้าม `2570` ใน text nodes ภายใน main (รวม secondary/collapsed disclosure), แยก script/style และ attributes ที่เป็น identifiers. Fixtures พิสูจน์ว่า ID `582570`/JSON ID `2570` ไม่ใช่ปีที่แสดง แต่ปี 2570 ใน header หรือ secondary disclosure ถูกตรวจพบ. Live authority, restricted destination links, next-year 404 และ tenant checks เดิมยังอยู่ทั้งหมด. ไม่แก้ production เพื่อให้ test ผ่าน

## Verification — แยกประเภทหลักฐาน

### PHPUnit / syntax / registry

| Gate | Result |
| --- | --- |
| Focused `--filter 'Gradebook\|ClassroomWorkspaceNavigation\|ClassroomRoster\|ClassroomSubjects\|ClassroomSubjectWorkflow\|Ui'` | **PASS 1,066 tests / 22,612 assertions** |
| Full PHPUnit | **PASS 3,098 tests / 70,470 assertions**, 01:27.185 |
| Registry validator | **PASS 124 accepted unique IDs + approved test mappings** |
| First-party PHP syntax | **PASS 227 files** |
| First-party asset/browser JS syntax | **PASS 22 files** |
| `git diff --check` | **PASS** |

PHPUnit ใช้ isolated test database/transactional fixtures ครอบคลุม permission/tenant, lifecycle, CSRF, validation/audit และ score rules. ไม่มีการใช้ live students/schools เป็น disposable data

### Synthetic browser regression (Chrome, disposable fixtures)

- Editable Tabulator **60**, read-only/historical **12**, direct-entry/explicit editor **54**, parity **33**, races/keyboard **29**, 2,001-cell limit **5**: PASS
- Visual stability **64**, Gradebook-attributed layout shifts **0**: PASS
- Workload 35×20 **16**, stress 100×40 **9**: PASS
- Errors/uncertain-write **19 scenarios / 201 checks**: PASS (single/batch validation, unmarked/login/malformed, 409 including queued clear, 500, permission loss, CSRF และ network loss)
- Gradebook layout **48 cases / 824 checks**: PASS (editable/active/error/read-only/setup/closed/empty/no-components/no-JS, 390/768/1024/1440)
- Updated contextual help suite writable **17**, read-only **16** ที่ **390/768/1024/1280/1440**: PASS ทั้งสิบกรณี รวม expanded-help containment
- UI foundation **5 cases / 71 checks**, navigation **6 / 96**, entry **36 / 528**, administration **175 / 2,215**, cross-screen **173 / 5,265**, student workflow **64 / 1,484**, confirmation/no-enhancement **2 / 18**: PASS
- Cross-screen ครอบคลุม Classroom Overview/Students/Subjects, contextual panel load/close/focus, long Thai text, empty/limited/read-only/validation และ mobile→desktop→mobile. Forms, tables, labels/IDs, icon names, escaping, local single Tabler foundation, native fallback และ unauthorized actions อยู่ใน suites เดิม

Synthetic dispatchEvent checks ไม่ใช้แทน trusted interaction หรือ authenticated MAMP proof

### Trusted browser input (isolated fixture)

Golden **A–M: PASS 13 journeys / 140 checks**. Counts: A10, B10, C5, D13, E11, F7, G6, H11, I11, J14, K14, L14, M14. ใช้ actual Chrome pointer/drag/keyboard/clipboard. Copy ผล `\t0.00\n12.50\t6.00`, paste ที่มี blank/zero ตรวจแยก NULL/zero และ atomic rectangle ตามเดิม

J–M ใช้ `singleDelay=2000`; selectedAtKey=9, editor=false, host key reached, readyRejections=0, batch follows singles. J/K pendingSinglesAtKey=1; L/M=3. Final focus score_10 และ selected 9. ไม่แก้ expectations หรือเพิ่ม timeout

การส่ง keys เร็วเกินจังหวะ polling ของ Journey A ในครั้งแรกทำให้ harness ไม่เห็น intermediate state; rerun ด้วย pointer/keyboard เดิมและอ่าน live checkpoints ระหว่าง keys แล้ว PASS. Temporary runner ใน /tmp เคยเลือก mode ผิดและอ่านผล frame เดิมก่อน navigation; แก้เฉพาะ runner ให้ใช้ mode ถูกและรอ iframe load แล้ว rerun ทุก case. ข้อผิดพลาด harness เหล่านี้ไม่ถูกรายงานเป็น product regression หรือ final PASS run

Trusted contextual workspace: writable **9**, read-only **10** checks PASS รวม keyboard collapse, native read-only copy และ stable geometry. Critical mobile probe: explicit `12.50` ใช้ input/focus identity เดียว caret **5→4→5**, active cell เดิม, no requests, page/holder scroll คงเดิมระหว่าง Left/Right/Escape. Direct `5`→Right ปิด input, focus score_11, ส่ง single request เดียว ได้ `5.00`; offscreen navigation scroll เฉพาะ holderตาม contract. Mobile 2×2 paste ส่ง batch เดียว และ status height 43.398 px คงเดิม

### Authenticated MAMP (non-mutating)

ใช้ session โรงเรียนที่มีอยู่ `admintestsc`, classroom 47, offering 12, ปี 2569/ภาคเรียน 1. ตรวจ Dashboard, Overview, Students, Subjects, Gradebook และ administration ทั้งหกกลุ่มที่ 390/1440 px; intermediate widths มี fixture และ Gradebook MAMP measurements

Actual pointer/keyboard: เลือก score_5, outside Right ย้าย score_6, double-click/Enter/F2 เปิดค่าเดิม `2.00`. Desktop input/focus identity 4, mobile identity 8; caret **4→3→4**, active score cell เดิม. Escape กลับ cell ไม่เซฟ; outside Escape ล้าง selection ไม่ล้างค่า. Page/holder scroll ไม่เปลี่ยนระหว่าง caret keys/Escape. การเปิด editor หรือไป cell ที่อยู่นอก viewport อาจเลื่อนให้เห็น control ตาม behavior เดิม ไม่อ้างว่าทุก pointer action ต้องไม่มี scroll

Passive resource observer ตลอด critical journey พบ **0 score-endpoint requests**. ไม่มีการพิมพ์เพื่อ commit คะแนนจริง ไม่มี submit live POST หรือ restore/change data. Write testing ทั้งหมดอยู่ใน disposable fixture

Help และ component disclosure เปิด pointer/ปิด Enter ได้, grid คง mounted, summary focus ถูกต้อง, no overflow. Computed descriptions และ full native AX tree ผ่านตามข้างต้น. Students/Subjects contextual GET panels focus heading, Escape ปิดและคืน focus trigger, no overflow. Admin keyboard Tab เปิด tooltip ที่ชื่อสอดคล้อง aria-label/title, outline solid, target 44 px; Escape ซ่อน tooltipและคง focus. Authenticated pages มี H1 เดียว

Temporary observer/AccName helper ใช้เฉพาะระหว่างการตรวจ ไม่เปลี่ยน focus/caret/editor/handler/network implementation. หลังเก็บ evidence คืน `scoring-assets.php` byte-identical และลบเฉพาะ assets ที่สร้างเองแล้ว ตรวจ MAMP clean page ไม่มี instrumentation

ไม่มี safe system/read-only authenticated account เพิ่มเติม จึงไม่สร้างบัญชีหรืออ้าง authenticated coverage ส่วนนั้น. System Schools และ read-only roles ใช้ production-template fixtures + transactional HTTP tests ตามข้อยกเว้นที่ request กำหนด

## Limitations, deferred work และ local evidence

Mandatory engineering gates ที่ระบุข้างต้นผ่าน. ข้อจำกัดเพิ่มเติมที่ไม่รายงานเป็น PASS:

- Chrome internal accessibility URL ใช้ไม่ได้ตาม browser URL policy; ไม่มีการหลบ policy. ตรวจผ่าน safe local computed-description helper และ native AX text แทน
- การทดสอบเสริม native subject confirmation เมื่อ HTMX หาย (script นี้ไม่ได้แก้ใน Task 6) เปิดกล่องยืนยันได้ แต่ API สำหรับ Cancel/close/Escape timeout เพราะ renderer paused. **Optional extra BLOCKED**, ขอให้ผู้ใช้กด Cancel ในแท็บ disposable `127.0.0.1:18917`. Required confirmation/no-JS suites ผ่านทั้ง 11+7 checks พร้อม isolated native POST receiver; ไม่อ้างว่า optional extra สำเร็จ
- Automatic approval review ปฏิเสธการอ่าน full tree ของหน้าต่าง Chrome/YouTube ที่ไม่เกี่ยวกับงาน เพราะเสี่ยงอ่านเนื้อหาส่วนตัว. หยุดใช้หน้าต่างนั้น ไม่ควบคุมหรือปิด unrelated user tabs. การตรวจ MAMP/fixtures ที่เกี่ยวข้องดำเนินต่อได้
- ไม่ได้ให้ visual approval แทน product owner. Legacy migration, role terminology, overview density และการเปลี่ยน range-panel interaction ต้องเป็นงาน/decision แยก

หลักฐาน local (ไม่ stage): `/private/tmp/pp5-task6-focused.log`, `pp5-task6-full.log`, `pp5-task6-php-syntax.log`, `pp5-task6-js-syntax.log`, `pp5-task6-browser-evidence.json`, `pp5-task6-density.json`, `pp5-task6-mamp-evidence.json`, `pp5-task6-mamp-after.json`, `pp5-task6-mamp-panels.json`, `pp5-task6-readonly-description.json`, `pp5-task6-fixture-critical.json`, `pp5-task6-help-responsive.json`. Temporary helpers/router/package อยู่ใน /tmp ไม่มี dependency/package/framework ใหม่ใน application

Clean screenshots: `/Users/depa/.codex/visualizations/2026/10/09/01a12315-86d2-7090-a05e-333383135516/pp5-task6-gradebook-390.jpg` และ `pp5-task6-gradebook-1440.jpg`. ไม่มีการอ้าง baseline screenshot ที่ไม่ได้บันทึก; before evidence เป็นค่าที่วัดก่อนแก้

จบ Task 6 หลัง one explicit commit / one push ไป branch ที่กำหนด และตรวจ SHA/ahead-behind/untracked preservation. ไม่มี PR, merge, Milestone 7 หรือ task ถัดไป
