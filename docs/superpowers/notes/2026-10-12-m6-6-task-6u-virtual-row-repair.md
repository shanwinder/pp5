# M6.6 Task 6U — Virtual-row navigation และ Clipboard measurement repair

วันที่ 12 ตุลาคม 2569 (Asia/Bangkok)

**สถานะ: mandatory release gates 1–17 PASS. Product Owner อนุมัติ isolated authenticated MAMP เป็นหลักฐาน Gate 17 แล้ว; ส่งมอบด้วยหนึ่ง commit/push ตาม Task 6U หลังตรวจ staging เฉพาะหกไฟล์.**

Product Owner visual acceptance ของ M6.6 ยังคง **PENDING**. ไม่เริ่ม Task 6T ต่อ ไม่สร้าง PR/merge/Milestone 7.

## 1. Baseline Git status

- Repository `/Applications/MAMP/htdocs/pp5`; branch `milestone/6-6-soft-government-ui`.
- Local HEAD และ remote milestone SHA ก่อนเริ่มและตรวจซ้ำท้ายงาน: `e52d06b5059ef7fb2c777fd229281029e6ad4a7b` ตรง required baseline.
- ก่อนเริ่มไม่มี tracked/staged modifications. มี Task 6T untracked 9 ไฟล์ และรายงาน Task 5R อีกหนึ่งไฟล์.
- Sandbox DNS ครั้งแรกอ่าน remote ไม่ได้; approved `git ls-remote` ตรวจสำเร็จ. ไม่ fetch/reset/clean/bulk-stage.
- อ่าน AGENTS, Product Owner Task 6U/6S decisions, registry, contract, Thai lock, Golden A–M, รายงาน 6S/6T และ investigation `/private/tmp/pp5-nav-investigation-20261012/report-th.md` ก่อนแก้ production.

## 2. Confirmed root causes

Investigation ของ Task 6T ยืนยัน trusted authenticated navigation exception บน baseline bytes:

```text
TypeError: previous?.removeAttribute is not a function
  at activate (gradebook.js:130:41)
  at select (gradebook.js:175:5)
  at HTMLDivElement.<anonymous> (gradebook.js:696:70)
```

`state.active.getElement()` เป็น literal `false` หลัง Tabulator ลบ pre-generated CellComponent ระหว่าง `scrollRows → _addBottomRow → initialize → deleteCells → Cell.delete`. Logical active row 26, target row 27 แต่ DOM focus ค้าง row 20; score POST 0. Incremental scrolling ไม่รับประกัน `renderComplete`.

Task 6U regression ใหม่ทำซ้ำอีกด้านบน baseline: rapid Up มี logical active row 1 แต่ DOM focus row 27, active highlight 0 และ rendered score Tab stop 0 (`baseline-navigation.json`, FAIL หลัง 18 checks). จึงไม่ได้ใช้แค่ fixture ที่ผ่านอยู่แล้วอ้างการซ่อม.

ระหว่าง repair พบ full virtual fill สามารถ remove แล้ว reattach **DOM node เดิม** ภายใน turn เดียว: observer เห็น `isConnected=true` แต่ browser focus เป็น BODY และ Shift+Down บางคำสั่งสูญหาย. Authenticated expanded-limit attempt ได้เพียง 48×40 แทน 51×40 และส่ง batch ที่เล็กกว่าขีดจำกัด. หลังแก้การตรวจ removal records ได้ 51×40, focus row 51/last score และ zero POST เมื่อปฏิเสธ 2040 cells.

พบด้วยว่า response ที่อ้าง CellComponent ก่อน source row recreation ทำ authoritative normalization ผ่าน cell ที่ถูกลบ ทำให้เกิด false uncertain-write lock. Controlled temporary asset ที่ถอดเฉพาะ live response reconciliation ทำให้คะแนน `8` ไม่กลายเป็น `8.00` และ uncertain=true; current patch ได้ `8.00`, uncertain=false และ focus ยังอยู่ row 21.

Clipboard production ทำงานถูกต้อง: baseline Golden F/G และ tiling อ่าน native clipboard ได้ exact TSV แต่ original tests อ่าน retained ClipboardEvent หลัง dispatch จึงได้ empty string. ไม่แก้ production clipboard.

