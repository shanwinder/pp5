# Task 6V-B1 — Product Owner Decision Record

วันที่ 13 October 2026 · Asia/Bangkok

Authority: คำสั่ง Task 6V-B1 เดิมและ Product Owner Authority Supplement (A–D) ที่ Product Owner ส่งตรงใน chat นี้ ไม่ใช่ข้ออนุมานจาก implementation หรือผลทดสอบ

## Decision matrix

| Decision | Status | Approved recommendation v1.2 |
| --- | --- | --- |
| D1 | APPROVED | COURSEWORK:FINAL_EXAM; กลางภาคอยู่ COURSEWORK; 50.00 ต่อ term / 100.00 เมื่อสอง term structurally ready |
| D2 | APPROVED WITH CONDITIONS A–D | Annual identity แยก school/year/classroom ไม่ใช้ Subject ID เป็น annual identity |
| D3 | APPROVED WITH CONDITIONS A–D | Explicit actual offerings; ต่าง Subject ID ได้; existing unscoped GRADEBOOK_COMPONENT_MANAGE; no auto-pair |
| D4 | APPROVED WITH CONDITIONS A–D | Integer 1–99 รวม 100; exact decimal/integer-hundredths |
| D5 | APPROVED WITH CONDITIONS A–D | First publish freezes annual ratio; own-term readiness; late T2 explicit link |
| D6 | APPROVED WITH CONDITIONS A–D | Managed history restrictions ตาม Condition D; Legacy semantics คงเดิม |
| D7 | APPROVED WITH CONDITIONS A–D | Private server JSON draft, revision/OCC, 100 sections/200 items/1 MiB |
| D8 | APPROVED WITH TECHNICAL GATES | Normalized structural 6 + governance/cutover 2 = 8 tables; candidate index + S10 receipt FKs |
| D9 | APPROVED WITH CONDITIONS A–D | Native planner ใน B4; ไม่มี Gradebook interaction change ใน B1 |
| D10 | APPROVED WITH CONDITIONS A–D | Independent governance + durable school epoch; mixed A/conditional C; activation ต้อง B3+B4+B5+release approval |
| New-course mandatory policy | APPROVED | POST_ACTIVATION ต้อง ratio + own-term published structure ก่อนเขียนคะแนนเมื่อเปิดใช้ภายหลัง |

## Approval layers

- Product Decision Approval: APPROVED subject to binding conditions below.
- Architecture Approval: v1.2 recommended eight-table model APPROVED WITH TECHNICAL GATES.
- Exact DDL Verification: PASS on disposable MySQL 8.0.40; evidence in the B1 report, independently of product approval.
- B1 Implementation Verification: PASS on disposable DB; worktree still awaiting Product Owner review.
- Release Activation Approval: NOT GRANTED; B2–B5 need separate instructions.

ข้อความเงื่อนไข A–D ต่อไปนี้เก็บตาม Authority Supplement ครบถ้วน:

## 2. Binding Condition A — Annual Course Creation

Annual Course ที่สร้างเพื่อยืนยันใช้งานต้องผูกกับ Actual Offering อย่างน้อยหนึ่งรายการอย่างชัดเจนภายใน Transaction เดียวกัน

Draft ก่อนกำหนด Ratio หรือก่อนมี Annual Course Link ยังเก็บได้ผ่าน Offering Governance โดยไม่ต้องสร้าง Annual Course ที่ไม่มี Offering ผูกอยู่

การสร้างซ้ำจากสองแท็บ การส่งคำขอซ้ำ หรือ Retry หลัง Network Failure ต้องไม่ก่อให้เกิด Annual Course ซ้ำโดยไม่ตั้งใจ

ต้องมีการตรวจสอบ Unique Constraints, Authorization, Scope, Concurrent Creation และ Idempotency ตามความเหมาะสม

ห้าม Auto-pair Offering จากชื่อหรือรหัสรายวิชา

## 3. Binding Condition B — Cutover Integrity

Offering ใหม่หลัง School Cutover Activation ต้องได้รับสถานะ:

`POST_ACTIVATION + SETUP_REQUIRED`

