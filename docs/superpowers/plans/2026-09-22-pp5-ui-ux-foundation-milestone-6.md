# PP5 UI/UX Foundation + Application Shell Milestone 6 Implementation Plan

> **For agentic workers:** Implement this plan task-by-task with genuine TDD and browser verification. Each task ends with focused tests, full regression where specified, a clean git diff, and a commit. Do not start the next task until the current task is verified. UI changes must never weaken authentication, authorization, tenant isolation, CSRF, transaction, audit, PII, or lifecycle guarantees established in Milestones 1–5.

**Goal:** Transform the verified Milestones 1–5 functional UI into a coherent, accessible, responsive PP5 product foundation before Attendance, Evaluation, Activities, Results, Finalization, Promotion, and official reports are added. Milestone 6 introduces a shared application shell, permission-driven navigation, a small reusable visual system, consistent page/form/table/status patterns, and a deliberate redesign of all existing full-page surfaces while preserving established business behavior.

**Architecture:** Keep the existing PHP MVC-lite and server-rendered-first architecture. Full-page views render through shared layouts and UI context; HTMX fragments remain fragment-only. Bootstrap 5.3-compatible CSS is local/vendor-pinned with PP5 custom CSS and minimal Vanilla JS. Navigation visibility is derived from live authorization and available resources, never from trusted browser fields or role-name shortcuts. Existing controllers/services/repositories remain the source of business truth.

**Tech Stack:** PHP 8.2-compatible, FastRoute 1.3.x, PDO, MySQL 8 / MariaDB-compatible SQL, PHP Session, PHPUnit 11, HTML5, Bootstrap 5.3-compatible CSS, HTMX 2.x, Vanilla JS, MAMP locally, InfinityFree-compatible deployment.

**Spec:** docs/superpowers/specs/2026-09-08-pp5-technical-architecture-v1.2-design.md

**Baseline:** Milestones 1–5 are merged to main. Planning baseline is commit 4b176ca6bef624af1d9d376267aa27fc520049a4 after the post-Milestone-5 README cleanup. Milestone 5 verified 2,651 tests / 60,718 assertions, 166 PHP files, first-party JavaScript syntax, git diff --check, and real MAMP MySQL smoke.

**External behavioral references supplied during planning:** ปพ5-ป4-ปีกศ 68.xlsb and 2568 แบบประเมินสมรรถนะสำคัญของผู้เรียน ป4.xlsx. These files may inform data-entry density and real school workflow expectations. They are references only: do not copy workbook cell coordinates, colors, worksheets, formulas, macros, or spreadsheet layout into the web architecture.

---

## Source Baseline and Current UI Reality

Milestones 1–5 intentionally optimized correctness, security, tenant isolation, lifecycle rules, transactions, audit, and automated regression before product-level UI polish.

At the start of Milestone 6:

- htdocs/views contains 36 PHP view files.
- Three Gradebook files are fragment-style views: row-summary.php, score-cell.php, score-error.php.
- The remaining views are mostly complete HTML documents that repeat doctype, html, head, viewport, title, body, and local navigation.
- There is no shared application layout directory yet.
- There is no PP5 application CSS file yet.
- htdocs/assets currently contains gradebook.js and the locally vendored HTMX asset/license.
- Dashboard is a functional list of links rather than a role-aware application home.
- Some pages already use Bootstrap-compatible classes such as container, table, table-responsive, and alert, but no shared Bootstrap stylesheet is loaded by the application.
- Navigation is distributed across pages, often as a "กลับแดชบอร์ด" link.
- Status values such as ACTIVE, INACTIVE, DRAFT, CLOSED, TRANSFERRED_OUT, and WITHDRAWN are frequently exposed directly instead of using consistent Thai presentation labels.
- Gradebook already has valuable UX contracts: local HTMX, blur save boundary, Enter navigation, native Tab behavior, server-rendered totals, NULL distinct from zero, explicit success header, and historical read-only rows. M6 must preserve them.

Milestone 6 is therefore a structural UI foundation milestone, not a cosmetic repaint.

---

## Global Constraints

- Preserve every Milestone 1–5 authentication, authorization, school isolation, scope, CSRF, transaction, audit, error-safety, PII, lifecycle, and historical-data guarantee.
- No database migration is expected in Milestone 6.
- No seed file is expected in Milestone 6.
- Do not add or rename business permission codes merely to support navigation.
- Do not change gradebook score semantics, roster semantics, score validation, audit behavior, or NULL-vs-zero behavior.
- Do not add Attendance, Evaluation, Competencies, Activities, grade calculation, annual results, finalization, promotion, PP5/PP6 reporting, DMC/XLSX/XLSB import, or any other future domain.
- Do not create placeholder links for unimplemented features.
- UI hiding is convenience only. Existing backend authorization remains the security boundary.
- Navigation must follow live permissions and live resource authorization. Do not authorize by role code/name in view templates.
- Browser-provided school_id, user_id, actor, role, scope, context, or resource identifiers never become UI authority.
- Continue escaping all dynamic HTML output.
- Continue serving core assets locally. Do not introduce runtime CDN dependencies.
- No npm, Node build, Sass compiler, React, Vue, Angular, bundler, SPA router, or frontend build pipeline.
- No external web font requirement. Use a Thai-capable system font stack.
- No icon-only critical action. Text labels remain sufficient when icons fail or are absent.
- No JavaScript-only security or validation rule. JavaScript may improve interaction but server behavior remains authoritative.
- Full-page rendering and HTMX fragments must remain distinct. A fragment response must never accidentally include the full application shell.
- Existing HTTP status codes and safe-error contracts must remain stable unless a task explicitly locks a better compatible contract and updates tests.
- Avoid visual changes that require business repositories to expose sensitive data.
- Keep production compatible with InfinityFree and request-driven PHP.
- Legacy Excel is behavioral reference only; do not reproduce fixed spreadsheet geometry as the application information architecture.

---

## Milestone Boundary

### Included

~~~text
shared full-page rendering primitive
school-context application shell
system-context application shell
guest/login shell
safe error shell
permission-driven navigation
active navigation state
responsive sidebar / mobile navigation
top bar and current-school identity
consistent logout placement
PP5 design tokens and base stylesheet
local Bootstrap 5.3-compatible stylesheet asset
minimal app.js interaction layer
typography and spacing rules
button hierarchy
form field pattern
validation/error presentation
status badge presentation
table and data-density pattern
empty states
page headers and breadcrumbs
dashboard redesign
gradebook landing/listing UX
system school screens redesign
school user screens redesign
academic setup screens redesign
teaching assignment redesign
student screens redesign
enrollment screens redesign
student import step-flow redesign
gradebook setup redesign
gradebook data-entry visual redesign
responsive behavior
keyboard/focus/accessibility hardening
Thai UI terminology normalization
M1–M5 UI regression coverage
real MAMP browser smoke
documentation
~~~