## 3. Production patch

จำกัด production ไว้ที่ `htdocs/assets/gradebook.js`:

- ตรวจ `Element` ก่อนใช้ DOM APIs; false/null/detached ไม่ถูกปฏิบัติเป็น DOM.
- Resolve cell ด้วย stable enrollment ID + score field; `liveCell`, keyboard commands, selection references และ authoritative response ใช้ current CellComponent.
- ถ้า navigation target ยังไม่ connected ให้ public RowComponent `scrollTo` render/reveal row แล้ว resolve cell **อีกครั้งก่อน**ตั้ง active/focus. ใช้ top/bottom ตามทิศและ existing minimum holder visibility logic.
- Pinned Tabulator 6.6.0 render ใน public scroll call แบบ synchronous; ไม่มี async navigation completion ที่มาดึง focus/viewport จากคำสั่งใหม่.
- ล้าง previous/live previous/rendered active markers และ Tab stops; repaint selection หลัง target rendering.
- MutationObserver ของ holder ดู child-list changes เพื่อรองรับ incremental/full virtual replacement นอก `renderComplete`. ตรวจ removed nodes รวมกรณี reattach node เดิม. คืน focus เฉพาะ score cell เมื่อ grid ยังเป็นเจ้าของ focusและ browser เสีย focusจาก rendering; ไม่คืนจาก input ที่ปิดระหว่าง editor commit และไม่แย่ง external control.
- Deferred pointer completions ใช้ selection revision เพื่อไม่ทับ keyboard command ใหม่. Click เลือก score แบบ synchronous โดยไม่เปิด editor; deferred callbackยังใช้ทำ focus หลัง pointer lifecycle.
- Response application และ attempted-value checks resolve live scoreก่อน reconcile; serialization/revision/server validation/409/uncertain protections เดิมยังอยู่.
- Editor focus ใช้ `preventScroll` เพื่อไม่เลื่อน document ระหว่าง direct entry ใน virtual rows. Native explicit caret/input identity คงเดิม.

ไม่มี CSS/layout/view/vendor/version/schema/endpoint/permission/score-service/business-arithmetic change. ไม่มี catch เพื่อกลบ navigation exceptionแล้วปล่อย focus ผิด.

## 4. Clipboard measurement correction

แก้ทั้งสาม Browser files ที่ retain copy eventแล้วอ่านภายหลัง: Golden journeys, trusted tiling และ trusted read-only workspace.

Capture listenerเก็บ primitive `text`, `type`, `trusted` และ copied type names. เนื่องจาก production host capture handlerเรียก `stopImmediatePropagation`, listenerหลัง gridจะไม่ทำงาน: test capture listenerจึงติด **transparent observation** เฉพาะ `clipboardData.setData` ของ native eventนั้น, delegate native methodด้วย argumentsเดิม, อ่าน `getData('text/plain')` ทันทีขณะ production copy listenerกำลัง dispatch แล้วคืน methodเดิม. ไม่มี substitute clipboard value, mock copy, production clipboard patch หรือเก็บ ClipboardEventไว้ใน assertion log. Assertionsอ่าน string snapshotเท่านั้น.

คง expected TSV, native Meta+C/Meta+V และ trusted pointer/key checks ทุกข้อ. Independent `navigator.clipboard.readText()` หลัง native shortcutยืนยันค่าเดียวกันใน driver.

## 5. Files changed

1. `htdocs/assets/gradebook.js`
2. `tests/Browser/gradebook-golden-journeys.js`
3. `tests/Browser/gradebook-paste-tiling-real.js`
4. `tests/Browser/gradebook-workspace-real.js`
5. `tests/Browser/gradebook-virtual-navigation.cjs` — regression ใหม่ ใช้ fixture/runtimeเดิม
6. รายงานฉบับนี้

Registry, contract, Thai lock และ mappings byte-identicalกับ baseline. Task 6T files ไม่ถูกแก้หรือรวม delivery.

## 6. Before/after runtime evidence

