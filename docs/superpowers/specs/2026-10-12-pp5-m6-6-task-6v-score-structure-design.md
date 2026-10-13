# PP5 — M6.6 Task 6V: Annual Score Ratio & Score Structure Design Proposal — Revision v1.2

วันที่ 12 ตุลาคม 2569 (2026-10-12), Asia/Bangkok

**TASK 6V v1.2 — PRODUCT/ARCHITECTURE APPROVED WITH CONDITIONS A–D / B1 VERIFIED ON DISPOSABLE DB / AWAITING PO REVIEW**

Current status, 13 October 2026: the Product Owner approved the v1.2 recommendations with binding Conditions A–D and authorized B1 worktree/disposable-database implementation only, per the Decision Record below. Exact DDL and implementation verification require separate evidence; no release activation, commit/push, PR/merge or M7 approval is granted. The original Phase A analysis and historical revision evidence remain below. M6.6 visual acceptance remains **PENDING**.

## Current approval — 13 October 2026

Product Owner อนุมัติ D1–D10 และ New-course mandatory policy ตาม [Decision Record](../notes/2026-10-13-m6-6-task-6v-product-owner-decision.md) พร้อมเงื่อนไข A–D ครบถ้วน. Eight-table architecture **APPROVED WITH TECHNICAL GATES**; อนุญาต **B1 เท่านั้น** ใน worktree/disposable DB. Exact DDL Verification และ B1 Implementation Verification **PASS ON DISPOSABLE DB — AWAITING PO REVIEW**; Release Activation Approval **NOT GRANTED**. ข้อความ pending/NOT AUTHORIZED ใน Phase A และ revision notes ด้านล่างเป็นสถานะประวัติ ณ 12 October ไม่ยกเลิก authorization B1 ครั้งนี้. Architectural analysis และ historical evidence คงเดิม; B2–B5/real DB migration/activation/commit/push ไม่ได้รับอนุญาต.

B1 verification follow-up: Exact DDL และ B1 foundation tests **PASS บน disposable MySQL 8.0.40** ตาม [B1 report](../notes/2026-10-13-m6-6-task-6v-b1-foundation.md). Worktree ยังรอ PO review; verification ไม่ใช่ release approval. ไม่มี production deployment/release activation approval.

## Revision history / confirmed authority

| Revision | วันที่ | การตัดสินใจ / ขอบเขต |
| --- | --- | --- |
| v1.0 | 2026-10-12 | ร่างเริ่มต้น; ยังไม่รับรอง ratio interpretation, same-Subject-IDscope, topology restriction หรือ schema |
| v1.1 | 2026-10-12 | ตาม Product Owner review และคำขอ “Task 6V: Phase A Design Revision (v1.1)”: **D1 = APPROVED**, กลางภาคเป็น COURSEWORK; เปลี่ยน recommendation เป็น Annual Course Identity พร้อม explicit links; ทบทวน schema, history/legacy, lifecycle และ B1–B5 |
| **v1.2** | **2026-10-12** | D1 คง APPROVED; **New-course mandatory enforcement APPROVED** แทนข้อยกเว้น v1.1; D2–D9 เสนอคำตัดสินรุ่นแรก; เพิ่ม D10 governance/cutover/mixed terms; แนะนำ 8 ตารางใหม่, guard inventory และ B1 readiness; D2–D10 ยังรอ PO |

D1 ใช้คำตัดสินที่แนบกับคำขอครั้งนี้โดยตรง: **70:30 = คะแนนระหว่างเรียน 70% : คะแนนสอบปลายภาค 30%**. คะแนนสอบกลางภาคอยู่ใน **COURSEWORK**. ทั้งสองภาคเรียนของ annual course เดียวกันใช้อัตราส่วนเดียวกัน. Fixed **50.00/term**, **100.00 เมื่อสอง term พร้อม**, และ **Draft/Publish separation** เป็น requirements ที่ Product Owner ยืนยันแล้ว. D2–D10 ทั้งหมดตาม section 12 ยังรอ PO approval; การเห็นด้วยกับแนวทางบางส่วนไม่ใช่การอนุมัติ schema/behavior/Phase B. Section 13 เก็บประวัติ v1.0→v1.1 และ exact differences v1.1→v1.2; ไม่ใช้ข้อเสนอเก่าเป็นกฎปัจจุบัน. โครงสร้างครบ 100 ไม่พิสูจน์ว่านักเรียนกรอกครบหรือผ่านการประเมิน.

**Confirmed New-course policy — APPROVED:** Offering ที่สร้างหลัง durable school cutover ต้องกำหนด annual ratio และ Publish own-term structure ที่ครบ 50.00 พร้อม classification allocations ก่อนเขียนคะแนน. สร้าง Offering/partial private Draft ก่อน ratio ได้ แต่ Draft ไม่ active และไม่มี score write/no-op success ก่อน readiness. Legacy ก่อน activation คง accepted read/score/component workflow จน explicit safe adoption. Feature Flag, absence annual link และ total=50 อย่างเดียวไม่พิสูจน์ governance/readiness. กลไกบังคับและ D10 เป็น recommendation ยังไม่อนุมัติ implementation.

## 1. Git baseline / worktree preservation

| รายการ | หลักฐานที่ตรวจใน Phase A |
| --- | --- |
| Repository | `/Applications/MAMP/htdocs/pp5` |
| Branch | `milestone/6-6-soft-government-ui` |
| Required baseline / HEAD | `186dfb56a232149313ef4005c98209e6f7149424` |
| HEAD subject | `fix: stabilize gradebook virtual-row navigation and clipboard tests` |
| Origin | `https://github.com/shanwinder/pp5.git` |
| Remote tracking ref | SHA ตรง HEAD |
| Actual remote branch | v1.1 เคยยืนยัน remote ด้วย read-only ls-remote; v1.2 ตรวจซ้ำตาม section 14 แยกจาก local tracking ไม่ใช้ historical result อ้าง current remote |
| ก่อนเริ่ม v1.2 | ไม่มี tracked/staged changes; untracked Task 6T 9 ไฟล์ + Task 5R 1 ไฟล์ + design v1.1 1 ไฟล์ |
| การเปลี่ยนในงานนี้ | แก้เอกสารร่างเดิมนี้เพียงไฟล์เดียว; คง untracked; ไม่ stage |

v1.2 เก็บ SHA256 ของ untracked 11 ไฟล์, tracked 344 ไฟล์ และ index ก่อนแก้ที่ `/private/tmp/pp5-6v-v1.2-preservation.json`; สำเนา v1.1 อยู่ `/private/tmp/pp5-6v-v1.1-design.md` เพื่อ comparison (นอก repository ไม่ใช่ proposal ซ้ำ). v1.1 evidence เป็น historical record แยกต่างหาก. ตรวจสิบ preservation files และ tracked/index ซ้ำก่อนส่งมอบ. Task 5R คือ `docs/superpowers/notes/2026-10-09-m6-6-gradebook-explicit-editor-regression.md` ต้องคง hash `e92361caa761f2da5a84ab9d6e79d933a50130a1f759abf52249b0757e49ad58`.

Task 6T ที่รักษาไว้: `docs/superpowers/notes/2026-10-12-m6-6-task-6t-browser-e2e-harness.md`, `docs/testing/gradebook-browser-e2e.md`, `tests/E2E/{authenticated.cjs,database.php,fixtures.cjs,provision.php,run.cjs,web-router.php}`, `tools/e2e/run-gradebook.sh`. ไม่แก้ ไม่ลบ ไม่ stage และไม่สร้าง harness ใหม่.

### Authority ที่ตรวจ

Historical authority review ใน v1.1: อ่าน AGENTS, accepted decisions ของ Task 6R/6S/6U ที่บันทึกใน contract/รายงาน, registry, interaction contract, Thai lock, Golden A–M และ focused mappings; ตรวจรายงาน Task 6S/6U. คำขอ v1.1 รับรอง D1/กลางภาคและคงเป้าหมาย 50/100 กับ draft separation; schema และ D2–D9 ยังรออนุมัติ. อ่าน proposal v1.0 ทั้งฉบับและตรวจ implementation ก่อน revision v1.1. v1.2 อ่านคำตัดสิน PO ที่แนบมา/AGENTS/proposal v1.1, ตรวจ registry validator/unchanged mappings และสำรวจ creation/component/score/authorization source เพิ่ม; ไม่อ้างว่ารัน Golden journeys จากการอ่าน mapping.

Registry ปัจจุบันมี **130 records / 125 accepted / 5 retired_ui**, เก็บ original 124 IDs ครบ. จำนวน 124 ในส่วนต้นของ AGENTS/Thai lock เป็นข้อความก่อน Task 6S; accepted Task 6S และท้าย Thai lock ระบุการ supersede อย่างจำกัดแล้ว จึงใช้ inventory ปัจจุบันโดยไม่แก้ lock/registry/mapping. ไม่มี behavioral conflict ใหม่ที่ต้องตัดสินใน Phase A.

Golden coverage ที่อ่าน: A rapid horizontal direct entry; B vertical; C explicit same-input caret/no POST; D forward clear/typing; E reverse clear; F exact score-only TSV/NULL/zero/batch; G retired UI + native repeating paste/insufficient-target rejection; H Tab/read-only/history; I geometry/scroll stability; J/K immediate clear after pending single; L/M rapid horizontal/vertical then clear. Retired Fill UI ไม่ใช่ workflow ที่กลับมาเปิดใช้.

อ้างอิง: [registry](2026-10-02-pp5-gradebook-behavior-registry.json), [contract](2026-10-02-pp5-gradebook-spreadsheet-interaction-contract.md), [Thai lock](2026-10-10-pp5-gradebook-interaction-lock.md), [Task 6S](../notes/2026-10-11-m6-6-task-6s-gradebook-roster-paste.md), [Task 6U](../notes/2026-10-12-m6-6-task-6u-virtual-row-repair.md), [Golden source](../../../tests/Browser/gradebook-golden-journeys.js).

## 2. Existing score structure analysis

| Source ที่ตรวจ | ข้อเท็จจริงและผลต่อแบบ |
| --- | --- |
| `database/migrations/20260910_001_academic_structure.sql` | Subject มี school-scoped ID และ unique `(school_id, code)`; classroom เป็น school/year/grade-level; offering unique `(school_id, academic_year_id, classroom_id, subject_id, term_no)`; ไม่มี annual course identity/link |
| `database/migrations/20260915_001_teaching_gradebook_core.sql` | Component มี `id, school_id, academic_year_id, subject_offering_id, code, name_th, max_score DECIMAL(7,2), sort_order, status, created_at, updated_at`; code unique school/offering รวม inactive; composite FK ป้องกันข้าม tenant/year/offering |
| `GradebookComponentService` / repository | แต่ละ create/update/status มี transaction และ audit ของตัวเอง; school → year → offering → component → current score-history read; max 0.01–99999.99, order 0–65535; ไม่มี aggregate 50/ratio validator |
| `GradebookComponentRepository::hasScoreHistory` | `gradebook_scores` มีแถวแม้ `score IS NULL` ก็เป็น history; `LIMIT 1 FOR UPDATE` หลัง component lock; ไม่ตรวจเฉพาะ non-NULL |
| `GradebookScoreService` | Single/batch ถือ school/year/offering/component locks ก่อน score writes; batch sort component/enrollment IDs; permission locking read และ NULL/zero semantics เดิมต้องคงไว้ |
| `GradebookReadService` | อ่าน active components เรียง order/id และบวก exact decimal; active maxima ใด ๆ ปัจจุบันถูกใช้จริงใน summary; ไม่มี annual grades/ratio ใน read model |
| `htdocs/views/gradebook/setup.php` | Create form บนสุด, แยก edit/status forms ต่อ item และ inactive/history; แสดง active total แต่ไม่ได้บังคับ 50 |
| Routes/controllers | Setup 3 component POST families กับ Classroom Workspace อีก 3 families ใช้ `GradebookComponentService` เดียวกัน; legacy API fields code/order ยังรองรับ |
| `SubjectOfferingAdministrationService` | school lock ก่อน academic mutation; update classroom/subject/term ของ offering ได้ตาม validation เดิม; linked identity จึงต้องมี lifecycle guard ใน Phase B |
| Authorization / seeds | Setup/Workspace ใช้ existing unscoped `GRADEBOOK_COMPONENT_MANAGE`; default SCHOOL_ADMIN/ACADEMIC_ADMIN มีสิทธิ์; SUBJECT_TEACHER มี offering-scoped VIEW/SCORE_ENTER แต่ไม่มี component manage โดย default |
| `AuditLogRepository` | before/after JSON ใน audit_logs ผ่าน PDO เดียวกัน; audit failure ต้อง rollback domain writes |
| `tools/migrate.php` | ไล่ SQL migration files และบันทึกชื่อหลังสำเร็จ ไม่มี down migration framework; DDL recovery ต้องออกแบบเอง ไม่สมมติว่า transaction rollback schema ได้ |

Relevant existing tests: `GradebookComponentServiceTest`, `GradebookComponentHttpTest`, `GradebookComponentIsolationTest`, `ClassroomSubjectWorkflowTest`, `SubjectOfferingAdministrationTest`, `GradebookScoreRevalidationTest`, `TeachingGradebookSchemaTest`, scoped authorization/read/score/batch suites. Test ปัจจุบัน `testAnyScoreHistoryFreezesMaxButAllowsMetadataAndStatus` ยืนยันว่า history lock ห้ามแก้ max แต่อนุญาต metadata/status; ห้ามเขียนว่า baseline ห้ามทุก status change.

ยังไม่มี persisted score section, annual policy, draft, structure revision หรือ atomic whole-structure publish. ชื่อ component ไม่ใช่หลักฐานว่าเป็นระหว่างเรียน/ปลายภาค. Phase A ตรวจ schema/source/tests เท่านั้น **ไม่ได้สำรวจจำนวนข้อมูลจริงใน DB** จึงไม่อ้างว่า legacy รายวิชาใดพร้อมหรือจับคู่ครบ.

## 3. D1 confirmation / D4 exact ratio precision

**D1 = APPROVED**: annual ratio เป็น **COURSEWORK : FINAL_EXAM**, ไม่ใช่ Term 1 : Term 2. กลางภาคนับใน COURSEWORK; FINAL_EXAM มีเฉพาะสอบปลายภาค. แต่ละ term ของ annual course ต้องผ่าน ratio ของตนเองและรวม **50.00** พอดี. ห้ามนำ Term 1 มาชดเชย ratio ที่ผิดใน Term 2. 100.00 คือคะแนนเต็มโครงสร้างเมื่อสอง term พร้อม ไม่ใช่การเปลี่ยนสูตรผลเกรดนักเรียน.

| Ratio | T1 COURSEWORK | T1 FINAL_EXAM | T2 COURSEWORK | T2 FINAL_EXAM | Annual max |
| --- | ---: | ---: | ---: | ---: | ---: |
| 70:30 | 35.00 | 15.00 | 35.00 | 15.00 | 100.00 |
| 80:20 | 40.00 | 10.00 | 40.00 | 10.00 | 100.00 |
| 60:40 | 30.00 | 20.00 | 30.00 | 20.00 | 100.00 |
| 65:35 | 32.50 | 17.50 | 32.50 | 17.50 | 100.00 |

ตัวอย่าง 70:30: `50 × 70 / 100 = 35.00`, `50 × 30 / 100 = 15.00` ต่อ term; ปีครบเป็น 70.00 + 30.00. ตัวอย่าง 65:35 ได้ 32.50 + 17.50 ต่อ term โดยไม่มีเศษให้ปัด. ห้ามบังคับทุกวิชาใช้ 70:30; presets 70:30/80:20/60:40/65:35 และ custom integer ratio เป็นทางเลือกของผู้มีสิทธิ์.

**D4 — RECOMMENDED — PENDING PO APPROVAL:** percentage จำนวนเต็ม **1–99 ต่อฝั่ง** และรวม 100. `0:100` / `100:0` และ percentage decimals เสนอ **DISABLED ในรุ่นแรก**; เป็น recommendation รอ PO ไม่ใช่ข้ออนุมัติ. หากรับรอง extremes ภายหลังต้อง revision CHECK/UI/zero-target rule พร้อมกัน (active item max >0 จึงไม่มี active item ในกลุ่ม target 0). ไม่เพิ่ม percentage decimals โดยไม่มี rounding/scale/residual allocation decision.

คะแนนเต็มเป้าหมายเป็นจุดคะแนน: `COURSEWORK target = 50 × coursework_percent / 100`; `FINAL_EXAM target = 50 × final_percent / 100`. การคำนวณหน่วย 0.01 ด้านล่างเป็นรูป exact ที่เทียบเท่ากัน ไม่มี rounding.

**Exact arithmetic:** max เป็น decimal string ตาม existing `DECIMAL(7,2)` contract, scale ไม่เกินสองตำแหน่ง. Parse แบบ string เป็นหน่วย 0.01; เป้าหมาย term `5000`, COURSEWORK `50 × coursework_percent`, FINAL_EXAM `50 × final_percent` หน่วย 0.01. Bounded integer/string-integer sum/compare; SQL ใช้ DECIMAL SUM. ห้าม binary floating point, `parseFloat`, epsilon. 49.99/50.01 reject; 34.00+16.00 สำหรับ 70:30 reject แม้รวม 50.00; T1 40+10/T2 30+20 ไม่ผ่าน 70:30 แม้ยอดรายปีดูพอดี. Invalid text แสดง “ตรวจค่า” ไม่แทนด้วยศูนย์. Backend max range เดิม 0.01–99999.99 คงเดิม แต่ managed active sum 50 ทำให้ item ที่มากกว่า target publish ไม่ได้.

Section classification เป็น explicit `COURSEWORK` หรือ `FINAL_EXAM`; section หนึ่งมีกลุ่มเดียว. ชื่อ “ก่อนกลางภาค/กลางภาค/หลังกลางภาค/ปลายภาค” เป็นชื่อแสดงผล ไม่ใช่เครื่องมือเดา classification ของ legacy. Authorized user ต้องรับรอง classification จากหลักฐานทางวิชาการ; หลังรับรองแล้ว rename ไม่เปลี่ยน group. ห้ามเปลี่ยนคะแนนเดิมเพื่อให้ตรง ratio.

## 4. D2/D3 — Annual Course Identity and explicit term links

### 4.1 Architecture alternatives

