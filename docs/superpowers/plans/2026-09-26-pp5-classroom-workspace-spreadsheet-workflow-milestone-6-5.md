# PP5 Classroom Workspace + Spreadsheet Workflow Milestone 6.5 Implementation Plan

> **For agentic workers:** Implement this plan one task at a time. Every task must end with focused automated tests, relevant regression tests, syntax checks, `git diff --check`, a clean working tree, and exactly one reviewable commit unless the task explicitly requires a follow-up fix. Do not begin the next task until the current task has been reviewed and accepted. Do not start Milestone 7 while Milestone 6.5 is open.

## Goal

Transform the verified Milestones 1–6 application from an entity-oriented web administration experience into a classroom-oriented PP5 work surface that feels familiar to teachers who previously worked in Excel, while preserving the security, auditability, correctness, lifecycle, and multi-user advantages of the web system.

The product direction is:

> **Online first, Excel familiar.**

The system must not imitate Excel's implementation details, workbook geometry, formulas, macros, hidden sheets, or unrestricted cell editing. It must preserve the *workflow advantages* teachers rely on:

- choose the classroom/year context once;
- work continuously in that context;
- see related information together;
- edit data where it is displayed when safe;
- use dense tables for dense school work;
- use keyboard-first data entry;
- copy and paste tabular values from Excel/Google Sheets;
- avoid unnecessary create/edit/back navigation loops;
- let calculations and downstream summaries update from authoritative server data.

Milestone 6.5 is a workflow-refactor milestone. It does **not** add Attendance, Evaluation, Competencies, Activities, grade calculation, annual results, finalization, promotion, or official PP5/PP6/PDF reporting. It reshapes the existing Milestones 1–6 capabilities so those future domains can be added using the same classroom-workspace and matrix-entry interaction model.

## Architecture

Keep the existing PHP MVC-lite, server-rendered-first architecture, FastRoute routes, PDO repositories/services, SchoolContext, permission middleware, resource authorization, CSRF, transactions, audit logging, local assets, Bootstrap-compatible CSS, HTMX where appropriate, and Vanilla JavaScript.

Do not replace the existing domain model. `academic_years`, `classrooms`, `subjects`, `subject_offerings`, `teaching_assignments`, `students`, `student_enrollments`, `student_classroom_placements`, gradebook components, and scores remain separate domain concepts in the backend. Milestone 6.5 adds a task-oriented application layer that composes those existing concepts for teachers.

The guiding architecture is:

~~~text
Classroom / Teaching Workspace UI
            |
            v
workspace read models / application orchestration
            |
      existing domain services
            |
      existing repositories
            |
            v
         database
~~~

No UI convenience may bypass the existing service-layer rules.

## Tech Stack

- PHP 8.2-compatible
- FastRoute 1.3.x
- PDO
- MySQL 8 / MariaDB-compatible SQL
- PHP sessions
- PHPUnit 11
- HTML5
- Bootstrap 5.3-compatible local stylesheet
- HTMX 2.x local asset where it improves fragment interaction
- Vanilla JavaScript
- MAMP local verification
- InfinityFree-compatible request-driven deployment
- no Node/npm/frontend build pipeline

## Source baseline

Planning baseline: `main` at `a1c5f8a53ed4f06d57e30c25bbb5831b2a44fc79` after Milestone 6 merge and README post-merge cleanup.

Milestone 6 final verification documented:

- PHPUnit: 2,838 tests / 65,687 assertions
- PHP syntax: 196 first-party files
- JavaScript syntax: 11 files
- synthetic browser matrix: 264 cases / 4,983 checks
- real MAMP smoke across six personas
- Gradebook autosave and live offering-scope revocation verification
- responsive verification across representative screens/viewports

Milestone 6.5 must treat those contracts as regression baseline rather than replace them.

## Behavioral references

The following user-supplied workbooks are behavioral references for teacher workflow and document expectations:

- `ปพ5-ป4-ปีกศ 68.xlsb`
- `2568 แบบประเมินสมรรถนะสำคัญของผู้เรียน ป4(1).xlsx`

Use them to understand real school work, data-entry density, reference content, and expected printed-book content. Do not reproduce proprietary workbook geometry, macros, formulas, hidden implementation cells, or internal spreadsheet coupling.

---

# Product problem being solved

Milestones 1–6 successfully established correctness and a coherent web application shell, but the visible workflow still exposes too much of the backend entity model.

A teacher currently encounters concepts such as:

~~~text
academic year
classroom
subject
subject offering
teaching assignment
score component
enrollment
placement
~~~

These concepts are valid and necessary in the backend, but they are not the primary mental model of a teacher completing PP5 work.

The teacher's mental model is closer to:

~~~text
ปีการศึกษา 2569
ป.4/1
  นักเรียน
  รายวิชาและครู
  คะแนน
  เวลาเรียน
  การประเมิน
  สรุปผล
~~~

The original Excel workflow is often easier because one workbook establishes a stable classroom context and then lets the user work directly in dense tables. The web system must retain its stronger architecture while recovering that simplicity.

---

# Locked product principles

## 1. One classroom, one workspace

For classroom-centric administrative work, the primary context is a concrete `classrooms.id`. A classroom already belongs to exactly one school, one academic year, and one grade level. The server must load the classroom tenant-safely and derive the year/grade from the authoritative database record.

Preferred canonical route shape:

~~~text
/workspaces/classrooms/{classroomId}
~~~

A browser-provided classroom ID is a locator only. It is never authority. Every request re-resolves the classroom under the authenticated school and verifies the permissions/resources required by the requested section.

## 2. Context is visible, not silently trusted

The workspace header should continuously show:

~~~text
ปีการศึกษา 2569 · ป.4/1 · โรงเรียน ...
~~~

Changing year/classroom is navigation to another server-validated resource. Do not store a browser-controlled `school_id`, role, scope, or trusted academic context.