| Metric | Baseline / failing predecessor | Current patch |
| --- | --- | --- |
| Virtual-row exception | Task 6T authenticated reproduction, previous=false | 0 ใน final trusted virtual casesและ authenticated run |
| Active vs DOM focus mismatch | Baseline regression row 1 vs row 27 | 0 ใน assertions ที่วัด; burst endpoints + continuous per-key samples |
| Stale CellComponent | Deleted component returns false; unsafe cleanup/reconcile | Actual deleted-component regression ผ่าน; current live identity/focus/marker/Tab stop ตรงกัน |
| Unexpected score POST | 0 ใน original navigation reproduction | 0 ใน navigation/copy/cancel/nonmutation windows |
| Golden F/G assertions | FAIL จาก late read | PASS exact original TSV |
| TSV expected values | Original literals | คงเดิมทั้งหมด |
| Registry IDs | 130 / 125 accepted / 5 retired | 130 / 125 accepted / 5 retired; unchanged bytes |
| Recreated saved source | `8`, false uncertain lock | `8.00`, uncertain=false, focus row 21 |

Final production JS SHA256: `2dba97713e6edfb0d8de5eee5d3090fd03dc63bf62a69198411ef57f09114054`. Authenticated browser-delivered asset hashตรงกับ working tree; versioned GET 200.

## 7. Virtual-row tests

`gradebook-virtual-navigation.cjs`: **PASS 4,460 checks**, Playwright 1.62.1 / fresh headless System Chrome 154.0.8037.98.

| Synthetic roster | CSS viewport | Mode | Checks | Exceptions | Unexpected POST |
| --- | --- | --- | --- | --- | --- |
| 52×5 | 1440 | writer | 601 | 0 | 0 |
| 52×5 | 390 | writer | 577 | 0 | 0 |
| 52×40 | 1440 | writer | 584 | 0 | 0 |
| 100×40 | 1440 | writer | 1064 | 0 | 0 |
| 100×40 | 390 | writer | 1064 | 0 | 0 |
| 52×5 | 1440 | reader | 570 | 0 | 0 |

ครอบคลุม uninterrupted burst Down/Up, reverse Shift+Up ข้าม virtual boundaries, continuous Down/Up พร้อม per-key identity/focus/highlight/tabindex/editor checks, reformatที่ยืนยัน old getElement=false, forward/reverse range, native scalar/rectangular/historical Copy, 2040-cell expanded-limit rejection, same explicit input/caretเมื่อ resize, Escape, direct vertical entry, delayed serialized singles, recreated saved source, native repeating Paste, immediate replacement typing และ trusted external-control focus ownership.

Writer 52×5/1440มี **6 intentional no-DB response-fixture POSTs** หลังจบ nonmutation window: 5 singlesและ1 batch. ไม่ใช่ database writes; ไม่ปะปนกับ unexpected=0. Single fixtureเดิมรองรับเฉพาะ component 10–13/row 1–4 จึง reuse response protocolเดิมผ่าน isolated route ID translationสำหรับ synthetic roster; ไม่แก้ fixtureหรือ production response.

Passive private-state getterใน no-DB assetเป็น test diagnostic; นี่คือ synthetic rosterที่รับ trusted browser input ไม่ใช่ authenticated proof. Authenticated runแยกใช้ production bytesจริง.

## 8. Clipboard exact values

| Trusted scenario | Expected / actual native clipboard | Result |
| --- | --- | --- |
| Golden F | `\t0.00\n12.50\t6.00` | PASS |
| Golden G | `0.00` | PASS |
| Tiling scalar | `8.00` | PASS |
| Tiling rectangle | `8.00\t9.00\n7.00\t10.00` | PASS |
| Read-only workspace | `0.00` | PASS |
| Reverse virtual range | 52/100 rows of `1.50`, including historical row | PASS exact native TSV |

Additional trusted workspace/feedback: writable workspace 10, reader workspace 11, writable/reader feedback 8+8 = **37 checks PASS**.

## 9. Golden A–M และ browser release checks