| แบบ | Permissions / integrity | Migration / lifecycle / trade-offs |
| --- | --- | --- |
| A Policy ต่อ `(school,year,classroom,subject_id)` แบบ v1.0 | Scope ชัดและ FK ง่าย แต่บังคับ Subject ID เดียว; ไม่ตอบ requirement ต่าง ID | Additive ได้ แต่กรณี ค 14101/ค 14102 ต้องแก้ subject เดิมหรือแยกสอง policy ซึ่งไม่ใช่ annual authority เดียว; **ไม่แนะนำ** |
| **B Annual Course Identity + explicit term links** | Annual ID อยู่ใน school/year/classroom; authorized confirmation ระบุ actual Offering IDs แต่ละ term; FKscope/term + unique ป้องกัน cross-scope/duplicate | Subject IDs ต่างได้; ขาด term ได้โดยไม่มี fake offering; policy/link audit และ guards เพิ่ม; ไม่ backfill equivalence; **แนะนำ** |
| C Annual pair record มี nullable `term 1_offering_id`, `term 2_offering_id` + ratio โดยไม่มี link table | UI pair ง่ายและลดหนึ่ง table; composite FK แยกสอง slot | Unique แยกแต่ละ column ไม่กัน offering ซ้ำข้าม slots หากไม่ผูก term constant; ต้อง duplicated scoped keys/checks และ publish state/revision ต่อ slot; drafts/sections อ้าง managed offering ได้ยากโดยไม่มี link authority; lifecycle/report queries ซ้ำ; ไม่คุ้มการลด table ใน PP5 |

**D2/D3 recommendation = B — RECOMMENDED — PENDING PO APPROVAL.** เป็น annual course instance ของหนึ่งห้องในหนึ่งปี ไม่ใช่ subject catalog ใหม่ทั้งโรงเรียน. ใช้ stable surrogate `annual_course_id` + server UUID `course_key`; `name_th` เป็น label เช่น “คณิตศาสตร์พื้นฐานรายปี” ไม่ใช่ candidate identity. Annual row รวม ratio policy ในตัว **ไม่เพิ่ม table policy แยก**. ไม่มี `subject_id` ใน annual key; grade_level derive จาก classroom. หลายห้องมี annual identity แยกกัน; การบังคับ ratio โรงเรียนเดียวกันทุกห้องต้อง decision ใหม่.

ตัวอย่างประกอบเท่านั้น: user อาจรับรอง Term 1 Offering ของ ค 14101 คณิตศาสตร์พื้นฐาน 1 และ Term 2 Offering ของ ค 14102 คณิตศาสตร์พื้นฐาน 2 ว่าอยู่ annual course เดียวกัน. ชื่อ/รหัสนี้ไม่ทำให้คู่ถูกต้องโดยอัตโนมัติ; โรงเรียนอื่นอาจไม่จับคู่เลย. ชื่อคล้าย/รหัสต่อเนื่อง/Subject ID ตรงกันก็ไม่อนุญาต auto-pair.

C สามารถทำให้ปลอดภัยได้ด้วย slot constants `term1_no=1` / `term2_no=2` ที่มี CHECK, scoped composite Offering FKs ของแต่ละ slot รวม school/year/classroom/actual subject/term และ UNIQUE Offering ID ต่อ slot. Offering จริงมี term เดียวจึงเข้าอีก slot ที่ constant ต่างกันไม่ได้; UNIQUE ต่อ slot ป้องกันใช้ Offering เดิมใน annual records อื่น. Nullable slot หมายถึง missing โดยไม่สร้าง Offering. ยังต้อง explicit authorized confirmation/audit/locks/live lifecycle และ publish provenance สองชุดบน annual row; immutable governance ยังต้องอยู่กับ Offering ไม่ derive จาก nullable slot. Draft/section ต้อง FK ไป actual Offering แล้ว service ตรวจ annual slot membership เพราะไม่มี generic link candidate key ให้ FK อ้างตรง ๆ. จึงรองรับต่าง Subject IDs อย่างปลอดภัยได้ แต่ duplicated columns, service membership guards และ rollback/query/test complexity ทำให้ความเรียบง่ายโดยรวมด้อยกว่า B สำหรับ PP5; ไม่เลือก C ใน exact proposed schema ของ section 5.

### 4.2 Binding authority and invariants

1. Session tenant/actor เป็น authority; resolve school/year/classroom จริงและ actual offerings รวม inactive candidates สำหรับ review. UI แสดง ID, subject code/name, term, lifecycle และประวัติโครงสร้างตามสิทธิ์; user เลือกและยืนยัน explicit academic relationship พร้อมเหตุผล. ไม่เลือก candidate จากชื่อคล้ายให้อัตโนมัติ.
2. DB unique `(annual_course_id,term_no)` → Term 1/2 ได้ไม่เกินหนึ่ง Offering; unique `(subject_offering_id)` → Offering อยู่ได้หนึ่ง annual course. Scoped FK ของ annual และ Offering ต้องตรง school/year/classroom/term. `subject_id` อยู่ใน **individual link FK** เพื่อจับ live offering identity แต่ไม่ต้องเท่ากับ link อีกฝั่ง.
3. ใช้ existing live unscoped `GRADEBOOK_COMPONENT_MANAGE` เป็นข้อเสนอสำหรับ ratio/link/adoption/publish; default SCHOOL_ADMIN/ACADEMIC_ADMIN มีสิทธิ์ ครูที่มีเฉพาะ scoped score/view ไม่มีสิทธิ์นี้. สิทธิ์ทุก affected scope ตรวจใน transaction อีกครั้ง; owner draft ไม่ได้สร้าง grant. ไม่เพิ่ม permission/role framework. PO ต้องยืนยันว่าบุคคลที่มี permission นี้รับรองความสัมพันธ์เชิงวิชาการได้หรือไม่; หากต้อง authority อื่นให้กลับมาออกแบบก่อน B1.
4. Create/bind/change/archival/status มี before/after audit ใน PDO transaction เดียว พร้อม annual ID, actual offering IDs/subject IDs, expected revision, missing-link acknowledgement และเหตุผล; ไม่มี student score/PII. FK ไม่แทน authorization/academic confirmation/lifecycle checks.
5. ไม่มี link = `TERM_NOT_LINKED`; UI ระบุ “ยังไม่เปิด / ยังไม่ยืนยัน Offering” และ annual incomplete. ไม่อ้างว่าไม่มี Offering จริงจาก absence link หรือค้น Subject ID เดียว. User สามารถยืนยัน “ยังไม่เปิด” ตอนตั้ง policy/review; เก็บ acknowledgement ใน audit และ draft base snapshot. Unrelated offerings ในห้องไม่ถูกนับว่าเป็นคู่. Candidate ที่ user ระบุแต่ยังไม่ยืนยันทำให้ review unresolved ต้องแก้หรือยืนยัน missing ก่อน publish; ห้ามซ่อน history ของ selected candidate.
6. Term 1 publish ได้เมื่อ own link/50/ratio ถูกต้องและอีก term missing ที่รับทราบแล้ว; Term 2 legacy linked ยังคง legacy/incomplete ไม่บังคับ migrate เพื่อให้ Term 1 พร้อม. ไม่สร้าง Offering, section, component หรือ score ของ term ที่ขาด.

### 4.3 Offering/link lifecycle

| เหตุการณ์ | Proposed handling / approval boundary |
| --- | --- |
| เปิด Term 2 ภายหลัง | Authorized user เลือก actual T2 offering ใน scope เดิมและยืนยัน binding ใช้ annual ratio เดิม; live T1 config/history revalidated; annual revision++ ทำให้ drafts stale; T1 ยัง ready หาก totals/classification/lifecycle ตรง แม้ provenance revision เก่ากว่า; T2 ต้อง own draft/adoption/publish |
| Link/unlink/re-pair ก่อน publish | เสนอ explicit reviewed transaction เฉพาะ affected annual courses/offerings **ไม่มี history, published receipt, section หรือ draft dependents**; audit เดิมคงอยู่, annual revisions++ ทั้งต้นทาง/ปลายทาง; ไม่มี blind generic update; D3 ต้อง review |
| มี dependent draft/section หรือ publish/history แล้ว | ห้ามแก้ link identity ผ่าน generic endpoint; re-pair/remediation เป็น **DISABLED รุ่นแรก / future decision** แยกงาน. เก็บ current link ไม่ delete เพื่อ reuse slot; archival ไม่ทำให้ Offering หลุด managed guard |
| Offering เปลี่ยน classroom/subject/term | Existing administration update ถูก scoped link FK RESTRICT และ service guard ปฏิเสธ identity change ที่ linked; แม้ subject ต่างกันระหว่างสอง term ก็ห้ามแก้ subject ของ link เดิมเงียบ ๆ. No-op ที่ lifecycle ไม่ writable ยัง reject |
| Offering/subject/classroom inactive | คง link/history; derive unavailable/read-only แทน complete. Existing authorized Offering status workflow ยังใช้ได้โดยตรวจผลกระทบและ audit; activation rechecks parents. การลบ link เพื่อหลบ guard ไม่อนุญาต |
| Annual identity archive | เก็บ row/links/receipts; ห้าม archive ระหว่างมี writable managed term ในรุ่นแรก; archive ไม่เปิด old endpoints. Exact archival/rebind UX ยัง pending D3 |
| CLOSED year | อ่านตาม live permission; ห้าม annual/ratio/link/draft/publish/adoption mutations แม้ no-op; ไม่มีการ reopen year ในงานนี้ |
| Cross-school/year/classroom | FK + service reject; รหัสเดียวกันไม่ใช่หลักฐาน scope หรือ equivalence |

## 5. D8 — Revisited exact schema / migration proposal

### 5.1 Structural base: six tables versus five tables (ไม่รวม governance)

ทั้งสอง option ใช้ `gradebook_annual_courses` แทน annual policies ของ v1.0 และ `gradebook_annual_course_offerings` เป็น explicit link; ratio อยู่ annual row. Tables ที่เหมือนกัน: annual courses, term links, score sections, structure drafts, publish receipts. ตารางเทียบด้านล่างนับเฉพาะ structural base เดิม; mandatory governance เพิ่มอีก 2 ตารางใน recommendation จึงรวม Option A=8, Option B=7. Option A ยังต้อง ALTER candidate index บน offerings แต่ไม่เพิ่ม component/score columns.

| ประเด็น | Option A — normalized **6 tables** | Option B — simplified **5 tables** |
| --- | --- | --- |
| Membership | `gradebook_component_sections` แยก; one row/component | เพิ่ม nullable `section_id BIGINT UNSIGNED` ใน `gradebook_components` |
| Migrations / existing table impact | Candidate unique index หนึ่งตัวใน offerings + CREATE TABLE หก steps; component/score table ไม่ ALTER | Candidate index เดียว + CREATE ห้า tables + ALTER component column/index/composite FK; hot table อาจ rebuild/lock ตาม server/version |
| Cross-scope integrity / composite FK | Component และ section scoped FKs บังคับ same school/year/offering; missing membership ของ managed ตรวจ service | `(section_id,school_id,academic_year_id,subject_offering_id)` → section candidate; NULL ทำให้ FK ไม่ตรวจ membership สำหรับ legacy จึงยังต้อง service completeness |
| History | ไม่แตะ score-bearing component rows เพื่อเพิ่ม membership; history max rule เดิม | ไม่เปลี่ยน scores/max เช่นกัน แต่ backfill section_id เป็น component update ต้องแยก audit/change scope |
| Atomic publish | Component/section/membership/link/audit/receipt PDO transaction เดียว | เช่นเดียวกัน โดย UPDATE component.section_id รวม metadata/status |
| Partial DDL recovery | CREATE ว่าง แยก steps ตรวจง่าย; failure ไม่ทิ้ง column ใน component | ALTER สำเร็จแต่ ledger ไม่บันทึกต้อง inspect column/index/FK; mixed app versions ต้อง tolerate nullable field |
| Legacy compatibility | No membership สำหรับ unadopted legacy; existing explicit column projections ไม่กระทบ | Existing repository explicit columns ต้องเพิ่ม planner projection; old app ignores nullable column แต่ไม่มี managed guard |
| Queries / performance | Extra indexed join PK/component และ section index; bounded planner 200 items; Gradebook read เดิมไม่เพิ่ม join | ลดหนึ่ง join; component direct section query. ไม่มี benchmark ว่าเร็วขึ้นอย่างมีนัยสำคัญ |
| Maintenance / tests | เพิ่ม repository + FK tests; ลด coupling ของ high-risk component/score authority | ลด repository แต่เพิ่ม component migration/repository regression และ nullable managed integrity tests |
| Rollback | New empty tables reverse FK order; หลังมี data compatible app only | Drop FK/index/column กระทบ table เดิมและอาจทำ membership หาย; หลังมี data compatible app only |
| Future reports/versioning | Current membership ชัด; audit/receipt snapshot เก็บ previous interpretation; **ไม่ใช่ temporal membership table** | Current section_id ชัด; historical reports ก็ต้อง snapshot/versioning เพิ่มเท่ากัน |

**D8 — RECOMMENDED — PENDING PO APPROVAL: เลือก normalized Option A เป็นฐาน 6 ตาราง + governance/cutover 2 ตาราง = 8 ตารางใหม่ ตาม section 5.6; ไม่อนุมัติ 6 ตารางล้วน.** เหตุผลจาก PP5 คือ existing component repository ใช้ explicit columns, มี score FK/history และ accepted status rules อยู่แล้ว; additive independent metadata ลด ALTER ของ hot score-authority table และ recovery coupling. Extra join/table มีขอบเขตเล็กและ index ชัด ไม่จำเป็นต้องสร้าง generic workflow engine. Five-table model เป็นทางเลือกที่ถูกต้องได้ แต่การลดชื่อ table หนึ่งตัวไม่ลด integrity/service/revision tests และมี rollback cost เพิ่ม. หาก PO เลือก B ต้องรับรอง component ALTER เพิ่มก่อน migration.

### 5.2 Exact proposed columns — six-table structural base of recommended eight-table model (proposal ONLY)

ใช้ InnoDB/utf8mb4_unicode_ci ตาม repository. `ID` = `BIGINT UNSIGNED NOT NULL`; primary `id` เป็น AUTO_INCREMENT. `R` = `BIGINT UNSIGNED NOT NULL DEFAULT 1`; `T` = `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`; updated_at service เขียนทุก mutation. `U` = `CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL`, `H` = `CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL`. ทุก field NOT NULL เว้นมี NULL ระบุ. ไม่มี migration file/DDL execution ใน Phase A.

| Table | Exact columns |
| --- | --- |
| `gradebook_annual_courses` | `id ID PK`, `school_id ID`, `academic_year_id ID`, `classroom_id ID`, `course_key U`, `name_th VARCHAR(190)`, `coursework_percent TINYINT UNSIGNED NULL DEFAULT NULL`, `final_percent TINYINT UNSIGNED NULL DEFAULT NULL`, `revision R`, `status VARCHAR(20) DEFAULT 'ACTIVE'`, `ratio_frozen_at DATETIME NULL`, `ratio_frozen_by BIGINT UNSIGNED NULL`, `created_by ID`, `updated_by ID`, `created_at T`, `updated_at T` |
| `gradebook_annual_course_offerings` | `id ID PK`, `annual_course_id ID`, `school_id ID`, `academic_year_id ID`, `classroom_id ID`, `subject_id ID`, `term_no TINYINT UNSIGNED`, `subject_offering_id ID`, `published_annual_revision BIGINT UNSIGNED NULL`, `published_at DATETIME NULL`, `created_by ID`, `updated_by ID`, `created_at T`, `updated_at T` |
| `gradebook_score_sections` | `id ID PK`, `school_id ID`, `academic_year_id ID`, `subject_offering_id ID`, `section_key U`, `name_th VARCHAR(190)`, `classification VARCHAR(20)`, `sort_order SMALLINT UNSIGNED`, `status VARCHAR(20) DEFAULT 'ACTIVE'`, `created_by ID`, `updated_by ID`, `created_at T`, `updated_at T` |
| `gradebook_component_sections` | `component_id ID PK`, `school_id ID`, `academic_year_id ID`, `subject_offering_id ID`, `section_id ID`, `created_by ID`, `updated_by ID`, `created_at T`, `updated_at T` |
| `gradebook_structure_drafts` | `id ID PK`, `draft_key U`, `school_id ID`, `academic_year_id ID`, `subject_offering_id ID`, `owner_user_id ID`, `revision R`, `base_annual_revision BIGINT UNSIGNED NULL DEFAULT NULL`, `base_structure_revision BIGINT UNSIGNED`, `base_snapshot JSON`, `base_snapshot_hash H`, `payload JSON`, `validation_errors JSON`, `status VARCHAR(20) DEFAULT 'EDITING'`, `created_by ID`, `updated_by ID`, `created_at T`, `updated_at T` |
| `gradebook_structure_publish_receipts` | `id ID PK`, `school_id ID`, `academic_year_id ID`, `subject_offering_id ID`, `draft_id ID`, `actor_user_id ID`, `idempotency_key U`, `request_hash H`, `payload_hash H`, `draft_revision BIGINT UNSIGNED`, `annual_revision BIGINT UNSIGNED`, `result_annual_revision BIGINT UNSIGNED`, `published_structure_revision BIGINT UNSIGNED`, `result JSON`, `created_at T` |

`annual_revision` ใน receipt เป็น expected revision ก่อน publish; result revision อาจ +1 หาก freeze ครั้งแรก. `published_annual_revision` ใน link คือ provenance หลัง commit; ไม่บังคับ equality กับ annual revision ปัจจุบันเพื่อคง readiness T1 เมื่อเพิ่ม T2. Governance row ใน section 5.6 เป็น persisted authority ไม่ derive จาก flag/link. Link ไม่มี mode/revision ซ้ำอีกชุด; structure_revision อยู่ governance. Unlinked ก็จำแนก NEW/LEGACY ได้; linked unadopted LEGACY ไม่มี published structure. Annual ratio ทั้งคู่ NULL ได้ก่อน setup; publish ต้องทั้งคู่ non-NULL/valid และ freeze. Ratio freeze คงถาวรแม้ Offering inactive/annual archived; service ไม่อนุญาต clear freeze fields.

### 5.3 Primary/candidate keys, unique constraints, indexes, composite FKs, checks

ทุก new FK เป็น `ON DELETE RESTRICT ON UPDATE RESTRICT`; ไม่มี cascade deletion. ทุก FK tuple ต้องมี leading index; exact index tuples ต่อไปนี้คือ specification ไม่ใช่ SQL migration. ใช้ชื่อ constraint สั้นและไม่ซ้ำเมื่อเขียน DDL ภายหลัง.