### Explicitly Deferred

Do not implement in Milestone 6:

~~~text
attendance domain or screens
evaluation / competencies domain or screens
activities domain or screens
grade symbols or grade calculation
annual results / GPA
subject finalization
unlock / approval workflows
promotion / repeat / graduation
PP5 / PP6 report rendering
mPDF official documents
native DMC/XLSX/XLSB import
legacy Excel migration
bulk score import/export
school chooser
multi-school active membership
email invitation/reset
2FA / SSO
audit browsing UI
generic cross-domain scope editor
staff profile subsystem
homeroom assignment
theme editor
user-customizable colors
dark mode
native mobile app
public API
frontend build pipeline
visual screenshot snapshot framework that requires Node
~~~

---

## UX Principles

### 1. Correctness before decoration

The existing application is trusted because the backend revalidates permission, tenant, entity state, CSRF, and transactions. M6 must make those rules easier to understand without moving authority into the browser.

### 2. School work is task-oriented

Teachers and administrators should see actions in terms of work they need to perform, not internal table names or implementation layers.

Good:
- นักเรียน
- การลงทะเบียน
- สมุดคะแนน
- โครงสร้างวิชาการ
- การมอบหมายครูประจำวิชา

Avoid exposing internal wording such as permission scope, role assignment ID, or database status codes when a clear Thai label exists.

### 3. Data entry must stay fast

PP5 replaces spreadsheet-heavy workflows. The UI must not trade speed for decorative cards or excessive modal dialogs.

For dense workflows:
- keyboard navigation stays available;
- tables may remain tables;
- horizontal scrolling is preferable to unreadably narrow columns;
- key identifiers stay visible;
- actions are near the data they affect;
- repeated forms do not require needless page transitions.

### 4. Progressive disclosure

A user should first see the information needed for the current task. Secondary explanations, history, and administrative metadata may be visually quieter but must remain accessible.

### 5. Permission-aware, not role-hardcoded

Two users with the same role label may have different current permissions or offering scopes. The UI follows live permission checks and resource access, never assumptions based only on role names.

### 6. Server-rendered first

Navigation, forms, status labels, tables, and essential actions work as normal HTML. HTMX and JavaScript improve interaction but must not become mandatory except where Milestone 5 already explicitly requires JavaScript for per-cell Gradebook autosave.

### 7. Accessibility is a foundation feature

Target WCAG 2.2 AA-compatible practices for the surfaces implemented in M6:
- semantic landmarks;
- logical heading order;
- explicit form labels;
- keyboard reachability;
- visible focus;
- skip link;
- text alternatives where needed;
- error messages not conveyed by color alone;
- adequate contrast;
- aria-current for current navigation;
- aria-live/role=status for async save feedback;
- touch targets that remain practical on mobile.

---

## Locked Design Decisions

### 1. No database change

Milestone 6 is a presentation and application-shell milestone. Existing domain tables, migrations, seeds, permission codes, audit codes, and business state machines remain unchanged.

A proposed UI improvement that requires a new persisted business fact belongs in a future domain milestone unless it is separately approved.

### 2. Full-page layout and fragment rendering are separate contracts

Keep App\Support\View::render() suitable for raw templates/fragments.

Add an explicit full-page rendering API rather than making every render call automatically wrap a layout.

Recommended shape:

~~~text
View::render(template, data)
→ raw template/fragment only

View::page(template, data, pageContext)
→ render content template
→ inject into selected shared layout
→ return one complete HTML document
~~~

Exact method names may change during implementation if tests prove a cleaner API, but the separation is locked.

Gradebook score-cell, row-summary, and score-error stay fragment-only.

### 3. Layout families

Use three deliberate layout families:

~~~text
layouts/app.php
→ authenticated SCHOOL or SYSTEM application pages

layouts/guest.php
→ login / unauthenticated entry

layouts/error.php
→ safe 403/404 presentation that requires no sensitive context
~~~

If SYSTEM and SCHOOL shells need materially different navigation, represent the difference through page context/navigation data rather than duplicating the complete HTML document.

### 4. Central page context

Introduce a small server-side UI context builder/service responsible for presentation context only.

Expected page context fields include:

~~~text
document title
page title
optional subtitle
context type: SYSTEM or SCHOOL
school display identity when valid
signed-in display name
logout CSRF token
navigation sections/items already authorized
current navigation key
breadcrumbs
page actions
body/layout modifiers when needed
optional page-specific head assets
optional page-specific scripts
~~~

Do not let layouts query PDO directly.

Do not let layouts call authorization repositories directly.

The controller/application layer resolves context and passes safe display data into the view.

### 5. Navigation model

SCHOOL navigation is grouped by user tasks and only renders implemented/authorized entries.

Recommended hierarchy:

~~~text
ภาพรวม
  แดชบอร์ด

นักเรียน
  รายชื่อนักเรียน
  การลงทะเบียน
  นำเข้านักเรียน          [only with STUDENT_IMPORT]

วิชาการ
  ปีการศึกษา
  ห้องเรียน
  รายวิชา
  การเปิดรายวิชา
  การมอบหมายครูประจำวิชา [only when authorized]

การเรียนการสอน
  สมุดคะแนน              [only when at least one gradebook is accessible
                           or user has a school-wide gradebook view path]

การจัดการ
  ผู้ใช้งาน               [only when authorized]
~~~

SYSTEM navigation currently contains only implemented system-level work such as school management.

Do not show Attendance, Evaluation, Activities, Results, or Reports until those routes exist.

### 6. Gradebook needs a stable landing surface

Add a read-only gradebook landing route such as GET /gradebooks, using the existing GradebookReadService::listAccessibleOfferings() resource authorization.

The landing page:
- lists only offerings the current user can actually view;
- works for school-wide administrators, scoped subject teachers, and read-only executives;
- does not accept browser-provided school authority;
- does not introduce a new generic permission gate;
- links each accessible offering to its existing gradebook page;
- presents empty state when none are available.

Dashboard may show a short "งานของฉัน / สมุดคะแนน" subset, but the sidebar should not depend on a fragile dashboard anchor.