A session may remember a last-visited convenience URL if later justified, but the canonical request remains resource-addressed and revalidated.

## 3. Task language replaces domain language in normal teacher UI

Preferred visible terms:

- นักเรียน
- ย้ายห้อง
- ย้ายออก
- รายวิชาและครู
- เพิ่มรายวิชาในห้อง
- เปลี่ยนครูผู้สอน
- การเก็บคะแนน
- เพิ่มช่องคะแนน / เพิ่มรายการคะแนน
- คะแนน

Avoid exposing ordinary teachers to terms such as:

- Subject Offering
- Enrollment ID
- Placement record
- component code
- sort order
- authorization scope

The backend names remain unchanged where they are technically useful.

## 4. Dense work stays dense

Do not replace useful tables with card grids merely for appearance. Student rosters, subject/teacher lists, score structures, Gradebook, future attendance, and future assessments are matrix/table work and should remain optimized for scanning and keyboard operation.

## 5. Inline where safe, full page where necessary

Use the lightest interaction compatible with correctness:

- direct cell editing for repeated scalar entry;
- inline/select controls for small changes;
- compact drawer/dialog/popover for 2–4 field contextual actions;
- full-page forms for complex or high-risk setup;
- explicit confirmation only for materially destructive/high-impact state changes.

Do not introduce modal-heavy UX.

## 6. Auto-save should feel quiet but trustworthy

Frequent score edits should not require explicit Save buttons. The user must be able to see saving/error state, but normal successful saves should not interrupt the workflow.

## 7. Spreadsheet familiarity is interaction, not security

Clipboard, multi-cell selection, keyboard navigation, and matrix entry are presentation conveniences. All values are revalidated by the server. JavaScript never becomes the source of score limits, tenancy, permissions, enrollment validity, lifecycle state, or audit truth.

## 8. Existing security boundaries remain intact

Preserve:

- Auth -> SchoolContext -> Permission -> handler ordering where applicable;
- offering-scoped Gradebook authorization in the service layer;
- tenant-scoped repository queries;
- CSRF on every state-changing request;
- transactions for writes;
- audit logging;
- safe errors;
- PII minimization;
- academic-year lifecycle rules;
- historical rows and immutable-history rules;
- `NULL != 0` score semantics.

## 9. Permission-driven composition, never role-hardcoded UI

Workspace sections are shown according to live permissions and accessible resources. Do not write view logic such as `if role == SUBJECT_TEACHER` to grant capability.

A user who can see only Gradebook resources must not gain classroom-wide student/admin information merely because a workspace exists.

## 10. No placeholder future-feature links

Milestone 6.5 may reserve internal extension points for Attendance, Evaluation, Competencies, Activities, Results, and Reports, but must not render clickable links to unimplemented screens.

---

# Future PP5 completeness requirements locked by this plan

These items are **not implemented in Milestone 6.5**, but future milestones must treat them as first-class requirements rather than optional extras.

## A. Assessment domains

The classroom workspace must eventually contain:

~~~text
การประเมิน
  อ่าน คิด วิเคราะห์ และเขียน
  คุณลักษณะอันพึงประสงค์
  สมรรถนะสำคัญของผู้เรียน
  กิจกรรมพัฒนาผู้เรียน
~~~

`สมรรถนะสำคัญของผู้เรียน` is explicitly part of the PP5 Online product even though the legacy PP5 workbook handled it in a separate workbook.

Milestone 8 should therefore be understood as **Assessment Framework + Evaluation + Competencies**, not merely a few result columns.

## B. Versioned reference content

Future assessment/reference architecture must support versioned, printable content such as:

- learning standards;
- subject indicators (`ตัวชี้วัด`);
- assessment criteria;
- rubrics;
- desired-characteristic definitions;
- observable behaviors / `พฤติกรรมบ่งชี้`;
- competency definitions and behaviors;
- reading/thinking/writing criteria;
- quality-level thresholds and descriptions;
- other reference pages required in the PP5 bound volume.

Historical academic years must continue to print the reference version actually used for that year. Updating a future framework must never silently rewrite the reference content of a finalized historical PP5 record.

## C. Printable PP5 composition

Milestone 14 must compose authoritative result data **and** versioned reference content into the PP5/PP6/document package. Reference material must not be hard-coded only inside a PDF template.

The future document composition flow is expected to be:

~~~text
versioned reference framework
        +
finalized student/class result data
        +
school/class metadata/signature context
        |
        v
Report Data Service
        |
        v
HTML print view
        |
        +--> browser print
        +--> mPDF where required
~~~

Milestone 6.5 must not implement those domains, but its workspace architecture must leave them a clear place to attach later.

---

# Milestone boundary

## Included

~~~text
classroom workspace read model and canonical context
workspace landing / overview using existing data only
workspace-local navigation for implemented domains only
student roster workspace using existing student/enrollment/placement services
subject + offering + teaching assignment combined work surface
in-context score-structure management using teacher language
Gradebook spreadsheet selection model
keyboard navigation improvements
copy from Gradebook to clipboard
paste tabular scores from Excel/Google Sheets
transactional server-authoritative batch score paste
multi-cell fill for selected editable score cells where safely defined
quiet save / error state
My Teaching / accessible Gradebooks task landing
permission/resource-driven workspace composition
responsive behavior for dense work surfaces
accessibility/focus/live-region hardening
legacy-route compatibility where required
M1–M6 regression protection
real MAMP workflow smoke
documentation
~~~

## Explicitly deferred

Do not implement in Milestone 6.5:

~~~text
attendance data model or attendance entry
evaluation data model
reading/thinking/writing results
characteristic results
competency result data
learner-activity result data
assessment framework database tables
indicator/rubric/behavior database tables
versioned printable reference framework
score-to-grade calculation
GPA
annual result records
finalization
unlock/approval
promotion/repeat/graduation
PP5/PP6 PDF generation
official report composition
DMC native integration
XLSX/XLSB migration engine
bulk student import beyond the existing CSV flow
spreadsheet formulas in browser
arbitrary user-defined formulas
offline editing
real-time websocket collaboration
Google Sheets synchronization
Node/npm/grid framework dependency
React/Vue/Angular/SPA rewrite
~~~