| Table | Candidate/unique keys และ indexes เพิ่มจาก PK | Exact FK tuples |
| --- | --- | --- |
| Existing `subject_offerings` | **เพิ่มอย่างเดียว** UNIQUE `(id,school_id,academic_year_id,classroom_id,subject_id,term_no)`; existing business identity key และ `(id,school_id,academic_year_id)` คงเดิม | ไม่แก้ existing FKs/data |
| Annual courses | UNIQUE `course_key`; UNIQUE `(id,school_id,academic_year_id,classroom_id)`; INDEX `(classroom_id,school_id,academic_year_id)`; INDEX `(school_id,academic_year_id,classroom_id,status,id)` | `(classroom_id,school_id,academic_year_id)` → `classrooms(id,school_id,academic_year_id)`; classroom FK พิสูจน์ school/year |
| Term links | UNIQUE `(annual_course_id,term_no)`; UNIQUE `subject_offering_id`; UNIQUE `(subject_offering_id,school_id,academic_year_id)`; INDEX `(annual_course_id,school_id,academic_year_id,classroom_id)`; INDEX `(subject_offering_id,school_id,academic_year_id,classroom_id,subject_id,term_no)`; INDEX `(school_id,academic_year_id,classroom_id,subject_offering_id)` | Annual tuple `(annual_course_id,school_id,academic_year_id,classroom_id)` → annual candidate; Offering tuple `(subject_offering_id,school_id,academic_year_id,classroom_id,subject_id,term_no)` → new Offering candidate; `(subject_offering_id,school_id,academic_year_id)` → governance candidate (INDEX เพิ่ม tuple นี้); **ไม่มี subject equality ข้ามสอง links** |
| Sections | UNIQUE `(subject_offering_id,section_key)`; UNIQUE `(id,school_id,academic_year_id,subject_offering_id)`; INDEX `(subject_offering_id,school_id,academic_year_id)`; INDEX `(school_id,subject_offering_id,status,sort_order,id)` | `(subject_offering_id,school_id,academic_year_id)` → term-link candidate |
| Membership | INDEX `(component_id,school_id,academic_year_id,subject_offering_id)`; INDEX `(section_id,school_id,academic_year_id,subject_offering_id)`; INDEX `(school_id,subject_offering_id,component_id)` | Component tuple → existing `gradebook_components(id,school_id,academic_year_id,subject_offering_id)`; section tuple → section candidate. PK component_id = one current membership/component |
| Drafts | UNIQUE `draft_key`; UNIQUE `(school_id,subject_offering_id,owner_user_id)`; UNIQUE `(id,school_id,academic_year_id,subject_offering_id)`; INDEX `(subject_offering_id,school_id,academic_year_id)`; INDEX `(school_id,subject_offering_id,status,updated_at)` | `(subject_offering_id,school_id,academic_year_id)` → governance candidate; draft ไม่ต้องมี annual link |
| Receipts | UNIQUE `(id,school_id,academic_year_id,subject_offering_id)`; UNIQUE `(school_id,subject_offering_id,idempotency_key)`; UNIQUE `(draft_id,draft_revision)`; INDEX `(draft_id,school_id,academic_year_id,subject_offering_id)`; INDEX `(school_id,subject_offering_id,created_at)` | Draft tuple → draft candidate; same offering guaranteed through draft governance FK |

Actor references: ทุก `created_by`, `updated_by`, `owner_user_id`, `actor_user_id`, nullable `ratio_frozen_by` มี individual INDEX + FK → `users(id)`. User FK ไม่พิสูจน์ tenant/live grant; service ต้องตรวจ authenticated context. UUID/hash canonical lower-case hex ตรวจ service; SQL CHECK ไม่แทน JSON envelope validation. ชื่อรายวิชารายปี/code ไม่ unique เพื่อไม่บังคับ academic equivalence จากข้อความ; Offering uniqueness เป็นตัวกัน duplicate pairing.

Exact CHECK proposal:

- Annual: ratio ทั้งคู่ NULL หรือทั้งคู่ NOT NULL และ BETWEEN 1 AND 99/sum=100 (ใช้ explicit IS NULL/IS NOT NULL branches ป้องกัน SQL UNKNOWN ผ่าน CHECK); revision>0; status IN (`ACTIVE`,`ARCHIVED`); freeze fields ทั้งคู่ NULL หรือทั้งคู่ NOT NULL; frozen implies ratio non-NULL. Publish ปฏิเสธ ratio NULL.
- Link: term_no IN (1,2); published_annual_revision/published_at ทั้งคู่ NULL หรือทั้งคู่ NOT NULL และ published_annual_revision>0. Governance state PUBLISHED ต้อง link provenance ครบ ตรวจใน transaction; ไม่มี default LEGACY จาก link.
- Section: classification IN (`COURSEWORK`,`FINAL_EXAM`); status IN (`ACTIVE`,`INACTIVE`); sort_order อยู่ 0–65535 ตาม unsigned type.
- Draft: revision/base_structure_revision>0; base_annual_revision IS NULL หรือ >0; status IN (`EDITING`,`PUBLISHED`,`ARCHIVED`); native JSON validity. NULL annual base ต้อง snapshot ว่า unbound/unconfigured; publish deny จน bind/setup/rebase/review.
- Receipt: draft_revision/annual_revision/result_annual_revision/published_structure_revision>0; result_annual_revision >= annual_revision.

Service-only invariants: managed active component ทุกตัวมี membership ใน active section หนึ่งส่วน; active sections ไม่ว่าง; inactive member retained ไม่รวม totals; term active sum=5000/class targets exact; governance PUBLISHED implies annual freeze + valid own link + receipt; revision/provenance values refer to real committed states; linkage/parent lifecycle และ permission live; history max immutable. CHECK/FK ไม่สามารถตรวจ cross-row aggregate หรือ academic truth เหล่านี้. ต้องตรวจ target DB version/SQL mode/CHECK enforcement/JSON/collation/index limits บน deployment จริงระหว่าง authorized B1 ก่อน deployment และ negative inserts ใน disposable DB; Phase A ไม่อ้างว่า DDL ผ่านแล้ว.

**Structural Option B exact delta หากเลือก 5+2=7 new tables:** คงอีกห้าตาราง/columns/keys/checks ข้างต้นทั้งหมด; ไม่สร้าง membership table. เพิ่ม `section_id BIGINT UNSIGNED NULL DEFAULT NULL` ลง existing components, INDEX `(section_id,school_id,academic_year_id,subject_offering_id)`, composite FK tuple นั้น → section candidate, RESTRICT ทั้งสองทิศ. Existing component PK/candidates/code unique/max/history FKs ไม่เปลี่ยน; NULL ใช้เฉพาะ legacy/unassigned, managed active non-NULL ตรวจ service. Section ID/status updates bump governance structure_revision และ audit ใน atomic transaction. ไม่เพิ่ม student-score column/trigger; rollback/recovery ต้องรวม ALTER นี้.

### 5.4 Draft payload / stable identities / revision ownership

`payload.schema_version=1`; `sections[]` มี server-validated stable UUID `section_key`, name/order และ explicit classification (NULL ได้ใน partial draft). `items[]` มี stable UUID `item_key`, existing `component_id` (NULL สำหรับใหม่), existing code read-only, name/max decimal **string**/order/status intent. New code allocate ภายใต้ offering lock ด้วย unique rule เดิมรวม inactive/DB collation; receipt mapping `item_key → component_id`. ไม่รับ score/enrollment/student identities. IDs ไม่พิสูจน์ permission.

Unknown/duplicate component/item/section keys reject. Full proposed active snapshot ต้องครอบคลุม existing active components ทุกตัวหรือมี explicit INACTIVE intent; omission ไม่ใช่ delete/deactivate. Existing ลดช่อง/ลด section เป็น reviewable status/membership intent; unsaved new item ลบจาก draft ได้. Existing inactive/history อ่านยุบได้และ references retained; ไม่บังคับ copy archive หลายพัน item ลง payload เพื่อเกิน 200: base_snapshot ใช้ digest + counts ของ complete inactive configuration (ไม่รวมคะแนน), live locking validation fetch จริง; payload มี inactive item เฉพาะที่ explicitly เปลี่ยน. New classification/max/status proposals ใช้ section 8 gate.

Annual row revision เป็นเจ้าของ label/ratio/status/link changes และ first freeze; ทุก link writer bump annual revision ใน transaction เดียว. Governance structure_revision เป็นเจ้าของ component/section/membership/config changes ทั้ง linked/unlinked; legacy component endpoints bump revision และ audit เมื่อ governance foundation ติดตั้งครบ. Existing unlinked inventory ใช้ full snapshot fingerprint ตอน bootstrap/bind; ไม่มี link revision ที่ diverge จาก governance. Draft revision เป็น OCC ของ row/payload/base reset/status **เพิ่มตลอด ไม่ reset เป็น 1**; owner/offering immutable. Receipts immutable หลัง commit.

`base_snapshot` เก็บ nullable annual ID/ratio/revision/freeze (ก่อน bind/ratio ให้ explicit NULL marker), actual linked Offering IDs และแต่ละ subject_id/term/scope/lifecycle, unlinked-term marker/acknowledgement, governance origin/state/revisions และ cutover epoch ของสองฝั่ง, exact active component/section/membership configuration และ history booleans, inactive configuration/history-presence digests ของ complete retained components. History digest รวม NULL-row existence ไม่รวม score values/PII. `base_snapshot_hash` = canonical SHA256 ไม่ใช้ timestamps วินาทีเป็น authority. ไม่เก็บคะแนน/นักเรียน; live snapshot/history ตรวจใหม่ทุก publish. New first-history เปลี่ยน boolean แม้ structure revision เดิมต้อง conflict/review ใหม่. Score values ที่เปลี่ยนระหว่าง preview ไม่ใช้ stale snapshots ตัดสินผลรวมคะแนนนักเรียน.

### 5.5 DDL sequence / partial failure recovery / compatible rollback (ไม่ execute)

1. หลัง **B1 authorization แยก** และ D2–D10/exact schema approval: read-only authorized inventory (offering scope/status/history presence/configuration ไม่ export PII), backup + disposable restore rehearsal, target DB version/SQL mode/native JSON/CHECK enforcement/index length/negative FK proof. ไม่มี live DB inventory/writes ใน Phase A.
2. Ordered additive DDL: **S1** Offering unique candidate `(id,school_id,academic_year_id,classroom_id,subject_id,term_no)`; **S2** cutovers; **S3** governance ไม่มี receipt FKs ชั่วคราว; **S4** annual courses; **S5** links; **S6** sections; **S7** memberships; **S8** drafts; **S9** receipts พร้อม scoped receipt candidate; **S10** ALTER governance เพิ่ม first/latest receipt FKs. ทุก PK/unique/index/CHECK/actor FK ใน table step นั้น. Final approved schema ต้องครบ S10 ก่อน readiness attestation; ห้ามเปิดบน intermediate schema. Option B structural base เปลี่ยน S7 เป็น component ALTER และรวม 7 ตารางใหม่แทน 8; recommendation คง A. ใช้ SQL runner/ledger เดิม ไม่สร้าง framework ใหม่.
3. MySQL DDL implicit commit: process อาจตายหลัง DDL ก่อน ledger INSERT. หยุด activation; compare SHOW CREATE TABLE/information_schema ของ columns/defaults/indexes/FKs/CHECKs กับ approved step และ ledger. Exact completed step ให้ authorized recovery บันทึกเฉพาะ step หลังตรวจ; partial/mismatch ให้ reviewed repair/drop เฉพาะ empty new objects/missing constraints แล้วตรวจซ้ำ. ไม่ blind IF NOT EXISTS และไม่กล่าวว่า transaction rollback DDL ได้. Failure S10 ห้ามถือ schema complete เพราะ CREATE ทั้งแปดผ่านแล้ว.
4. B1 deploy schema **ไม่ activate/ไม่เปลี่ยน score-entry behavior**. Classification bootstrap/creation integration/score + component guards/revision hooks ต้อง rollout ตาม B3 พร้อม B4 setup/publish UI และ B5 release gates; exact activation sequence อยู่ 5.6.4. Deploy guard-capable release ทุก worker/HTTP/CLI ก่อน ACTIVE; mixed old worker เป็น blocker.
5. ก่อน domain data/activation และหลัง verify empty+backup: remove governance receipt FKs → receipts → drafts → memberships → sections → links → annual courses → governance → cutovers → new Offering candidate index. ห้าม drop objects ที่ถูกใช้อยู่. หลัง bootstrap/drafts/adoption/publish/audit/receipts หรือ activated boundary: **keep all provenance/data/schema**, compatible app rollback รักษา authoritative guards/creation classification; planner อาจอ่านอย่างเดียวแต่ NEW ที่ยังไม่พร้อมยัง DENY พร้อมเหตุผล. Pure baseline 186dfb5 ไม่ safe rollback หลัง activation.
6. Partial DML bootstrap resume จาก scoped PK/hash/audit ภายใต้ school lock, ตรวจ exact coverage ไม่มี origin overwrite; activation/audit transaction rollback ร่วมกันเมื่อ failure. หาก activation committed แต่ response หาย ดู authoritative epoch ก่อน retry; ห้าม clear ACTIVE/relabel NEW→LEGACY. DB disaster restore เป็น separate authorization พร้อม recovery boundary/traffic freeze; ไม่ restore เฉพาะ scores หรือ backfill max/history.

### 5.6 D10 — persistent governance and school cutover (RECOMMENDED — PENDING PO APPROVAL)

#### 5.6.1 Classification alternatives / ranked recommendation

| Strategy | Score guard / unlinked Offering | Migration / creation / activation race | Scope / integrity / imports | Recovery / maintainability / actual added tables |
| --- | --- | --- | --- | --- |
| **A Offering columns + cutover table (rank 2)** | Persist origin/state/revision/epoch/adoption/receipt references on subject_offerings; covers no annual link | ALTER populated academic table หลาย columns/indexes/FKs/CHECKs; create sets columns in same transaction; school lock + cutover required | scoped checks/FKs; defaults must be UNCLASSIFIED fail-closed, never LEGACY; import/admin must classify | 6 structural + 1 cutover = **7 new tables** plus substantive Offering ALTER; recovery/mixed-version coupling สูงกว่า B; maintainable if approved rebuild cost |
| **B Independent governance + cutover (rank 1, recommended)** | one mandatory scoped row/Offering regardless annual link; missing row = UNCLASSIFIED, not LEGACY; authoritative service checks | 8 CREATE tables + Offering candidate-index ALTER + governance receipt-FK ALTER; creation inserts Offering+governance+audit in one transaction; school lock serializes cutover | PK offering ID/scoped composite FKs, school epoch; anti-join detects missing import/seed rows; scoped annual binding separate | **8 new tables**; metadata join/coverage invariant เพิ่ม แต่ไม่เพิ่ม hot component/score columns; explicit recovery/provenance and future revisions ชัด |
| **C Cutover + immutable creation provenance (rank 3, not proven for baseline)** | guard derives origin from server provenance/epoch; cannot use created_at alone; separate published metadata still needed | baseline created_at DATETIME ไม่เป็น immutable boundary และ raw INSERT paths ไม่รับ epoch; ต้องเพิ่ม provenance/constraints/creation hooks ก่อนจะพิสูจน์ได้ | school-wide boundary/all years; nullable/forged old date/unknown creator fail-closed; ID high-water/name/link invalid | nominal **7 new tables** if provenance columns on Offering + cutover; also Offering ALTER + published-state authority changes. ไม่เลือกเพราะ baseline provenance ไม่พอ; timestamp race/backfill correctness ยัง unproven |
| **D Merge governance into expanded links (rank 4)** | nullable annual link row created for every Offering can work; original six-table link alone cannot | add separate cutover = **7 new tables**, repurpose link schema/OCC/defaults substantially; school lock mandatory | nullable annual scope/term slots need careful unique/FK/service completeness; imports coverage still required | ลด table แต่ปะปน creation provenance กับ academic linkage; annual/course unlink risk สูง; no-link must never bypass. ไม่คง six-table count ด้วย absence-link heuristic |

ทุกแบบต้อง authoritative creation+score/component service guards, live permission/lifecycle, atomic publish และ durable activation ไม่ใช่ feature flag. Table count เป็น **จำนวนตารางใหม่ของ Task 6V** ไม่ใช่จำนวนทั้ง repository. Recommendation B ยอมรับว่าขยายจาก 6 เป็น 8 เพื่อแก้ enforcement ก่อนมี annual link; D8/D10/exact DDL ยังไม่ approved. Existing scores/components/audits ไม่ถูก backfill/change.

#### 5.6.2 Exact governance schema additions and cross-table changes

ใช้ ID/R/T/U/H และ NOT NULL convention ใน 5.2; PK ของสองตารางนี้ **ไม่ AUTO_INCREMENT**. Fields ต่อไปนี้ทุกตัว exact; NULL/default ต้องตามนี้ (field ที่ไม่ระบุ default ต้องส่งโดย service).

| New table | Exact columns |
| --- | --- |
| `gradebook_school_cutovers` | `school_id ID PK`, `epoch_key U`, `phase VARCHAR(20) DEFAULT 'PREPARING'`, `revision R`, `inventory_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL`, `inventory_count BIGINT UNSIGNED NULL DEFAULT NULL`, `verified_release VARCHAR(64) NULL DEFAULT NULL`, `activation_reason VARCHAR(1000) NULL DEFAULT NULL`, `activated_at DATETIME NULL DEFAULT NULL`, `activated_by BIGINT UNSIGNED NULL DEFAULT NULL`, `created_by ID`, `updated_by ID`, `created_at T`, `updated_at T` |
| `gradebook_offering_governance` | `subject_offering_id ID PK`, `school_id ID`, `academic_year_id ID`, `cutover_epoch_key U`, `origin VARCHAR(20)` (no default), `state VARCHAR(20)` (no default), `classified_via VARCHAR(20)` (no default), `classification_reason VARCHAR(1000)`, `classified_by ID`, `classified_at T`, `classification_hash H`, `revision R`, `structure_revision R`, `first_publish_receipt_id BIGINT UNSIGNED NULL DEFAULT NULL`, `latest_publish_receipt_id BIGINT UNSIGNED NULL DEFAULT NULL`, `adopted_at DATETIME NULL DEFAULT NULL`, `adopted_by BIGINT UNSIGNED NULL DEFAULT NULL`, `adoption_reason VARCHAR(1000) NULL DEFAULT NULL`, `adoption_evidence_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL`, `created_at T`, `updated_at T` |

| Table | Exact keys/indexes | Exact FKs (RESTRICT update/delete) |
| --- | --- | --- |
| Cutovers | PK school_id; UNIQUE epoch_key; UNIQUE `(school_id,epoch_key)`; INDEX `(phase,school_id)`; individual indexes created_by/updated_by/activated_by | school_id → schools(id); actor fields → users(id) |
| Governance | PK subject_offering_id; UNIQUE `(subject_offering_id,school_id,academic_year_id)`; INDEX `(school_id,cutover_epoch_key)`; INDEX `(school_id,academic_year_id,origin,state,subject_offering_id)`; INDEX `(first_publish_receipt_id,school_id,academic_year_id,subject_offering_id)` and same tuple with latest; individual indexes classified_by/adopted_by | `(subject_offering_id,school_id,academic_year_id)` → subject_offerings(id,school_id,academic_year_id), existing candidate; `(school_id,cutover_epoch_key)` → cutovers candidate; each first/latest receipt scoped tuple → receipts(id,school_id,academic_year_id,subject_offering_id) after S9; actor fields → users(id) |