### 7. Design system remains deliberately small

Do not build a generic component framework.

Create reusable patterns for the surfaces already needed:

~~~text
app shell
page header
breadcrumbs
section heading
primary/secondary/danger button
form field and field help
alert
status badge
data table
filter bar
empty state
definition/details panel
action group
save status
step indicator for import
~~~

Prefer semantic HTML + CSS classes over PHP component abstraction when the abstraction would add more complexity than repetition.

Use PHP partials only where reuse is clear and testable.

### 8. Bootstrap is locally served

The architecture names Bootstrap 5.3. M6 must load a pinned 5.3-compatible CSS asset locally under htdocs/assets/vendor with its license.

Do not load Bootstrap from a CDN.

Do not introduce a Bootstrap JavaScript dependency merely for styling. Mobile navigation and simple disclosure can use minimal first-party Vanilla JS. If a Bootstrap JS component becomes necessary, vendor it locally and lock it explicitly with tests/license.

### 9. PP5 custom CSS owns product identity

Add htdocs/assets/app.css.

Use CSS custom properties for semantic design tokens rather than scattering literal values through templates.

Token groups:

~~~text
color:
  background
  surface
  surface-muted
  text
  text-muted
  border
  primary
  primary-hover
  success
  warning
  danger
  info
  focus

spacing:
  compact steps suitable for data-heavy screens

radius:
  small / medium

shadow:
  subtle only

layout:
  sidebar width
  content max width
  header height
  table density
~~~

Do not make color the only status indicator.

### 10. Visual direction

PP5 should feel like a dependable school administration tool, not a marketing site.

Desired characteristics:
- calm;
- high legibility;
- compact but not cramped;
- Thai-first;
- trustworthy;
- clear hierarchy;
- restrained decoration;
- consistent spacing;
- low visual noise;
- dense tables where the work demands density.

Avoid:
- giant hero sections;
- oversized dashboard cards;
- excessive gradients;
- glassmorphism;
- decorative animation;
- hidden controls that appear only on hover;
- low-contrast gray text;
- icon-only workflows.

### 11. Typography

Use a Thai-capable system stack and no remote font dependency.

Recommended stack concept:

~~~text
system-ui,
-apple-system,
BlinkMacSystemFont,
"Segoe UI",
Tahoma,
sans-serif
~~~

Do not require downloading a Thai font for the application to remain usable.

Use a readable body size and slightly compact data-table size. Inputs must not become so small that mobile browsers zoom unexpectedly.

### 12. Page width strategy

Normal forms/details:
- centered readable content region;
- do not stretch simple forms across the full monitor.

Data tables:
- use available width;
- wrap in responsive overflow region;
- preserve column readability.

Gradebook:
- deliberately wide workspace;
- horizontal scroll is expected;
- important identity columns may be sticky;
- do not compress score columns into unreadable widths.

### 13. Responsive strategy

Desktop-first remains correct for administration and Gradebook.

Break behavior conceptually into:
- wide desktop: persistent sidebar;
- tablet/small laptop: narrower/collapsible navigation;
- mobile: navigation drawer/toggle, stacked forms/actions;
- data tables: horizontal scroll when structural conversion would reduce clarity.

Mobile is especially important for future Attendance/Evaluation, so M6 must make the shell reusable at narrow widths even though those modules are deferred.

### 14. Navigation accessibility

Application shell includes:
- skip link to main content;
- nav landmark with accessible label;
- aria-current="page" on the current destination;
- visible focus indicators;
- mobile menu button with aria-expanded;
- deterministic focus behavior when menu opens/closes;
- logout as POST form with CSRF, never converted into an unsafe GET link.

### 15. Status presentation

Persisted codes remain unchanged. UI uses a central display mapping.

Initial display vocabulary:

~~~text
Academic year:
DRAFT  → ร่าง
ACTIVE → กำลังใช้งาน
CLOSED → ปิดปีแล้ว

Generic entity:
ACTIVE   → ใช้งาน
INACTIVE → ปิดใช้งาน

Membership/user:
ACTIVE    → ใช้งาน
SUSPENDED → ระงับ
INACTIVE  → ปิดใช้งาน

Enrollment:
ACTIVE          → กำลังเรียน
TRANSFERRED_OUT → ย้ายออก
WITHDRAWN       → ลาออก

Gradebook roster:
CURRENT    → รายชื่อปัจจุบัน
HISTORICAL → ประวัติ
~~~

Rendered status keeps enough semantic class/data to test the underlying code without showing raw English codes as the primary user-facing label.

Unknown status codes must render safely as escaped text rather than silently mapping to a wrong meaning.

### 16. Button hierarchy

Use consistent action hierarchy:

~~~text
Primary:
  save / create / continue / apply

Secondary:
  back / cancel navigation / reset filter

Neutral:
  view / detail / edit entry point

Danger:
  destructive or disabling transitions

Link:
  low-emphasis navigation
~~~

Do not make every button primary.

State transitions such as closing/deactivating/suspending should use clear wording that describes the resulting action.

### 17. Confirmation strategy

Use confirmation only for high-impact transitions, not routine save actions.

Potential enhanced confirmation targets:
- deactivate/suspend account or membership;
- close/deactivate important academic entities;
- cancel an import preview;
- other actions whose result is operationally significant.

Confirmation is a UI enhancement. Server authorization and validation still execute normally.

Do not add confirmation to Gradebook cell autosave.

### 18. Form pattern

Every form field should have:
- explicit label;
- appropriate input type/inputmode;
- required indication in text/semantics;
- concise help only where needed;
- server error summary at a predictable position.

Use autocomplete attributes where safe and appropriate.

Do not expose internal IDs as user-facing labels.

Retain submitted values after validation errors where the existing controller contract makes that possible without weakening security. Do not fabricate field values from rejected authority inputs.

### 19. Filter bars

List/search pages use a consistent filter-bar pattern:
- filters grouped above the result table;
- primary "ค้นหา/แสดง" action;
- clear/reset route when useful;
- selected values remain visible;
- filter controls stack cleanly on narrow screens.

Do not move authorization into filters.

### 20. Tables

All data tables:
- use scope on header cells;
- have visible table headings;
- use responsive overflow wrapper when needed;
- use stable action columns;
- render meaningful empty state;
- do not rely on color alone;
- preserve safe escaped values;
- avoid arbitrary truncation of critical identifiers.

Where a table has many row actions, group them visually without hiding security-relevant information.

### 21. Dashboard is a work surface