โดย Classification, Offering Creation และ Audit ต้องอยู่ภายใน Transaction เดียวกัน

ก่อน Activation ต้องยืนยันว่าไม่มี Application Worker, HTTP/CLI Caller, Import Path หรือช่องทางเขียนข้อมูลรุ่นเก่าที่ยังสามารถข้าม Governance Guards ได้

ห้าม Activate จนกว่าจะผ่านครบ:

- B3 — Authoritative Backend Enforcement
- B4 — Supported Planner and Publish Workflow
- B5 — Verification / Acceptance Gates
- Independent Product Owner Release Approval

การทำ B1 ไม่ใช่การอนุมัติให้เปิด Enforcement

ต้องคง Legacy Workflow เดิมไว้จนกว่าจะมี Explicit Safe Adoption

ห้ามใช้ Feature Flag, Hidden UI หรือ Missing Annual Link เป็นตัวตัดสิน Governance State

## 4. Binding Condition C — Authorization Locking

ความเสี่ยง Lock Inversion ระหว่าง:

- School-first Scoring / Publishing
- Membership/User-first Permission Revocation

เป็น Mandatory Blocker ก่อน B3 Activation

ต้องพิสูจน์ด้วย Two-connection Concurrency Tests และตรวจ Lock Order จาก Actual Production Code/SQL

หากพบปัญหาต้องเสนอ Minimal Corrective Change ที่รักษา Authorization Semantics, Tenant Isolation และ Permission Lifecycle เดิม

ห้ามทำ Broad Authorization Rewrite โดยไม่มี Product Owner Approval

สำหรับ B1 ให้บันทึกความเสี่ยงและ Dependency นี้ไว้ ไม่ต้องขยายขอบเขตไปแก้ B3 ล่วงหน้า

## 5. Binding Condition D — Historical Status Semantics

การจำกัด Historical Deactivate/Reactivate ใน Managed Mode เป็น Accepted Product Decision ใหม่เฉพาะ Managed Scope

สำหรับ Managed รุ่นแรก:

- Historical component deactivation: DISABLED
- Historical component reactivation: DISABLED
- Historical reclassification/cross-group remapping: DISABLED
- Post-publish annual ratio amendment: DISABLED
- Safe metadata/reorder/no-history structural corrections: ALLOWED เมื่อผ่าน Atomic 50/Ratio Validation, Revision, Permission และ Audit
- Component max_score ที่มี Score History: IMMUTABLE แม้ score เป็น NULL

สำหรับ Legacy:

**Existing historical status behavior ต้องคงเดิม**

ก่อนเปลี่ยน Accepted Behavior ที่เกี่ยวข้อง ต้องจัดทำ Reviewed Change Evidence ใน Contract, Registry, Golden/Focused Tests และ Change History ตาม AGENTS.md

ห้ามลบหรือลดความเข้มงวดของ Existing Legacy Tests เพื่อทำให้ระบบใหม่ผ่าน

การอนุมัตินี้ไม่อนุญาตให้ Rewrite Student Scores, Delete History หรือ Auto-normalize Legacy Structures

## Operational boundary

B1 เท่านั้น; ห้าม migration/write บนฐานข้อมูลจริง, activation/enforcement, B2–B5, commit/push/PR/merge/M7. เก็บ Task 6T9/Task 5R/architectural analysis เดิม. Material schema/integrity conflict หรือ isolation พิสูจน์ไม่ได้ → STOP ขอ review. ก่อนเปลี่ยน accepted behavior ต้อง reviewed contract/registry/golden/focused/change-history evidence; B1 ไม่เปลี่ยน interaction เหล่านั้น.

M6.6 Product Owner Visual Acceptance remains **PENDING**.

## B1 technical verification follow-up (not a new product approval)

ผลตรวจ implementation ภายหลังบันทึก authority: Exact DDL Verification และ B1 Implementation Verification **PASS บน disposable MySQL 8.0.40**, ตาม [B1 report](2026-10-13-m6-6-task-6v-b1-foundation.md). Worktree ยังรอ PO review; Release Activation Approval ยังคง **NOT GRANTED** และ M6.6 visual acceptance **PENDING**.