CHECKs: cutover phase IN(PREPARING,ARMED,ACTIVE), revision>0; ARMED/ACTIVE require non-NULL inventory_hash/count/verified_release; ACTIVE requires non-NULL activated_at/by/reason; PREPARING/ARMED require all activation fields NULL. Governance origin IN(PRE_ACTIVATION,POST_ACTIVATION); state IN(LEGACY,SETUP_REQUIRED,PUBLISHED); classified_via IN(INVENTORY,CREATE,IMPORT); revisions>0. PRE origin allows LEGACY/PUBLISHED; POST origin allows SETUP_REQUIRED/PUBLISHED (never LEGACY). PUBLISHED requires both receipt IDs non-NULL; other states require both NULL. PRE+PUBLISHED requires all four adoption fields non-NULL; all other origin/state combinations require all adoption fields NULL. Use explicit NULL branches to prevent SQL UNKNOWN bypass; hash/UUID formats and nonblank reasons validated server-side. Classification fields/origin/epoch immutable after initial commit; service rejects changes, audit records provenance. FK/CHECK cannot prove immutability, mandatory child-row existence, approved academic evidence or exact totals; no technical-lock claim.

Classification hash covers scoped Offering identity at classification, origin, epoch, source and audit reason; **not scores/PII**, not inferred created_at/ID threshold. INVENTORY only PRE before activation; CREATE from authorized service; IMPORT through same classified transaction with logged source. New schools must get cutover row/coverage via approved provisioning before enforcement-enabled create; absent cutover on an enabled deployment cannot default to pre-activation. Register PREPARING school explicitly; school-scoped ACTIVE covers **all years including future offerings in old years** (CLOSED writes remain denied). No per-year flag exemption.

One authority: governance stores origin/state/structure_revision; link stores annual relation and published annual revision/time only. `MANAGED_NEW` display = POST+PUBLISHED; `LEGACY_ADOPTED` = PRE+PUBLISHED; pre-activation Legacy = PRE+LEGACY; new requiring setup = POST+SETUP_REQUIRED. “Managed invalid” is derived not-ready over PUBLISHED, **never downgrade** to Legacy. UNCLASSIFIED is missing/mismatched row/cutover corruption and denies mutation pending audited recovery after activation; not a silently-created Legacy row.

Draft FK now points to scoped governance, with nullable base_annual_revision; supports unlinked/pre-ratio partial draft with explicit snapshot markers. Sections still require term link and are live only after valid publish. Annual ratio fields NULL before configuration; binding/ratio setup causes revision and explicit draft rebase/review. No fake components/annual term. First/latest receipt scoped FKs create a deployment dependency only: insert receipt for valid operation first, then update governance and link within same publish transaction; failure rolls all back. First receipt immutable; latest pointer updates at each publish; first PRE publication sets adoption provenance in same transaction. Receipts carry immutable result snapshot/governance origin/epoch/previous-result revisions/academic evidence digest in JSON (no students); audit carries full approved reason/evidence references. Cross-row latest receipt/state/link/freeze correspondence is live transaction validation, not assumed from FK alone.

#### 5.6.3 Creation, activation and score-write boundary

Authorized creation transaction: school FOR UPDATE → cutover row FOR UPDATE → existing year/classroom/subject locks → create Offering → insert governance scoped row with immutable epoch/origin → creation+classification audit → commit. PREPARING/ARMED creates PRE+LEGACY **because they commit before activation under same school lock**; ACTIVE creates POST+SETUP_REQUIRED even without annual link. Governance/audit failure rolls back Offering; no unclassified successful response. Existing uniqueness/retry semantics retained: duplicate retry does not make new row, overwrite origin or convert existing Legacy; authorize and inspect persisted Offering before reporting current duplicate. Creation idempotency upgrade is not silently introduced.

Activation takes same school first lock then cutover row; pre-activation in-flight compliant creation either commits before boundary and is inventoried PRE, or waits and creates POST after ACTIVE commit. Compliant score writes similarly finish before boundary or recheck after it; existing PRE Offering remains Legacy, POST never writes until valid publish. Boundary is committed epoch+phase transition, not browser/server wall time. Do not schedule activation between old worker deployments: an old worker can still bypass guards. HTTP/CLI queue drains or maintenance pause + release attestation/rollback target verification are required operational gates. Arbitrary privileged SQL is outside service authorization; do not claim schemas mechanically prevent an administrator writing scores. Unregistered raw Offering insertion after ACTIVE remains UNCLASSIFIED and fails service writes until audited classification as POST; cannot use inventory to relabel it PRE.

#### 5.6.4 Safe activation sequence and recovery

1. Read-only inventory authorized scoped IDs/configuration/history-presence; preserve pre-activation behavior and byte-equivalent score/max/history data. Save counts/digests/reconciliation evidence without PII. This is a future controlled operation, not done in Phase A.
2. Deploy additive S1–S10 schema; verify exact keys/CHECKs/FKs/ledger and disposable restore. Create PREPARING school epoch records by explicit authorized provisioning; no enforcement activation in B1.
3. While PREPARING deploy authoritative creation classification on every creator first and drain old creators; then bootstrap exact existing Offering IDs under school lock and record PRE+LEGACY origin/audit. Reconcile full coverage before enabling component/revision and score guard paths on every worker; draft/adoption/publish remain unavailable during incomplete guard deployment. Existing Legacy score/component behavior remains as before while the inactive deployment preparation finishes; no readiness enforcement after B1 alone. Bootstrap may use bounded chunks, then final school-locked reconciliation. No max/score/status/audit-history rewrites and no auto pair. Missing rows during incomplete preparation block enabling the guarded rollout, not evidence of Legacy. Once coverage is attested and guarded paths enabled, any unexpected missing row is safe-denied/reconciled before activation; never flip ACTIVE early or run score traffic through a partially deployed Managed publish release.
4. Under school lock verify complete Offering→governance scoped coverage, receipts/link invariants, creation/import/admin callers and absence of old workers. ARMED means verified release/coverage at prior snapshot only; writes still obey pre-activation behavior; pre-activation creations increment cutover revision/audit and make cached inventory hash/count stale (keep non-NULL fields to satisfy CHECK), final activation recomputes current coverage/digest. Stored hash is not a current-readiness grant. Existing Legacy regression must work.
5. Verify every single/batch/raw component/direct service entry denies forced POST+SETUP_REQUIRED test fixtures; ready setup/Draft/review/publish + blocked-score feedback/action via B4 available; B5 required gates and PO visual/release approval. Flags can hide rollout UI but cannot disable durable guards.
6. Activation transaction locks school/cutover, reconciles newly created rows and coverage with current reads, recomputes inventory hash/count, verifies authorized activation/reason/release, transitions ARMED→ACTIVE, increments revision and audits atomically. Any failed check aborts with no activation. No data dates/high-water heuristic. Clients already open re-read readiness on POST.
7. Verify post-boundary compliant creates POST+SETUP_REQUIRED and single/batch/old setup/Workspace/direct service writes denied before publish. Verify old PRE Legacy unchanged. Missing classification/corrupt row safe deny; alert/recovery without Legacy relabel. Verification writes only isolated synthetic fixtures; production sampling read-only.
8. Recover partial schema/bootstrap before boundary by reviewed resume; if ACTIVE committed keep epoch immutable and guards deployed through app rollback. Disable creation/publish temporarily if supported workflow unavailable, with clear feedback, **never** revert to Legacy writes for new offerings. Resume after reconciliation; uncertain activation query current row/audit before retry. No activation reversal API in first release.

Feature activation requires **B3 enforcement + B4 supported workflow + B5 acceptance**, independent PO release authorization, and coverage proof. B1 alone cannot activate mandatory enforcement. School serialization is coarse and existing: monitor transaction duration/deadlocks with workload evidence before release; no unapproved finer-grained replacement.

## 6. D7 — Server draft storage and practical limits

| แบบ | ข้อดี | ข้อจำกัด |
| --- | --- | --- |
| Browser memory only | editing เร็ว/no schema | Reload/resume/OCC ไม่ครบ |
| Local Storage | resume ในเครื่องเดียว | Shared-device/tenant stale data ไม่ใช่ authority; **ไม่ใช้** |
| **Server JSON draft + relational active model** | partial values/reload/revision/live permissions โดยไม่เปลี่ยน active | Bounded envelope/IDs/validation/OCC จำเป็น |
| Normalized draft section/item tables | Relational references | Incomplete values ยังต้อง validation; tables/multi-item revision เพิ่มโดยไม่มี requirement |

**D7 — RECOMMENDED — PENDING PO APPROVAL:** server JSON; edit memory ก่อนแล้ว Save Draft. Save partial 42.00/missing classification/invalid max text ได้โดย validation_errors ผูก stable key แต่ invalid UTF-8/envelope/foreign IDs/size/lifecycle/permission reject. Draft save ไม่เปลี่ยน active components ไม่ส่ง score POST.

| Limit / ownership choice | Proposal / rationale |
| --- | --- |
| Backend safety ceiling | **100 sections / 200 proposed items / 1 MiB UTF-8 payload** คงเดิม; bound JSON depth/field lengths/duplicate keys server-side; counts ไม่ใช่ score batch 2,000 limit |
| Practical UI | ช่วงออกแบบใช้งานหลัก (ยังไม่มี usage survey) 3–8 sections/10–40 items; quick “เพิ่มหลายช่อง”ครั้งละ 1–20; soft warning เมื่อ>12 sections หรือ>60 items ให้เลือกแสดงต่อได้จน backend ceiling; ไม่ truncate imported legacy หรือ history. ประเมิน 390/1440 performance ก่อนรับรอง ไม่ตั้ง hard UI limit ต่ำกว่า backend โดยไม่มีหลักฐาน |
| One draft per owner/offering | UNIQUE เดิมรองรับ private editing หลาย owner, กันสร้างร่างซ้ำและสอง tabs; one row reusable/resume ง่าย. **แนะนำ** |
| One draft per offering | Row น้อยกว่าแต่ต้อง current-owner arbitration/takeover หรือกันผู้มีสิทธิ์คนอื่น; เสี่ยง overwrite และกลายเป็น collaboration/transfer requirement; ไม่เลือก |
| Multiple drafts per owner/offering | ต้อง draft picker/version branching ไม่มี requirement; ไม่เลือก |

Owner จาก session ต้องมี live component manage เพื่ออ่าน/แก้/publish ไม่ใช่ grant จาก ownership; user อื่นทำร่างตนเองจาก active ไม่มี shared edit/transfer. CLOSED year ยังอ่าน saved ตามสิทธิ์แต่ save/publish deny; archived draft retained อ่านได้ไม่ mutation until authorized resume.

Save ใช้ expected_revision (0 สำหรับ create); locking read+compare+increment หรือ CAS; unique create-race/stale second tab → **409** ไม่ overwrite. Local edit generation ป้องกัน response เก่าล้าง dirty ที่ใหม่กว่า. Reload ดึง persisted+live stale status; no auto rebase/merge. Publish ใช้ persisted exact revision เท่านั้น; unsaved ต้อง save สำเร็จก่อนใน separate draft request แล้วหนึ่ง publish request.

PUBLISHED draft row ไม่ delete; “เริ่มร่างต่อ” reset base/payload จาก live ผ่าน expected revision และ revision++ โดย receipt เดิมเก็บ immutable payload hash/result/review snapshot ไม่พึ่ง payload ปัจจุบัน. Partial draft ไม่ ready; saved valid ก็เป็น preflight ไม่ reserve write. Navigation เตือน unsaved, beforeunload best-effort โดยมือถืออาจไม่เรียก; network fail คง memory+persisted last save ไม่สัญญากู้ unsaved ได้ทุกครั้ง.

## 7. Atomic publish / locking / idempotency — revised design

### 7.1 Proposed transport (ไม่มี route ใหม่ใน Phase A)

- GET `/gradebook/{offeringId}/setup`: existing entry point แสดง annual identity/actual links/readiness/legacy/draft.
- POST `/gradebook/{offeringId}/annual-policy`: explicit create/update ratio/bind intent + expected annual revision; annual ID/Offering IDs re-resolved from session scope. Generic update ไม่อนุญาตแก้ frozen ratio หรือ historical re-pair.
- POST `/gradebook/{offeringId}/structure-draft`: create/save expected revision (0 สำหรับ create). Resume ผ่าน setup GET.
- POST `/gradebook/{offeringId}/structure-draft/validate`: preflight persisted exact revision ไม่ reserve publish.
- POST `/gradebook/{offeringId}/structure-draft/publish`: draft ID/revision, expected annual revision, expected structure revision, exact review hash/acknowledgements และ idempotency key; ไม่รับ scores/student IDs/client totals เป็น authority.
- GET `/gradebook/{offeringId}/structure-draft/publish-result/{idempotencyKey}`: authorized read-only receipt lookup หลัง uncertain outcome.

Mutation ต้อง protected SCHOOL context, existing live unscoped `GRADEBOOK_COMPONENT_MANAGE`, CSRF; tenant/actor ไม่มาจาก browser. เสนอ 403 permission, consistent 404 foreign/missing, 419 CSRF, 409 revision/review/key conflict, 422 invalid/lifecycle/history compatibility, 5xx/transport uncertain. AJAX ส่ง authoritative revision/hash/result; no-JS forms + PRG ต้องคง partial draft/errors. Route contracts ทั้งหมด pending D8/D9.

Preflight review hash ครอบคลุม persisted payload hash, annual/link identities/revisions, base/current configuration diff, history presence, impact category และ explicit acknowledgement list. ไม่เก็บคะแนน/PII ใน draft/receipt; history warning ใช้ component/aggregate impact ไม่แสดง student rows. Publish คำนวณ review ใหม่ภายใต้ locks; เปลี่ยน diff/history/identity →409 ให้ review ใหม่ ไม่ใช้ checkbox ครั้งเก่ารับรอง diff ใหม่.

### 7.2 Single transaction and stable lock order

ไม่ใช้ browser loop ของ component POSTs และไม่เรียก public `create/update/changeStatus` ซ้อนกัน เพราะแต่ละ method เปิด transaction เอง. เสนอ `GradebookStructurePublishService` orchestration + private/internal transaction-owned mutation helpers/repositories ใช้ validation/history/audit เดิม. ไม่มี public unchecked bypass/browser flag. Term ที่แก้มี active writes; อีก term มี locking validation เท่านั้น ยกเว้น shared annual freeze/provenance/revision และ audit.

1. Verify session, route permission, CSRF, bounded identifiers; begin PDO transaction. Lock active **school first** เหมือน academic/component/score services เดิม → cutover row; verify schema/epoch/coverage state.
2. Discovery ภายใต้ school lock: resolve current annual ID และ actual linked offerings ทั้งสอง term รวม inactive; lock year → all affected offerings **ascending ID** → classroom → distinct subjects ascending ID. Confirm requested offering/live subject_id/term/school/year/classroom ตรง link. Missing link ตรวจใหม่จาก annual ไม่ค้น Subject ID เดียวเพื่อเดาคู่.
3. Lock governance rows ของ affected offerings ascending ID → affected annual courses ascending ID (ปกติหนึ่ง) → links ascending offering ID → draft. Annual/link writers ทุกตัวใช้ school boundary และ order เดียวกัน; existing offering administration บาง method locks offering ก่อน year แต่ school lock ร่วม serialize จึงไม่สมมติว่า order ภายในเหมือนกันทุก service. Re-pair ที่ยังไม่มี dependents ต้อง lock ทั้งต้นทาง/ปลายทางตาม order เดียว.
4. Live locking authorization: granting user/membership/assignment/role/role_permission rows ตาม predicates ของ existing **unscoped component manage** ทั้ง scope ที่จะเปลี่ยน. Existing `hasSubjectOfferingPermissionForUpdate()` แสดง pattern ที่ lock grant ได้แต่รวม scoped grants; หาก reuse ต้องตรวจ unscoped requirement ให้ตรง route ไม่ทำให้ score grant publish ได้. Middleware nonlocking อย่างเดียวไม่พอ; review revocation lock order และ two-connection tests ก่อน B3. ไม่เพิ่ม permission seed.
5. Lookup receipt ภายใต้ scope/auth locks ด้วย key. **ถ้า identical committed operation คืน stored result ก่อนตรวจ staleness ของ draft/policy ปัจจุบัน** ที่ถูก publish/reset ไปแล้ว; actor/hash ต่าง→409. ไม่ต้อง validate current payload/history ซ้ำเพื่อทำ operation เดิม; ยังต้อง current permission/CSRF/lifecycle ของ mutation. ปี CLOSED ดูผลผ่าน authorized GET ไม่ POST ซ้ำ. New operation จึงไปขั้นถัดไป.
6. ตรวจ draft owner/status/expected draft revision/base annual revision/base structure revision/live binding/governance origin/state/epoch/parents; lock components ascending ID ทั้งสอง term → sections ascending ID → memberships ascending component ID; includes relevant inactive/history. Resolve IDs/keys/full active coverage/code collisions ตาม DB collation.
7. Current **locking score-history reads** หลัง component locks ทั้งสอง term; any retained row แม้ NULL/0/cleared/inactive เป็น history. Existing school/offering/component lock serialization กัน first score ระหว่าง validation กับ write. Compare base snapshot+live review: first history race/config change ทำ 409 หรือ history rejection ไม่ publish ด้วย stale evidence. Parent/lifecycle/history ของ legacy อีก term ถูกอ่านด้วยแต่ไม่ force adoption.
8. Validate proposed current term active sum **5000** และ exact classification targets, active sections/membership/status, history max unchanged, zero score-row deletion/rewrite, compatibility matrix และ explicit review. Other MANAGED term ต้องยัง valid ตาม ratio เดิม; linked LEGACY term ยัง incomplete และ preserved; missing term ไม่ error ของ current term. Inactive other term ทำ annual unavailable ไม่ทำให้อ่านว่าครบ 100; current term publish ที่ ratio เดิมไม่ reactivate อีก term.
9. Apply เฉพาะ allowed current term component/section/membership changes; preserve existing IDs/history max/code authority. Legacy accepted status ไม่เปลี่ยน; Managed allowed status ใช้ narrow D6 policy **เมื่อได้รับ PO approval** (historical deactivate/reactivate/reclassification disabled รุ่นแรก). ห้าม save SQL ต่อ `gradebook_scores`. Insert immutable scoped receipt, then set governance PUBLISHED/revision++/structure_revision++/first+latest receipt pointers (first immutable), adoption provenance for PRE origin; update current link published_at/annual provenance. ทั้งหมด PDO transaction เดียว; ไม่มี state ใหม่บน link. ถ้า first publish set annual `ratio_frozen_at` / `ratio_frozen_by` และ annual revision++ใน transaction นี้. Draft status=PUBLISHED, revision++; receipt เดียวตามลำดับข้างต้น และ audit component delta/sections/memberships/adoption/publish/freezing ใน PDO เดียวกัน.
10. Commit once; receipt/result มี mapping/new structure revision/result annual revision/exact totals/review diff และ configuration snapshot สำหรับ interpretation (no student scores). Failure ก่อน commit rollback domain/audit/receipt/draft changes ทั้งหมด; persisted pre publish draft ยังอยู่. Commit uncertain ตรวจ receipt ผ่าน connection ใหม่;ไม่ประกาศ failure เด็ดขาดแล้ว retry อัตโนมัติ.