Dashboard should answer:
- Where am I?
- Who am I signed in as?
- What can I work on?
- What gradebooks are available to me?
- Where do I manage school/academic/student data if authorized?

Do not add fake KPIs or counts that are not already backed by correct repository queries.

Dashboard navigation and cards must be derived from the same authorization truth as direct routes.

### 22. Login

Login becomes a focused guest layout:
- PP5 identity;
- username;
- password;
- clear submit action;
- generic authentication error;
- no school chooser;
- no role chooser;
- no leaking whether a username exists;
- reasonable autocomplete attributes;
- keyboard-first flow.

### 23. Error pages

403 and 404 become visually consistent safe pages.

403 must not reveal which hidden resource exists.
404 must not echo untrusted request paths.
500 stays generic and must never reveal SQL/stack/config/path details.

If a styled 500 page is introduced, tests must continue proving it contains no sensitive detail.

### 24. Student import UX

Present canonical CSV as a deliberate multi-step flow:

~~~text
1 อัปโหลด
→ 2 ตรวจสอบตัวอย่าง
→ 3 ยืนยันนำเข้า
~~~

Preview status labels should communicate CREATE / MATCH / NOOP / CONFLICT / ERROR in understandable Thai while preserving machine values for tests.

Never reveal raw national ID in preview/error/audit surfaces.

Apply remains blocked by backend rules exactly as Milestone 4 defines.

### 25. Gradebook UX

Preserve Milestone 5 save contract exactly.

Enhance presentation with:
- clear offering identity at top;
- read-only/editable state banner;
- compact instructions;
- sticky table header where practical;
- sticky student identity columns where practical;
- current vs historical row distinction using text plus visual treatment;
- score inputs sized for numeric entry;
- per-cell save state visible and screen-reader accessible;
- summary columns visually separated;
- horizontal scroll container;
- component setup link shown only when authorized;
- no client-side total/grade calculation.

Enter continues moving down the same component.
Tab/Shift+Tab remain native.
Arrow keys remain native unless a future approved design explicitly changes them.
Blank remains NULL; 0.00 remains real zero.

### 26. Page-specific assets

The shared layout always loads the PP5 base CSS.

Only pages that need Gradebook HTMX load:
- local HTMX 2.0.8;
- gradebook.js;
- htmx-config metadata.

Do not load HTMX on every page solely for convenience.

Any new app.js loaded globally must stay small and contain shell-only enhancement such as mobile navigation/confirm behavior.

### 27. No inline presentation style in migrated full pages

Move layout/presentation rules from style attributes into app.css.

Dynamic values needed by HTMX or semantics may remain as data attributes.

### 28. Thai language consistency

Use one preferred term for the same concept across all screens.

Examples:
- "ปีการศึกษา" rather than alternating internal variants;
- "ห้องเรียน";
- "รายวิชา";
- "การเปิดรายวิชา";
- "การมอบหมายครูประจำวิชา";
- "สมุดคะแนน";
- "การลงทะเบียนนักเรียน";
- "นำเข้านักเรียน".

Technical English codes may be visible secondarily when genuinely useful to administrators, but should not dominate ordinary teacher workflow.

---

## Information Architecture

### SCHOOL context

~~~text
PP5
├─ ภาพรวม
│  └─ แดชบอร์ด
│
├─ นักเรียน
│  ├─ รายชื่อนักเรียน
│  ├─ การลงทะเบียน
│  └─ นำเข้านักเรียน
│
├─ วิชาการ
│  ├─ ปีการศึกษา
│  ├─ ห้องเรียน
│  ├─ รายวิชา
│  ├─ การเปิดรายวิชา
│  └─ การมอบหมายครูประจำวิชา
│
├─ การเรียนการสอน
│  └─ สมุดคะแนน
│
└─ การจัดการ
   └─ ผู้ใช้งาน
~~~

Every item is conditional on live access. Empty groups are omitted.

### SYSTEM context

~~~text
PP5 — ผู้ดูแลระบบ
└─ โรงเรียน
   ├─ รายการโรงเรียน
   └─ เพิ่มโรงเรียน [only when create permission exists]
~~~

Do not mix SYSTEM and SCHOOL navigation.

### Future extension slots

The shell must support later groups without redesigning the layout:

~~~text
เวลาเรียน
การประเมิน
กิจกรรมพัฒนาผู้เรียน
ผลการเรียน
รายงาน
~~~

M6 does not render those entries.

---

## Screen Inventory to Migrate

### Guest / error

~~~text
auth/login.php
errors/403.php
errors/404.php
~~~

### Dashboard

~~~text
dashboard/index.php
~~~

### SYSTEM

~~~text
system/schools/index.php
system/schools/create.php
~~~

### School users

~~~text
admin/users/index.php
admin/users/create.php
admin/users/edit.php
~~~

### Academic setup

~~~text
academic/years/index.php
academic/years/create.php
academic/years/edit.php

academic/classrooms/index.php
academic/classrooms/create.php
academic/classrooms/edit.php

academic/subjects/index.php
academic/subjects/create.php
academic/subjects/edit.php

academic/offerings/index.php
academic/offerings/create.php
academic/offerings/edit.php

academic/teaching-assignments/index.php
~~~

### Students and enrollment

~~~text
students/index.php
students/create.php
students/edit.php
students/show.php

academic/enrollments/index.php
academic/enrollments/create.php
academic/enrollments/edit.php

academic/student-import/index.php
academic/student-import/preview.php
~~~

### Gradebook

~~~text
gradebook/view.php
gradebook/setup.php
gradebook/row-summary.php        fragment: preserve fragment contract
gradebook/score-cell.php         fragment: preserve fragment contract
gradebook/score-error.php        fragment: preserve fragment contract
~~~

Add a gradebook landing page under the gradebook view namespace.

---

## Planned Routes

Existing routes remain unless explicitly noted.

New read-only route:

~~~text
GET /gradebooks
context: SCHOOL
generic permission middleware: none
authorization: GradebookReadService filters each offering using live GRADEBOOK_VIEW resource authorization
~~~

Existing Gradebook routes and HTMX score endpoint remain unchanged.

No other new business route is required for M6.

---

## File Map

### New foundation files

Recommended:

~~~text
htdocs/assets/app.css
htdocs/assets/app.js

htdocs/assets/vendor/bootstrap-5.3.x.min.css
htdocs/assets/vendor/bootstrap-LICENSE.txt

htdocs/views/layouts/app.php
htdocs/views/layouts/guest.php
htdocs/views/layouts/error.php