Final isolated diagnostic run: **118 original cases PASS**, **3,210 original fixture checks**, **289 runner checks**. Counter classesไม่บวกกันเป็น authenticated assertions.

| Journey | Checks | Result |
| --- | --- | --- |
| A | 10 | PASS |
| B | 10 | PASS |
| C | 7 | PASS |
| D | 13 | PASS |
| E | 11 | PASS |
| F | 7 | PASS |
| G | 21 | PASS |
| H | 11 | PASS |
| I | 11 | PASS |
| J | 14 | PASS |
| K | 14 | PASS |
| L | 14 | PASS |
| M | 14 | PASS |

รวม Golden **157 trusted checks**. Trusted tiling **43 checks PASS**. Focused synthetic suites direct-entry/editor/parity/read-only/races/tiling/workload/stress/visual/compact/workspaceและ27 error/409/uncertain scenariosผ่าน; layout60 mode-width casesที่390/768/1024/1280/1440และsequential resize0/1/2/4/20 componentsผ่าน.

Task 6T runnerทั้ง9ไฟล์ถูกใช้ read-only. Original driverหยุดที่ literal multiline tiling instructionหลัง clipboard repairทำให้เดินถึงขั้นใหม่ได้. Temporary diagnostic copyนอก repositoryแก้เฉพาะการอ่านข้อความ/clipboard sourceที่มี newline; native driver actions, assertions, expected valuesและisolation guardsคงเดิม. Original driver failureยังเก็บไว้ ไม่อ้างว่า Task 6T harnessได้รับการซ่อมหรือผ่านตาม original invocation.

Intermediate production failures (duplicate editor focus, full-fill focus loss, old rangeหลังclick) ถูกเก็บและแก้ก่อน final PASS. ไม่ลบ/ลด assertions. Early source-response attemptsใช้ unsupported fixture IDsแล้วได้404; **ไม่นับเป็น valid save evidence**. Valid translated-response controlled reproductionและfinal assertionsแยกเก็บ.

## 10. Registry / PHPUnit / syntax

- Registry PASS: 130 records, 125 accepted, 5 retired; all124original IDs retained. Registry/contract/lockไม่เปลี่ยนแม้แต่ byteเดียว.
- Final focused PHPUnit: **752 tests / 16,632 assertions PASS**, 17.482s.
- Final full PHPUnit: **3,106 tests / 70,732 assertions PASS**, 1:28.375.
- Final JS syntax: production asset, three repaired suitesและnew CJS regression PASS.
- PHP syntax: existing Browser fixtureและGradebook view PASS; ไม่มี PHP source changes.
- `git diff --check`: PASS.

Sandbox PHPUnitครั้งแรกเข้า MySQL socketไม่ได้; approved rerunผ่าน. Initial overlapping PHPUnit processesถูกหยุดแล้วรัน final focused/fullแบบ sequentialเพื่อไม่ใช้ test databaseพร้อมกัน; interrupted resultไม่ถูกนับ PASS.

## 11. Authenticated evidence และ gate 17

Actual real-login guarded **isolated MAMP PHP cli-server/MySQL** run PASS. Runtime/application/vendor/assetsเป็น copiesใน owned temporary root, restricted synthetic database/principal, independent session path/name, HTTP identity guardและnegative guardก่อนwrites. ไม่ใช่ no-DB fixtureและไม่ใช้ live students/schoolsเป็นtest data.

- Reader reload positive document GET **200**; score POST **0**ตลอด native selection/arrows/Shift range/Copy/Escape/editor-denial window.
- Writer/versioned JS/CSS fresh GET200และcontent hashesตรงproduction. Explicit input `1.50`, same input, caret4→3→4, Escapeกลับcell, score POSTdelta0.
- Native authenticated writes/validation/history/mixed protection/2040 rejection/390-768-1440 responsive/frozen ordinal/internal horizontal scroll/open-editor resizeผ่าน.
- HTTP totals: GET200=70, login POST302=2, authorized synthetic score POST200=9, intended validation/permission POST422=2, protected setup GET403=1. 11 scorePOSTอยู่ในdedicatedsynthetic DBและแยกจากnonmutationwindows.
- Cleanup PASS: exact ownership-guarded DB/user/runtime/session/credentials/profile/child-process cleanup. ไม่มี page/network errorsในfinal run.