---

# Database and permission boundary

## Database

**No database migration is expected in Milestone 6.5.**

Everything in scope can be represented through existing M1–M5 domain data. If implementation discovers a genuinely new persisted business fact is required, stop that task and report the requirement before creating a migration.

Do not add a persistence table merely to remember UI selection or expanded/collapsed state.

## Permissions

**No new business permission codes are expected in Milestone 6.5.**

Reuse existing permissions and resource authorization. Workspace visibility must be a projection of existing authority, not a new source of authority.

Do not rename existing permission codes merely to match new UI wording.

---

# Target information architecture

## School-level shell

Normal school-context navigation should increasingly emphasize work entry points rather than expose every technical entity as a peer menu item.

Target direction:

~~~text
ภาพรวม / งานของฉัน

ปพ.5 / งานชั้นเรียน
  <accessible classroom workspaces>

การเรียนการสอน
  <accessible teaching/gradebook work>

การจัดการ
  ปีการศึกษา
  โครงสร้างชั้นเรียน
  หลักสูตร/รายวิชา master
  ผู้ใช้งาน
~~~

Existing entity pages may remain available for administrators and compatibility, but should not be the only route to ordinary PP5 work.

## Classroom workspace local navigation

For Milestone 6.5, render only implemented sections for which the current user has legitimate access:

~~~text
ภาพรวม
นักเรียน
รายวิชาและครู
คะแนน
~~~

Future non-rendered extension slots:

~~~text
เวลาเรียน
การประเมิน
สรุปผล / เอกสาร
~~~

Do not render disabled placeholder tabs for those future sections.

## Teaching-oriented landing

A user with offering-scoped Gradebook access should have a fast path such as:

~~~text
งานสอนของฉัน

คณิตศาสตร์ 4 · ป.4/1 · ภาคเรียน 1     [กรอกคะแนน]
คณิตศาสตร์ 4 · ป.4/1 · ภาคเรียน 2     [กรอกคะแนน]
วิทยาการคำนวณ 5 · ป.5/1 · ภาคเรียน 1 [กรอกคะแนน]
~~~

This list must be produced from the same live accessible-offering logic as Gradebook authorization/read access.

---

# Classroom workspace authorization model

The workspace is not one giant permission grant.

A user may legitimately see one section and not another.

The server must build a section capability map such as:

~~~text
workspace overview: only safe facts derived from sections/resources the user may see
student roster: STUDENT_VIEW (and mutation actions require existing manage permissions)
subject/class setup: existing academic setup / offering permissions
teacher assignment actions: TEACHING_ASSIGNMENT_MANAGE
score work: accessible offerings through GradebookReadService/resource authorization
score structure actions: GRADEBOOK_COMPONENT_MANAGE plus valid offering/lifecycle
score editing: offering-scoped score authorization
~~~

Exact mapping must follow the existing authorization services and tests rather than assumptions from role labels.

A count must not leak data the user could not otherwise access. For example, a subject teacher who only has two offering scopes must not receive a hidden "12 students / 12 subjects" classroom summary unless existing permissions authorize that information.

---

# Routing strategy

Preferred additions, subject to Task 1 inventory verification:

~~~text
GET /workspaces
    task-oriented school work landing

GET /workspaces/classrooms/{classroomId}
    classroom overview

GET /workspaces/classrooms/{classroomId}/students
    classroom student roster projection

GET /workspaces/classrooms/{classroomId}/subjects
    subject + offering + teacher work surface

GET /workspaces/classrooms/{classroomId}/scores
    accessible score/subject landing within classroom
~~~

Gradebook canonical resource URLs may remain:

~~~text
GET /gradebook/{offeringId}
GET /gradebook/{offeringId}/setup
~~~

Milestone 6.5 should improve the route *entry path* and in-context navigation without needlessly invalidating working deep links.

Existing entity routes remain valid unless a task explicitly proves a safe redirect/deprecation strategy.

Do not break bookmarks unnecessarily.

---

# Read-model strategy

Introduce presentation/application read services where a single screen needs multiple existing domains.

Examples:

~~~text
ClassroomWorkspaceReadService
TeachingWorkspaceReadService
ClassroomRosterReadService (name may differ if existing services already fit)
ClassroomSubjectReadService (name may differ)
~~~

These services may aggregate existing repositories/services but must not become alternate business-rule implementations.

Rules:

- tenant scope is always explicit/server-derived;
- only fetch fields needed by the UI;
- avoid loading national IDs into workspace list models;
- derived counts must be based on authorized data;
- no write-side business rules in view templates;
- no SQL in controllers/views.

---

# Write-orchestration strategy

A task-oriented UI may combine concepts visually, but backend writes remain explicit and safe.

Examples:

- `เปลี่ยนครูผู้สอน` may map to existing teaching-assignment mutation behavior;
- `ย้ายห้อง` maps to existing placement transition behavior;
- `เพิ่มช่องคะแนน` maps to existing Gradebook component behavior.

For a UI action that would need multiple domain writes (for example creating offerings for two terms and immediately assigning a teacher):

1. do not chain multiple browser requests and pretend the operation is atomic;
2. do not duplicate validation in the controller;
3. first determine whether existing services expose composable transaction-safe operations;
4. if not, refactor the application/service layer under tests before adding the combined action;
5. one user-visible success must correspond to a business-consistent committed state.

If atomic composition cannot be achieved cleanly in the task scope, provide a simpler inline workflow rather than accepting partial state.

---

# Spreadsheet interaction contract

Milestone 6.5 upgrades Gradebook from "inputs inside a table" toward a true spreadsheet-like score work surface.

## Editable grid boundary

Only cells that are already legally score-editable may participate in edit operations:

- offering is accessible to the actor;
- score-entry authorization passes;
- academic year/offering state allows write;
- component is active/eligible;
- row is current, not historical;
- enrollment/resource relationship is valid.

Read-only/historical cells may be selectable for copying if safe, but must never become writable through keyboard, paste, or script manipulation.

## Visual active cell

One grid cell may have a clear active-cell focus/selection state.

The active cell must be keyboard reachable and must retain a visible focus indicator independent of color alone.

## Range selection

Support contiguous rectangular range selection for score cells using at minimum:

- pointer drag; and/or
- Shift + navigation after the task contract is proven accessible.

The implementation must not disable normal text selection outside the grid.

## Keyboard navigation

Required end-state behavior:

- Enter: commit/leave current edit and move to next editable row in the same score column where possible;
- Shift+Enter: previous editable row where implemented;
- Tab / Shift+Tab: predictable editable-cell traversal without trapping the user inside the grid;
- Arrow keys: grid navigation when not actively editing text/caret content, with exact behavior locked by tests;
- Escape: leave/cancel transient selection/edit mode without causing a write where feasible;
- Delete/Backspace on a selected editable score cell/range may clear to NULL only when the interaction is explicit and tested;
- shortcuts must respect IME composition and must not break Thai text entry elsewhere.

Do not create an inaccessible keyboard trap.

## Clipboard copy

Copy a selected rectangular range as TSV/plain text so it can be pasted into Excel or Google Sheets.

Blank score -> empty TSV field.

Saved `0.00` -> a zero value, never blank.

Do not copy hidden national IDs or unrelated metadata.

## Clipboard paste

Accept plain-text tab/newline matrices from Excel/Google Sheets.

Required parsing behavior:

- `\t` separates columns;
- newline separates rows;
- normalize CRLF/LF safely;
- surrounding empty lines are handled deliberately;
- blank field means requested NULL/clear;
- `0`, `0.0`, `0.00` are real zero;
- locale/thousands formats are not silently guessed unless explicitly supported and tested;
- values outside score validation fail;
- matrix must fit within eligible target cells;
- paste must not spill into summary, identity, historical, or disabled cells.

## Transactional paste

**A multi-cell paste is all-or-nothing.**

The server validates the full requested matrix before commit. If any cell is invalid/unauthorized/stale, no score mutation from that paste is committed.

This avoids a dangerous state where a 40-cell paste silently saves 37 cells and rejects 3.

## Server authority

Client JavaScript may parse clipboard shape for immediate feedback, but the server must independently validate:

- offering access;
- CSRF;
- component ownership/status;
- enrollment ownership/current eligibility;
- numeric syntax;
- max score;
- lifecycle;
- write permission;
- NULL semantics;
- transaction behavior.

## Batch endpoint

A dedicated batch score command is expected, with exact route/wire shape decided during the relevant task after inspecting existing controller/service contracts.

Preferred conceptual shape:

~~~text
POST /hx/gradebook/{offeringId}/scores/batch
~~~

or a non-`hx` command route if the response is not an HTMX fragment.

The response must be authoritative. Do not recalculate row totals/completeness solely in JavaScript.

A successful response should provide enough server-rendered/authoritative state to refresh affected cells and row summaries.

## Existing single-cell contract

Do not break the Milestone 5/6 contract:

- blur is the existing single-cell save boundary;
- success requires HTTP 200 and `X-Gradebook-Saved: 1`;
- invalid input remains visible for correction;
- `NULL` remains distinct from zero;
- historical rows remain read-only;
- live scope revocation must be enforced on every write.

Batch paste is additive capability, not a reason to weaken the proven single-cell path.

## Multi-cell fill

A selected range may support filling one scalar value across eligible cells, but only after transactional batch infrastructure exists.

Example:

~~~text
select 16 cells -> type/fill 10 -> one validated batch command
~~~

Do not implement Excel-style formula propagation.

A drag-fill handle is optional and should not be added until basic range selection, copy/paste, keyboard operation, accessibility, and transactional batch behavior are proven stable.

---

# Workspace 1: classroom overview

The overview is a work surface, not a decorative analytics dashboard.

Use only facts backed by implemented domains and authorized data.

Possible administrator view:

~~~text
ปพ.5 · ป.4/1 · ปีการศึกษา 2569

นักเรียน              16 คน
รายวิชา               12 วิชา
ครูผู้สอน              12/12 วิชา
การเก็บคะแนน           10/12 วิชาพร้อม

งานที่เกี่ยวข้อง
- นักเรียน
- รายวิชาและครู
- คะแนน
~~~

Do not display future attendance/evaluation/result readiness until those domains exist.

For a user with limited resource access, reduce the overview to authorized work rather than leak school-wide/classroom-wide counts.

---

# Workspace 2: student roster

The roster should visually combine the teacher's concept of "นักเรียนในห้อง" while preserving student master, yearly enrollment, and placement history internally.

Target table:

~~~text
เลขที่ | รหัส | ชื่อ-สกุล | สถานะ | การจัดการ
~~~

Do not show full national IDs in the roster.

Contextual actions may include, when existing permissions/lifecycle allow:

- ดูข้อมูล;
- แก้ไขข้อมูล;
- ย้ายห้อง;
- เปลี่ยนสถานะ/ย้ายออก using existing terminal-state rules;
- ดูประวัติ.

The user should not need to understand "placement record" to move a student.

Any quick action must call the existing authoritative services or a thin tested orchestration layer.

The existing import Upload -> Preview -> Apply/Cancel workflow remains valid and should be linked contextually from the student workspace rather than rewritten in Milestone 6.5.

---

# Workspace 3: subjects and teachers

The work surface should combine what a teacher/admin thinks of as the class's subjects and teachers.

Target columns where existing data supports them:

~~~text
รหัส | รายวิชา | ภาคเรียน | สถานะ | ครูผู้สอน | การเก็บคะแนน | การจัดการ
~~~