htdocs/views/components/app-sidebar.php
htdocs/views/components/app-topbar.php
htdocs/views/components/page-header.php
htdocs/views/components/breadcrumbs.php
htdocs/views/components/status-badge.php
htdocs/views/components/alert.php
htdocs/views/components/empty-state.php

htdocs/app/Services/AppUiContextService.php
~~~

Names may be adjusted during implementation if the existing architecture suggests a simpler shape, but responsibilities must remain separated.

### Likely modified foundation files

~~~text
htdocs/app/Support/View.php
htdocs/app/Application.php
htdocs/routes/web.php
htdocs/app/Controllers/DashboardController.php
htdocs/app/Controllers/GradebookController.php
~~~

### Modified full-page views

All full-page files listed in Screen Inventory.

### Test additions

Recommended:

~~~text
tests/Feature/UiLayoutTest.php
tests/Feature/UiNavigationTest.php
tests/Feature/UiAccessibilityContractTest.php
tests/Feature/UiResponsiveMarkupTest.php
tests/Feature/GradebookLandingHttpTest.php
~~~

Existing HTTP/isolation/gradebook tests should be updated only when their assertions intentionally depend on old markup. Security/business assertions remain.

---

# Task 1: Add Full-Page Rendering Foundation

**Objective:** Introduce shared layout rendering without changing visible business behavior yet.

## RED tests

Create tests proving:

1. View::render still returns raw fragments without doctype/app shell.
2. New page rendering API returns exactly one doctype, html, head, body, and main content region.
3. Dynamic page title is escaped.
4. Dynamic layout values are escaped.
5. Missing content template/layout fails safely through existing exception handling.
6. Gradebook score-cell/row-summary fragment tests continue to see fragment-only markup.
7. No page rendering helper queries the database.
8. Existing 403/404 safe-content assertions remain green.

## Implementation

Extend App\Support\View with an explicit full-page render path.

A safe implementation pattern:

~~~text
render content template into string
→ render chosen layout with content + page context
→ return complete document
~~~

Avoid hidden global state.

Do not automatically wrap View::render calls.

Create initial app/guest/error layouts with minimal markup sufficient for tests; visual styling belongs to Task 2.

Layout must provide extension points for:
- title;
- body class;
- head assets;
- page-specific scripts;
- main content.

## Verification

~~~sh
htdocs/vendor/bin/phpunit tests/Feature/UiLayoutTest.php
htdocs/vendor/bin/phpunit tests/Feature/GradebookScoreUxTest.php
htdocs/vendor/bin/phpunit
git diff --check
~~~

Commit suggestion:

~~~text
feat: add shared page rendering foundation
~~~

---

# Task 2: Add Local UI Assets and PP5 Design Tokens

**Objective:** Establish deterministic styling and minimal shell JavaScript with no runtime CDN/build dependency.

## RED tests

Tests should assert:

1. Local Bootstrap-compatible CSS path exists.
2. Vendor license exists.
3. app.css exists and is loaded by full-page layouts.
4. app.js exists and is loaded only where intended.
5. No http:// or https:// stylesheet/script dependency appears in core layouts.
6. Core CSS defines semantic focus, status, surface, text, border, and layout tokens.
7. Full pages include viewport metadata.
8. No migrated full page depends on inline style for structural layout after its task is complete.

## Implementation

Vendor a pinned Bootstrap 5.3-compatible minified CSS file and license.

Create app.css with:
- reset/Bootstrap override layer only where needed;
- semantic tokens;
- typography;
- body/surface/background;
- app shell;
- sidebar/topbar;
- skip link;
- page header/breadcrumb;
- buttons/forms;
- alerts;
- badges;
- tables;
- empty state;
- responsive utilities specific to PP5;
- Gradebook hooks without changing autosave behavior.

Create app.js for shell enhancement only:
- mobile nav open/close;
- aria-expanded synchronization;
- escape-to-close;
- focus return;
- optional data-confirm enhancement for explicitly marked high-impact forms.

No fetch/AJAX in app.js.
No business calculation in app.js.
No permission logic in app.js.

## Verification

~~~sh
php -l htdocs/app/Support/View.php
node is NOT required
use browser syntax check or existing project-approved JavaScript syntax method
htdocs/vendor/bin/phpunit tests/Feature/UiLayoutTest.php
git diff --check
~~~

Commit suggestion:

~~~text
feat: add pp5 design system assets
~~~

---

# Task 3: Add Permission-Driven Application Shell and Navigation

**Objective:** Replace page-local navigation with one live-authorized shell for SCHOOL and SYSTEM contexts.

## RED tests

Create navigation tests for at least:

### SCHOOL_ADMIN
Expected authorized groups/items based on current seed.

### ACADEMIC_ADMIN
Academic/student/gradebook management items as permitted, without school-user links unless permission exists.

### SUBJECT_TEACHER
Only accessible Gradebook offering navigation/landing behavior; no admin links merely because the role name is SUBJECT_TEACHER.

### EXECUTIVE
Read-only gradebook access when seeded; no score/component management action.

### VIEWER / HOMEROOM_TEACHER baseline
No unauthorized links.

### Direct grant/revocation
Grant an existing permission to a normally unauthorized role:
- navigation appears immediately;
- remove grant:
- navigation disappears immediately;
- backend route result changes consistently.

### Scope revocation
For subject teacher:
- accessible gradebook appears when live offering scope is active;
- disappears after scope revocation without new login;
- foreign-school offering never appears.

### Forged browser authority
Query/post values claiming another school/role do not alter shell identity/navigation.

### SYSTEM context
School-context navigation never leaks into SYSTEM pages.

## Implementation

Add AppUiContextService or equivalent server-side presentation service.

Responsibilities:
- build safe current user/school display context;
- determine authorized nav items through AuthorizationService;
- determine whether gradebook landing should appear through GradebookReadService;
- mark current nav key;
- provide logout CSRF token;
- never mutate data.

Do not duplicate permission logic in view templates.

Update Application dependency wiring.

Migrate a small representative page first to prove the shell:
- dashboard;
- system school index;
- one academic list.

Navigation HTML requirements:
- nav landmark;
- section labels;
- aria-current;
- no empty sections;
- no dead links;
- mobile toggle semantics;
- POST logout form with valid CSRF.

## Verification

