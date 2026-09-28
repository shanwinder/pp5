# M6.5 Task 8 — Transactional Multi-Cell Score Paste

## Baseline and scope

- Branch: `milestone/6-5-classroom-workspace`.
- Starting local and fetched origin SHA: `b9f8805696b02605078e4d1b768126447538041d`.
- Started with a clean tree and ahead/behind `0 0`; fetched before editing. Work began September 28 and completed September 29, 2026.
- Read architecture v1.2, M6/M6.5 plans, future assessment/reporting requirements, Tasks 1–7 notes, and actual services, repositories, controllers, routes, middleware, views, JS and fixtures.
- Task 8 only. No migration, schema change, seed, permission code, frontend dependency, formula engine or Task 9 implementation.

## Existing single-cell contract and shared implementation

`POST /hx/gradebook/{offeringId}/components/{componentId}/enrollments/{enrollmentId}/score` retains its form body, CSRF, HTTP behavior, HTML cell/OOB summaries and success condition: **HTTP 200 AND `X-Gradebook-Saved: 1`**. Its controller method and score-cell/row-summary templates are unchanged. Blur autosave continues using HTMX and its table queue.

Previously `setScore()` owned its transaction and performed context checks, target locks, normalization, mutation and audit inline. Calling it once for every pasted cell would commit partial work, so batch does **not** loop over `setScore()`.

`GradebookScoreService` now has private shared methods for context, component, enrollment/placement, live authorization, normalization and mutation/audit. Both public commands own their transaction. `mutateCell()` never begins or commits a transaction and cannot be called publicly. Single-cell lock order and error/normalization/no-op behavior remain the same. The existing permission locking query also joins the ACTIVE actor's user row, ensuring account suspension cannot pass between middleware and mutation; this closes the same race for both commands.

## Request contract

One new route:

```text
POST /hx/gradebook/{offeringId}/scores/batch
Content-Type: application/x-www-form-urlencoded
_token=<existing session CSRF token>
batch=<JSON object below>
```

Example decoded `batch`:

```json
{
  "enrollment_ids": [101, 102],
  "component_ids": [201, 202],
  "values": [["5", ""], ["0", "12.5"]]
}
```

- Row/column dimensions derive from the two nonempty ID arrays. `values` must have exactly that many rows/columns; no claimed dimensions are trusted.
- IDs must be positive JSON integers. Strings, floats, zero, repeated enrollment IDs and repeated component IDs are rejected. Duplicate rows/columns would repeat a target and are never deduplicated silently.
- Every value must be a string. Empty string means NULL; JSON null is malformed input. Arrays must be JSON arrays, not numeric-key objects. Unknown keys inside `batch` are rejected.
- Arrays describe an explicit Cartesian rectangle. The server does not use DOM indices or require browser display order as identity; it validates every concrete target. The browser maps contiguous rendered score cells to these IDs.
- School, year, actor, permissions and maxima are absent from the contract. Extra outer form authority fields are ignored; trusted context comes from the route/session/database.
- The single JSON form field fits existing `Request::post()` and CSRF conventions and avoids PHP's `max_input_vars` truncating thousands of per-cell fields.
- **Maximum: 2,000 cells** (for example 50 students × 40 score items). The JSON field is limited to **262,144 bytes** before decode; service callers also have a cumulative value-byte guard. A 2,000-cell matrix is tested. This bounds locks/audits while accommodating normal classroom work.
- Route uses Auth → SchoolContext → controller CSRF → service offering-scoped authorization; there is no generic permission gate that would exclude valid scoped teachers.

## Transaction and lock order

A batch has one BEGIN and one COMMIT. The service does the following inside it:

1. Lock active school.
2. Discover offering year tenant-safely; lock that year and require DRAFT/ACTIVE.
3. Lock offering; recheck school/year and ACTIVE status.
4. Lock each unique component in numeric component-ID order; require same offering/year and ACTIVE.
5. In numeric enrollment-ID order, lock each enrollment and its active placement; require same school/year, ACTIVE enrollment, and matching offering classroom.
6. Check and lock the live `GRADEBOOK_SCORE_ENTER` grant, membership, role/permission mapping, exact granting scope when required, and ACTIVE user.
7. Fetch safe student display names once, after all targets and authority pass. Normalize and validate **every** value against its component's locked maximum.
8. Lock every score cell in `(enrollment_id, component_id)` numeric order, including lookups for absent rows.
9. Mutate changed cells and insert their audits in that same deterministic order.
10. Commit once; return cells in request row-major order independently of lock order.

