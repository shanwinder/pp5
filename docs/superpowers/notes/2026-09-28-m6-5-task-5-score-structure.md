# M6.5 Task 5 — In-Context Score Structure UX

## Baseline and boundary

- Branch `milestone/6-5-classroom-workspace`, clean and synchronized with origin at `1cd4f293d744464a9622754b7bbd4b9dccdd1bef` before edits.
- Existing model remains `subject_offerings` → `gradebook_components` → `gradebook_scores`. The canonical `GET /gradebook/{offeringId}/setup` and existing component POST routes remain.
- `GradebookComponentService` continues to own transactions, school/year/offering locks, field validation, history checks, and the existing create/update/status audit events. No migration, column, seed, permission, grade calculation, or Gradebook cell behavior was changed.

## Teacher workflow

- Setup presents **การเก็บคะแนน**, **รายการคะแนน**, **หัวข้อคะแนน**, **เพิ่มช่องคะแนน**, and **คะแนนเต็ม**. It derives subject, classroom, year, and term from the offering, shows the classroom workspace shell when that workspace is already accessible, and links back to classroom subjects when authorized. It retains the generic Gradebook list return path for deep links.
- The ordinary create form posts name and maximum only. `createScoreItem` holds the authoritative offering lock, scans existing codes, allocates the first available `SCOREnnn` code, and appends using the largest existing `sort_order` plus one. At `65535`, the existing `ORDER BY sort_order, id` tie rule still places a new row last. The existing explicit code/order POST contract remains available to legacy clients and continues to use DB uniqueness and service validation. Existing codes are never rewritten by the teacher form; a secondary disclosure shows each persisted code.
- Ordinary editing posts name and maximum only. `updateScoreItem` preserves persisted code and sort position under the existing locks. Numeric sort order is absent from the normal page. Direct reorder controls were deferred because multi-row order changes would need a new atomic audit contract; no client-only ordering was introduced.
- Current and inactive items have distinct sections. Inactive history remains retrievable and may be reactivated under existing service rules. Closed years and inactive offerings are read-only. A batched non-locking history projection marks maximum inputs read-only once any score row exists, including retained NULL rows; the write service independently rejects forged max changes under lock.

## Totals and classroom overview

- The repository uses a tenant-scoped batched `SUM(CASE WHEN status='ACTIVE' THEN max_score ELSE 0 END)` and active/inactive counts for authorized offering IDs. MySQL DECIMAL aggregation stays exact; PHP/JavaScript floating point is not used. An empty offering displays `0.00` and a clear unconfigured state. Inactive items do not inflate the active maximum.
- The classroom subject surface shows active item count, active maximum, an empty state, and inactive count for each visible offering. One `IN (...) GROUP BY` component query covers all visible offerings; no student rows, roster, or score values are loaded.
- Repository architecture, schema, config, and current reporting requirements define **no authoritative required target total**. The UI therefore reports factual totals and zero active items, and does not assert a 100-point target or classify any nonzero total as invalid.

## Authorization and limits

- Existing `GRADEBOOK_COMPONENT_MANAGE` route middleware remains separate from `GRADEBOOK_VIEW` and score-entry permissions. Classroom setup links still follow live component-manage permission; direct writes still pass middleware, CSRF, tenant checks, and service validation. Forged browser classroom/year locators cannot replace offering-derived context. Foreign offering/component reads and writes retain safe denial.
- The normal form does not provide manual code editing or direct reorder controls. Legacy explicit POSTs preserve their existing backend semantics. No Task 6 active-cell, keyboard, IME, focus-restoration, or score-cell JavaScript work began.

## Verification

Focused tests cover teacher terminology, context, canonical link, generated code and append boundary, decimal totals, empty/inactive sections, retained NULL history, forged max edits, audit/no-write failures, revocation, tenant isolation, and a constant-count batched summary query. Existing component, score, authorization, workspace, and dashboard suites remain in the full regression run. Synthetic browser fixtures cover configured, empty, inactive, error, and closed-year setup across desktop/tablet/mobile viewports; they are not an authenticated real-MAMP workflow.

Verification: Task 5 focused **69 tests / 1,610 assertions**; Task 1 **23 / 496**; Task 2 **29 / 211**; Task 3 **32 / 269**; Task 4 **13 / 124**; component suites **165 / 4,944**; score suites **222 / 4,679**; Gradebook read/access **92 / 1,574**; authorization/resource-scope/dashboard/UI selection **357 / 7,640**; full PHPUnit **2,939 / 66,810**. Groups overlap and are not additive. Synthetic cross-screen browser matrix: **123 cases / 6,022 checks**, including 1440×900, 1024×768, 768×1024, and 390×844. First-party PHP and JS syntax sweeps and `git diff --check` passed. Authenticated real-MAMP workflow smoke was not performed because no authenticated test session or credentials were available.