~~~sh
htdocs/vendor/bin/phpunit tests/Feature/UiNavigationTest.php
htdocs/vendor/bin/phpunit tests/Feature/DashboardAccessTest.php
htdocs/vendor/bin/phpunit tests/Feature/AuthorizationTest.php
htdocs/vendor/bin/phpunit tests/Feature/ScopedGradebookAuthorizationTest.php
htdocs/vendor/bin/phpunit
git diff --check
~~~

Commit suggestion:

~~~text
feat: add permission driven application shell
~~~

---

# Task 4: Redesign Login, Errors, Dashboard, and Gradebook Landing

**Objective:** Establish the first complete end-to-end product surfaces using the new shell.

## Login

Migrate auth/login.php to guest layout.

Requirements:
- one H1;
- username/password labels;
- autocomplete username/current-password;
- generic error alert;
- no school selector;
- no role selector;
- submit button clear and keyboard accessible;
- no remote assets.

## Error pages

Migrate 403/404 to error layout.

Preserve:
- no leaked school/resource existence;
- no echoed hostile path;
- no SQLSTATE;
- no stack trace;
- no filesystem/config path.

Provide safe navigation:
- login or dashboard/home as context allows without exposing hidden data.

## Dashboard

Redesign dashboard into:

~~~text
school/user context
page title + concise welcome
quick work areas based on live permission
accessible gradebooks summary
administrative/academic entry points
meaningful empty states
~~~

Do not add unbacked counts.

Do not show a card merely because a role name suggests access.

## Gradebook landing

Add GET /gradebooks and gradebook/index.php.

List accessible offerings with:
- academic year;
- classroom;
- subject;
- term;
- year/offering status;
- clear "เปิดสมุดคะแนน" action.

For users with zero accessible offerings show an empty state, not a 403, because the landing itself is a filtered resource view.

Direct access to an unauthorized offering keeps the existing non-enumerating denial contract.

## Verification

~~~sh
htdocs/vendor/bin/phpunit tests/Feature/AuthenticationTest.php
htdocs/vendor/bin/phpunit tests/Feature/DashboardAccessTest.php
htdocs/vendor/bin/phpunit tests/Feature/GradebookLandingHttpTest.php
htdocs/vendor/bin/phpunit tests/Feature/GradebookReadHttpTest.php
htdocs/vendor/bin/phpunit tests/Feature/ScopedGradebookAuthorizationTest.php
htdocs/vendor/bin/phpunit
git diff --check
~~~

Commit suggestion:

~~~text
feat: redesign app entry and dashboard surfaces
~~~

---

# Task 5: Migrate SYSTEM, User Administration, and Academic Setup Screens

**Objective:** Move the administration/academic foundation to consistent patterns without changing business operations.

## SYSTEM schools

Migrate:
- system/schools/index.php
- system/schools/create.php

Use:
- page header;
- action button only with correct permission;
- status badge;
- clear status-action wording;
- safe confirmation enhancement for high-impact transitions.

## School users

Migrate:
- admin/users/index.php
- admin/users/create.php
- admin/users/edit.php

Requirements:
- separate profile, membership, roles, and password-reset sections visually;
- dangerous/sensitive actions visually distinct;
- passwords never re-rendered;
- role labels informational only; backend remains permission authority;
- current permission behavior unchanged.

## Academic year

Migrate index/create/edit.

Make lifecycle visible:
- ร่าง;
- กำลังใช้งาน;
- ปิดปีแล้ว.

CLOSED read-only/mutation rules must remain backend-enforced.

## Classrooms / Subjects / Offerings

Migrate list/create/edit.

Use consistent:
- filters;
- status;
- create/edit page headers;
- form actions;
- breadcrumbs;
- table actions.

Offering list should make links to Gradebook setup/view understandable without making unauthorized actions visible.

## Teaching assignments

Migrate index.

Keep year filtering and history.
Make ACTIVE/INACTIVE assignment state understandable.
Do not collapse permission scope semantics into role-name assumptions.

## RED/regression focus

Existing tests must continue proving:
- permission gates;
- tenant isolation;
- status/lifecycle;
- CSRF;
- escaped hostile labels;
- no unauthorized navigation.

Add structural UI assertions rather than deleting security assertions just because markup changes.

## Verification

Run focused test families for:
- SystemSchool;
- SchoolUser;
- AcademicYear;
- Classroom;
- Subject;
- SubjectOffering;
- TeachingAssignment;
- Dashboard navigation.

Then full PHPUnit and diff check.

Commit suggestion:

~~~text
feat: migrate administration and academic ui
~~~

---

# Task 6: Migrate Student, Enrollment, and Import UX

**Objective:** Make the student workflows coherent and fast while preserving M4 data/PII guarantees.

## Students

Migrate:
- students/index.php
- students/create.php
- students/edit.php
- students/show.php

List page:
- consistent search/filter bar;
- clear create action if authorized;
- status badges;
- compact table;
- empty state.

Detail page:
- identity summary;
- current status;
- yearly enrollment/history timeline or clearly grouped history;
- actions conditional on permission;
- no national ID leakage beyond already-approved surfaces.

Create/edit:
- grouped identity fields;
- clear required fields;
- safe validation alert;
- primary/secondary actions.

## Enrollment

Migrate:
- academic/enrollments/index.php
- create.php
- edit.php

Index:
- filters for year/grade/classroom/status/search;
- selected filters remain visible;
- responsive table;
- state labels in Thai.

Edit:
- distinguish enrollment status from classroom placement;
- make place/move/unassign actions understandable;
- show history/read-only state;
- terminal status actions visibly high-impact.

Do not merge placement and enrollment into one persisted concept.

## Student import

Migrate:
- academic/student-import/index.php
- preview.php

Use step indicator:
1. อัปโหลด
2. ตรวจสอบ
3. ยืนยัน

Preview:
- row decision/action badges;
- error summary;
- explicit apply/cancel actions;
- no raw national ID;
- warn that preview is temporary without inventing new expiry behavior;
- keep all-NOOP/apply rules unchanged.

No JavaScript parser becomes import authority.

## Verification

~~~sh
htdocs/vendor/bin/phpunit tests/Feature/StudentHttpTest.php
htdocs/vendor/bin/phpunit tests/Feature/StudentAdministrationTest.php
htdocs/vendor/bin/phpunit tests/Feature/StudentIsolationTest.php
htdocs/vendor/bin/phpunit tests/Feature/EnrollmentHttpTest.php
htdocs/vendor/bin/phpunit tests/Feature/EnrollmentAdministrationTest.php
htdocs/vendor/bin/phpunit tests/Feature/StudentImportHttpTest.php
htdocs/vendor/bin/phpunit tests/Feature/StudentImportTest.php
htdocs/vendor/bin/phpunit
git diff --check
~~~