The common school parent lock matches existing score, component, offering and enrollment mutation patterns and serializes cooperating mutations within a school. It also protects absent-cell creation. Numeric child ordering avoids opposite order among overlapping batches. All targets pass before score writes begin. Query strategy is one common context/grant resolution, C component locks, R enrollment/placement pairs, one safe label query, N score locks, and at most N mutations plus N audits. It does not reload year/offering/permission/component for every cell.

Tests record executed lock parameters with reversed input order and verify sorted locks plus response order. A second real PDO connection proves permission revocation waits for the batch transaction's grant lock. A current-cell hook and interleaved single/batch commands verify that locked latest values determine no-op and audit before-values. This is not a full two-browser parallel-load benchmark. Across independent users/tabs, the later serialized write wins; no version column, conflict merge or offline queue was added.

## Authorization, isolation and lifecycle

All identifiers are locators. `data-grid-editable`, selection, coordinates, browser `canScore`, hidden fields and previously loaded pages never grant write authority.

Each request rechecks active school, actor, membership, granting role/permission/scope, year, offering, components, enrollments and active placement. A scope revoked after page load rejects the entire batch, including blank clears. CLOSED years, inactive offerings/components, historical/noncurrent students, wrong classroom/year, foreign offering/component/enrollment and missing resources fail without score/audit changes.

Unsafe or unauthorized targets use the same generic Thai denial and no location/name metadata. No foreign names, student private fields, SQL or paths are exposed. Authorized numeric errors include student display name, component name, maximum, and one-based request row/column plus supplied target IDs. Invalid clipboard text itself is not echoed.

## Scores, no-ops and audit

- Empty field `""` → NULL. A field containing only spaces is invalid, matching existing single-cell behavior.
- `0`, `0.0`, `0.00` → real `0.00`.
- Both paths reuse the original strict parser: ASCII digits, optional decimal with one or two fractional digits, existing ASCII whitespace trimming, canonical two decimal digits, maximum five significant integer digits. Leading zeros normalize away.
- Comparison with authoritative component maximum uses decimal strings; no float score arithmetic.
- Above maximum, malformed numeric, Thai digits, commas, thousands separators, currency, percentages and formula text are rejected. There is no locale guessing or formula evaluation.
- Equal canonical value → no UPDATE and no audit; affected state still returns.
- Absent score row plus NULL → no row created and no audit.
- Clearing an existing value retains its score-row identity/history and audits the change to NULL.
- Every actual change uses existing `GRADEBOOK_SCORE_CHANGED` and records offering/enrollment/component IDs, old/new score, school, actor, timestamp and validated `REMOTE_ADDR`. Forwarded headers are ignored. No parent batch audit is added.
- Repository or audit failure rolls back **all** score and audit changes. Tests inject failure before writes, on later repository/audit preparation, and after real score/audit executions. In-transaction snapshots prove pending changes existed; final snapshots equal the original database state.
- A six-target test verifies four changed cells produce four audits while two no-ops produce none.

## Response and refresh contract

Batch success requires **HTTP 200 AND `X-Gradebook-Batch-Saved: 1`**. It does not use the single-cell saved header.

Controller responses have `Content-Type: application/json; charset=UTF-8` and `Cache-Control: no-store`. Success shape:

```json
{
  "committed": true,
  "offering_id": 1,
  "row_count": 2,
  "column_count": 2,
  "targeted_count": 4,
  "changed_count": 3,
  "cells": [
    {"enrollment_id": 101, "component_id": 201, "score": "5.00", "changed": true}
  ],
  "rows": [
    {"enrollment_id": 101, "entered_score_total": "5.00", "configured_max_total": "35.50",
     "entered_component_count": 1, "active_component_count": 2, "complete": false}
  ]
}
```

The example abbreviates arrays; real success contains every target and affected row exactly once. `changed` describes the committed command; displayed scores come from the subsequent authoritative refresh, which may include a later authorized writer's result.

After commit, `GradebookReadService::getAffectedRows()` rechecks GRADEBOOK_VIEW and reuses the original Gradebook aggregation function. Repository roster and score queries accept an optional enrollment filter under their existing tenant/offering joins. Only affected rows are loaded; no teacher list or school-wide roster is refreshed. No total/completeness formula is duplicated in controller or JavaScript. Response contains no student names/roster metadata on success.