Lock protocol ต้องรวม governance-aware LEGACY revision hook/managed old endpoint guards/annual binding writers ก่อนเปิด feature. Aggregate checks เป็น server authority ไม่ใช้ FK presence/`event.defaultPrevented` เป็น proof. School lock กว้างสอดคล้อง baseline แต่มี contention cost; B3 ต้องวัด 2-term 200-item workloads/concurrent scores ไม่ลด safety เพื่อ optimize ก่อนมี evidence.

### 7.3 Idempotency and safe retry

Server `request_hash` = canonical SHA256 ของ tenant/actor/offering/annual ID/governance origin+epoch+expected revision/draft ID/expected draft revision/expected annual revision/expected structure revision/review hash/acknowledgements; ไม่รวม idempotency key. `payload_hash` คำนวณจาก persisted exact payload สำหรับ original operation; retry เทียบ original request hash กับ receipt ไม่อ่าน payload ที่ถูกแก้ภายหลังมาสร้าง hash ใหม่.

UNIQUE `(school_id,subject_offering_id,idempotency_key)` กัน same key parallel; UNIQUE `(draft_id,draft_revision)` กัน new key double publish ของ revision เดียว. School lock serializes initial insert รวม absent receipt; DB constraints เป็น backstop. Same key+same actor/hash คืน result ไม่ duplicate writes/audits. Changed actor/hash→409; different key แต่ same published draft revision→409 ให้ lookup original receipt ไม่ create ซ้ำ. Receipts ไม่ expire/delete เองรุ่นแรก.

Response หายหลัง commit: UI ล็อก publish เพิ่ม แสดง “ตรวจผลการยืนยัน” GET receipt. Not-found หนึ่งครั้งยังไม่พิสูจน์ rollback หาก transaction แรกยังค้าง; authorized GET อาจ poll แบบ bounded/backoff จนจบหรือให้ explicit retry **key เดิม/request เดิม** เมื่อ user เลือกและ lifecycle ยัง writable. ไม่มี automatic mutation retry/loop/deadlock retry. Receipt เก่าแสดง published revision เทียบ current revision ไม่อ้างว่า receipt เก่าคือ active ล่าสุด. Validation/persisted-save แยก request ได้ แต่ active publish เป็นหนึ่ง atomic transaction.

### 7.4 Safe Term 2 addition after Term 1 publication

Example: annual A,70:30,T1 linked PUBLISHED governance structure_revision 3,T2 ไม่มี link,ratio frozen. User เปิด actual Offering T2 ที่อาจใช้ Subject ID ต่าง → explicit bind under school/cutover/year/offerings/governance/annual/auth locks → scope/unique/lifecycle check → T1 live 50.00/35.00/15.00 และ history max ตรวจ → link T2 พร้อมคง governance origin เดิม: post-activation T2 เป็น SETUP_REQUIRED; pre-activation T2 ยัง LEGACY; annual revision++และ binding audit. ไม่มี T1 component/score write; T1 published provenance คงเดิมและ readiness derive valid; T2 ยัง not ready. T2 draft ต้อง base annual revision ใหม่และใช้ 35/15; publish T2 ตาม transaction ข้างต้นจึง annual ready100. MissingT2 ไม่ทำ T1invalid และการเพิ่ม T2 ไม่มี generic ratio change เพื่อ bypass freeze.

## 8. D5/D6 — Annual lifecycle, history compatibility, legacy rollout

### 8.1 Annual ratio freeze alternatives

| แบบ | ประโยชน์ | ความเสี่ยง / recommendation |
| --- | --- | --- |
| A Freeze หลังมี score history | แก้ policy ได้หลัง publish ถ้ายังไม่มี score | ทำให้ publishedT1 35/15 ผิด ratio ใหม่ 40/10 โดยไม่เปลี่ยน structure; ถ้าบังคับ two-term rewrite ก็กลายเป็น C |
| **B Freeze หลัง first term publish แม้ไม่มี history** | Stable published authority; missingT2 เข้ามาใช้ ratio เดิม; simplest review/guard | ต้องเลือก ratio ให้ถูกก่อน first publish; แก้ความผิดพลาดหลัง publish ต้อง separate amendment; **แนะนำ** |
| C Explicit atomic revision ทั้งสอง term | รองรับ controlled amendment ตรวจผลกระทบพร้อมกัน | Scope/locking/review/history/report versioning และ authorization มากขึ้น;ไม่มี requirement ให้ implement ตอนนี้ |

**D5 — RECOMMENDED — PENDING PO APPROVAL: B.** Ratio แก้ได้ก่อน first publish ภายใต้ expected annual revision/live links/history review; drafts ทั้งสอง term stale. Legacy ที่มี history ยังไม่มี adopt ต้อง explicit academic ratio/classification approval ก่อน first assignment; การเปลี่ยน candidate ratio ที่เคยรับรองแล้วและกระทบ historical interpretation เป็น pendingD6/remediation ไม่ใช้ B อนุญาตเปลี่ยนอย่างไร้เงื่อนไข. หลัง publish freeze ถาวรแม้ยังไม่กรอกคะแนนหรือ Offering inactive; generic update ปฏิเสธ. Future controlled amendment ต้อง separate PO decision+two-term atomic impact/versioning plan ไม่ implement workflow ใน 6V รุ่นแรก.

| State | Readiness / authority |
| --- | --- |
| Annual defined/T1draft/T2 missing | Ratio ใช้ทั้งปี; partial save draft ได้; no active changes; annual incomplete |
| T1 published valid/T2 missing | T1 ready 50 และ own ratio; annual incomplete; missing ไม่ errorT1 |
| T1 published/T2linked LEGACY | T2ratio candidate รอ adoption; legacy read/entry คงเดิม; annual incomplete |
| Both PUBLISHED managed valid | Each50/own ratio/live lifecycle valid → annual configuration ready100; ไม่ได้ยืนยัน student scores complete |
| New score history | Component max freeze ทันทีแม้ NULL; ratio ที่ publish แล้ว frozen; safe metadata/reorder/same-group moves คงได้; Managed historical status/reclassification เสนอ disabled ตาม D6 ไม่เปลี่ยน Legacy baseline |
| Inactive parent/Offering | Relevant term unavailable/read-only; links/history คงเดิม;ไม่ display annual complete |
| CLOSED year | Historicalread ตาม permission; no mutations รวม draft/policy/link/no-op;ไม่เปิดปีใหม่ |

### 8.2 History compatibility matrix — no blanket topology lock

Baseline จาก `GradebookComponentService::{createComponent,updateComponent,changeStatus}` และ `testAnyScoreHistoryFreezesMaxButAllowsMetadataAndStatus`: max ของ component ที่มี any score-row แก้ไม่ได้; metadata/code/order และ ACTIVE/INACTIVE ยังแก้ตาม live permission/lifecycle ได้; baseline ยังไม่มี classification/sections/ratio.

**Core rule binding:** history-bearing max_score immutable; no delete/rewrite คะแนน/history/audit. Component ที่ไม่มี history แก้ max ได้แม้ component อื่นใน Offering มี history. Managed proposal ควรอนุญาต safe corrections ที่ new active 50/ratio ตรง,history max คง,score rows คง,review diff+transaction+audit ครบ; **ไม่ได้ถือว่าผลรวม 50 เพียงอย่างเดียวพิสูจน์ historical meaning ปลอดภัย**.

Legend: `M`=existing live GRADEBOOK_COMPONENT_MANAGE+open year/active offering/tenant/CSRF; `R`=exact diff review+revision check+atomic50/ratio/membership validation. ทุก new planner policy ใน matrix ยัง pendingD6 approval ทั้งชุด; “conditional”ไม่ใช่อนุญาต production. Historical interpretation หมายถึง meaning ของ components/active aggregate ที่ถูกเปลี่ยน ไม่ใช่การแก้ saved scores.

| # / Action | Existing behavior | Planner proposal | Active totals / annual ratio | Historical interpretation | Auth | Atomic publish safe? | Additional explicit approval |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 1 Rename component | ได้แม้มี history | แก้ชื่อที่ไม่เปลี่ยน score authority/classification | ยอดและ ratio คงเดิม | ID/max คง; audit ชื่อก่อน/หลัง | M+R | ได้ | D6 matrix; ชื่อที่เปลี่ยนความหมายวิชาการต้อง review แยก |
| 2 Reorder component | แก้ sort_order ได้แม้มี history; teacher form เดิมรักษาลำดับ | ลำดับ deterministic พร้อม keyboard controls | ยอดและ ratio คงเดิม | Position ไม่ใช่ score key; IDs คง | M+R | ได้ | D6 matrix |
| 3 Add new component | ได้; ไม่มี offering-wide history ban | อนุญาตแบบมีเงื่อนไขเมื่อ balancing changes ปลอดภัย | เพิ่ม active max; publish ต้องจบ 50 และตรง targets | Existing scores/max คง; columns/completeness เปลี่ยนจึงต้อง review | M+R | ได้เมื่อ compatibility พิสูจน์ครบ | D6; ถ้า balance ด้วย historical deactivation ต้องข้อ 7 decision |
| 4 Change max before history | ได้ 0.01–99999.99 | แก้ component ที่ไม่มี history แม้อื่นมี; locking read กัน first-score race | Delta ต้อง balance 50/ratio ใน transaction | Component นี้ไม่มี score rows; historical maxima อื่นคง | M+R | ได้ | D6 matrix; ไม่ freeze ทุก max ใน offering |
| 5 Change max after history | **ห้าม** แม้ NULL/cleared | **ห้ามเสมอ**; planner ไม่ override | ไม่เปลี่ยน | Preserve score authority | M ไม่ bypass | ไม่ได้ | Core rule คงเดิม; D6 ไม่ยกเลิกข้อห้าม |
| 6 Deactivate without history | ได้ผ่าน status workflow | Explicit inactive intent; ห้าม omission เป็น silent deactivation | ลด active max; ต้อง balance 50/ratio | Component/archive คง; ไม่มี scores ของ item นี้ | M+R | ได้เมื่อ balanced | D6 matrix และ review รายการปิดใช้ |
| 7 Deactivate with history | **ได้ตาม Legacy workflow เดิม** เก็บ rows; active aggregate ตัด item | **DISABLED in first Managed release — recommendation pending PO** | ลด active/entered totals; balance 50 ไม่พิสูจน์ meaning | เก็บ scores แต่ความหมาย active aggregate เปลี่ยน | M+R ไม่ bypass | ไม่เปิดในรุ่นแรก | Separate academic remediation decision; checkbox ไม่อนุญาต; Legacy PRESERVED |
| 8 Reactivate historical component | Legacy ได้ผ่าน authorized status; max คง | **DISABLED in first Managed release — recommendation pending PO** | เพิ่ม maxima/entered totals และเปลี่ยน active meaning | คะแนนเก่ากลับ aggregate | M+R ไม่ bypass | ไม่เปิดในรุ่นแรก | Separate remediation; ไม่สร้าง replacement ID เพื่อลืม history |
| 9 Move within same classification | ไม่มี section concept; reorder เดิมได้ | Same offering/group; section/order audit | Group/term maxima คง | Membership label เปลี่ยน; group authority คง | M+R | ได้ | D6 matrix; section label ที่สื่อความหมายอื่นต้อง review |
| 10 Change classification after history | ไม่มี classification authority เดิม | **DISABLED in first Managed release**: historical cross-classification ต้อง separate remediation decision | Term total อาจคง แต่ group allocation เปลี่ยน | เปลี่ยนความหมายคะแนน; receipt/audit อย่างเดียวไม่รับรองความถูกต้องวิชาการ | M+R ยังไม่พอ | Disabled รุ่นแรก | PO separate remediation; ไม่ reclassify เพื่อทำยอดให้พอดี |
| 11 Change section membership after history | ไม่มี section concept | Same group ตามข้อ 9; cross-group ตามข้อ 10; เปลี่ยน classification ทั้ง section ที่มี historical members ใช้ข้อ 10 | Same group คง; cross-group เปลี่ยน allocation | ID/max คง; historical group remap ไม่ implicit | M+R | Same group ได้; historical cross-group disabled รุ่นแรก | D6 matrix + ข้อ 10 เมื่อข้ามกลุ่ม |
| 12 Correct initially inconsistent legacy | Metadata/status ได้; historical max ห้ามแก้; ไม่มี 50 rule | ไม่มี history ใช้ safe draft/adoption; มี history ที่ยังผิดกฎคง LEGACY/INCONSISTENT และ remediation แยก | ห้าม auto-normalize 50/backfill ratio | Original scores/max/interpretation คง | M+R+academic mapping review | ไม่มี history ทำได้; historical inconsistency disabled/แยก remediation | PO remediation plan; ไม่บังคับ adopt เพื่อกรอกคะแนนต่อ |

**D6 — RECOMMENDED — PENDING PO APPROVAL:** narrow first release; Managed historical deactivate/reactivate/reclassify/cross-group remap และ post-publish ratio amendment **DISABLED**. Checkbox/review/audit ไม่เปิดข้อห้ามเหล่านี้; future separate academic remediation ต้อง PO decision ใหม่. ถ้า PO ไม่รับรอง Managed restriction ที่ต่าง accepted status behavior ให้ **STOP ก่อน implementation** และ update contract/registry/golden/test evidence/change history ที่ได้รับ review ตาม AGENTS; ห้ามใช้ green tests เป็น approval. ข้อเสนอนี้ไม่แก้ baseline ตอนนี้; Legacy historical status behavior **PRESERVED**. การมี score history เฉย ๆ ไม่ห้าม 1/2/4/6/9 ที่ปลอดภัย และไม่ทำให้ existing legacy status workflow หายไป.

ตัวอย่าง safe correction หลังมี history: CW items 10(history)+25(no history) และ FINAL 15; draft แยก 25 เป็น 15+10 โดยแก้ max ของ**no history** item และเพิ่ม item ใหม่ จบ CW 35/FINAL 15/50; history10 คง scores/max/ID จึงพิจารณา atomic ได้ตาม 3/4. ตัวอย่างที่ยัง pending: ปิด CW 5 ที่มี scores แล้วเพิ่ม CW 5 ใหม่แม้จบ 35/15/50 คะแนนที่เคยอยู่ active aggregate หายจาก current total จึงห้ามเรียกว่า safe เพียง max เท่าเดิม รุ่นแรก Managed disabled ตาม recommendation ข้อ 7; ไม่เปิดด้วย confirmation.

Initial classification ของ legacy with history ยังไม่มี group authority มาก่อนไม่ใช่ reclassify หลัง adoption: require explicit academic review/evidence/before-after interpretation ทั้งสอง linked terms; preserve all components/max/status/scores สำหรับ first adoption. ถ้าหลักฐานไม่ครบหรือผิด 50/ratio ให้คง legacy. Existing regression tests คงเดิม; เพิ่ม managed cases หลัง D6 approval ไม่ delete/loosen baseline เพื่อบังคับ restriction ใหม่.

### 8.3 Legacy / New / Managed lifecycle and compatibility matrix

Confirmed New mandatory policy supersedes v1.1 permissive pre-Publish New workflow. Governed readiness ไม่ derive จาก link/flag/total. Legacy ก่อน activation เท่านั้นยังใช้ accepted score/component workflow; explicit binding ไม่เท่ากับ adoption.

| Scenario | Before activation | After activation | Score entry | Structural mutation |
| --- | --- | --- | --- | --- |
| Existing Legacy (linked/unlinked) | Existing rules | PRE+LEGACY preserved; annual incomplete until explicit adoption | Existing rules/live permission/enrollment/lifecycle; no ratio guard imposed | Existing create/update/status rules; governance revision/audit makes drafts stale |
| New unconfigured, no annual link | N/A | POST+SETUP_REQUIRED; ratio/binding needed | **DENY** including clear/NULL/no-op/direct call | Draft/annual setup only; raw component endpoints DENY |
| New draft incomplete/pre-ratio | N/A | Private draft saved; not active | **DENY**; saved valid draft also not published | Draft only; no fake component to unlock |
| New valid published | N/A | POST+PUBLISHED; own 50/CW/FINAL/ratio/link/freeze/receipts valid | **ALLOW only if otherwise authorized/active** and transaction live-readiness valid | Atomic planner only; revision/history/50/ratio |
| Managed becomes invalid | N/A | PUBLISHED remains durable; derived NOT_READY | **DENY / safe locked revalidation**; no automatic score/max repair | Controlled planner correction only if history-safe; otherwise separate remediation |
| Legacy explicitly adopted | Legacy rules | PRE+PUBLISHED after reviewed atomic adoption; provenance preserved | Managed live-readiness + existing scoring rules | Atomic planner only; historical restrictions pending D6 PO approval |
| Legacy inconsistent max/history | Existing actual max/read/entry | Preserve Legacy, not silently normalize/adopt | Existing rules; no annual compliant claim | Legacy accepted metadata/status; max with any history immutable; adoption blocked if evidence invalid |
| Mixed Legacy T1/New T2 | T1 existed; T2 not yet created | T1 PRE+LEGACY, T2 POST+SETUP_REQUIRED/PUBLISHED | T1 existing rules; T2 DENY until valid own publish | T1 legacy rules; T2 draft/planner only; annual incomplete until valid approved T1 adoption |
| Unknown/missing governance in ACTIVE school | Deployment preparation incomplete | UNCLASSIFIED / safe recovery needed | **DENY**; no default Legacy from absence | DENY raw mutations; audited recovery only, POST classification after boundary |
| Closed year | Read-only | Read-only even Legacy or PUBLISHED | **DENY** | **DENY** all writes/no-ops/draft/link/publish |
| Offering/parents unavailable | Existing lifecycle rules | Keep all provenance/links/history; read by permission | DENY if existing lifecycle or managed readiness disallows | Existing lifecycle denies mutations; no mode downgrade |

New ready predicate = persisted origin/state/epoch + committed current receipt/link + frozen valid annual ratio + scope/term/identity + complete active memberships/sections/classification + exact term 5000 and CW/FINAL targets + current lifecycle + live permission. Total 50 alone never ALLOW. Annual ready100 requires **both terms independently managed-ready**; linked Legacy lacking evidence is not compliant. Gradebook selected/read-only/copy behavior stays accepted; readiness denial must not report saved success.

### 8.4 Safe adoption and old endpoint bypass inventory

Safe adoption: authorized user สร้าง annual identity ใน correct scope → explicit term selection หรือ acknowledge missing → รับรอง ratio ไม่ default → live inventory active/inactive/max/history และ classification from academic record → partial draft → exact diff review สอง terms → atomic current term publish. First adoption with history preserve all active IDs/max/status/score rows; initial section/group metadata audit เท่านั้น. Another linked legacy term ไม่ต้อง adopt พร้อมกันแต่ relationship/history ต้องรับทราบ; annual ยัง incomplete. หาก history legacy inconsistent คงเดิมและเสนอ separate remediation plan ไม่เปลี่ยน max/deactivate history-bearing items เพื่อผ่านโดยไม่มี decision.