Commit suggestion:

~~~text
feat: redesign student enrollment and import ui
~~~

---

# Task 7: Redesign Gradebook Without Changing Its Save Contract

**Objective:** Make Milestone 5 Gradebook feel like a production data-entry tool while preserving every write/read invariant.

## RED tests

Before changing markup, lock the existing interaction contract:

- HTMX loaded locally and only when canScore is true.
- gradebook.js loaded locally and only when canScore is true.
- every editable cell posts to the same offering/component/enrollment endpoint.
- CSRF source remains available.
- HTTP 200 plus X-Gradebook-Saved: 1 is required for UI success.
- failed responses do not swap success fragment.
- user-typed invalid value remains available for correction.
- Enter moves to next row same component.
- Tab/Shift+Tab stay native.
- arrow keys stay native.
- read-only users have no hx-post score inputs.
- historical rows have no editable score inputs.
- server renders totals/completeness.
- no client-side grade/total calculation.
- blank and 0.00 remain distinguishable.

## Page redesign

Top area:
- page header "สมุดคะแนน";
- compact offering metadata;
- current read/edit state;
- link to setup only when component-management authorization allows it;
- concise keyboard/save guidance.

Table:
- horizontal scroll;
- sticky header;
- sticky student identity columns where browser support permits;
- compact numeric inputs;
- component header includes name + maximum;
- historical rows include explicit "ประวัติ — อ่านอย่างเดียว";
- summary columns separated visually;
- save state near each editable cell without creating excessive visual noise.

Accessibility:
- headers attribute/scope relationships remain understandable;
- score input accessible name includes student/component context where practical;
- save status is announced appropriately;
- focus state remains obvious inside dense grid.

## Component setup redesign

Keep existing endpoints.

Present:
- offering summary;
- add-component form;
- component list;
- edit metadata;
- activate/deactivate.

Make immutable max-score-after-history rule understandable without moving enforcement client-side.

## JavaScript

gradebook.js changes only when required for markup adaptation/accessibility.

Do not rewrite a proven save loop merely for style.

## Verification

~~~sh
htdocs/vendor/bin/phpunit tests/Feature/GradebookReadHttpTest.php
htdocs/vendor/bin/phpunit tests/Feature/GradebookReadServiceTest.php
htdocs/vendor/bin/phpunit tests/Feature/GradebookScoreHttpTest.php
htdocs/vendor/bin/phpunit tests/Feature/GradebookScoreServiceTest.php
htdocs/vendor/bin/phpunit tests/Feature/GradebookScoreRevalidationTest.php
htdocs/vendor/bin/phpunit tests/Feature/GradebookScoreIsolationTest.php
htdocs/vendor/bin/phpunit tests/Feature/GradebookScoreUxTest.php
htdocs/vendor/bin/phpunit tests/Feature/GradebookComponentHttpTest.php
htdocs/vendor/bin/phpunit tests/Feature/GradebookComponentServiceTest.php
htdocs/vendor/bin/phpunit
git diff --check
~~~

Commit suggestion:

~~~text
feat: refine gradebook production ux
~~~

---

# Task 8: Accessibility, Responsive, and Cross-Screen Consistency Hardening

**Objective:** Treat the whole M1–M5 UI as one product and catch defects introduced by migration.

## Automated contract checks

Add/expand tests for:

### Document structure
- exactly one main landmark;
- valid lang="th";
- one primary H1 per full page;
- no nested full HTML document from partials;
- viewport present;
- local core assets.

### Navigation
- skip link target exists;
- aria-current on active route;
- mobile toggle has accessible name and aria-expanded;
- hidden unauthorized routes are absent;
- logout remains POST + CSRF.

### Forms
- meaningful input/select/textarea controls have labels;
- required controls expose required semantics;
- destructive forms use explicit action wording;
- no password value echoed.

### Alerts/status
- error alerts use role=alert or equivalent;
- autosave uses status semantics;
- badge text exists in addition to color.

### Tables
- header cells use scope where practical;
- responsive wrapper on designated wide tables;
- empty-state row/page exists for no results.

### Assets/security
- no core CDN;
- no inline event handler such as onclick in migrated screens;
- no javascript: URLs;
- hostile fixture strings remain escaped;
- no SQL/paths/session/password hashes leak in errors.

## Responsive manual matrix

Test at minimum:

~~~text
1440 × 900   desktop
1024 × 768   small laptop/tablet landscape
768 × 1024   tablet portrait
390 × 844    phone
~~~

Pages to inspect at every size:
- login;
- dashboard;
- one system/admin table;
- academic offering list/form;
- student list/detail;
- enrollment edit;
- import preview;
- teaching assignment;
- gradebook;
- gradebook setup;
- 403/404.

Acceptance:
- no inaccessible navigation;
- no horizontal page overflow except intentional table/grid regions;
- forms do not clip;
- buttons do not overlap;
- focus remains visible;
- table scrolling does not hide the only path to an action;
- Gradebook remains usable without compressing columns illegibly.

## Keyboard manual matrix

Using keyboard only:
- login;
- open/close mobile nav;
- traverse sidebar;
- use filter form;
- create/edit form;
- logout;
- Gradebook Enter/Tab/Shift+Tab;
- focus remains visible;
- no keyboard trap.

## Reduced motion

Do not require animation.
If transitions are added, respect prefers-reduced-motion.

## Verification

Run all UI contract tests, all changed-module tests, then full PHPUnit.

Commit suggestion:

~~~text
test: harden responsive and accessible ui contracts
~~~

---

# Task 9: Milestone 6 Full Verification, MAMP Browser Smoke, and Documentation

**Objective:** Prove M6 is a presentation refactor that did not weaken the verified M1–M5 system.

## Clean baseline checks

From project root:

~~~sh
git status
git branch --show-current
git log -1 --oneline --decorate
~~~

Expected implementation branch:
milestone/6-ui-ux-foundation

Before final PR, branch must be current with the approved main baseline according to the project's normal merge/review workflow.

## Automated verification

Run:

~~~sh
htdocs/vendor/bin/phpunit
find . -path './htdocs/vendor' -prune -o -name '*.php' -type f -exec php -l {} \;
git diff --check
~~~

Run the project's approved first-party JavaScript syntax check for:
- htdocs/assets/app.js
- htdocs/assets/gradebook.js
- browser helper scripts, if changed.