| Outcome | Contract |
| --- | --- |
| Malformed body, domain denial or pre-commit failure | 422, `committed:false`, safe `message`; authorized numeric failures may include `location`. No success marker, score or audit effects. |
| CSRF failure | 419 JSON, `committed:false`, reload guidance, no locks/writes. |
| Missing session | Existing middleware redirect to login. |
| Invalid school context/inactive session resources | Existing safe middleware 403. |
| Successful commit and refresh | 200 + dedicated marker + complete authoritative JSON. |
| Commit succeeded but authorized read/response reconstruction failed | 409, `committed:true`, reload instruction, no success marker. The entire batch remains committed. |
| Network loss/timeout/500 or malformed/unmarked success | Browser cannot establish final state; it asks for refresh and never claims rollback or success. |

A followed login HTML 200 cannot satisfy the marker/content-type/schema checks. Response parsing checks every ID, count, score type, row summary and current DOM destination before changing any value. A missing/malformed destination after a confirmed commit reports committed-but-refresh-failed; no partial DOM application. Error text is inserted with `textContent`, never `innerHTML`.

## Browser paste and autosave boundary

- Uses the native `paste` event for Ctrl+V/Cmd+V and only `text/plain`.
- Paste starts at the **focused editable current score input**, regardless of an existing range anchor. There is no implicit destination from body focus or arbitrary selected cells.
- Other inputs, setup pages, read-only Gradebooks and historical display cells do not become batch destinations. No read-only batch URL/control is rendered. IME composition does not trigger custom paste.
- Normalize CRLF and lone CR to LF. Remove exactly one final LF as the spreadsheet row terminator. Preserve all tabs, leading/interior/final fields, and additional blank rows.
- Empty plain text explicitly means a 1×1 blank field. HTML-only clipboard is unsupported. A blank row in one-column TSV is meaningful; a blank row in a wider TSV must contain enough tabs or it is ragged.
- Reject ragged rows, too many cells, too wide/tall matrices, historical spill, disabled/frozen targets, detached topology and inconsistent stable IDs. Never pad, clip or skip targets.
- Existing one-time TD topology remains valid because this task changes values only. Before send, targets are re-resolved and checked for connection and matching IDs. Server relationships remain authoritative on stale pages.
- Reject paste while **any** Gradebook single-cell phase is SAVING, including queued blur saves. Ask the teacher to wait and paste again; do not autoqueue.
- Prevent native input insertion. Batch takes ownership before fetch and freezes existing score inputs while in flight, suppressing blur requests. A dirty starting value cannot race the new command.
- A second paste while pending is refused. A 30-second abort deadline releases pending/read-only state. Page navigation and native Tab remain available.
- Values and totals are not replaced optimistically. A confirmed response updates the existing input nodes and summary spans using stable IDs, so Task 6 active identity and Task 7 TD range remain intact.
- Successful paste selects its rectangle. Pending/failed valid geometry also keeps the intended rectangle. Preflight rejection leaves the existing selection. Focus stays where the user put it; responses never move it back from a newer destination.
- One visible polite/atomic status region announces pending/success/failure. Success counts targeted cells and explicitly labels changed cells. Numeric failures mark the safe offending input `aria-invalid` and associate it with the batch message. Success/typing clears that association.
- A WeakMap records values already handled by batch. An unchanged subsequent blur does not send duplicate single-cell POST. Explicit typing removes this suppression and restores ordinary blur autosave. Following an unconfirmed/failed batch, unchanged old text is also suppressed, preventing stale automatic overwrites; explicit typing/paste is still available. Refresh is recommended before retry after an uncertain commit.
- The teacher can correct the source and paste again. There is no hidden automatic retry, persisted clipboard, paste preview or queue. Browser clipboard remains the retry source; clipboard contents are not logged.
- Copy reads the resulting visible values with NULL/zero distinction. Enter/Shift+Enter/arrows/native Tab/caret/IME behavior and focus revision remain covered by existing browser regressions.
- Semantic table, native inputs, existing horizontal scrolling, sticky headers/identity and focus outlines remain. No ARIA grid or new CSS is required.

## Verification

Results are recorded after the final checks below. Database tests use existing disposable transactional `pp5_test` fixtures, with PHP 8.3.14; first-party PHP syntax is checked with PHP 8.2.26.

Initial RED: the new service test failed with missing `setScoresBatch()`. An initial sandboxed DB attempt was denied access; tests were then run with approved local database access. During development, a test-only nth-failure counter was corrected to reset after firing, and the maximum-size fixture was corrected to supply the schema-required sort order. Existing assertions were preserved. The old UI assertion forbidding every `fetch()` was replaced with exactly one paste fetch plus no fetch in copy; its no-client-arithmetic and single-cell-header assertions remain.

