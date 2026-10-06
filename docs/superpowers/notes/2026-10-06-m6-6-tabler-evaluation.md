# M6.6 Task 1c — Tabler UI evaluation spike

วันที่ 2026-10-06 (Asia/Bangkok) · baseline `efbe9658164d8113de92b8a8555d8d3d738b76bf` · branch `spike/m6-6-tabler-ui-evaluation`

## คำตอบและขอบเขต

Prototype เฉพาะ Dashboard, Classroom Overview และ Students Workspace โดยยังใช้ข้อมูลจริงและสิทธิ์จาก read model เดิม ไม่มี endpoint ธุรกิจใหม่, migration, schema หรือการแก้ Gradebook. หน้าอื่นยังใช้ M6.6 เดิมโดยตรง. ภาพหน้าใหม่ต่างจาก Task 1b อย่างเห็นได้ชัด: shell, ลำดับหัวข้อ, การ์ดงาน และตารางใช้จังหวะ/รูปทรงร่วมกันจาก Tabler; สี pastel เป็น PP5-specific layer. การตัดสินใจรับ Tabler ทั่วแอปยังต้องให้ product owner เปรียบเทียบภาพก่อน.

## แหล่งที่มาและ dependency

- Stable release ที่ตรวจเมื่อ 2026-10-06: **`@tabler/core@1.6.1`**, [GitHub release ของโครงการ](https://github.com/tabler/tabler/releases/tag/%40tabler/core%401.6.1), เผยแพร่ 2026-09-28. ดึง compiled package จาก [official npm package tarball](https://registry.npmjs.org/@tabler/core/-/core-1.6.1.tgz); npm integrity `sha512-lgmR9gcxRp5JlLxcmRi9Rz85JrEvH6Bz71KnlcPVSa97wtwQNjgSs3g3fxwbAlulHyRMghhIV04QUuW3pqDsIg==`.
- License: [MIT ใน release tag เดียวกัน](https://raw.githubusercontent.com/tabler/tabler/%40tabler%2Fcore%401.6.1/LICENSE). Vendored `htdocs/assets/vendor/tabler/LICENSE` (SHA-256 `4f88a82d13be3c5c63a12c5631eae914aa4381b6dc17641bf1ab85f3f8f6c8a5`). Compiled CSS SHA-256 `370a2d4e608ae518351d10528fc7dbe19b354ae5062c51b2a9cb294850d65403`.
- [Official Tabler README](https://github.com/tabler/tabler) ระบุว่า compiled `tabler.min.css`/`tabler.min.js` รวม Bootstrap foundation แล้ว. Package metadata ระบุ `@popperjs/core ^2.11.8` สำหรับชุด JS; prototype ไม่โหลด Tabler JS/Popper เพราะใช้ native `<details>` สำหรับ student context และ JS navigation เดิมของ PP5. ไม่มี plugin ใน `dist/libs`, chart, Tabler Icons, webfont, CDN, external font หรือ remote icon ที่โหลดตอน runtime.
- Production direction ยังเป็น PHP 8.2+, server-rendered HTML, vanilla JS, local static CSS. npm ใช้เฉพาะ inspect/download ตอน spike; ไม่มี Node, npm หรือ bundler ใน server runtime. Tabler 1.6 ระบุ browser baseline Chrome 123, Firefox 128, Safari 17.5 ใน [release/upgrade notes](https://github.com/tabler/tabler/releases).

## Bootstrap compatibility และ integration

พิจารณา A: แทนที่ local Bootstrap CSS ด้วย `tabler.min.css` ในสามหน้า prototype เท่านั้น; และ B: selective Tabler integration. Official compiled `tabler.min.css` รวม Bootstrap 5 foundation; ไม่มี official compiled layer ของเฉพาะ components ที่นำมาใส่ทับ Bootstrap เดิมได้โดยไม่ต้อง build Sass. จึงเลือก **A** ด้วย `layouts/tabler-spike.php` และ `View::page` เลือก shell ห้องเรียนตาม layout. หน้า prototype โหลด Tabler CSS + `tabler-spike.css`; **ไม่โหลด** `bootstrap-5.3.8.min.css` หรือ `app.css`. หน้า Gradebook, Subjects และหน้าอื่นยังโหลด Bootstrap + `app.css` แบบเดิม. ไม่มี Bootstrap ซ้อนสองชุด, duplicate JS plugin หรือ global CSS hack ในหน้าเดียวกัน.

ความเสี่ยง rollout: การนำ shared Tabler shell ไปทุกหน้าจะต้องค่อย ๆ ย้าย template และ UI contracts ที่ยังพึ่ง `pp5-*`; ระหว่างทางหน้า prototype กับหน้าเดิมมีรอยต่อด้านภาพเมื่อคลิกลิงก์ข้ามหน้า. Prototype ไม่ใช่ข้อพิสูจน์ว่าทุกหน้าจะเข้ากันโดยอัตโนมัติ.

## Asset และปริมาณ CSS

| Asset | raw bytes | gzip โดยประมาณ | runtime ใน prototype |
| --- | ---: | ---: | --- |
| Bootstrap 5.3.8 CSS เดิม | 232,111 | 31,143 | ไม่โหลด |
| `app.css` เดิม | 29,234 | 6,192 | ไม่โหลด |
| `app.js` เดิม | 3,399 | — | โหลด ใช้ mobile nav/native disclosure |
| Tabler 1.6.1 CSS | 635,394 | 80,002 | โหลด |
| Tabler 1.6.1 JS ใน package | 135,181 | ไม่ได้วัด | **ไม่ vendor/ไม่โหลด** |
| `tabler-spike.css` | 6,558 | 1,953 | โหลด |
| Icons/font assets | 0 | 0 | ไม่มี |

Custom CSS เพิ่ม 6,558 bytes / 87 logical lines (ไฟล์ spike); ลบ CSS เดิมใน Git **0 bytes**. แต่สามหน้า prototype ไม่โหลด `app.css` 29,234 bytes. CSS ของ spike จำกัดไว้ที่ brand/context color, layout grid placement, Thai text, roster density, mobile action cell และ focus. Tabler แทนรูปแบบพื้นฐานของ navbar/nav, page header, cards, buttons, badges, alerts, table และ responsive utilities. อัตรา raw CSS รวมสูงขึ้นอย่างมีนัยสำคัญ (~262 KB → ~642 KB เฉพาะ stylesheet), gzip ~37 KB → ~82 KB. ต้องประเมิน cache/network cost ก่อน rollout.

## ผลหน้า prototype

- **Dashboard:** welcome hierarchy, classroom selection, gradebook จริง และ authorized management areas อยู่ใน Tabler cards; ไม่มี KPI/chart ที่สร้างขึ้นเอง.
- **Classroom Overview:** identity/context header, tab navigation และสาม work domains เป็นการ์ดพร้อม accent น้ำเงิน/sage/sand, counts/status จริงและ action เด่นหนึ่งรายการต่อโดเมน. Permission composition ยังควบคุม domain ที่แสดง.
- **Students:** ตารางยังแน่นและเป็น neutral, action column คงอยู่ใน mobile horizontal scroll; native `<details>` แสดงชื่อนักเรียน สถานะ และลิงก์ authoritative ที่สิทธิ์อนุญาตใน workspace เดิม. ไม่เพิ่ม mutation logic ใน browser.
- **Responsive/authenticated MAMP:** desktop Dashboard/Overview/Students, 768 Overview/Students, 390 Overview/Students ตรวจจริง. ไม่มี document horizontal overflow; ที่ 390 ตารางนักเรียนเลื่อนภายใน region (table 592px / region 364px), mobile menu เปิด/ปิดและคืน focus, student panel อยู่ใน viewport. Tabler accents เห็นชัดโดยไม่ย้อมตารางยาว. Focus outline ชัดเจน; Enter เปิด student context, Escape ปิดและคืน focus ที่ summary, Tab ไปยัง action link ได้. ไม่ใช้ offcanvas จึงไม่มี focus trap หรือ dependency JS เพิ่ม.

## Matrix (1–5; 5 = เหมาะ/ดีกว่า/ความเสี่ยงต่ำกว่า)

| เกณฑ์ | Task 1b hand-built | Tabler prototype | เหตุผลหลัก |
| --- | ---: | ---: | --- |
| Visual polish | 2 | 4 | shell/cards/type hierarchy ลงตัวกว่า |
| Visual difference | 2 | 4 | เห็นความต่างทันทีทั้งสามหน้า |
| Thai readability | 4 | 4 | system/Tahoma stack และข้อความเดิม |
| Government/school suitability | 4 | 4 | accent สุภาพ; ยังต้องให้ PO ดูภาพ |
| Information density | 4 | 4 | ตารางยังแน่น; cards ไม่ดันเนื้อหาหลักมาก |
| Dashboard quality | 2 | 4 | งานจริงจัดเป็นลำดับชัดขึ้น |
| Workspace clarity | 3 | 4 | context + 3 domains แยกชัด |
| Roster usability | 4 | 4 | native detail เดิมยังดี; mobile action ชัดขึ้น |
| Mobile usability | 4 | 4 | ไม่มี overflow, nav/roster ใช้ได้ทั้งสองแบบ |
| Accessibility | 4 | 4 | native details/focus/landmark คงอยู่ |
| Development effort | 2 | 4 | ใช้ primitives แทนสร้าง card/nav/button ใหม่ |
| Custom CSS burden | 2 | 4 | spike override 6.6 KB เทียบ shared `app.css` 29.2 KB ที่ไม่โหลด |
| Future consistency | 3 | 4 | class conventions เดียวกันบนหน้าที่ migrate |
| Maintenance risk | 4 | 3 | เพิ่ม vendor/version และช่วง coexistence ของสอง layout |
| Dependency risk | 5 | 3 | CSS vendor ขนาดใหญ่และ browser baseline สูงขึ้น |
| Architecture fit | 5 | 4 | static local CSS เหมาะ; ต้องคง isolated layout ระหว่าง rollout |

คะแนนเป็น judgment จาก prototype และ browser checks ไม่ใช่สถิติผู้ใช้. ไม่มีตัวเลข token saving เพราะยังไม่วัดจริง. ข้อบ่งชี้เชิงคุณภาพ: shell, card, badge, button, table, spacing/ responsive utilities ใช้ markup ของ Tabler ได้ทันที; PP5 ยังต้องดูแล permission composition, Thai copy, classroom semantics, roster selection, mobile density, accessibility checks และ Gradebook grid เฉพาะทาง. การ rollout สู่ Subjects/Score setup/หน้า admin ไม่ได้ทำใน spike และจะต้องตรวจรูปแบบตาราง/ฟอร์มทีละหน้า.

## Verification

- Behavior Registry validator: **PASS 124** accepted behaviors; Gradebook vendor hashes คงเดิม.
- Golden Journeys A–M: **PASS 140 real-browser checks** ด้วย pointer/keyboard/clipboard จริงบน isolated fixture. Journey I ต้องใช้คีย์ต่อเนื่องบน focused cell เพื่อหลีกเลี่ยง tool auto-scroll; E/J ที่พลาดตอนทดลองเกิดจากตำแหน่ง pointer ก่อนหน้าเลื่อนและผ่านหลังอ่านพิกัดใหม่. Final run ของแต่ละ journey ผ่าน.
- Browser UI: foundation **PASS 5 cases/66 checks**; entry **36/528**; administration **72/908**; student workflow **64/1,484**; cross-screen **172/7,600**, 0 failures.
- Focused Classroom/Student/UI PHPUnit: **PASS 85 tests / 1,617 assertions**. Full PHPUnit: **PASS 3,063 tests / 69,878 assertions**.
- PHP syntax ของไฟล์ที่แก้/เพิ่ม, JS syntax ของ `app.js`, `gradebook.js`, Golden Journeys harness และ `git diff --check`: **PASS**.
- Files outside presentation (`gradebook.js`, `gradebook-grid.css`, Tabulator vendor, score views/API, auth, permissions, tenants, CSRF, audit, services/repositories, schema/migrations) ไม่เปลี่ยน.

## ภาพสำหรับ PO

ไฟล์ภาพอยู่ภายนอก Git และไม่ได้ commit ใน `/Users/depa/.codex/visualizations/2026/10/05/01a10e4e-c02b-71b1-b02f-69b62d4eb38d/tabler-spike-screenshots/`:

- `dashboard-desktop.jpg`
- `classroom-overview-desktop.jpg`
- `students-desktop.jpg` (student context เปิด)
- `classroom-overview-768.jpg`
- `students-768.jpg`
- `classroom-overview-390.jpg`
- `students-mobile-390.jpg` (student context เปิด)

## Recommendation

**NEEDS PRODUCT-OWNER VISUAL REVIEW BEFORE DECISION**. Prototype แสดงผลทางภาพดีขึ้นอย่างชัดเจนและไม่กระทบ Gradebook หรือ runtime architecture แต่ CSS payload เพิ่ม, สอง layout จะอยู่ร่วมกันระหว่าง rollout และ suitability ของภาพสำหรับโรงเรียนรัฐยังเป็นการตัดสินใจของ product owner. เทียบ screenshot กับ Task 1b ที่ baseline SHA ก่อนเลือก adoption.

## Product-owner decision after evaluation — 2026-10-06

**ADOPT TABLER** as the production UI foundation. The original recommendation above records the evidence at the time of the spike and remains unchanged. The product owner rejected the pastel-first appearance, requested stronger attractive color, fuller use of Tabler primitives, and compact repeated row actions with icon help on both hover and keyboard focus. Task 1d ports the useful evaluated structure onto the exact M6.6 production baseline without merging or cherry-picking spike history. Final colorful visual acceptance remains pending product-owner review.