No new database migration/seed should appear. If one exists, stop and re-evaluate scope.

## Security regression focus

Explicitly rerun/check:
- authentication;
- authorization;
- school isolation;
- scoped Gradebook authorization;
- CSRF;
- hostile display-name/school/subject/classroom/student strings;
- forged browser authority;
- closed-year mutation rejection;
- score transaction/audit rollback;
- NULL vs 0;
- historical roster read-only behavior;
- student import PII protection.

## Real MAMP smoke

Use safe temporary fixtures and clean them fully.

Smoke paths by persona:

### SYSTEM_ADMIN
- login;
- system school list;
- create page visibility;
- status action visibility according to permission;
- no SCHOOL navigation leakage.

### SCHOOL_ADMIN
- dashboard shell;
- users;
- academic setup;
- students;
- enrollments;
- import;
- teaching assignments;
- gradebooks;
- logout.

### ACADEMIC_ADMIN
- no unauthorized system/user controls;
- academic/student/gradebook actions according to actual permissions.

### SUBJECT_TEACHER
- dashboard;
- only scoped Gradebook offering(s);
- enter score;
- see save state;
- revoke scope during same session;
- access disappears immediately.

### EXECUTIVE
- gradebook read-only;
- no editable score fields;
- no component-management actions without permission.

### VIEWER / minimal user
- no unauthorized navigation;
- direct forbidden routes remain forbidden.

## Browser behavior smoke

Verify:
- shell renders with local CSS;
- no external font/CSS/JS network requirement;
- mobile menu;
- skip link;
- focus visibility;
- filter/forms;
- tables;
- Gradebook scroll/sticky behavior;
- autosave success and error state;
- 403/404 presentation;
- logout POST.

## Cleanup

Delete temporary:
- users;
- schools;
- memberships;
- role assignments;
- scopes;
- academic fixtures;
- students/enrollments/placements;
- components/scores;
- audit rows;
- cookies;
- helper scripts;
- response artifacts.

Confirm no fixture marker remains.

## Documentation

Update README to describe:
- shared UI shell;
- local assets;
- navigation model;
- responsive strategy;
- Gradebook landing;
- M6 verification counts/results;
- new baseline branch instructions only while branch is active;
- post-merge README cleanup requirement.

Do not claim visual perfection. Document what was actually tested.

## Final commit suggestion

~~~text
docs: complete milestone 6 ui ux verification
~~~

Push and stop for pre-PR review. Do not create or merge PR automatically unless explicitly instructed.

---

## Milestone 6 Acceptance Checklist

~~~text
[ ] Milestones 1–5 full regression remains green
[ ] no database migration added
[ ] no seed added
[ ] no business permission code added solely for UI
[ ] full pages use shared layout strategy
[ ] HTMX score fragments remain fragment-only
[ ] core CSS/JS assets are local
[ ] no required CDN dependency
[ ] PP5 design tokens exist in one base stylesheet
[ ] SCHOOL and SYSTEM shells remain context-separated
[ ] navigation derives from live authorization
[ ] navigation never derives authority from browser fields
[ ] navigation does not hardcode role names as authorization
[ ] permission revoke changes navigation on next request
[ ] subject-offering scope revoke changes Gradebook access on next request
[ ] no dead links to future modules
[ ] logout remains POST + CSRF
[ ] login has no school/role chooser
[ ] dashboard is task-oriented and contains no fake metrics
[ ] GET /gradebooks shows only live-authorized offerings
[ ] zero accessible gradebooks produces safe empty state
[ ] status codes have consistent Thai presentation
[ ] unknown status renders safely
[ ] system school pages migrated
[ ] school user pages migrated
[ ] academic year/classroom/subject/offering pages migrated
[ ] teaching assignment page migrated
[ ] student pages migrated
[ ] enrollment pages migrated
[ ] student import pages use clear multi-step UX
[ ] raw national ID remains excluded from prohibited surfaces
[ ] gradebook setup migrated
[ ] gradebook main grid is responsive via intentional horizontal scroll
[ ] gradebook current/historical distinction remains explicit
[ ] gradebook blank remains NULL and 0.00 remains real zero
[ ] gradebook save contract still requires HTTP 200 + X-Gradebook-Saved: 1
[ ] gradebook totals remain server-rendered
[ ] Enter navigation behavior preserved
[ ] Tab/Shift+Tab native behavior preserved
[ ] unauthorized users never receive editable gradebook inputs
[ ] all dynamic labels remain escaped
[ ] no inline event handlers introduced
[ ] skip link exists
[ ] visible keyboard focus exists
[ ] active navigation uses aria-current
[ ] form controls have labels
[ ] error messages are not color-only
[ ] mobile navigation is keyboard accessible
[ ] 390px viewport remains usable
[ ] 768px viewport remains usable
[ ] 1024px viewport remains usable
[ ] 1440px viewport remains usable
[ ] no page-level horizontal overflow except deliberate data table/grid containers
[ ] 403/404 remain non-enumerating and safe
[ ] 500 errors remain generic
[ ] project-wide PHP syntax passes
[ ] first-party JavaScript syntax passes
[ ] git diff --check passes
[ ] real MAMP smoke passes for key personas
[ ] smoke fixtures/artifacts are fully cleaned
[ ] README documents the verified M6 baseline
~~~

---

## Expected End State

After Milestone 6:

~~~text
User logs in
↓
sees one coherent PP5 shell
↓
school/system identity is obvious
↓
navigation shows only work the user can actually access
↓
existing M1–M5 screens share one visual language
↓
forms/tables/status/errors behave consistently
↓
desktop data entry remains fast
↓
mobile/tablet shell is usable
↓
Gradebook keeps its proven transactional/autosave semantics
↓
future Attendance/Evaluation/Activities/Results modules
can reuse the same shell and UI patterns
without redesigning the application foundation again
~~~

Milestone 6 is complete when PP5 stops looking like a collection of individually implemented HTML screens and becomes one coherent application, while all verified domain/security behavior from Milestones 1–5 remains intact.

---

## Implementation Branch Setup

Do this only when implementation is approved and ready to begin.

~~~sh
git checkout main
git pull --ff-only origin main
git status
git checkout -b milestone/6-ui-ux-foundation
~~~

Before the first implementation commit, verify:

~~~sh
git branch --show-current
git log -1 --oneline --decorate
git status
~~~

Expected:
- branch is milestone/6-ui-ux-foundation;
- base includes the approved M6 planning commit;
- working tree is clean.

Do not implement M6 directly on main.