Current routes ที่ตรวจจริง (ทุก POST ใช้ existing component manage/CSRF):

| Surface | Existing route / service family | Proposed managed guard / legacy compatibility |
| --- | --- | --- |
| Gradebook setup | GET `/gradebook/{offeringId}/setup`; POST `/gradebook/{offeringId}/components`, POST `.../components/{componentId}`, POST `.../components/{componentId}/status` | NEW_REQUIRED/PUBLISHED controls เป็น planner entry; raw POST/direct service DENY แม้ยังไม่มี annual link; PRE+LEGACY forms/accepted behavior คง |
| Classroom Subject Workspace | POST `/hx/workspaces/classrooms/{classroomId}/subjects/{offeringId}/components`, POST `.../components/{componentId}`, POST `.../components/{componentId}/status` | New/Managed planner link/read-only summary; forged POST deny โดย service ไม่เพียงซ่อน button; expected classroom check คง |
| Shared component service | `createComponent/createScoreItem/updateComponent/updateScoreItem/changeStatus` | Persisted governance lookup ภายใต้ school/cutover/year/offering locks ก่อน writes; POST+SETUP_REQUIRED หรือ PUBLISHED raw mutation deny; ไม่มี client bypass. PRE+LEGACY ใช้ accepted mutation+bump governance `structure_revision`/audit (linked หรือไม่) |
| Offering administration | `SubjectOfferingAdministrationService::updateOffering/changeStatus` | Linked identity FK+service guard; status ไม่ unlink/downgrade mode. New inactive→active validate parents; parent/year status serializes with publish |

Production structural SQL inventory: `GradebookComponentRepository::{create,update,updateStatus}` เป็น INSERT/UPDATE writers ที่ public component service ใช้; ไม่พบ production component DELETE. Proposed raw-repository calls for POST/Published must also reject outside transaction-owned atomic planner orchestration; no caller flag can authorize a bypass. Internal planner helper validates live unscoped manage/history/50/ratio in the same transaction, cannot be a public unchecked component writer. `SubjectOfferingController::store` → `SubjectOfferingAdministrationService::createOffering` → `SubjectOfferingRepository::create` เป็น production Offering create call path ที่พบ; no production import/seed writer found in tools/database baseline. Any future import/admin/direct repository create must classify Offering+governance+audit in the creation transaction; unregistered raw insert cannot pass guarded scores. Re-inventory before activation.

Guard outcome เสนอ 422managed structure-required หรือ 409stale ตาม adapter ไม่แสดง success redirect/partial write; legacy fields/code/order/tenant/CSRF คง contract เดิม. Managed ทั้ง metadata/order/max/status ต้องผ่าน same atomic review เพื่อ OCC ไม่หยิบ old endpoint rename เป็นช่องแก้ topology. No generic downgrade PUBLISHED/SETUP_REQUIRED→LEGACY,unlink หรือ flag-off bypass; actual DB constraints+services required ก่อน activation. Phase B ต้องเพิ่ม score-service governance/readiness guard ตาม approved new-course policy; **ไม่เปลี่ยนคะแนน/สูตร/NULL/zero/atomic range/clipboard/editor behavior**. Legacy read/entry ไม่ถูก stop โดย ratio readiness. Managed safe metadata ก็ผ่าน planner OCC/audit ไม่ raw endpoints; rejected POST ไม่มี success redirect/header.

### 8.5 Production score-write call path and authoritative guard map

Repository inspection at baseline (source evidence, **not runtime proof**): `htdocs/routes/web.php:310–317`, `GradebookScoreController::{store,storeBatch}`, `GradebookScoreService::{setScore,setScoresBatch,lockContext,mutateCell}`, `GradebookScoreRepository::{create,updateScore}`; Application wires the same service/repositories. Search SQL mutation + method calls across htdocs/database/tools found **two production score HTTP paths**, one service family, INSERT and UPDATE repository writers; no production DELETE/import/bulk-score job found at this baseline. Tests/E2E provision and tests/Support use direct service calls or isolated SQL fixtures, not production routes; preserve them and classify future test fixtures explicitly. Re-inventory before B3; absence in this search is not permission to add an unchecked writer.

| Entry / call path | Existing protections retained | Required proposed guard (B3 only after authorization) |
| --- | --- | --- |
| POST `/hx/gradebook/{offeringId}/components/{componentId}/enrollments/{enrollmentId}/score` → controller store → setScore | SCHOOL/session tenant+actor, CSRF 419, decimal range, enrollment/placement, active component, live score permission, one transaction/audit | After school/cutover/year/offering locks, resolve governance **even no link**, require state PUBLISHED for POST origin, live locked readiness; deny before any score lookup mutation/success flag |
| POST `/hx/gradebook/{offeringId}/scores/batch` → storeBatch → setScoresBatch | JSON/byte/2,000-cell limits, rectangular unique targets, sorted locks, validate all before writes, atomic audit+rollback | Same shared guarded context once per batch **before first cell**; all target components checked against ready structure. Deny whole rectangle, no partial success/changed_count. Clear/empty TSV also a score write intent |
| Authorized internal calls setScore/setScoresBatch (no HTTP) | Trusted server-context actor/school, transaction/live score grant and existing validation | Same service guard without frontend flag; no “internal=true” exemption; no service bypass in import/seed/admin |
| private mutateCell → repository create/updateScore | No public service unchecked mutate; repository writes scoped by school/year/offering/enrollment/component | Keep internal transaction-owned writer; public repository mutation reachable for New/Published must fail closed unless transaction + authoritative locked governance/readiness and authorization validated by guarded write path. Do not trust a client/caller boolean “ready”. Sanctioned callers use score service; B3 encapsulation/guard must cover direct repository calls with negative tests; Legacy repository isolation baseline remains tested |
| Direct production SQL / future importer | None found outside repository at baseline; tools/migrate executes DDL, not sanctioned scoring | Prohibit application bypass; any newly discovered caller must join same validated writer/transaction before activation. Arbitrary privileged DB administration is outside this service guarantee, requires controlled access; no schema mechanical-lock claim |

Service transaction order for governed scoring: school → cutover → discover year/all affected actual Offering IDs → year → offerings ascending → active classroom/distinct subject rows (new readiness) → governance ascending → annual/links → **all relevant active/inactive components ascending**, sections/memberships → existing enrollment/placement locks → existing live score authorization locking query → score cells sorted enrollment/component → writes/audit/commit. Hold locks until commit. All components locked to revalidate exact live aggregate, not just submitted component. Score guard validates own term; another linked Legacy term does not block ready New term under 8.6. Lock actual paired scopes when needed for policy identity/readiness; no lookup by Subject ID. PRE+LEGACY skips managed 50/ratio readiness and uses accepted scoring rules unchanged. Current-read FOR UPDATE prevents snapshot from preceding activation/publish; no preflight cache/receipt alone authorizes writes.

| Governance/readiness result | Score mutation result |
| --- | --- |
| POST + SETUP_REQUIRED / no annual link / ratio absent / only Draft | `NOT_PUBLISHED → SCORE WRITE DENIED`; includes clear, zero, same value and NULL/no-op intents; no saved header/success toast |
| PUBLISHED + invalid active memberships/classification/exact 50/ratio/link/receipt/freeze or unavailable lifecycle | `PUBLISHED + INVALID LIVE STRUCTURE → DENIED / SAFE REVALIDATION`; revalidation reads only, no score rewrite/max repair; audited controlled correction or separate remediation |
| PUBLISHED + valid own structure + live score permission + active accepted lifecycle/enrollment/component | Existing scoring rules apply: decimals/max/NULL/zero/history/audit/rectangular batch unchanged |
| PRE + LEGACY | Accepted legacy score/component/lifecycle rules; no inferred annual-compliance claim |
| Missing/mismatched governance/cutover in ACTIVE school | Safe deny pending recovery; no fallback to Legacy and no opportunistic grandfathering |

HTTP denial uses existing 422 validation failure contracts where appropriate with teacher message/action; stale review remains 409; CSRF remains 419, auth isolation unchanged. Single denied response must not emit `X-Gradebook-Saved: 1`/saved-cell marker; batch must return `committed:false`, no partial mutations. After uncertain **score** commit keep existing 409/refresh semantics, never relabel as “not published, rolled back” without proof. Publish receipt/idempotency is separate from existing score semantics. Existing keyboard/clipboard contract and grade calculation remain LOCKED; new blocked-offering state/response needs PO-reviewed accepted evidence before B3 release.

Negative/regression suites impacted: GradebookScoreService/Http/Isolation/Ux/Revalidation, GradebookBatchScoreService/Http, GradebookComponentService/Http/Isolation, ClassroomSubjectWorkflow/ClassroomSubjects, SubjectOfferingAdministration/Http, TeachingGradebookSchema, ScopedGradebookAuthorization, read/history/year lifecycle. Keep existing accepted Legacy fixtures/assertions and add explicit New unlinked/incomplete/PUBLISHED/invalid/adopted cases; never remove or loosen baseline status/caret/batch tests. No tests added/run now.

### 8.6 D10 Mixed Legacy Term 1 / New Term 2 recommendation

Scenario: T1 Offering created/scored before school activation = PRE+LEGACY (max may not be 50, classification unknown); T2 created after boundary = POST+SETUP_REQUIRED. Explicit authorized academic pairing does not transfer T1 exemption to T2, rewrite T1, adopt it or establish classification evidence.

| Option | Enforcement / Legacy preservation | Annual readiness / trade-off |
| --- | --- | --- |
| **A Own New-term compliance; Legacy unchanged — recommended default** | T2 must set annual ratio + own exact 50/CW/FINAL and publish before score entry. Bind actual pair with reason/audit and mark T1 unadopted; T1 read/score/component workflow stays accepted | T2 may be ready; annual remains **NOT READY / Legacy evidence pending** until T1 safe adoption with classification/ratio/50 proof. Supports teaching without silent New exception; later T1 may need separate remediation because ratio freezes at first T2 publish |
| B Explicit grandfathered continuation | Would allow New T2 to inherit Legacy exemption only with separately approved exception authority/provenance/audit | Conflicts with confirmed mandatory New policy; **not enabled in first release**, requires PO policy amendment and schema/guard/test redesign before any exception. Pair confirmation/checkbox alone insufficient |
| C Block pairing/publish when historical proof fails | No New score writes and no Legacy rewrite; send separate remediation | Strict blocking may stop valid T2 despite unchanged T1; appropriate when **academic relationship/scope itself cannot be confirmed**, or attempted T1 adoption/mapping fails. Do not use absence of T1 ratio evidence alone to override recommended A |

Recommendation **A + C for unverifiable academic link or unsafe Legacy adoption** remains D10 RECOMMENDED — PENDING PO APPROVAL. Review shows actual T1 maxima/unknown classification, missing evidence and frozen-ratio consequence before T2 publish; neither copy inferred T1 classification nor claim both-term annual ratio compliance. If actual pairing not yet confirmed, do not falsely bind: T2 may have a correctly authorized annual identity with T1 explicitly unresolved/missing acknowledgement, or pause for academic decision if uniqueness/conflict unresolved; never invent a second identity to evade known conflicting pairing.

T1 Legacy component changes remain accepted and increment governance revision/digest; linked changes invalidate drafts and annual readiness but do not revoke T2 score entry if **T2 own** published structure/ratio/live identity remains valid. Unsafe T1 re-pair or conflicting academic evidence requires resolution; no deletion/unlink to hide history. Later adoption must preserve every history-bearing ID/max/score row (including NULL), explicit evidence and first-adoption audit/receipt; if max ≠50 or allocation cannot satisfy frozen ratio safely, remain Legacy and separate remediation. Annual maximum dashboard uses “ภาค 2 พร้อม 50.00 · ภาค 1 ข้อมูลเดิมยังไม่รับรอง · รายปี ยังไม่พร้อม” rather than compliant100; student completeness is separate.

## 9. D9 — Single-page spreadsheet-like planner / revised wireframes

**D9 — RECOMMENDED — PENDING PO APPROVAL.** Workflow หลัก **เปิด/เลือกรายวิชา → สร้าง/ผูกรายวิชาตลอดปีในโรงเรียน/ปี/ห้อง → เลือกอัตราส่วนคะแนน → กำหนดส่วนคะแนน/จำนวนช่องในร่าง → เก็บไว้แก้ต่อ → ตรวจยอดและกลุ่มคะแนน → ยืนยันโครงสร้างคะแนน 50.00 → กรอกคะแนนเมื่อ server พร้อม**. Partial Draft ก่อนเลือก ratio/binding ได้แต่ Publish/score entry ไม่ได้. Annual identity/link confirmation เป็น context แถวบนของหน้าเดียว ไม่เพิ่ม wizard หลายหน้า. Text wireframes เป็น design artifacts ไม่ใช่ browser visual acceptance.

Gradebook ก่อนพร้อม (390/1440/no-JS): ยังอ่าน/เลือก/นำคะแนนที่มีสิทธิ์ไปคัดลอกได้ตาม accepted read-only contract; message **“ยังไม่พร้อมกรอกคะแนน — กรุณากำหนดอัตราส่วนคะแนนและยืนยันโครงสร้างคะแนนให้ครบ 50.00”**, action **“ไปกำหนดคะแนน”** → scoped existing `/gradebook/{offeringId}/setup` หลังตรวจ live component-manage access. ผู้มีเฉพาะ score-entry permission เห็นเหตุผลและ “ติดต่อผู้ดูแลรายวิชา” พร้อม context ที่ไม่เผย PII; ไม่ลิงก์ไป form ที่ตนไม่มีสิทธิ์แก้/ไม่ยกระดับ permission. Structure corrupt/lifecycle closed แสดงเหตุผลเฉพาะและ action ตามสิทธิ์; authoritative POST deny ยังคงจำเป็นสำหรับ stale tabs. ห้าม fake empty components/unlock ด้วย client readiness.

คำครูใช้ **รายวิชาตลอดปี / ภาคเรียนที่ 1 / 2 / อัตราส่วนคะแนน / ระหว่างเรียน / สอบปลายภาค / เก็บไว้แก้ต่อ / ยังไม่พร้อมกรอกคะแนน / ยืนยันโครงสร้างคะแนน**. UUID/Policy Revision/Receipt เป็นข้อมูลอ้างอิงยุบได้ ไม่เป็นศัพท์ที่ผู้ใช้ต้องเข้าใจ. Wireframes ที่คงด้านล่างใช้ native table design เดิมและปรับคำหน้า UI เป็น “เก็บไว้แก้ต่อ/ยืนยันโครงสร้างคะแนน”.

### 9.1 Desktop 1440px — 70:30 / Term 2 not yet linked

```text
การเก็บคะแนน · คณิตศาสตร์พื้นฐานรายปี              [กลับรายวิชาในห้อง]
โรงเรียน [ชื่อ] · ปี [ปี] · ห้อง [ห้อง] · [ข้อมูลอ้างอิงรายวิชารายปี]
ภาค 1: ค14101 พื้นฐาน 1 · [ดูรายวิชา / การรับรอง]
ภาค2: ยังไม่เปิด/ยังไม่ยืนยัน Offering   [เลือกรายวิชา / ยืนยันคู่]
อัตราส่วนทั้งปี [70] : [30] ระหว่างเรียน : สอบปลายภาค
T1 ร่างบางส่วน · T2 ยังไม่พร้อม · ปี ยังไม่ครบ100 · เป้าหมายภาคละ50

[ร่างส่วนตัว · บันทึกแล้ว revision4 · ยังไม่ครบ]  [+ส่วน] [ดูที่ใช้งานอยู่]
┌ส่วน / เลื่อน────Classification──ช่อง──ชื่อช่อง inline────Max────Subtotal┐
│▼ก่อนกลางภาค ↑↓ COURSEWORK      [2]   งาน1              [10.00]   20.00│
│                                      งาน2              [10.00]        │
│▼กลางภาค     ↑↓ COURSEWORK      [1]   สอบกลางภาค         [10.00]   10.00│
│▼หลังกลางภาค ↑↓ COURSEWORK      [1]   งาน3               [ 5.00]    5.00│
│▼ปลายภาค     ↑↓ FINAL_EXAM      [1]   สอบปลายภาค         [12.00]   12.00│
│  [+ช่อง / ลดช่องพร้อมตรวจ diff]                                      │
└──────────────────────────────────────────────────────────────────────┘
[▶ประวัติ / ปิดใช้งาน · maxที่มีประวัติแก้ไม่ได้]
Sticky summary/actions ในพื้นที่ของplanner ไม่ทับตาราง:
COURSEWORK 35.00/35.00 · FINAL_EXAM 12.00/15.00 · รวม 47.00/50.00 ขาด 3.00
[เก็บไว้แก้ต่อ] [ตรวจการเปลี่ยนแปลง] [ยืนยันโครงสร้างคะแนน disabled: ยังไม่ครบ]
```

เมื่อ user แก้ปลายภาคเป็น 15.00 → live exact 35/35,15/15,50/50; Save Draft เก็บ revision 5; Review ในหน้าเดียวแสดง existing diff/add/inactive/history impact+other term readiness+missing term acknowledgement. Published T1 ready 50,T2 not linked,annual incomplete และ ratio frozen. ตัวเลขในตัวอย่างเป็น synthetic design ไม่มีการแก้คะแนนจริง.

### 9.2 Desktop 1440px — 80:20 / different Subject IDs

```text
รายวิชารายปี: คณิตศาสตร์พื้นฐานรายปี · โรงเรียน/ปี/ห้องเดียวกัน
T1 ค14101 พื้นฐาน 1 [รับรองแล้ว] · T2 ค14102 พื้นฐาน 2 [ดูรายวิชา]
Ratioทั้งปี [80] : [20]  (ล็อกหลัง T1 publish)       [ดูความสัมพันธ์]
T1 พร้อม40.00+10.00=50.00 · T2 ร่าง · ปี ยังไม่ครบ100
แก้ T2: ก่อนกลางภาค COURSEWORK 2ช่อง ×10.00 =20.00
         กลางภาค     COURSEWORK 1ช่อง ×10.00 =10.00
         หลังกลางภาค COURSEWORK 1ช่อง ×10.00 =10.00
         ปลายภาค     FINAL_EXAM 1ช่อง ×10.00 =10.00
COURSEWORK 40.00/40.00 · FINAL_EXAM 10.00/10.00 · Term 50.00/50.00
[▶History warning: มีรายการคะแนนเต็มที่ล็อก / สถานะกระทบยอดให้ตรวจ]
[ตรวจdiff: ชื่อ/ลำดับ/สร้าง/เปลี่ยนสถานะ/ย้ายส่วน/ไม่มีscore writes]
[เก็บไว้แก้ต่อ] [ตรวจการเปลี่ยนแปลง] [ยืนยันโครงสร้างคะแนน]
หลังserverยืนยัน T2: ทั้งสองtermพร้อม50.00 → Annualmax100.00
```