**Gate 17: PASS ตามคำวินิจฉัย Product Owner ใน CONTINUE Task 6U — Gate 17 Final Adjudication วันที่ 12 ตุลาคม 2569.** การหยุดส่งมอบก่อนหน้านี้เพราะรอคำวินิจฉัยปิดแล้ว. ตรวจรายงาน, runtime identity, assertion implementation และ final Network/DOM evidence เดิมครบ จึงไม่รันทดสอบซ้ำโดยไม่มีเหตุจำเป็น และไม่แก้ production เพิ่ม.

Production entry point จริงคือ isolated copy ของ `htdocs/index.php` และ `App\Application`; router เพิ่ม ownership/identity guard แล้วส่งต่อ entry point เดิม ไม่ mock application/authentication/score endpoints. ใช้ MAMP PHP 8.3.14 และ MySQL, real login POST 302 สอง role, restricted database/principal และ session แยก. Browser เป็น process/profile ชั่วคราวของ Playwright; cleanup ทุกส่วน PASS.

Network recording มี actual `/gradebook/1` document GET 200 ใน `readonly-nonmutation` และ asset GET 200; ไม่อนุมานว่า recording ทำงานจาก score POST = 0 เพียงอย่างเดียว. Versioned JS/grid CSS/workspace CSS GET 200 และ SHA256 ตรง working tree ที่ตรวจซ้ำระหว่าง adjudication. Read-only navigation/Shift range/native Copy/Escape/editor denial ส่ง score POST = 0; writer explicit caret/Escape POST delta = 0. Historical navigation/protection และ expanded 2040-cell rejection ผ่าน zero-write assertions. Authenticated expanded virtual range ได้ 51×40, DOM focus ordinal 51/last score; ไม่มี page/network exception. Private logical-state identity/focus/highlight/tab-stop correspondence ตรวจโดย regression 4,460 checks แยกใน trusted no-DB fixture ไม่อ้างว่า private getter ถูกเพิ่มใน production/authenticated asset.

Existing Apache session: NOT TESTED — not required for Task 6U Gate 17 under approved isolated-authenticated-MAMP interpretation

ไม่มีการเปิด Chrome Profile หลักหรืออ้าง Apache/CGI parity proof.

## 12. Known limitations

- User-facing Product Owner visual/spreadsheet-feel acceptance PENDING.
- Existing Apache session และ Apache/CGI parity ไม่ได้ตรวจ; ไม่เป็น mandatory Gate 17 ตามคำวินิจฉัยที่อนุมัติ. หลักฐานที่ยอมรับคือ real authenticated PP5 บน isolated MAMP PHP/MySQL.
- Virtual revealอาศัยaudited pinned Tabulator6.6.0 synchronous row rendering; ไม่มีrenderer upgradeและไม่มีnew E2E infrastructure.
- Burst keysวัดendpointและpost-scroll state; continuous keysมีper-key samples. ไม่อ้างว่าสังเกตทุกmicrosecondของburst.
- 409/uncertain racesเป็นexisting synthetic response suites; ไม่อ้างว่าgenerated409จากauthenticated DB.

## 13. Task 6T preservation

ทั้ง9ไฟล์ยังuntrackedและSHA256ตรงpre-taskaudit `/private/tmp/pp5-6u-preservation.json`: Task6Tnote, `docs/testing/gradebook-browser-e2e.md`, `tests/E2E/{authenticated.cjs,database.php,fixtures.cjs,provision.php,run.cjs,web-router.php}`, `tools/e2e/run-gradebook.sh`. changedFiles=[]; ไม่stage/แก้/ลบ.

## 14. Task 5R checksum

`docs/superpowers/notes/2026-10-09-m6-6-gradebook-explicit-editor-regression.md` ยังคงuntrackedและSHA256:

`e92361caa761f2da5a84ab9d6e79d933a50130a1f759abf52249b0757e49ad58`

## 15. Final recommendation / evidence index