| PHPUnit group (subsets of full run) | Tests | Assertions |
| --- | ---: | ---: |
| Batch service | 74 | 1,342 |
| Batch HTTP | 41 | 1,231 |
| Single-cell score service/HTTP/isolation/revalidation/UX | 224 | 5,011 |
| Gradebook read/access | 81 | 1,300 |
| Task 5 components/setup | 165 | 4,944 |
| Tasks 1–4 workspace/roster/subjects/navigation/landing | 108 | 1,374 |
| Authorization/dashboard/UI | 249 | 5,209 |
| **Full PHPUnit** | **3,056** | **69,725** |

All passed, with no skipped/incomplete tests reported. The focused batch run separately passed 115 tests / 2,573 assertions. Following the final JavaScript malformed-JSON handling adjustment, UiGradebookTest passed again: 14 tests / 750 assertions.

| Browser/syntax check | Exact result |
| --- | --- |
| Task 8 editable paste | 95 assertions, PASS |
| Task 8 read-only paste | 3 assertions, PASS |
| Task 6/7 navigation/autosave/range/copy | 110 assertions, PASS |
| Read-only range/copy | 4 assertions, PASS |
| Gradebook responsive matrix at 390/768/1024/1440 px | 44 cases / 680 checks, PASS |
| Cross-screen matrix | 123 result cases including final resize / 6,034 numeric checks, PASS |
| PHP 8.2 syntax | 216 files, 0 failures (215 under htdocs/tests/tools plus ignored local config linted separately) |
| JavaScript syntax | 13 first-party files, 0 failures |
| git diff --check | PASS |
| Complete diff, transaction and authorization review | Completed |

Cross-screen focus checks were rerun with their tab active after an initial concurrent-tab run produced focus-only failures. The final uninterrupted run passed. No test expectations were relaxed for those failures.

Native browser clipboard smoke used Cmd+V with `5\t0\n\t12.5`; all four values became `5.00`, `0.00`, NULL and `12.50`. Cmd+C returned exactly `5.00\t0.00\n\t12.50`. This used the synthetic PHP fixture; its deliberately recognizable totals test server authority, not arithmetic. Real arithmetic is covered by database-backed HTTP/read tests. The real MAMP `/gradebooks` page redirected to login; no credentials were provided.

Failure coverage includes invalid middle cells, six nth-prepare write/audit failures, four failures injected after actual SQL execution, two HTTP write/audit failures, and committed-but-refresh-failed retry/no-op behavior. Production transaction boundaries, deterministic locks, safe error metadata, CSRF, tenant joins, live grants, and no-op audit behavior were reviewed.

## File inventory

### Added (6)

- `docs/superpowers/notes/2026-09-29-m6-5-task-8-transactional-paste.md`
- `htdocs/app/Services/GradebookBatchException.php`
- `tests/Browser/gradebook-paste.js`
- `tests/Feature/GradebookBatchScoreHttpTest.php`
- `tests/Feature/GradebookBatchScoreServiceTest.php`
- `tests/Support/GradebookBatchStatement.php`

### Modified (13)

- `htdocs/app/Application.php`
- `htdocs/app/Controllers/GradebookScoreController.php`
- `htdocs/app/Repositories/AuthorizationRepository.php`
- `htdocs/app/Repositories/GradebookRepository.php`
- `htdocs/app/Repositories/StudentEnrollmentRepository.php`
- `htdocs/app/Services/GradebookReadService.php`
- `htdocs/app/Services/GradebookScoreService.php`
- `htdocs/assets/gradebook.js`
- `htdocs/routes/web.php`
- `htdocs/views/gradebook/view.php`
- `tests/Browser/gradebook-autosave.php`
- `tests/Feature/UiGradebookTest.php`
- `tests/Support/GradebookComponentFixtures.php`

## Known limits and Task 9 handoff

- Authenticated real-MAMP smoke was not performed; no authenticated session or credentials were available in this task. Synthetic browser fixtures and database-backed service/HTTP tests are separate evidence.
- Automated plain TSV tests and native browser clipboard checks were performed. External Excel/Google Sheets paste was not manually verified.
- No manual screen-reader certification or multi-browser compatibility certification is claimed.
- No offline editing, real-time presence, optimistic score versioning, automatic conflict resolution or formula/locale conversion.
- Existing school-wide mutation serialization favors correctness and may constrain concurrent throughput; no load benchmark is claimed.
- Task 7's range-selection limits (no touch range drag, no drag autoscroll) remain.
- **Task 9 is deferred:** no scalar fill, fill down/right, repeated-entry command, drag-fill, Delete-to-clear range or separate multi-cell clear button. Only explicit blank fields inside a pasted matrix clear scores.
- Future work must keep batch as one validated transaction, preserve both success headers, coordinate single-cell pending state, and honor committed/unknown response outcomes.