### 9.3 Mobile 390px / keyboard / fallback

```text
การเก็บคะแนน · [ชื่อรายวิชารายปี]
ปี / ห้อง · [ดูคู่ T1/T2]
80:20 [ล็อก] · T1พร้อม / T2ร่าง
ร่างบันทึกแล้ว · ยังไม่ยืนยันใช้
┌ตารางเลื่อนภายใน card──────────┐
│ส่วน / กลุ่ม / ช่อง / ชื่อ / Max│ → internal horizontal scroll
│▼กลางภาค COURSEWORK            │
│  สอบกลางภาค [10.00]            │
└──────────────────────────────┘
[▶ประวัติ] [ตรวจdiffในหน้าเดียว]
ระหว่างเรียน 40/40 · ปลายภาค 10/10 · รวม 50/50
[เก็บไว้แก้ต่อ] [ตรวจและยืนยัน]
```

Annual UUID/Offering IDs แสดงเฉพาะข้อมูลอ้างอิงที่ยุบได้; context หลักใช้ชื่อรายวิชารายปี, code/name ของแต่ละ term และ school/year/classroom ที่ user ตรวจได้. Classification selector แสดงชื่อไทย “ระหว่างเรียน / สอบปลายภาค” โดย enum ภายในเป็น `COURSEWORK` / `FINAL_EXAM` ตาม wireframe.

Semantic HTML table/native inputs/selects/buttons + page-scoped vanilla JS/CSS ใช้ Tabler 1.6.1/tokens เดิม; planner เป็น separate editing context **ไม่ clone Gradebook renderer** ไม่โหลด Tabulator/gradebook.js เพื่อ structure editing ไม่เปลี่ยน keyboard contract/vendor. Table เพิ่ม/ลด sections/จำนวนช่อง/name/max/classification/inline order/subtotal ได้; history/inactive ยุบได้. Reducing existing items เป็น explicit INACTIVE review ตาม D6 ไม่ hard delete; deleting unsaved new items เฉพาะ draft.

Desktop table card ใช้ bounded height/internal vertical scroll เมื่อรายการมาก; context กระชับ summary/action bar sticky ภายใน planner พร้อม reserved space/scroll-padding ไม่บัง focused row. Mobile context/actions wrap/table internal horizontal ไม่ document overflow; summary ไม่ fixed ทับ soft keyboard/safe area ให้ flow แทน sticky เมื่อ viewport เตี้ย. ไม่บังคับ whole page scroll เพื่อดูยอดทุกครั้ง แต่ไม่สัญญาว่าไม่มี page scroll ในทุก font zoom/no-JS case. Accessibility: labels/error anchors/aria-expanded/keyboard move buttons/visible focus/adequate touch size; responsive/200 items/zoom ต้อง trusted proof.

Keyboard: Tab/Shift+Tab ตาม DOM; arrows/caret/copy/paste/delete ใน input เป็น native ไม่ทำ score-cell navigation หรือ range tiling. เสนอ Enter ใน name/max focus next field เมื่อไม่ composing; ไม่ POST active. Composition Enter ใช้ Thai IME ตาม native ไม่ intercept; select/native controls คง semantics. No global keydown; targeted summary updates รักษา same input/value/caret/focus. Escape ไม่ discard draft อัตโนมัติ มี explicit discard/reload review. Read-only ไม่มี enabled mutation controls แต่ history อ่านได้.

**No accidental Enter publish:** draft edit form default submission ได้เพียง Save Draft; “ตรวจและยืนยัน”เป็น explicit button เปิด review บนหน้าเดียว. Confirm Publish form แยกไม่ nested ไม่มี editable table input เป็น implicit-submit trigger; requires valid persisted revision/exact review/acknowledgements. Intentional Enter บน focused confirm button ทำงานตาม native button ได้เมื่อ review ครบ; typing Enter ใน table หรือ IME ไม่ active publish. Server revalidates ทุกครั้งไม่เชื่อ disabled button.

Live preview ใช้ exact hundredths เป้าหมาย COURSEWORK/FINAL_EXAM/term,total delta,partial/dirty/saved/stale/readiness. ไม่ rerender whole table เพื่อ badge; polite announcement ไม่รบกวนทุก key. Network failure คง unsaved memory+saved base;409 ให้ดู diff/load ใหม่ไม่ silent overwrite; dirty navigation warning+best-effort beforeunload. Review แสดง same-group move ต่างจาก cross-group/history impact และ missing term ชัด; pending D6 actions disabled พร้อมเหตุผล.

No-JS fallback native form Save Draft มี add/remove/move intent+expected revision ไม่ยิง component POSTs; server render คงค่าที่กรอก/error anchor/partial state. Review submit recalculates exact revision และ separate Publish confirmation; ไม่อ้าง reactive preview/unsaved warning เท่า JS. ทุก planner action **score POST=0**; mutation มีเฉพาะ annual/draft/publish ตาม authorized intent และ server validation.

## 10. Test and security plan

### 10.1 Phase B test matrix (ยังไม่ได้รันใน Phase A)

| กลุ่ม | Cases และผลที่ต้องพิสูจน์ | Evidence |
| --- | --- | --- |
| Ratio/precision | 70:30/80:20/60:40/65:35/75:25/85:15; 0:100/100:0 and decimal percent reject in first release per pending D4; invalid sum/negative/>100/decimal percent/NaN/exponent/array; max 0/negative/3 decimals/overflow; 49.99 reject/50.00 accept/50.01 reject; 34+16 reject สำหรับ 70:30; T1 40+10/T2 30+20 ไม่ผ่าน annual averaging | Unit exact arithmetic + Feature HTTP/DB |
| Identity/isolation | Cross-school/year/classroom; Annual course school/year/classroom scope; different Subject IDs accepted only with explicit authorized confirmation; same Subject ID also no auto-pair; wrong term/duplicate link/Offering already linked reject; missing T2 and later link stale drafts without invalidating valid T1; forged tenant/actor/owner/component/section IDs; selected unconfirmed candidate history must be reviewed | Feature/schema negatives + isolated DB |
| Draft | Save partial/invalid/42.00, reload/resume, errors ผูก field keys; two tabs/two owners; stale revision 409; response เก่าไม่ล้าง dirty generation; oversized payload/invalid UTF-8; resume หลัง publish; invalid envelope ไม่มี write | Feature + trusted browser |
| Atomicity | Fail หลัง create/update/status/section/membership/audit/receipt และที่ commit; active configuration/scores/audit เท่า before ทั้งชุด; ร่างอยู่; no partial commit; code collision ตาม DB collation | DB integration/fault injection |
| Retry | Double click/concurrent publish; response หายหลัง commit แล้ว GET receipt; key/hash เดิมคืนผลเดิม; changed hash/actor conflict; key ใหม่กับ draft revision เดิมไม่ duplicate; receipt เก่าไม่อ้างว่าเป็น active revision ล่าสุด | Feature two-connection + trusted delayed response |
| History/lifecycle | NULL/zero/nonzero/cleared/inactive history ล็อก max; first-score race; first publish freezes annual ratio without history; NULL history freezes individual max while safe metadata/same-group/no-history changes remain; Managed historical status/group changes disabled per pending D6 recommendation; Legacy accepted status preserved; legacy adoption with history metadata-only, inconsistent remains legacy; year close/offering move/status/parent inactive ระหว่าง preflight/publish; CLOSED no-op reject | Existing regressions + new Feature + real concurrency |
| Permissions/security | Existing unscoped component manage เท่านั้น; score grant ไม่ publish; role/member/grant ถูก revoke ก่อน write; foreign/missing 404 สม่ำเสมอ; CSRF 419 ทุก mutation; XSS label/error/form; ไม่มี score/enrollment payload writes | Feature HTTP + two DB connections |
| Old endpoint bypass | Setup create/update/status ทั้ง teacher fields และ code/order; Workspace ทั้งสาม families; direct service calls; POST+SETUP_REQUIRED/PUBLISHED reject via UI/service/repository guards even without annual link/with flag off; linked/unlinked PRE+LEGACY retain accepted behavior with governance revision hook; explicit adoption boundary; offering identity update cannot detach/re-pair linked identity | Baseline suites retained + new rollout/linked-scope cases |
| Audit | Before/after ของ annual identity/ratio/link/section/membership/components/publish/freeze/adoption; no-op ไม่ duplicate delta; score audit ไม่เปลี่ยน; failure ไม่เหลือ audit/receipt; retries ไม่ audit ซ้ำ | DB snapshots |
| Planner UI | Add/remove sections/columns, rename/reorder; subtotals/states; 390/1440 และ resize; keyboard/IME/native caret/clipboard; same input identity/caret/focus หลัง response; unsaved warning; read-only/no-JS; uncertain feedback; **score POST = 0** ทุก planner action | Trusted pointer/keyboard/Network + authenticated isolated MAMP |
| Migration/recovery | Upgrade empty/legacy fixtures; CHECK enforcement/cross-scope FK rejection; interrupted DDL recovery; ก่อนมีข้อมูล revert ได้; หลังมีข้อมูล compatible rollback รักษา guards | Disposable DB integration |

Security: tenant/actor จาก session, parameterized queries, bounded payload, tenant composite FKs, live permissions, resource identity, CSRF, lifecycle/current locks, immutable history, exact server totals และ before/after audit ใน transaction. Owner ไม่ใช่สิทธิ์ใหม่. Draft/receipt GET ต้อง authorized; evidence ไม่เก็บ credentials/CSRF tokens/cookies/raw HAR/PII. ใช้ score snapshots/write fixtures บน disposable isolated DB เท่านั้น ไม่ใช้ข้อมูลโรงเรียน/นักเรียนจริง.

### 10.2 Gradebook release gate สำหรับ implementation

แม้ planner assets แยกกัน Phase B เปลี่ยน components/setup/services/shared authorization/loaded assets ที่เกี่ยวข้อง จึงรัน release gate **ครบ** ตาม AGENTS:

- Registry validator 130/125/5 พร้อม baseline comparison ของ approved records/mappings/contract/lock/vendor hashes.
- Golden A–M ด้วย trusted pointer/keyboard/native clipboard และ virtual-row navigation regression.
- Focused suites เดิม: direct entry, explicit editor, selection/range, clipboard/tiling/clear, errors/races/409/uncertain outcome, parity, read-only/history, visual stability/compact/layout/workload และ workspace/feedback.
- Focused PHPUnit: component/identity/lifecycle/permissions/score/batch/workspace/planner และ full PHPUnit แบบ sequential เพื่อไม่ให้ DB fixtures ชนกัน.
- PHP/JS syntax, git diff check, isolated authenticated MAMP: real PP5 login/service/DB, production asset hashes, positive GET 200 Network proof; writes เฉพาะ task-owned synthetic DB.
- Caret/focus issues ของ Gradebook ต้องวัด `document.activeElement`, same input identity, `selectionStart`/`selectionEnd`, logical cell และ network count; `event.defaultPrevented`/input presence อย่างเดียวไม่พอ.
- แยก registry traceability, synthetic checks, trusted fixture, authenticated MAMP, PO visual approval. Required gate ที่ blocked/not run/fail ไม่ใช่ PASS; ไม่ลด expectations/ลบ tests ไม่ upgrade Tabulator ไม่สร้าง E2E harness.

### 10.3 Phase A v1.2 verification scope

ตรวจเอกสารและ working tree เท่านั้น: registry inventory/unchanged approved mappings, branch/HEAD/worktree/remote, preservation hashes, no tracked/staged/migration/service/endpoint/asset changes, doc comparison/whitespace/completeness และ contradiction scan. Actual verification ผลอยู่ section 14 หลัง revision เสร็จ; schema/FK/locking statements ทั้งหมดเป็น proposal ยังไม่ executed/proven runtime.

**ไม่ได้รัน** focused/full PHPUnit, syntax ของ production, Golden/browser/MAMP ใน Phase A v1.2 เพราะแก้ Markdown เพียงไฟล์ไม่มี production/test/schema/asset effect. ไม่มี Gradebook interaction ให้ release จาก revision นี้. ไม่ยืม Task 6U PASS มาอ้าง Task 6V Release Gates PASS. Baseline reports ใช้เข้าใจ accepted behavior เท่านั้น; B5 gates ยัง NOT RUN และ M6.6 visual acceptance PENDING.

### 10.4 Actual lock review and required two-connection concurrency proof (future)

| Existing baseline writer | Observed lock sequence / implication |
| --- | --- |
| GradebookScoreService | school → year → offering → target components → enrollment/placement → AuthorizationRepository joined FOR UPDATE → cells. Batch components/enrollments sorted numeric. Add readiness locks before cell writes per 8.5, no nested service transaction |
| GradebookComponentService | school → year → offering → component → hasScoreHistory FOR UPDATE (NULL rows count). Add governance guard/revision before raw mutations; Managed publish uses internal helpers, not public service transactions |
| SubjectOfferingAdministrationService | create: school → year → classroom → subject; update/status: school → **offering before year** → parents. Same school serializes these despite internal order difference; add cutover immediately after school and classify create atomically |
| AcademicYearAdministrationService | school → year including close. Blocks governed scoring/publish/create through shared school boundary; after close all denied including no-op |
| TeachingAssignmentService | school first, then offering/year and teacher/role/scope; common school lock controls scoped teaching grant revocation path |
| SchoolUserAdministrationService / SchoolMembershipRepository / RoleAssignmentRepository | target membership+user joined FOR UPDATE first, then role assignment mutation; **no common school-first lock shown in service**. AuthorizationRepository score check joins school/offering/user/membership/assignment/role/role_permission/permission_scope; query lock order depends on DB plan |
| System school status / global role-permission mutation | School status locks school audited row; global authorization changes cannot be assumed serialized by school. No new role/permission administration framework proposed; enumerate actual writers during B3 and prove grant locking coverage |

Proposed new workflows (activation/create/ratio/bind/draft/publish/score/component hooks) share school → cutover → years → offerings ascending → parents → governance ascending → annual/links → optional draft → components/sections/memberships → live grant locks/history/cells as needed; creation necessarily inserts Offering after parent checks. Paths not touching components skip those locks. Governance origin/epoch/classification stored in creation commit; ratio/binding revisions stale drafts; draft persistence never reserves readiness. Authorization grant locks held through commit. No claim all current services lock alike or school lock covers nonacademic revocation.

**Deadlock risk:** member-first revocation and school-first grant check can form inversion; B3 must either demonstrate no cycle in actual plan or make a separately reviewed minimal lock-order correction for affected writer(s), with unchanged permissions/tenant/status contract. Do not add unapproved broad authorization rewrite in B1. Required unresolved lock concern blocks activation. On DB deadlock/lock timeout abort full transaction and report safe retry/review, **no automatic score or publish mutation retry**; uncertain commit uses existing score refresh or publish receipt, respectively. Revocation committed first denies; protected write that obtained live locks first may commit before revocation linearizes. Coarse school lock throughput must be measured, not asserted acceptable.

| Two connections / controlled schedule | Required invariant / negative evidence |
| --- | --- |
| Score vs first publish (both lock acquisition orders) | Score first on SETUP_REQUIRED denied with zero cell/audit changes; publish first commits readiness then score revalidates and may write; no transient readiness/scores before receipt |
| Score before ready: no link/ratio, incomplete or valid unpublished draft | Single/batch/internal/repository reject clear/NULL/0/no-op; no saved marker; UI reason/setup action |
| Activation vs Offering create (including blocked creator) | Before boundary PRE+LEGACY; after boundary POST+SETUP_REQUIRED; coverage no missing row; Offering/governance/audit rollback together |
| Offering create retry / classification insert or audit fault | Unique duplicate stays original origin; no orphan or downgrade; release/epoch hash audit correct; raw import omission → UNCLASSIFIED deny |
| Concurrent link and policy edit | One revision wins, other 409; no cross-school/year/class/term pairing or stale ratio; score-entry-only actor denied |
| First-publish freeze vs late T2 create/bind | Ratio freezes exactly once; T2 inherits frozen ratio, origin determined by boundary; valid T1 readiness stays own-term valid; no fake T2 |
| Legacy/Managed boundary and raw component Workspace/Setup POST | Before adoption Legacy status/max rules; after adoption raw deny even flag off. New unlinked always deny; no demotion after status/inactive/archival |
| Stale draft save/publish (two tabs/owners) | expected revision/review hash/history digest; exactly one publish; loser409/no partial component/receipt/audit |
| First score-history row vs max/status proposal | Lock component/history including absent row: score first freezes max and invalidates review; publish first allowed only no-history safe adjustment then score checks committed max; NULL history immutable |
| Failed publish before/after receipt/audit/commit, response loss | Every precommit domain write rolls back; response loss GET receipt proves outcome; same key/hash returns one stored result; key/hash change409; no score writes |
| Revoked authorization: membership/user/role/role_permission/scoped grant | Enumerate joined concrete granting rows, two orderings; committed revocation before validation denies; no deadlock partial success; no unscoped publishing from score-only grant |
| Year close/Offering status or identity/parent inactive vs score/publish | Shared locks serialize; close committed first denies all; no scope move detaches historical annual guard; lifecycle/no-op contract kept |
| Published live invalid/corrupt row, missing classification/import | exact 49.99/50.01/wrong allocation/missing membership/receipt/epoch fail-closed; no repair by score write; Legacy not falsely relabelled |
| Mixed T1 Legacy mutation vs T2 review/publish/score | T1 audit/revision stales preview; T2 own readiness revalidates; no claimed annual100/adoption or student completeness |

Use deterministic barriers/lock waits and separate PDO connections on disposable synthetic DB; verify rows/configuration/audit/receipt before/after, no score/student PII writes in live DB. Schema/security/unit checks, synthetic browser, trusted browser and authenticated MAMP evidence reported separately in B5. All cases **NOT RUN for Task 6V Phase A**.

## 11. Revised B1–B5 dependencies, effort and readiness

Planning estimate for one developer familiar with PP5 after approvals: v1.0 18–25 → v1.1 23–32 → **v1.2 29–40 working days**, because governance/cutover coverage, source-wide enforcement, old endpoints, mixed terms and two-connection rollout/recovery proofs are added. Not commitment/authorization; excludes PO review time/data remediation, historical amendment/collaboration/report engine. Re-estimate after B1 reviewed schema/target DB evidence.

