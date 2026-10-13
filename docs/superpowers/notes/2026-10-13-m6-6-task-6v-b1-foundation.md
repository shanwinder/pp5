# M6.6 Task 6V-B1 — Annual Identity, Ratio & Governance Foundation

วันที่ 13 October 2026 · Asia/Bangkok

**B1 ACCEPT WITH CONDITIONS / REVIEW CLOSURE VERIFIED / AWAIT PRODUCT OWNER COMMIT AUTHORIZATION**

Current status, 14 October 2026: Product Owner รับ B1 WITH CONDITIONS; ตรวจรายการปิดครบตาม [Review Closure Addendum §19](#19-b1-review-closure-addendum--14-october-2026). Evidence/approval wording ใน §1–18 เป็นบันทึก B1 เดิม ณ 13 October; Addendum เป็นสถานะล่าสุด. ไม่มี release activation หรือ commit/push authorization.

งานจำกัด B1: schema foundation, internal backend ที่ยังไม่เชื่อมกับ runtime, tests และ review evidence. ไม่ commit/push, ไม่ PR/merge, ไม่เริ่ม B2–B5/M7. M6.6 Product Owner Visual Acceptance **PENDING**.

## 1. Product Owner Decision Record และ approval layers

Authority อยู่ที่ [Product Owner Decision Record](2026-10-13-m6-6-task-6v-product-owner-decision.md): D1–D10/New-course policy และเงื่อนไข A–D จาก Authority Supplement เก็บครบตามข้อความต้นฉบับ ตรวจเทียบแล้วตรงกัน. [Proposal v1.2](../specs/2026-10-12-pp5-m6-6-task-6v-score-structure-design.md) ปรับเฉพาะ current approval references/status/matrix; architectural analysis และ historical revision notes คงเดิม.

| Layer | สถานะ |
| --- | --- |
| Product Decision Approval | APPROVED ตาม A–D |
| Architecture Approval | Eight-table recommendation APPROVED WITH TECHNICAL GATES |
| Exact DDL Verification | PASS บน disposable MySQL 8.0.40 ตามขอบเขตด้านล่าง |
| B1 Implementation Verification | PASS บน disposable DB; ยังรอ PO review worktree |
| Release Activation Approval | NOT GRANTED; ต้อง B3+B4+B5 และ independent PO release approval |

การอนุมัติ product ไม่ถูกนับเป็น runtime evidence. Condition D เป็น Managed decision สำหรับ implementation ภายหลัง; ไม่มีการเปลี่ยน Legacy status semantics/contract/registry ใน B1.

## 2. Baseline / branch / HEAD / remote

- Repository: `/Applications/MAMP/htdocs/pp5`
- Branch: `milestone/6-6-soft-government-ui`
- Required HEAD / actual HEAD / actual remote SHA: `186dfb56a232149313ef4005c98209e6f7149424`
- Origin: `https://github.com/shanwinder/pp5.git`
- ตรวจ actual remote ด้วย read-only `git ls-remote` ทั้ง preflight และ delivery; ไม่ fetch/reset/stage/commit/push.
- เริ่มงานไม่มี tracked/staged changes; untracked เดิม 11 ไฟล์. ทุก tracked file เดิม 344 ไฟล์และ index คง byte เดิมถึงส่งมอบ. งานใหม่ยัง untracked ทั้งหมด.

## 3. Preservation manifest

หลักฐานก่อนแก้: [preservation.json](/private/tmp/pp5-6v-b1-preflight-xpg69p6k/preservation.json). หลังแก้: [preservation-after.json](/private/tmp/pp5-6v-b1-preflight-xpg69p6k/preservation-after.json).

Task 6T **9/9** และ Task 5R **1/1** คง SHA256 เดิม/ยัง untracked; ไม่มีการแก้ harness. Proposal เดิมเก็บ backup ก่อนแก้ที่ [design-before.md](/private/tmp/pp5-6v-b1-preflight-xpg69p6k/design-before.md); เปลี่ยนเฉพาะสถานะและ approval references ที่ได้รับคำสั่ง.

Task 5R SHA256: `e92361caa761f2da5a84ab9d6e79d933a50130a1f759abf52249b0757e49ad58`.

Registry/contract/Thai lock/Golden mappings/vendor/assets/views/routes/score and component services คงเดิม. Registry **130 records / 125 accepted / 5 retired UI**, original 124 IDs อยู่ครบ. ไม่มีการอ้าง lock เป็น GitHub mechanical protection.

## 4. Exact eight-table DDL inventory

สร้าง **10 SQL files: 8 CREATE TABLE + 2 ALTER TABLE** ใน [database/review/6v-b1](../../../database/review/6v-b1/README.md), นอก glob `database/migrations/*.sql` ของ runner เดิม. Repository automatic migration directory ยังมี 6 ไฟล์เดิมเท่านั้น. การย้าย SQL review ไป deployment path ต้องได้รับ review/คำสั่งแยก; ไม่ให้ MAMP runner พบโดยอัตโนมัติ.

| Step / SQL filename | Object / integrity |
| --- | --- |
| `20261013_001_offering_annual_candidate.sql` | เพิ่ม UNIQUE Offering `(id,school_id,academic_year_id,classroom_id,subject_id,term_no)` |
| `20261013_002_gradebook_school_cutovers.sql` | Durable school epoch, phase/revision, inventory/activation metadata, actor FKs |
| `20261013_003_gradebook_offering_governance.sql` | Scoped Offering authority, immutable-origin design, classification/adoption/revisions, receipt indexes |
| `20261013_004_gradebook_annual_courses.sql` | Annual ID/course UUID/school-year-classroom scope, nullable ratio, freeze/revision/audit actors |
| `20261013_005_gradebook_annual_course_offerings.sql` | Explicit actual Offering tuple; one Offering per annual term / one annual per Offering |
| `20261013_006_gradebook_score_sections.sql` | Scoped section key/classification/order/status |
| `20261013_007_gradebook_component_sections.sql` | One current membership per component; scoped component and section FKs |
| `20261013_008_gradebook_structure_drafts.sql` | Private owner/Offering draft identity; native JSON, nullable annual base, revisions |
| `20261013_009_gradebook_structure_publish_receipts.sql` | Scoped receipt; unique idempotency key and draft/revision, result hashes/JSON |
| `20261013_010_governance_receipt_foreign_keys.sql` | Scoped first/latest receipt FKs; completes circular deployment dependency |

Exact columns/types/defaults/keys/check expressions อยู่ใน SQL ทั้งสิบไฟล์. Final engine-rendered DDL ของทั้งสาม disposable schemas อยู่ใน [final-isolated-inventory.json](/private/tmp/pp5-6v-b1-preflight-xpg69p6k/final-isolated-inventory.json). New FKs ทั้ง **32** เป็น RESTRICT/RESTRICT; ไม่มี score/component ALTER, trigger, new permission, endpoint หรือ schema framework.

Index ที่ซ้ำ tuple เดียวกับ UNIQUE สำหรับ link/governance ไม่สร้าง nonunique ซ้ำโดยไม่จำเป็น: UNIQUE ที่มี leading tuple รองรับ FK อยู่แล้วตามแบบ. ไม่มี material identity/integrity/schema correction; exact approved eight-table model คงเดิม.

## 5. Target compatibility และ pre-DDL gate

Actual MAMP metadata: MySQL **8.0.40**, `pp5`, port **8889**, strict SQL mode (`ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`). ตรวจ parent candidate indexes และ metadata โดย READ ONLY transaction; ไม่อ่าน application/student rows. ตรวจ parent unsigned column types เพิ่มใน final metadata verification.

Disposable server ใช้ binary MySQL 8.0.40 เดียวกัน แต่ initialize data directory ใหม่ ไม่มี restore/copy ข้อมูลจริง:

- Data directory: `/private/tmp/pp5-6v-b1-preflight-xpg69p6k/mysql-isolated/`
- Dedicated loopback port: **13396**; socket และ PID/log files แยกจาก MAMP.
- Schemas: `pp5_b1_schema` (fresh B1), `pp5_b1_recovery` (legacy-shaped/recovery), `pp5_test` (baseline full regression แล้วติดตั้ง B1 เพื่อ focused post-migration regression).
- Test principal: `pp5_b1@localhost`, grants เฉพาะสาม disposable schemas; ไม่มี global/SUPER privilege.
- Connection guard ตรวจ host/port/datadir/selected DB/principal และ ownership token ก่อน fixture writes. Tests B1 ไม่ fallback ไป live config; ไม่มี ownership configuration จะ SKIP แทนการเขียน DB ที่ไม่ทราบตัวตน.
- Baseline tests ที่ hardcode `pp5_test` รันจาก temporary runtime/config ที่ชี้ port 13396; ไม่ใช่ `pp5_test` บน MAMP 8889.

Native JSON, CHECK enforcement, composite FK tuple order, parent candidate indexes, ASCII binary UUID/hash และ utf8mb4 labels ผ่าน actual engine tests. Nullable ratio/freeze/receipt/adoption ใช้ explicit NULL branches กัน SQL UNKNOWN. User FK พิสูจน์ user existence เท่านั้น; live tenant/grant checks อยู่ใน service.

Sandbox startup ครั้งแรก initialize ไม่สำเร็จ; ใช้ fresh data directory ใหม่และ authorized process execution สำเร็จ. ไม่ใช้ directory ที่ initialize ไม่สมบูรณ์. Schema/fixture writes ทั้งหมดเกิดหลัง private server identity verification.

## 6. Migration sequence / recovery proof

Fresh S1–S10 CREATE/ALTER ผ่านครบ. ใช้ unchanged `tools/migrate.php` จริงใน temporary runtime โดย stage SQL เข้า migration folder เฉพาะ copy นั้น: [existing-runner.log](/private/tmp/pp5-6v-b1-preflight-xpg69p6k/existing-runner.log). Final ledger ของทั้งสาม schemas = **16 entries (6 baseline + 10 B1)**; S10 ครบ.

[Recovery rehearsal](../../../tests/Database/gradebook-foundation-recovery.php) ผ่าน **46 checks**: [recovery.json](/private/tmp/pp5-6v-b1-preflight-xpg69p6k/recovery.json).

- จำลอง interruption หลัง DDL commit ก่อน ledger INSERT ทุกขั้น S1–S10.
- Blind replay ต้องถูก engine ปฏิเสธ; ไม่มี `IF NOT EXISTS` ซ่อน mismatch.
- เปรียบเทียบ complete `SHOW CREATE TABLE` กับ fresh reference (normalize เฉพาะ AUTO_INCREMENT counter); จึง reconcile ledger เฉพาะ exact completed step ใน rehearsal.
- ตรวจพบ ledger-complete/schema-absent, candidate index tuple ผิด และ S10 ขาด latest receipt FK.
- ทดลองซ่อมเฉพาะ index/FK ที่สร้างใน disposable test หลังตรวจ mismatch แล้ว attestation ซ้ำ; ไม่สมมติ transactional DDL rollback.
- Legacy-shaped fixture มี synthetic scores NULL/0.00/1.50, maxima 23.75 และ audit history. Hash ของข้อมูลทุก baseline table ก่อน/หลังเท่ากัน; ไม่มี automatic governance classification/annual pairing.

นี่เป็น disposable rehearsal ของ manual verified recovery ไม่ใช่ production auto-repair runner. Deployment/recovery จริงยังต้องคำสั่งและ review แยก.

## 7. Annual identity / explicit link foundation

`GradebookAnnualCourseFoundationService` ยังไม่ถูก wire ใน Application/route/controller. Internal create ใช้ authenticated server school/actor context, stable server-issued UUID สำหรับ retry, explicit actual Offering IDs 1–2 รายการ และเหตุผลรับรอง.

School lock → inactive PREPARING cutover → writable year → sorted actual Offerings → classroom/subjects → scoped governance → live unscoped component-manage grant locks. ตรวจ active parents/scope/term uniqueness; create annual row (ratio NULL) + links + audit ใน PDO transaction เดียว. Audit fault rollback ทั้ง identity/links; ไม่เกิด orphan. ไม่มี auto-pair, automatic adoption หรือ score writes.

Identical retry คืน ID เดิมโดยไม่เพิ่ม audit; changed request/actor/reason หรือ Offering ที่ถูกผูกด้วย key ใหม่ให้ conflict. Two real concurrent processes ถูกยืนยันว่ารอ school lock พร้อมกันก่อนปล่อย; จบด้วย annual 1 row, links 2 rows, audit 1 row และ ID เดียวกัน. CLOSED year ปฏิเสธแม้ identical retry.

Scope ของ B1 service จงใจเป็น **unconfigured annual creation foundation**; ไม่มี ratio-edit/publish/freeze/late-term rebind workflow หรือ public mutation endpoint. Persistent ratio/freeze fields, validation และ revision primitives พร้อมให้ขั้นถัดไปพัฒนาภายใต้ authorization แยก.

## 8. Governance/cutover และ draft/receipt boundary

`GradebookGovernanceRepository` เป็น transaction-required scoped locking reads; missing governance/invalid origin-state/epoch mismatch fail closed ใน foundation. ไม่มี API update origin/classification, bootstrap, ARMED/ACTIVE transition, downgrade หรือ activation. Service B1 ใช้เฉพาะ PREPARING + PRE_ACTIVATION/LEGACY.

POST_ACTIVATION/SETUP_REQUIRED แบบไม่มี annual link และ private pre-ratio JSON draft representable โดย actual isolated fixtures. Receipt/governance cross-scope rejection, receipt uniqueness, component membership และ retention FKs ผ่าน DB negatives. Test raw SQL fixture ไม่ใช่ publish orchestration/readiness proof.

Sections/membership/drafts/receipts ใน B1 มี schema เท่านั้นตาม minimum infrastructure ที่จำเป็น; ไม่สร้าง unused public writers หรือ draft editing/publishing workflow ล่วงหน้า. Classification integration/coverage/bootstrap/creation audit/guards เป็น B3; existing runtime ไม่ถือว่าตาราง governance มีข้อมูลครบ.

## 9. Ratio precision และ revision validation

`GradebookAnnualRatio` รับ integer 1–99/sum100; rejects float/decimal percentage/exponent/boolean/array/NULL/invalid sum ก่อน database coercion. ทดสอบทุก ratio 1:99 ถึง 99:1 ว่า targets รวม 5000 hundredths; 65:35 = 3250/1750. Decimal maxima parse จาก string เท่านั้น; 49.99/50.00/50.01 คง 4999/5000/5001 ไม่ใช้ binary floating point/epsilon.

SQL TINYINT เก็บ integer; ไม่อ้างว่า column type ป้องกันการปัดค่าจาก arbitrary raw fractional SQL input. Foundation input validator เป็น boundary สำหรับ exact percentage syntax. Aggregate publish validation ยังเป็น B3.

`GradebookFoundationIdentity` ตรวจ tenant/year/classroom, canonical UUID/text และ exact revision/OCC/overflow. ไม่มี revision write hook เข้า existing component service ใน B1.

## 10. Isolation / lifecycle / security tests

Actual negative cases ครอบคลุม wrong school/year/classroom/subject/term FK tuples รวม valid existing different-year/classroom parents, duplicate annual term, Offering linked twice, different Subject IDs แบบ explicit, invalid actor FK, nullable/invalid/frozen ratio, unknown governance/cutover/revision, JSON corruption, scoped membership/receipts, draft/receipt uniqueness และ RESTRICT deletion.

Service tests ครอบคลุม cross-school actor/resource, missing cutover/governance/epoch, CLOSED/inactive parents, inactive user/membership/assignment, missing/unscoped versus scoped-only permission, no confirmed Offering, duplicate creation/retry/concurrency และ audit rollback. Existing authorization code/permissions/seeds ไม่เปลี่ยน. CSRF/HTTP proof ของ new mutation = NOT RUN เพราะไม่มี new endpoint.

## 11. Legacy compatibility และ runtime effect

ไม่มี existing production source/asset/view/layout/keyboard/focus/route/config file เปลี่ยนแม้แต่ byte เดียว. Existing score/component behavior ไม่ถูก wire กับ foundation classes. ไม่มี governance default/fallback ที่เปลี่ยน Legacy runtime.

Full baseline tests และ focused post-migration tests ผ่านแยกกัน. Test expectations เดิมของ schema ระบุ baseline keys/6-file ledger แบบ exact จึงคงไว้และรันใน baseline disposable schema; B1 schema assertions รันในอีก migrated schema. ไม่แก้/ลด/ข้าม assertion เหล่านั้นเพื่อให้ schema ใหม่ดูผ่าน. Full single-schema run ที่บังคับ legacy schema assertions ให้คาดหวัง migrated ledger ไม่ได้อ้างว่า PASS.

## 12. Verification matrix

| Gate | Result / evidence |
| --- | --- |
| Exact S1–S10 on fresh isolated target | PASS; 8 tables + candidate index + receipt FKs |
| Legacy-shaped upgrade/recovery | PASS; 46 checks / equal old-data hashes |
| Focused foundation + teaching + Gradebook PHPUnit | PASS **1,155 tests / 26,024 assertions**; [focused.log](/private/tmp/pp5-6v-b1-preflight-xpg69p6k/focused.log) |
| Full PHPUnit final | PASS **3,198 tests / 71,150 assertions**, 1:26.847; [full-final.log](/private/tmp/pp5-6v-b1-preflight-xpg69p6k/full-final.log) |
| Existing runtime behavior after B1 schema installation | PASS **1,017 tests / 23,589 assertions**; [migrated-focused.log](/private/tmp/pp5-6v-b1-preflight-xpg69p6k/migrated-focused.log) |
| Added B1 cases | 92 cases included in final full run; actual DB schema/security/atomicity/concurrency plus unit precision |
| PHP syntax | PASS all **10 added PHP files** |
| Registry traceability | PASS **130/125/5**, original124 retained; mappings unchanged |
| Whitespace/diff/preservation | PASS; review diff includes untracked additions separately |
| Golden A–M / synthetic browser / trusted browser | NOT RUN for B1; no loaded asset/view/shared focus/keyboard/browser-test/runtime scoring changes |
| Authenticated MAMP browser proof | NOT RUN for B1; metadata-only live MAMP checks are not browser evidence |
| B3 permission-revocation two-connection proof | NOT RUN / mandatory pre-activation blocker |
| Product Owner visual acceptance / release activation | PENDING / NOT GRANTED |

Full run รอบแรก FAIL 6 asset checks เพราะ temporary copy omit `assets/vendor`; เติม pinned assets เดิมเข้า copy แล้วรัน full suite ใหม่ผ่าน. Focused รอบแรกมี sandbox connection errors; authorized isolated rerun ผ่าน. Test-development trigger fault injection ถูกแทนด้วย PDO fault injection เพื่อไม่ต้อง SUPER; concurrency barrier ตรวจทั้ง Query/Execute สำหรับ native prepared statements. ไม่ลบ/ลด assertions และไม่ยืม Task 6U browser PASS มาอ้าง B1.

## 13. Changed files / diff

Review package มี **23 new files + proposal เดิมแก้ approval status 1 file**. ทั้งหมดไม่ stage. รายการเต็มและ SHA256: [changed-files.json](/private/tmp/pp5-6v-b1-preflight-xpg69p6k/changed-files.json); [complete B1 diff](/private/tmp/pp5-6v-b1-preflight-xpg69p6k/b1-review.diff).

- `database/review/6v-b1/`: SQL 10 files + README.
- `docs/superpowers/notes/`: Decision Record และรายงานนี้.
- Proposal v1.2 เดิม: current status/approval references/matrix เท่านั้น.
- `htdocs/app/Repositories/`: `GradebookAnnualCourseRepository.php`, `GradebookGovernanceRepository.php`.
- `htdocs/app/Services/`: `GradebookAnnualCourseFoundationService.php`, `GradebookAnnualRatio.php`, `GradebookFoundationIdentity.php`.
- `tests/Feature/`: `GradebookAnnualCourseFoundationTest.php`, `GradebookFoundationSchemaTest.php`.
- `tests/Unit/GradebookAnnualFoundationTest.php`, `tests/Support/GradebookFoundationDatabase.php`, `tests/Database/gradebook-foundation-recovery.php`.

`git diff` ปกติว่างเพราะ baseline tracked files ไม่เปลี่ยนและ additions ยัง untracked; complete review diff เปรียบเทียบไฟล์ใหม่กับ empty และ proposal กับ preserved preflight copy. ไม่ใช้ bulk staging เพื่อทำ diff.

## 14. No production DB write evidence / cleanup

SQL ถูก execute **เฉพาะสาม schemas บน private server port 13396**. Live MAMP ใช้ READ ONLY metadata transactions เท่านั้น. Final [production-metadata-post.json](/private/tmp/pp5-6v-b1-preflight-xpg69p6k/production-metadata-post.json) ยืนยัน `pp5:8889`: **B1 tables 0, new candidate index 0, ledger 6 entries เดิม**. ไม่อ่าน/แก้คะแนนหรือใช้ข้อมูลจริงเป็น fixtures.

Private schemas ทั้งสามจบที่ ledger16 และ **ACTIVE cutovers 0**. ปิด private MySQL ด้วย SHUTDOWN หลังยืนยัน datadir/port; retained data/evidence เป็น synthetic ใน private temporary directory สำหรับ review. ไม่หยุดหรือแก้ process/data directory ของ MAMP จริง. ไม่มี live governance bootstrap, no new scoring guards, no normalization/rewrite.

## 15. Remaining B3 authorization lock inversion concern

Condition C ยัง binding: school-first scoring/publishing อาจกลับลำดับกับ membership/user-first permission revocation. B1 ไม่แก้ AuthorizationRepository/SchoolUserAdministrationService และไม่มี broad rewrite. Concurrent annual creation proof ไม่ใช่ permission-revocation proof. ต้องตรวจ actual production SQL/plans/grant writers และ two-connection schedules ทั้งสองลำดับใน B3; หากต้องแก้ให้เสนอ minimal corrective change preserving authorization/tenant/lifecycle ก่อน activation.

## 16. Risks / limits

- ไม่มี B1 technical failure ค้างจาก gates ที่รัน; ผลนี้รับรอง foundation ใน disposable environment เท่านั้น.
- SQL package ยังไม่เป็น approved production deployment; ต้องทบทวน operational ownership, backup/recovery และ deployment path แยก.
- FK/CHECK ไม่พิสูจน์ academic truth, immutable origin against arbitrary privileged SQL, mandatory governance coverage หรือ cross-row readiness. Backend integration/guards เป็น B3.
- No UI/workflow/HTTP integration, full structure validator/publish, bootstrap/activation และ authorization revocation concurrency ยังไม่ทำตามขอบเขต.
- Private test owner/config files มี credentials; เก็บไว้เฉพาะ directory ส่วนตัว ไม่รวมใน repository/diff/report contents.

## 17. B2 dependency assessment

Foundation schema รองรับ private unlinked/pre-ratio drafts, stable IDs, base revisions และ receipt identity แล้ว. B2 เริ่มได้เมื่อ PO review/รับรอง B1 และสั่ง B2 แยกเท่านั้น; ต้องเพิ่ม owner/privacy/envelope/OCC/resume/validation workflow ตาม D7 โดยไม่เปิด active publishing หรือ score guards. Ratio setup/later links/freeze/adoption ต้องถูกวางใน authorized follow-on service workflow พร้อม evidence ที่เกี่ยวข้อง ไม่ใช้ raw repository writes เป็น bypass.

## 18. Historical outstanding approvals / STOP (13 October)

รอ Product Owner ตรวจรับ B1 diff/schema/tests และยืนยันขั้นถัดไป. ยังไม่มี approval สำหรับ production migration, B2–B5 implementation, activation, commit/push/PR/merge หรือ M7. M6.6 visual acceptance ยังคง **PENDING**.

**STOP — WAIT FOR PRODUCT OWNER REVIEW.**

## 19. B1 Review Closure Addendum — 14 October 2026

Authority: Product Owner ส่งผล **B1 ACCEPT WITH CONDITIONS** และสั่งปิดเฉพาะ review conditions 1–8 ใน chat นี้. รับ eight-table architecture/S1–S10/foundation services/ผลเดิมใน disposable scope แล้ว; ไม่รื้อ architecture, ไม่ rerun all suites โดยไม่มีเหตุ. อนุญาตเฉพาะเสนอ Minimal Diff ของ Test Support ก่อนแก้หากจำเป็น. ผลตรวจพบว่า **ไม่จำเป็นต้องแก้ Test Support**: guard เดิมรองรับ fresh private datadir ตาม prefix ที่ล็อกไว้ได้. รอบนี้แก้เฉพาะรายงานนี้และเพิ่มเอกสาร reproduction หนึ่งไฟล์; production/foundation services, tests, guard, SQL, routes, migration design และ approved design คง byte เดิม.

**Final Review Status: B1 ACCEPT WITH CONDITIONS — closure checklist COMPLETE within B1/disposable scope; AWAIT PRODUCT OWNER COMMIT AUTHORIZATION.** การปิด checklist ไม่เปลี่ยน mandatory deferred gates ให้เป็น PASS และไม่ใช่การอนุมัติ B2–B5/deployment/activation.

### 19.1 Closure disposition

| PO item | ผลปิด / หลักฐานที่ตรวจจริง | ขอบเขตส่งต่อ |
| --- | --- | --- |
| 1. Safe repeatable disposable environment | CLOSED: อ่าน `GradebookFoundationDatabase.php`, schema/service tests และ recovery script; initialize fresh MySQL 8.0.40 datadir แล้ว provision/run ผ่าน guard เดิมจริง | Environment ต่าง port/path ต้อง STOP/จัด environment ให้ตรง guard; ไม่ลด isolation |
| 2. Reproduction / ownership / critical SKIP | CLOSED: [reproduction guide](../../testing/gradebook-foundation-b1-reproduction.md) มี server/schema creation, owner token/restricted grants, S1–S10, tests/JUnit inventory, recovery และ verified shutdown. รัน code blocks จริง: **92 tests / 418 assertions / 0 SKIP** | B5 ต้องรับรอง integration/release coverage แยก; full/B1 results เดิมไม่ถูกแทนด้วยผล browser |
| 3. Isolation Guard | CLOSED: SHA256 guard ตรง B1 เดิม; negative probes 8 กรณี fail closed ก่อนคืน connection, ไม่มี fixture writes. Host/port/schema/path ที่ unsafe ถูกปฏิเสธก่อนต่อ DB | Local developer trust boundary; ต้องรักษา owner/config/datadir เป็น private และไม่ใช้ shared DB |
| 4. Annual creation internal-only | CLOSED: repository มี scoped reads เท่านั้น; service ไม่มี Application/Controller/route/runtime callers จาก source search; tests เท่านั้นเป็น callers. Integration requirements ใน §19.3 | B3 backend/authorization integration และ B5 HTTP/retry/live-permission proof; ไม่สร้าง endpoint รอบนี้ |
| 5. Authorization Lock Inversion | CLOSED เฉพาะการยืนยัน disposition: **UNRESOLVED / MANDATORY B3 ACTIVATION BLOCKER**; Condition C คงเดิม | B3 actual production SQL/lock-order + two-connection revocation proof; B5 activation acceptance ห้ามข้าม |
| 6. Review SQL location | CLOSED: SQL ทั้ง **10 files** ยังใน `database/review/6v-b1/`; `tools/migrate.php` glob ยังเป็น `database/migrations/*.sql`; automatic directory **6 baseline files** เท่านั้นและ hash เดิม | Production deployment/ownership/recovery ต้องคำสั่ง/review แยก |
| 7. Preservation | CLOSED: tracked **344/344**, Task 6T **9/9**, Task 5R **1/1**, index และ approved design ตรง baseline hashes; registry validator **130/125/5**, original124 retained/mappings byte เดิม | Existing Gradebook acceptance/visual/release gates ไม่ถูกเปลี่ยน |
| 8. Addendum / evidence / deferred risks | CLOSED: Addendum นี้ระบุ actual closure evidence, changed/proposed files และ B2/B3/B5 handoff | ห้ามเริ่มงาน handoff จน PO สั่งแยก |

### 19.2 Actual verification and reproduction evidence

Reproduction: ใช้ Bash/PHP blocks จาก guide โดย extract ไป private temporary scripts แล้ว execute ตามลำดับ. Fresh environment แยกจาก B1 วันที่ 13 October และไม่ reuse datadir เดิม:

- Datadir: `/private/tmp/pp5-6v-b1-closure-JHnwXq/mysql-isolated/`; private socket/PID/logs; loopback **13396**, MySQL **8.0.40**, strict SQL mode. ตรวจ actual server identity ก่อน DDL แรก.
- สร้างเฉพาะ `pp5_b1_schema` และ `pp5_b1_recovery`; ไม่ใช้ `pp5_test`, MAMP config หรือข้อมูลจริง. Owner file mode **0600** ใน private directory; fresh random token/password. Actual grants: global **USAGE** เท่านั้น + ALL เฉพาะสอง disposable schemas; ไม่มี SUPER/global mutation/GRANT OPTION.
- `pp5_b1_schema`: six baseline SQL + review S1–S10 + four static reference seeds. `pp5_b1_recovery`: เริ่มเฉพาะ ownership marker ให้ existing recovery script สร้าง baseline และ synthetic fixtures เอง. ไม่มี SQL เข้า automatic migration directory.
- B1-focused PHPUnit exact class coverage: `GradebookAnnualFoundationTest` **32**, `GradebookFoundationSchemaTest` **35**, `GradebookAnnualCourseFoundationTest` **25** = **92 tests / 418 assertions**; JUnit **0 skipped / 0 errors / 0 failures**, CLI exit **0**. Flags `--fail-on-skipped`, `--fail-on-empty-test-suite`, `--fail-on-incomplete`, `--fail-on-warning`, `--fail-on-risky`; ตรวจ exact class counts เพิ่ม.
- Safety probe แยก: unset owner แล้วรัน **หนึ่ง** service case เพื่อพิสูจน์ `--fail-on-skipped`: **1 intentional SKIP / exit 1**, ไม่ถูกนับใน 92-case PASS. แสดงว่าการลืม owner ถูกตรวจเป็น failed reproduction gate.
- Guard negatives **8/8**: missing owner, wrong host, port, schema, unsafe path, actual datadir mismatch, token mismatch, actual principal mismatch. ไม่เปลี่ยน guard/marker จริง และไม่เขียน fixtures ใน probes. Port/schema/path probes หยุดก่อนต่อ DB; ownership probes ใช้ server ของรอบนี้เท่านั้น.
- Existing recovery rehearsal **46 checks PASS**; exact interrupted-step schema attestation, incomplete-ledger/index/S10 detection และ hashes ของ synthetic legacy data before/after ตรงกัน. ไม่ rerun production migration runner หรือเปลี่ยน recovery design.
- ก่อน shutdown ตรวจ owner/TCP + admin socket identity; ทั้งสอง schemas ledger **16 each**, ACTIVE cutovers **0 each**. สั่ง SHUTDOWN เฉพาะ private server และยืนยัน PID file ถูกลบ. Retain private evidence/data เพื่อ review.

| Evidence | Local artifact (temporary; ไม่รวม commit) |
| --- | --- |
| Actual server identity / strict mode | [server-identity.json](/private/tmp/pp5-6v-b1-closure-JHnwXq/server-identity.json) |
| Restricted principal | [principal-grants.json](/private/tmp/pp5-6v-b1-closure-JHnwXq/principal-grants.json) |
| B1 run / exact coverage | [b1-tests.log](/private/tmp/pp5-6v-b1-closure-JHnwXq/b1-tests.log), [b1-junit.xml](/private/tmp/pp5-6v-b1-closure-JHnwXq/b1-junit.xml) |
| Intentional missing-owner failure | [intentional-skip.log](/private/tmp/pp5-6v-b1-closure-JHnwXq/intentional-skip.log) |
| Unchanged guard rejection probes | [guard-negatives.json](/private/tmp/pp5-6v-b1-closure-JHnwXq/guard-negatives.json) |
| Recovery / equal legacy hashes | [recovery.json](/private/tmp/pp5-6v-b1-closure-JHnwXq/recovery.json) |
| Preservation hashes / SQL locations | [preservation.json](/private/tmp/pp5-6v-b1-closure-JHnwXq/preservation.json), [b1-package-preserved.json](/private/tmp/pp5-6v-b1-closure-JHnwXq/b1-package-preserved.json) |
| Final state / private shutdown | [shutdown-verified.json](/private/tmp/pp5-6v-b1-closure-JHnwXq/shutdown-verified.json) |
| Closure files / proposed 25-path scope / syntax / document checks | [closure-final-files.json](/private/tmp/pp5-6v-b1-closure-JHnwXq/closure-final-files.json), [syntax.json](/private/tmp/pp5-6v-b1-closure-JHnwXq/syntax.json), [document-diff-check.json](/private/tmp/pp5-6v-b1-closure-JHnwXq/document-diff-check.json) |

Initialize ภายใต้ default sandbox จบด้วย signal 11 (failed datadir ไม่มี data files); socket connection attempt ถูกปฏิเสธด้วย `Operation not permitted` ก่อน schema writes. หลังใช้ authorized isolated process/network execution บน fresh datadir ใหม่ ขั้นตอนทั้งหมดสำเร็จโดยไม่เปลี่ยน port/path/guard. Failed-initialize directory ไม่ถูก reuse และไม่มีการลด isolation เพื่อแก้ environment.

Registry validator รันซ้ำเป็น **traceability check** เท่านั้น: PASS **130 records / 125 accepted / 5 retired UI**, original124 retained. SHA256 ของ registry/contract/Thai lock/Golden mappings และ tracked source/assets ตรง B1 preservation เดิม; approved design SHA256 `e8de8f7c6da7dc9e47e9cc5df2e5fd00ce5b0d90d29e60acd27c429957648c98` คงเดิมรอบ closure. ตรวจ diff ของ design กับ pre-B1 backup แล้วมีเฉพาะ approved status/reference/history headings/matrix updates ที่บันทึกใน B1 เดิม ไม่มี architectural rewrite.

Test scope รอบ closure: **documentation-only**, ไม่มี plausible effect ต่อ Gradebook loaded assets/layout/focus/key/navigation/contracts หรือ relevant interaction tests. จึงไม่ rerun full PHPUnit/Golden/trusted browser/authenticated MAMP ตามคำสั่ง PO ที่รับหลักฐานเดิมแล้ว. ผล full/focused เดิมใน §12 เป็น historical accepted disposable evidence; **ไม่อ้างว่า rerun รอบนี้**. PHP syntax ของ unchanged B1 PHP files และ embedded provision/shutdown PHP ผ่าน; `git diff --check` และ untracked document diff checks ผ่าน. รอบนี้ไม่มี live MAMP connection/read/write และไม่มี browser/authenticated MAMP evidence ใหม่.

### 19.3 Binding requirements for future integration (deferred; not implemented)

1. **Server-issued UUID:** integration caller ต้อง issue canonical stable UUID บน server และ bind กับ authenticated tenant/actor/request intent. `GradebookFoundationIdentity::uuid()` ตรวจ format เท่านั้น; service รับ key จาก internal caller และ **ไม่ได้พิสูจน์ว่า caller issue key บน server**. ห้ามรับ arbitrary client-generated UUID เป็น creation authority หรือ mint key ใหม่ทุก network retry.
2. **Safe retry:** ต้อง recover/reuse operation key เดิมหลัง lost response/two tabs; identical intent คืน persisted annual ID/links/audit เดิม, changed actor/name/reason/offering set conflict. ปัจจุบัน retry semantics จำกัด unconfigured B1 creation revision/state เท่านั้น; ห้ามอ้างว่าครอบคลุม retry หลัง publish/ratio/state transition. Future integration ต้องกำหนดและทดสอบ intent binding, key lifetime, conflict/late retry โดยไม่สร้าง duplicate/orphan หรือ bypass lifecycle.
3. **Live authorization:** derive school/actor จาก authenticated session server context และตรวจ live membership/user/role/permission, resource scope/lifecycle ก่อน mutation **รวม retry**. Permission code/unscoped semantics คงเดิม; ห้ามเชื่อ client/session snapshot ที่ stale. HTTP integration ต้องมี existing CSRF/server validation/audit/atomicity และไม่มี unchecked repository/route/CLI/import bypass. B1 service ไม่ wire เข้าระบบ authenticated caller จึงไม่ใช่ end-to-end authorization/CSRF proof.
4. **Mandatory Condition C:** Annual-create concurrency PASS พิสูจน์ creation serialization เท่านั้น. ไม่พิสูจน์ school-first scoring/publishing versus membership/user-first revocation. ต้อง inspect actual production code/SQL/grant writers และ two-connection schedules ทั้งสองทิศทางก่อน B3 activation. หากต้องแก้ให้เสนอ minimal corrective change preserving authorization/tenant/lifecycle; ห้าม broad rewrite หรือรายงานว่า B1 แก้ lock inversion แล้ว.

### 19.4 Outstanding deferred risks / ownership

| Deferred work / risk | Future owner / mandatory gate |
| --- | --- |
| Private draft owner/privacy/envelope, OCC/resume/validation workflow | B2 ภายใต้ separate PO instruction |
| Annual integration: server UUID/intent binding, retry across future lifecycle, authenticated caller/live authorization/CSRF | B3 backend + B5 integration acceptance; B1 only internal foundation |
| Authorization Lock Inversion, revocation races/deadlock handling, actual SQL lock order | **B3 mandatory activation blocker** + B5 proof; unresolved |
| Full structure/readiness/publish/ratio freeze/late-term links; managed history rules; governance coverage/classification/bootstrap/import/worker guards | B3; Supported planner/workflow เป็น B4 dependency ตาม design; รอบนี้ไม่เริ่ม |
| Trusted Golden A–M, focused/full integration suites, authenticated MAMP safe fixtures, UI/visual acceptance, activation acceptance | B5 และ independent PO release approval; ไม่ใช้ disposable foundation PASS แทน |
| Production SQL deployment path/operational ownership/backup/recovery | Separate reviewed deployment instruction; SQL ยัง review-only |

Release Activation Approval **NOT GRANTED**; M6.6 Product Owner Visual Acceptance **PENDING**. ไม่รัน migration บนฐานข้อมูลจริง, ไม่เปลี่ยน saved scores/history/maxima/Legacy semantics, ไม่เริ่ม B2–B5/M7, ไม่ PR/merge/activate.

### 19.5 Changed files and proposed commit scope

Changed **ในคำสั่ง review closure นี้เพียง 2 files**:

- `docs/superpowers/notes/2026-10-13-m6-6-task-6v-b1-foundation.md` — current status + Addendum นี้; prior evidence retained.
- `docs/testing/gradebook-foundation-b1-reproduction.md` — new self-contained tested reproduction guide; provisioning scripts อยู่ใน fenced documentation/temporary execution เท่านั้น.

**Proposed single B1 commit scope หลัง PO อนุญาต: 25 paths** (original B1 review package 24 paths + reproduction guide; ไม่มีการ stage/commit ในคำสั่งนี้):

1. `database/review/6v-b1/20261013_001_offering_annual_candidate.sql`
2. `database/review/6v-b1/20261013_002_gradebook_school_cutovers.sql`
3. `database/review/6v-b1/20261013_003_gradebook_offering_governance.sql`
4. `database/review/6v-b1/20261013_004_gradebook_annual_courses.sql`
5. `database/review/6v-b1/20261013_005_gradebook_annual_course_offerings.sql`
6. `database/review/6v-b1/20261013_006_gradebook_score_sections.sql`
7. `database/review/6v-b1/20261013_007_gradebook_component_sections.sql`
8. `database/review/6v-b1/20261013_008_gradebook_structure_drafts.sql`
9. `database/review/6v-b1/20261013_009_gradebook_structure_publish_receipts.sql`
10. `database/review/6v-b1/20261013_010_governance_receipt_foreign_keys.sql`
11. `database/review/6v-b1/README.md`
12. `docs/superpowers/notes/2026-10-13-m6-6-task-6v-b1-foundation.md`
13. `docs/superpowers/notes/2026-10-13-m6-6-task-6v-product-owner-decision.md`
14. `docs/superpowers/specs/2026-10-12-pp5-m6-6-task-6v-score-structure-design.md`
15. `htdocs/app/Repositories/GradebookAnnualCourseRepository.php`
16. `htdocs/app/Repositories/GradebookGovernanceRepository.php`
17. `htdocs/app/Services/GradebookAnnualCourseFoundationService.php`
18. `htdocs/app/Services/GradebookAnnualRatio.php`
19. `htdocs/app/Services/GradebookFoundationIdentity.php`
20. `tests/Database/gradebook-foundation-recovery.php`
21. `tests/Feature/GradebookAnnualCourseFoundationTest.php`
22. `tests/Feature/GradebookFoundationSchemaTest.php`
23. `tests/Support/GradebookFoundationDatabase.php`
24. `tests/Unit/GradebookAnnualFoundationTest.php`
25. `docs/testing/gradebook-foundation-b1-reproduction.md`

Task 6T ทั้ง 9 files, Task 5R, temporary credentials/datadir/evidence และ unrelated worktree files **ไม่อยู่ใน proposed B1 commit scope**. HEAD ยัง `186dfb56a232149313ef4005c98209e6f7149424`, branch `milestone/6-6-soft-government-ui`; index คงเดิม/ไม่มี staged changes. No commit/push/PR/merge.

**STOP — AWAIT PRODUCT OWNER COMMIT AUTHORIZATION.**