**Final adjudication: mandatory gates 1–17 PASS.** หลักฐาน final run ตรง patch ปัจจุบัน, ไม่มี required failing/blocked gate ค้าง. ใช้ผลทดสอบเดิมที่ยืนยันแล้ว; งาน adjudication เพิ่มเฉพาะรายงานและตรวจความครบถ้วนของหลักฐาน.

| Gate | หลักฐาน final | ผล |
| --- | --- | --- |
| 1 Registry | 130/125/5; original 124 IDs และ approved mapping/contract bytes คงเดิม | PASS |
| 2–4 Golden A–M / F–G exact clipboard / trusted tiling | 157 Golden checks; exact TSV; 43 tiling checks | PASS |
| 5 Virtual navigation | 4,460 checks / 6 cases; exceptions และ unexpected POST = 0 | PASS |
| 6–9 Editor / range / read-only / historical / races / errors | 118 browser cases; 3,210 fixture checks; authenticated protection; 27 error scenarios | PASS |
| 10–12 Visual stability / responsive / workload | Focused suites; 60 mode-width layout cases; sequential resize 0/1/2/4/20 | PASS |
| 13 Focused PHPUnit | 752 tests / 16,632 assertions | PASS |
| 14 Full PHPUnit | 3,106 tests / 70,732 assertions | PASS |
| 15 Syntax | Production JS, three suites, new CJS และ relevant PHP | PASS |
| 16 Diff whitespace | `git diff --check` และ staged diff check ก่อน commit | PASS |
| 17 Authenticated MAMP | Real PP5/login/MAMP PHP/MySQL; production asset hashes; positive GET 200; nonmutation POST = 0; isolation/cleanup | PASS |

Staging allowlist คือหกไฟล์ใน section 5 เท่านั้น; ตรวจ staged path list, diff และ preservation hashes ก่อน commit. Task 6T ทั้งเก้าไฟล์และ Task 5R ต้องยัง untracked/ไม่ stage. Commit เดียวใช้ข้อความ `fix: stabilize gradebook virtual-row navigation and clipboard tests`; push เฉพาะ `origin/milestone/6-6-soft-government-ui`. Final SHA, committed file count และผลเทียบ remote SHA รายงานใน final delivery response หลังดำเนินการสำเร็จ. ไม่สร้าง PR/merge/Task ต่อ/Milestone 7; visual acceptance ยังคง PENDING.

หลักฐานsanitizedภายนอกrepository:

- `/private/tmp/pp5-6u/baseline-navigation.json` — failing baseline focus mismatch.
- `/private/tmp/pp5-nav-investigation-20261012/{report-th.md,rapid-diagnostic.json,pause-diagnostic.json,lifecycle-diagnostic.json,copy.json}` — unchanged-production exception/lifecycle/clipboard baseline.
- `/private/tmp/pp5-6u/navigation.json` — final4,460checks/sixcases/focus/scroll/network/recreated-response.
- `/private/tmp/pp5-6u/response-recreation-before-final.json` — controlledlive-reconciliationpredecessor.
- `/private/var/folders/y4/2lffpqds6571pyrszqv9jht00000gn/T/pp5-gradebook-evidence-3b422d30f9bd3666/result.json` — final118cases/289checks/3,210fixturechecks; authenticatedproductionhash/GETcontrol/nonmutation/isolation/cleanup.
- `/private/var/folders/y4/2lffpqds6571pyrszqv9jht00000gn/T/pp5-gradebook-evidence-9bb2885510de27b2/result.json` — earlierpassingoriginalassertionsrunก่อนeditorpreventScrolladdition; ไม่แทนfinalassetproof.
- `/private/tmp/pp5-6u/supplement.json` — trustedworkspace/feedback37checks.
- `/private/tmp/pp5-6u/{focused-phpunit-final.log,full-phpunit-final.log,release-final.log}`.
- `/private/tmp/pp5-6u-preservation.json` — untouchedTask6T/5Rchecksums.

เก็บเฉพาะsyntheticidentities/field/focus/caret/scroll/method/path/status/hash; ไม่มีlivePII/credentials/cookies/CSRF/Authorization/rawHARในevidence.