Do not invent yearly hours/weights/subject type fields if the current database does not yet store them. Those may belong to a future curriculum extension.

Milestone 6.5 may provide in-context actions for existing facts:

- add/open an existing subject for this classroom/term;
- change offering status where allowed;
- assign/change teaching assignment;
- jump directly to score setup;
- jump directly to Gradebook.

A classroom-scoped filter should eliminate repeated re-selection of school/year/classroom.

The UI may visually group Term 1 and Term 2, but backend offerings remain separate records.

Do not merge the two term records in the database.

---

# Workspace 4: score structure

Normal teacher terminology should emphasize:

~~~text
การเก็บคะแนน
รายการคะแนน
หัวข้อคะแนน
เพิ่มช่องคะแนน
คะแนนเต็ม
~~~

Avoid making `code` and `sort_order` primary concepts.

A component code may remain internally required, but the UI should derive/suggest it or place it behind advanced detail unless a real school workflow requires explicit entry.

Reordering should eventually feel direct (for example explicit up/down controls or safe ordering controls) rather than requiring teachers to understand arbitrary numeric sort values.

The component-max immutability rule after score history exists remains fully enforced.

Inactive components retain history and do not silently disappear from historical context.

A classroom/subject overview should make score-total misconfiguration visible without requiring opening every setup page.

Do not implement grade calculation in M6.5.

---

# Workspace 5: scores / Gradebook

The Gradebook remains offering-specific for authorization and storage, but entry from the classroom workspace should feel like choosing a subject rather than navigating internal resources.

Target top-level context:

~~~text
คะแนน > คณิตศาสตร์ 4
ป.4/1 · ปี 2569 · ภาคเรียนที่ 1
~~~

The table should prioritize:

- sticky student identity;
- compact score headers;
- score max visible near header;
- editable score matrix;
- server totals/completeness;
- clear but quiet save state.

Secondary metadata such as teacher assignment details should not dominate the score-entry viewport.

The current Gradebook page exposes columns such as `ประเภทแถว`, multiple summary columns, and administrative wording. Task work should evaluate whether those can be visually compressed or moved to secondary presentation without losing historical transparency or testability.

Historical rows must remain clearly distinct and read-only.

---

# Responsive strategy

Spreadsheet workflows are desktop-first but must remain usable, not destructive, on smaller screens.

Rules:

- do not force a 20-column score matrix into unreadably narrow columns;
- horizontal scrolling inside the grid is acceptable and expected;
- identity columns may remain sticky where browser support and accessibility are sound;
- the whole document should not horizontally overflow because the grid has an internal scroll container;
- mobile may prioritize viewing and simple single-cell edits over advanced multi-range operations;
- desktop keyboard/copy-paste behavior is the primary advanced data-entry target;
- no action required for correctness may be pointer-only.

Milestone 6.5 acceptance must test at least representative desktop, tablet, and mobile widths.

---

# Accessibility strategy

Spreadsheet behavior must not regress the M6 accessibility foundation.

Required considerations:

- semantic table headers and row headers;
- meaningful accessible labels for score inputs/cells;
- visible focus;
- no color-only selected/error/saved state;
- async save status available to assistive technology without repetitive announcements;
- range selection does not remove keyboard reachability;
- focus remains predictable after server refresh;
- clipboard/paste errors identify student and score column in human language;
- no hidden focus under sticky headers/top bars;
- `prefers-reduced-motion` remains respected;
- dialogs/drawers, if introduced, have correct focus entry/exit and Escape behavior;
- no keyboard trap.

Actual screen-reader verification remains a desirable manual checkpoint where practical; do not claim certification without it.

---

# Testing philosophy

Every task must test business contracts, not only CSS selectors.

Use layers:

1. unit/service tests for new read-model/orchestration logic;
2. feature tests for routes, permissions, tenant isolation, status codes, CSRF, safe errors;
3. Gradebook-focused tests for score semantics and batch behavior;
4. browser/synthetic checks for interaction, overflow, focus, keyboard, clipboard parsing where feasible;
5. real MAMP smoke for final user workflows.

Do not weaken existing tests to fit new UI.

When old HTML-shape tests are intentionally superseded, replace them with equal-or-stronger behavioral assertions in the same task.

---

# Implementation tasks

## Task 1 — Baseline Inventory + Classroom Workspace Read Foundation

### Purpose

Create the safest possible workspace foundation before moving any existing mutation UI.

### Required work

- Re-inventory current M6 routes, controllers, repositories, services, permissions, view context, and navigation related to:
  - classrooms;
  - students/enrollments/placements;
  - subjects/offerings;
  - teaching assignments;
  - Gradebook accessible-offering listing.
- Add a tenant-safe classroom workspace read model/service.
- Add a canonical classroom workspace overview route/view.
- Derive school/year/grade/classroom identity from the classroom database record.
- Build section visibility/capabilities from existing permissions/resources only.
- Render only implemented/authorized sections.
- Add tests proving cross-school classroom IDs cannot be viewed.
- Add tests proving a limited user does not receive counts/data beyond existing authority.
- Do not add write actions yet.
- Do not alter legacy entity routes yet.

### Expected files

Exact names may vary after inventory, but likely areas include:

~~~text
htdocs/routes/web.php
htdocs/app/Application.php
htdocs/app/Controllers/*WorkspaceController.php
htdocs/app/Services/*Workspace*Service.php
htdocs/app/Repositories/ClassroomRepository.php (only if a safe read primitive is missing)
htdocs/views/workspaces/classroom/*
htdocs/views/layouts/app.php / UI context integration as needed
tests/Feature/*Workspace*
~~~

### Acceptance criteria

- authenticated school user can open an authorized classroom workspace;
- wrong-school classroom ID returns safe denial/not-found according to existing convention;
- workspace context shows authoritative year/grade/classroom;
- no raw national IDs are loaded/rendered for overview;
- no new permission code;
- no DB migration;
- legacy M1–M6 routes still work;
- focused tests pass;
- full relevant regression passes;
- syntax and diff checks pass.

### Stop rule

After Task 1 commit, stop and report commit SHA, changed files, tests/assertions, permission cases, and any architectural discoveries. Do not begin Task 2.

---

## Task 2 — Workspace Shell + Task-Oriented Navigation

### Purpose

Make classroom context persistent and obvious without replacing the global application shell.

### Required work

- Add workspace-local navigation for authorized implemented sections only.
- Add fast classroom/year switching using server-validated resources.
- Integrate workspace entry points into existing permission-driven navigation/dashboard.
- Preserve system-context navigation behavior.
- Ensure active navigation state works for nested workspace pages.
- Ensure subject teachers with only Gradebook access are not given unauthorized classroom-admin tabs.
- Do not hard-code role names.
- Do not render placeholders for Attendance/Evaluation/Results.

### Acceptance criteria

- user can move between implemented classroom sections without re-selecting year/classroom;
- current context is always visible;
- school/academic context never comes from a trusted hidden browser field;
- navigation changes with live permission/resource access;
- logout remains POST+CSRF;
- responsive mobile menu remains accessible;
- M6 cross-screen accessibility contracts continue to pass.

### Stop rule

Commit and stop for review.

---

## Task 3 — Student Roster Workspace

### Purpose

Make the common "นักเรียนในห้อง" workflow feel direct while keeping student master, yearly enrollment, and placement history correct.

### Required work

- Add classroom-scoped roster read model.
- Show current classroom students in a dense table.
- Keep national ID absent/masked according to existing PII rules.
- Add contextual links/actions using teacher language.
- Move common placement/status actions closer to the roster where safe.
- Reuse existing Student/Enrollment/Placement services; do not duplicate lifecycle rules.
- Preserve historical placement data.
- Keep current import workflow available from roster context.
- Keep legacy student/enrollment admin pages for advanced/admin compatibility.

### Acceptance criteria

- roster reflects only current valid placements per existing rules;
- historical/terminal enrollment data is not silently rewritten;
- move-room/status actions are CSRF-protected, transactional, audited, permission-checked, and lifecycle-checked;
- failed action leaves authoritative state unchanged;
- user does not need to understand `Enrollment`/`Placement` terminology for ordinary moves;
- direct legacy route authorization remains intact.

### Stop rule

Commit and stop for review.

---

## Task 4 — Subjects + Offerings + Teaching Assignments Work Surface

### Purpose

Collapse three technical navigation concepts into one classroom task surface without collapsing their backend models.

### Required work

- List classroom offerings grouped intelligibly by subject/term.
- Show active teacher assignment(s) near each offering.
- Provide contextual opening/assignment/status actions using existing permissions.
- Provide direct links to score structure and Gradebook.
- Reduce repeated classroom/year selection.
- If a combined multi-write action is proposed, prove transaction-safe orchestration first.
- Do not invent curriculum facts absent from the database (hours/year, weight, subject type).

### Acceptance criteria

- offering uniqueness rules remain enforced;
- term 1/term 2 remain separate backend records;
- teaching assignment resource scope and audit remain intact;
- action visibility follows live permission;
- unauthorized direct POST still fails even if UI hides action;
- no partial success for any combined operation advertised as one action.

### Stop rule

Commit and stop for review.

---

## Task 5 — In-Context Score Structure UX

### Purpose

Replace developer-facing component management as the normal path with teacher-facing score-collection setup.

### Required work

- Rename visible concepts toward `การเก็บคะแนน`, `รายการคะแนน`, `เพิ่มช่องคะแนน`.
- Integrate setup entry into classroom/subject context.
- Reduce prominence of component `code` and numeric `sort_order` where safely possible.
- Provide simple ordering controls if they can map safely to existing ordering semantics.
- Show configured max total clearly.
- Surface misconfigured/empty score structures at classroom subject overview.
- Preserve inactive/history behavior.
- Preserve max-score immutability once score history exists.
- Preserve existing setup deep link.

### Acceptance criteria

- creating/editing score items still uses server validation and audit;
- teacher can understand setup without learning component-code semantics;
- score history prevents illegal max-score mutation exactly as before;
- inactive historical component data remains retrievable;
- permission revocation removes UI affordance and direct backend write remains denied.

### Stop rule

Commit and stop for review.

---

## Task 6 — Gradebook Active Cell + Keyboard Spreadsheet Navigation

### Purpose

Establish a robust spreadsheet interaction foundation before adding bulk writes.

### Required work

- Refine Gradebook viewport for score-entry priority.
- Add explicit active-cell model.
- Add keyboard navigation under a documented state machine.
- Preserve IME behavior.
- Preserve current blur autosave endpoint and success-header contract.
- Keep invalid typed value visible on failed single-cell save.
- Add selection styles that do not rely only on color.
- Ensure focus after an HTMX cell replacement returns predictably.
- Do **not** add multi-cell paste yet.

### Acceptance criteria

- Enter still supports fast same-column entry;
- Tab/Shift+Tab remain predictable and do not trap keyboard users;
- arrow navigation behaves according to tests and does not interfere with text caret unexpectedly;
- historical/read-only cells cannot become writable;
- single-cell `NULL` vs `0.00` round-trip remains correct;
- live authorization revocation during the session is still enforced;
- no duplicate score POSTs from focus/blur/queue races.

### Stop rule

Commit and stop for review.

---

## Task 7 — Range Selection + Clipboard Copy

### Purpose

Add spreadsheet selection and export-to-clipboard behavior without yet changing multiple server records at once.

### Required work

- Add rectangular range selection for eligible grid cells.
- Support mouse/pointer drag selection on desktop.
- Add keyboard-compatible extension where feasible and accessible.
- Copy selected range as TSV/plain text.
- Preserve blank vs zero in copied output.
- Permit copying read-only historical values only if that does not expose unauthorized data.
- Do not include identity metadata unless the user explicitly selects/copies a supported identity region.
- Do not add paste writes yet.

### Acceptance criteria

- copied matrix pastes correctly into Excel/Google Sheets;
- zero is not converted to blank;
- selection cannot turn a read-only cell editable;
- selection does not break normal page text selection outside Gradebook;
- mobile/basic single-cell use remains functional;
- keyboard focus remains visible.

### Stop rule

Commit and stop for review.

---

## Task 8 — Transactional Multi-Cell Score Paste

### Purpose

Deliver the most important Excel-familiar workflow: copy a score matrix from Excel/Google Sheets and paste it into Gradebook safely.

### Required work

- Add server-authoritative batch score command/service.
- Parse/normalize a rectangular paste request.
- Independently validate every targeted component/enrollment/value.
- Enforce all-or-nothing transaction semantics.
- Preserve auditability for every changed score.
- Return authoritative affected cell and row-summary state.
- Update UI from server response; do not compute authoritative totals in JS.
- Provide human-readable error identifying student and score column.
- Reject spill into summary/historical/disabled cells.
- Reject stale/unauthorized writes after live scope revocation.
- Keep single-cell endpoint unchanged.

### Required batch test matrix

At minimum test:

- 1x1 paste;
- multi-row one-column paste;
- multi-row multi-column paste;
- blank -> NULL;
- zero -> real zero;
- decimal valid value;
- value above max;
- malformed numeric value;
- target matrix too wide/tall;
- historical target;
- inactive/invalid component;
- wrong offering/component relation;
- wrong enrollment/offering/classroom relation;
- cross-school IDs;
- expired CSRF/session behavior;
- permission revoked after page load;
- one invalid cell causes zero committed changes;
- successful paste updates totals/completeness from server;
- audit captures all committed changes.

### Acceptance criteria

- a valid Excel TSV matrix can be pasted with one user action;
- invalid matrix is atomic: no partial write;
- error points to the offending row/column in human language;
- `NULL != 0` remains intact;
- existing 200 + `X-Gradebook-Saved: 1` single-cell contract is untouched;
- new batch success contract is explicit and tested.

### Stop rule

Commit and stop for review.

---

## Task 9 — Multi-Cell Fill + Fast Repeated Entry

### Purpose

Reduce repetitive entry when many students receive the same score.

### Required work

- Use Task 8 batch infrastructure for range fill.
- Allow one scalar value to populate a selected eligible range.
- Allow clear-to-NULL for an explicitly selected editable range if UX is unambiguous.
- Do not implement formulas.
- Do not implement a drag-fill handle unless the simple interaction is already stable and accessible.

### Acceptance criteria

- fill is one transactional server command;
- invalid value causes no partial changes;
- read-only/historical cells cannot be included silently;
- audit and totals remain authoritative;
- keyboard users have a non-pointer-only path.

### Stop rule

Commit and stop for review.

---

## Task 10 — My Teaching + Fast Entry Paths

### Purpose

Let subject teachers reach real work in minimal steps.

### Required work

- Refine `/gradebooks` or add `/workspaces` teaching section so accessible offerings are presented as `งานสอนของฉัน`.
- Group intelligibly by classroom/subject/term without assuming role names.
- Use `GradebookReadService::listAccessibleOfferings` or equally authoritative resource filtering.
- Provide direct score-entry action.
- Provide score-setup action only when component-manage permission allows.
- Zero accessible offerings remains an empty state, not an authorization error.

### Acceptance criteria

- scoped teacher sees only accessible offerings;
- executive/read-only user sees appropriate read-only entry path;
- school admin sees authorized broader resources;
- no role-code hard-coding;
- direct unauthorized Gradebook access remains denied.

### Stop rule

Commit and stop for review.

---

## Task 11 — Cross-Screen Consistency, Responsive, Accessibility, and Security Hardening

### Purpose

Harden the new workflow after all individual pieces exist.

### Required work

- Run cross-workspace semantic/accessibility checks.
- Verify table scroll containment.
- Verify sticky/focus behavior at representative viewports.
- Verify dialogs/drawers if any.
- Verify no inline script/style regressions inconsistent with M6 conventions.
- Verify all POST forms/commands have CSRF.
- Verify confirmation allowlist remains limited to high-impact actions.
- Verify no PII/error leakage.
- Verify no future placeholder links.
- Verify legacy entity pages still work for admins/compatibility.
- Verify permission UI hiding plus backend denial across workspace actions.

### Acceptance criteria

- existing M6 UI contract suite passes or is strengthened;
- new workspace tests cover authorized/unauthorized variants;
- no document-level horizontal overflow caused by dense grids;
- focus not obscured;
- reduced-motion CSS remains present;
- no external runtime asset introduced;
- no new permission or migration appears unexpectedly.

### Stop rule

Commit and stop for review.

---

## Task 12 — Full Verification + Real MAMP Workflow Smoke + Documentation

### Purpose

Verify the complete Milestone 6.5 user workflow before PR.

### Required automated verification

- full PHPUnit suite;
- PHP syntax for all first-party PHP files;
- JavaScript syntax for all first-party JS files;
- `git diff --check`;
- focused Gradebook regression;
- workspace permission/tenant regression;
- browser/synthetic checks for the new interaction model.

### Required real MAMP smoke

At minimum, exercise representative personas/resources:

1. school administrator / academic administrator classroom workflow;
2. subject teacher with offering-scoped Gradebook access;
3. read-only executive where available;
4. restricted/permission-revoked case.

Human workflow checks should include:

~~~text
open classroom workspace
-> move between implemented sections without losing context
-> inspect roster
-> inspect subjects/teachers
-> open score setup
-> add/edit a safe test score item if fixture permits
-> open Gradebook
-> keyboard single-cell edit
-> copy selected score range to clipboard
-> paste valid multi-cell values from spreadsheet/plain TSV
-> verify DB values/totals
-> paste an invalid matrix and prove no partial DB mutation
-> verify zero vs blank
-> verify live scope revocation denies subsequent write
~~~

Use dedicated disposable fixtures and restore baseline data after verification. Do not delete audit history merely to make cleanup convenient.

### Documentation

Update README/documentation to explain:

- classroom workspace entry path;
- Excel-familiar clipboard behavior;
- blank vs zero;
- batch paste atomicity;
- permission/resource behavior;
- known limitations;
- M7 remains not implemented;
- future assessment/competency/reference-print requirements remain deferred but locked.

### Known limitations to document honestly if still applicable

- no formula support;
- no offline edit;
- no live multi-user cell presence/locking;
- no Attendance/Evaluation/Competencies yet;
- no official PP5/PP6/PDF output yet;
- no native XLSX/XLSB migration;
- actual screen-reader testing only if performed;
- reduced-motion manual OS/browser verification only if performed.

### Final acceptance

Milestone 6.5 is ready for PR only when:

- all tasks have individual reviewed commits;
- full regression passes;
- real MAMP workflow passes;
- batch score paste is atomic and audited;
- tenant/permission/scope regression is clean;
- working tree is clean;
- implementation branch is pushed and synchronized with origin;
- no M7 code is included.

---

# Compatibility requirements

## Existing deep links

Do not remove working routes merely because a new workspace entry path exists. Preserve current admin/deep-link pages until there is an explicit deprecation decision.

## Gradebook

Keep existing offering-specific URLs and service authorization unless a task proves a stronger compatible design.

## Student import

Keep the existing server-side CSV preview/apply/cancel semantics. M6.5 changes entry context, not import business rules.

## Error behavior

Preserve safe 403/404 behavior and generic 500 handling. Workspace composition must not expose stack traces, SQL, filesystem paths, or sensitive data.

---

# Performance expectations

Milestone 6.5 should remain practical for normal primary-school classroom sizes without introducing a frontend framework.

Avoid obvious N+1 repository behavior when composing:

- roster rows;
- subject/teacher lists;
- Gradebook matrices;
- workspace status counts.

Batch paste must not perform a fresh full-page DB query per pasted cell when a scoped set-based validation/read can be used safely.

Do not optimize by weakening transactions or authorization.

---

# Concurrency and stale-state expectations

The system is online and may be used by multiple people.

At minimum:

- every write rechecks live authorization and lifecycle;
- batch paste validates authoritative current component/enrollment state at command time;
- no client-cached permission is trusted;
- a stale page must fail safely rather than overwrite an invalid target.

Optimistic version columns/real-time collaborative locks are not introduced in M6.5 unless a concrete defect proves they are necessary. If lost-update risk is discovered in a way existing semantics cannot safely tolerate, stop and propose the minimal architecture change rather than silently inventing a client-side lock.

---

# Audit expectations

Workspace convenience must not reduce audit quality.

For writes, preserve WHO / WHAT / WHEN / WHY / WHERE semantics established by the architecture.

For batch score operations, the audit record(s) must be sufficient to identify every committed score change. Do not log sensitive secrets or full unnecessary PII.

Failed validation and denied authorization must not produce fake success audit entries.

---

# Terminology dictionary for M6.5

Use these presentation terms consistently unless user testing proves a clearer phrase:

~~~text
Classroom Workspace       -> ปพ.5 ของห้อง / งานชั้นเรียน (context dependent)
Subject Offering          -> รายวิชาที่เปิดสอน (advanced/admin) / รายวิชา (normal classroom context)
Teaching Assignment       -> ครูผู้สอน
Score Component           -> รายการคะแนน / หัวข้อคะแนน
Gradebook                 -> สมุดคะแนน / คะแนน
Enrollment                -> การเรียนในปีการศึกษา only when explanation is necessary
Placement                 -> การจัดห้อง / ย้ายห้อง
ACTIVE/INACTIVE/etc.      -> central Thai status labels
~~~

Do not change backend identifiers merely for presentation wording.

---

# Review protocol: one task per prompt

The implementation workflow for this milestone is deliberately strict because usability work can easily become broad and under-tested.

For each prompt/task:

1. start from the current Milestone 6.5 implementation branch;
2. fetch origin and prove branch/working-tree state;
3. restate the single task boundary;
4. inspect relevant current files before editing;
5. implement only that task;
6. add/adjust tests before considering it complete;
7. run focused tests;
8. run the regression scope required by that task;
9. run syntax checks and `git diff --check`;
10. review the diff for accidental permissions/security/PII/business-semantic changes;
11. commit with one clear task-specific commit;
12. push;
13. report commit SHA, changed files, verification counts, manual checks, and known limitations;
14. stop.

The agent must **not** continue automatically to the next task, even when the current task passes.

The next task begins only after review/approval in a new prompt.

---

# Branch strategy

Planning document branch:

~~~text
docs/m6-5-classroom-workspace-plan
~~~

After this plan is reviewed and merged to `main`, implementation should use:

~~~text
milestone/6-5-classroom-workspace
~~~

Do not implement production code on the planning branch.

Do not merge the implementation branch until all Task 1–12 verification and final PR review are complete.

---

# Milestone 6.5 completion definition

Milestone 6.5 is complete when the system has shifted from entity-first daily PP5 work to a classroom/teaching workspace without losing the architecture established in M1–M6.

A teacher should be able to reach real score work quickly, operate the Gradebook with familiar spreadsheet interactions, paste score matrices safely from Excel/Google Sheets, and remain inside an obvious classroom context.

An administrator should be able to manage students, subjects, teachers, and score setup from task-oriented classroom surfaces while advanced backend concepts remain available but no longer dominate the workflow.

The milestone must leave clear non-rendered extension points for future Attendance, Assessment (including learner competencies), Results, and printable PP5 composition, with the requirement that future printable reference material such as indicators, rubrics, and observable behaviors is versioned and reproducible for historical academic years.

Only after Milestone 6.5 is verified and merged should Milestone 7 Attendance begin.