| Subtask | Independently reviewable future output | Dependencies / activation boundary | Effort |
| --- | --- | --- | ---: |
| **B1 Foundation** | Exact approved eight-table schema/keys/CHECKs/FKs, annual identity/explicit links/nullable pre-setup ratio persistence, governance/cutover foundation; S1–S10 recovery; focused schema/security/isolation tests | D2–D10/schema approval + **separate B1 authorization**. No enforcement activation or score-entry behavior change. No migration before review. ARMED/ACTIVE disabled until supported guards/workflow/acceptance | 6–8 days |
| **B2 Draft** | Server JSON draft/OCC/resume/stable keys, partial unbound/pre-ratio save, exact validation/review snapshots/history impact | B1 reviewed, D6/D7 approved + **separate B2 authorization**. No active structure or score writes | 4–5 days |
| **B3 Atomic Publish and Enforcement** | Publish/audit/receipts/freeze/adoption, all New score-service/repository/HTTP+old component endpoint guards, classified creation/import integration/coverage/bootstrap/revision hooks, concurrency/failure/cutover and compatible rollback proofs | B1+B2 reviewed, D6/D8/D10/lock concerns resolved + **separate B3 authorization**. Backend ready does not activate without B4+B5; no silent Legacy exception | 8–11 days |
| **B4 Planner UI** | Single-page native table/ratio/sections/items/draft/review/publish, denied Gradebook context/setup action, 390/1440/IME/accessibility/no-JS/uncertain outcome, zero score POST | B2+B3 contracts reviewed, D9 approved + **separate B4 authorization**. Supported setup/publish workflow prerequisite to activation | 4–6 days |
| **B5 Acceptance** | Legacy+New+Managed+mixed proof, schema/recovery/rollout and two-connection tests, focused/full PHPUnit, registry130/125/5, Golden A–M/focused suites, isolated authenticated MAMP, PO visual acceptance | B1–B4 reviewed + **separate B5 authorization**. Required blocked/failing gate is not PASS. Controlled activation only with independent PO release decision | 7–10 days |
| **Total** | Foundation B1 **6–8 days**; total includes design/test/recovery work | B1→B2→B3→B4→B5; no automatic next task/PR/merge | **29–40 days** |

Each subtask requires separate authorization and review. Feature activation depends on B3 authoritative guard rollout, B4 supported setup/publish + denied-write UX, and B5 release gates/PO acceptance. **B1 alone must never activate mandatory enforcement.** Flags only presentation/availability; after durable activation rollback cannot disable classification/guards.

### 11.1 Historical Phase A B1 readiness checklist (12 October; current approval in Decision Record)

| Gate before B1 | Current assessment |
| --- | --- |
| D1 and mandatory New policy explicitly approved | Confirmed; no need to ask definition/policy again |
| D2 annual identity; D3 actual pairing/authority/no historical re-pair | Recommendation specified; **PENDING PO** |
| D4 integer1–99/exact decimal; D5 first publish freeze; D6 narrow Managed history limits and Legacy preservation | Recommendation specified; **PENDING PO** including accepted-behavior approval boundary |
| D7 draft/privacy/revision/limits; D9 native planner/blocked-score workflow | Recommendation specified; **PENDING PO**; B1 schema depends on draft envelope/nullable annual base |
| D8 eight-table schema; D10 school-wide epoch/provenance/mixed A+conditional C/unknown deny | Exact design proposed; **PENDING PO** |
| Target DB version/CHECK/JSON/composite FK/index and S1–S10 DDL recovery/restore rehearsal | Plan specified; **NOT RUN**, required disposable proof during authorized B1 before deployment |
| Read-only inventory/coverage/bootstrap and import/admin/new-school cutover owners | Design specified; no live inventory performed; operational owner/release procedure must be approved before activation |
| Score/component call map and real lock-order review | Source surveyed; **runtime/concurrency proof NOT RUN**; revocation lock inversion remains B3 blocker unless resolved/reviewed |
| Separate explicit B1 task authorization | **NOT GIVEN**; Phase A request expressly forbids implementation/migration |

Assessment: **ready for PO decision review, NOT AUTHORIZED / NOT IMPLEMENTATION-READY for B1**. After PO records required decisions and separately authorizes B1, foundation may begin behind inactive rollout; this document does not pass runtime gates or authorize B2–B5.

## 12. Final D1–D10 decision matrix / remaining PO decisions

**D1 APPROVED; New-course mandatory policy APPROVED; D2–D10 APPROVED WITH CONDITIONS A–D**, per the 13 October 2026 Decision Record. Architecture approval is distinct from Exact DDL Verification, B1 Implementation Verification and Release Activation Approval. Fixed50/100, draft separation, no score/max/history rewrite and accepted Gradebook interactions remain confirmed constraints.

| ID | Status | Final v1.2 recommendation for PO decision |
| --- | --- | --- |
| D1 | **APPROVED** | COURSEWORK:FINAL_EXAM; midterm CW; own50/ratio each term; annual100 only both structurally ready, not student completeness |
| D2 | **APPROVED WITH CONDITIONS A–D** | One annual instance/school/year/classroom/course; not Subject ID; classroom ratios can differ; no school-wide shared ratio authority |
| D3 | **APPROVED WITH CONDITIONS A–D** | Actual Offering IDs/different subjects allowed; ≤1 each term; existing **unscoped GRADEBOOK_COMPONENT_MANAGE** academic confirmer needs PO endorsement; explicit reason/audit; no auto-pair/historical re-pair with dependents |
| D4 | **APPROVED WITH CONDITIONS A–D** | Integer1–99/sum100; exact decimal/integer-hundredths; extremes0:100/100:0 and percentage decimals disabled in first release |
| D5 | **APPROVED WITH CONDITIONS A–D** | First publish freezes ratio; T1 own-ready without T2; authorized late T2 same annual identity/frozen ratio; post-publish amendment disabled/separate future decision |
| D6 | **APPROVED WITH CONDITIONS A–D** | Safe rename/reorder/add/no-history max+deactivate/same-classification corrections under atomic50/ratio/revision/audit; Managed historical deactivate/reactivate/reclassification/cross-group and forced inconsistent Legacy correction disabled; Legacy historical status preserved; max with any history immutable |
| D7 | **APPROVED WITH CONDITIONS A–D** | Private server JSON/one owner-offering/revision409/resume/stable keys; 100 sections/200 items/1MiB, UI soft warnings; no collaboration/transfer |
| D8 | **APPROVED WITH TECHNICAL GATES** | Normalized6 structural tables + independent governance+school cutover = **8 new tables**; exact model/recovery in5.2–5.6, Offering candidate-index ALTER and governance receipt-FK ALTER; six alone cannot enforce unlinked New |
| D9 | **APPROVED WITH CONDITIONS A–D** | One compact Tabler native-input page; teacher vocabulary/annual+T1T2 context/ratio/draft/review/publish/history/390+1440/accessibility/IME/no accidental Enter publish/no-JS; blocked-score setup action; no Tabulator contract changes |
| D10 | **APPROVED WITH CONDITIONS A–D** | Strategy B, immutable PRE/POST provenance + school-wide durable epoch ACTIVE; missing governance deny; serialized create/activation/score; Mixed default A and conditional C, no grandfathered B exception; no activation until B3+B4+B5 and independent release approval |

Product decisions above are approved by the [13 October Decision Record](../notes/2026-10-13-m6-6-task-6v-product-owner-decision.md), including binding Conditions A–D. Existing recommendation text is preserved as the decision subject. Historical aggregate correction, historical re-pair and post-publish ratio amendment remain disabled/separate future decisions. Condition D requires reviewed contract/registry/golden/focused/change-history evidence before any affected accepted runtime behavior changes.

B1 technical evidence: exact target-engine DDL/constraints/recovery and B1 implementation verification PASS on disposable MySQL 8.0.40, as documented in the B1 report; worktree awaits PO review. Authorization locking concurrency proof remains a mandatory B3 activation blocker. B1 authorization is GIVEN for worktree/disposable testing only; B2–B5 and release activation are NOT AUTHORIZED. No runtime PASS follows from product approval.

## 13. Revision differences

### 13.1 Historical v1.0 → v1.1 change record (superseded where v1.2 differs)

ตารางนี้เป็นประวัติ v1.1 ไม่ใช่ recommendation ปัจจุบัน; D6 OPEN/six-table-only/optional New policy ถูกแทนโดย 13.2 และ current sections.

| v1.0 area | v1.1 change | Safety analysis retained |
| --- | --- | --- |
| Title/status/revision | Revision v1.1/history/D1 authority; design-only STOP | Same file/untracked; no production/commit/Phase B |
| D1 ambiguity | Confirm midterm COURSEWORK; four per-term ratio examples/formulas; remove open-midterm interpretation | No annual offset/inferred classification/changed scores |
| Strict same-Subject-ID scope | Recommend B Annual Course Identity + explicit different-ID links; compare A/B/C; individual subject-scoped Offering FK | Tenant/year/classroom integrity/lifecycle/audit/no auto-pair |
| Ratio 0–100 proposal | Recommend 1–99; extremes remain open and disabled | Exact integer hundredths/DECIMAL; rounding decision required for percentage decimals |
| Six-table policy/link model | Annual policies → annual courses (identity + ratio); links → annual course offerings; compare five-table section_id alternative/recommend six; exact columns/indexes/FKs/CHECKs/revisions/DDL recovery | Additive proposal; no schema write in Phase A; RESTRICT; compatible rollback |
| Draft limits/ownership | Keep 100/200/1 MiB; practical soft UI warnings 12/60/batch add 20; private owner/offering rationale; inactive archive digests | Server JSON/partial save/stable identities/OCC409/live permission/no PII |
| Freeze / later T2 | Compare A/B/C freeze; recommend B; later different-ID T2 bind preserves valid published T1 | Shared policy impact/revisions/CLOSED guards/no fake Offering |
| Blanket history topology/status ban | Replace with 12-action safe-compatibility matrix; historical status/group change OPEN | Any NULL history freezes individual max; no delete/rewrite; audit/locks retained |
| Global legacy structural cutover | Withdraw forced cutover; unlinked/linked LEGACY workflow retained + revision hook; managed guard after explicit adoption | No auto-normalize/backfill/pair; legacy read/score preserved; flag never disables managed guard |
| Atomic publish/idempotency | Live annual/link identities; review hash; first freeze; receipt before stale validation; late T2 binding; unique receipt + uncertain GET | Single PDO transaction/no score writes/audit rollback/live auth/stable locks/no automatic retries |
| Wireframes | Annual identity/linked Offerings/T1-T2 readiness/47.00 partial draft/history/review; 70:30 + 80:20/390px; explicit Enter/form boundary | Native inputs/IME/page-scoped events/dirty warning/no-JS/separate Gradebook editing contract |
| Tests/effort/open decisions | Update identity/status/legacy test plan; B1–B5 dependencies and 23–32 days; D1 approved/D2–D9 pending | Full future AGENTS release gates; synthetic/trusted/MAMP distinguished; PO visual PENDING |

Original source/history/security/atomicity/idempotency/precision/dirty-state/no-JS/release-gate analysis ที่ยังถูกต้องคงไว้หรือขยายพร้อมข้อแก้ในตารางนี้. ถอนเฉพาะ assumptions ที่ PO review ให้แก้; ไม่ลบ tests/registry mappings. Detailed v1.0→v1.1 comparison เป็น historical temporary evidence ของ v1.1; section 14 ปัจจุบันตรวจ v1.1→v1.2.

### 13.2 Exact v1.1 → v1.2 changes

| v1.1 behavior/design | v1.2 replacement | Retained correct analysis |
| --- | --- | --- |
| New before publish could use Legacy score/component workflow | Mandatory POST-origin setup/ratio+exact50 published before all score writes; raw structural routes deny before and after publish | PRE Legacy accepted read/entry/component behavior preserved; no score rewrites |
| Managed authority on link mode; unlinked New cannot be identified | Separate governance PK scoped Offering, immutable origin/epoch/state/revisions and receipt/adoption provenance; no default Legacy from missing link/row | Explicit annual actual IDs/scoped FKs; no auto-pair or Subject-ID authority |
| Six structural tables recommended unconditionally | Keep normalized6 base + governance/cutover2 = **8**; compare A/B/C/D with ALTER/correctness/recovery | No component/score column change in chosen option; independent membership |
| Annual ratio mandatory to create annual row; Draft FK to link | Both ratio fields NULL before configuration; draft FK governance/base annual revision nullable, publish requires configured/bound/rebased evidence | Private server draft, partial invalid fields, stable keys, exact arithmetic/409/no active draft |
| Link owns structure revision; linked Legacy revision hook | Governance owns single structure revision for linked/unlinked; link only binding/publish provenance | Fingerprint/history digest/live revalidation; OCC/idempotency/immutable receipt |
| Rollout UI flag/adoption mostly legacy-focused | Durable school PREPARING→ARMED→ACTIVE boundary, classified creation in same transaction, school serialization/coverage/all workers/import guards | Safe DDL implicit-commit recovery, additive backfill no scores, compatible rollback |
| Historical Managed status changes OPEN/checkbox approach | D6 recommended disabled historical status/cross-classification/aggregate remediation and ratio amendment in first release | Legacy history status allowed baseline; any NULL history freezes max; safe no-history/metadata corrections |
| No specific mixed Legacy/New enforcement policy | D10 A own-New-term valid publish + Legacy untouched; C if relationship/adoption proof fails; B disabled exception | No false annual compliance100/student completeness, no historical normalization |
| Structural endpoints map only | Add both score HTTP/controller/service/repository paths/internal calls/SQL search, negative setup+Workspace/direct call coverage | CSRF/live grant/NULL0/enrollment/max/audit/atomic batch/409/uncertain-write semantics |
| Generic shared lock narrative | Actual source order including offering-before-year/admin vs membership-first role revocation; explicit two-connection matrix/activation blocker | School serialization, history first-row checks, no speculative runtime PASS |
| Planner wireframes | Retain native tables/390+1440/context/totals; add teacher text/blocked setup action/pre-ratio draft/unconfigured state | IME/native caret/input identity/no Enter publish/no-JS/zero score POST/no Tabulator change |
| B1–B5 23–32 days/D2–D9 decision list | Governance B1 foundation, B3 enforcement, B4 workflow, B5 acceptance before activation; 29–40 days; D2–D10 pending, B1 checklist/no authorization | Each task separate PO order; full future AGENTS release gates/visual PENDING/STOP |

Preserved v1.0→v1.1 history stays above; v1.2 comparisons use `/private/tmp/pp5-6v-v1.1-design.md`. This is one revised proposal, no duplicate repository design/new harness.

## 14. Phase A v1.2 verification evidence (documentation only)

- Baseline/local tracking/actual remote branch: `milestone/6-6-soft-government-ui`, HEAD and remote `186dfb56a232149313ef4005c98209e6f7149424`; origin URL unchanged. Current read-only ls-remote initially sandbox DNS failed, approved read-only retry succeeded; no fetch/reset/stage/commit/push.
- Pre/post manifest `/private/tmp/pp5-6v-v1.2-preservation.json` compares all 344 tracked files, index metadata and untracked path set11; only existing untracked proposal changes. Task6T9/9 and Task5R1/1 original SHA256 preserved (table below); no harness/schema/service/route/asset/test modifications.
- Registry validator current result: **PASS130/125/5, all original124 retained**. Registry/approved mappings/contract/lock/vendor unchanged against HEAD and preservation hashes. This is traceability validation, not runtime/browser proof.
- Whitespace/table/section/status and contradiction scan + v1.1 comparison; exact current D2–D10 all pending PO, New mandatory approved distinct from mechanism. DDL/activation/lock/guard descriptions are proposals, not executed checks. Final verification artifacts `/private/tmp/pp5-6v-v1.2-audit.json`, `/private/tmp/pp5-6v-v1.2-doc-checks.json`, `/private/tmp/pp5-6v-v1.2-document.diff`.
- **No production DB connection/writes/inventory; no new test harness.** PHPUnit/Golden/trusted-browser/authenticated MAMP for Task6V **NOT RUN — Markdown-only Phase A**. No Task6V runtime PASS borrowed from6T/6U. M6.6 visual acceptance **PENDING**.

Preservation hashes (SHA256 ก่อนแก้; verify หลังแก้ต้องตรง):

| Preserved file | SHA256 |
| --- | --- |
| `docs/superpowers/notes/2026-10-09-m6-6-gradebook-explicit-editor-regression.md` | `e92361caa761f2da5a84ab9d6e79d933a50130a1f759abf52249b0757e49ad58` |
| `docs/superpowers/notes/2026-10-12-m6-6-task-6t-browser-e2e-harness.md` | `0274ce772ab58d0d98db1025e97a2c278cbf8c06208428d8080feeeb590b5989` |
| `docs/testing/gradebook-browser-e2e.md` | `26c0b4f0122b33485ffe897fa2f0dc783d0061009ad6cd2652c27bff15c80b49` |
| `tests/E2E/authenticated.cjs` | `43457d8b066440180f97816a3f89e1f5e933b85e0ae0765b1f0e042dcdc65099` |
| `tests/E2E/database.php` | `ffcce50e2da7d42b079fedbfaf4ac236443e936e5139a018dd66bf1f8fdba041` |
| `tests/E2E/fixtures.cjs` | `53edfd0e7dc312d4edce02244bca10f95739c5ed914920b4b75caf6229a560e4` |
| `tests/E2E/provision.php` | `6873735c7c539ea350d919ce10c90d801538255ef56abe1f791345645ae4703c` |
| `tests/E2E/run.cjs` | `f0ad0a59cd4393e4c8a90619e70792c964fcd34e24c37eea1b59b53e78484020` |
| `tests/E2E/web-router.php` | `e097f252f0bb33a05e0c4e141bb76941c1b6570ff25732f8eb1df73df57fcf58` |
| `tools/e2e/run-gradebook.sh` | `f537cc88f158c69b186849982b592c2f1ad978f142b03f68857f197feef6d18d` |

Verification result: **PASS — document/worktree preservation only**; final audit recorded in temporary artifacts above. Registry PASS is separate traceability check. Current Gradebook runtime/synthetic/trusted-browser/MAMP release gates for Task6V remain NOT RUN. No implementation permission or migration readiness proof from this document.

## 15. Historical Phase A implementation readiness / final STOP (12 October)

v1.2 closes the New-course enforcement design gap and is ready for Product Owner decision review; **NOT AUTHORIZED / NOT IMPLEMENTATION-READY for B1** until D2–D10/exact schema are approved and separate B1 instruction arrives. D1 and mandatory New policy are already APPROVED; proposed governance Strategy B/eight-table model and Mixed A+conditional C remain pending. No runtime/DDL/concurrency/HTTP/browser/MAMP proof is claimed. B1 cannot activate alone; required B3+B4+B5/PO release boundary is explicit. No new framework/permission/schema/services/routes/assets/tests in this Phase A.

**TASK 6V PHASE A v1.2 — DESIGN REVISED / AWAITING PRODUCT OWNER APPROVAL**

**STOP AFTER DESIGN REVISION. ห้ามเริ่ม Phase B / สร้าง migration / แก้ Production Code / Commit / Push / PR / Merge / Milestone 7.** แต่ละ B1–B5 ต้องคำสั่งแยกและหยุดให้ review. M6.6 Product Owner visual acceptance remains **PENDING**.
