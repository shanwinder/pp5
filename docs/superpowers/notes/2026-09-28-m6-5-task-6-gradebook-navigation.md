# M6.5 Task 6 — Gradebook active cell and keyboard navigation

## Starting point and boundaries

Clean, synchronized `milestone/6-5-classroom-workspace` at `dc57f097c7828a8f6b1be1857ea9ed21058eaabf`. Inspected the architecture, M6 and M6.5 plans, future reporting ledger, Tasks 1–5 notes, Gradebook JS/CSS/views, score service/repository/route/authorization, and feature/browser fixtures. The existing score-cell `hx-trigger="blur"`, table `hx-sync="closest table:queue all"`, server fragment/OOB totals, and success condition HTTP 200 **and** `X-Gradebook-Saved: 1` remain authoritative.

## Interaction state machine (design before implementation)

The browser holds one logical `activeKey` (`offeringId-componentId-enrollmentId`), a focus revision, a composition flag, and a small per-key phase map. It never holds a replaced input node as the active identity. Conceptual phases are `NO_ACTIVE_CELL` when focus enters a non-score control; `ACTIVE` after score focus or a saved replacement; `EDITING` after input; `SAVING` after blur/beforeRequest; `ERROR` after any failed/unconfirmed request; and `READ_ONLY` for non-editable display cells or a temporarily frozen in-flight input. Only `ACTIVE`, `EDITING`, `SAVING`, and `ERROR` need persistent per-key values. A score input blurred to the page body may retain its active mark until the next focus enters a control; focus on a non-score control clears it. The DOM's `data-save-state` and `aria-invalid` show the current result; the logical model coordinates focus and navigation.

| Event | Transition and effect |
| --- | --- |
| Pointer/focus enters an editable score input | Set its logical key active; clear prior visual mark; no write or announcement. |
| Typing | `EDITING`; show quiet `ยังไม่บันทึก` once, retaining exact text. |
| Enter / Shift+Enter | Focus next/previous editable **current** row in the same component. At boundary, blur current input. The resulting blur is the only save trigger. |
| Tab / Shift+Tab | Native browser traversal; no interception or trap. Blur may save the prior input. |
| ArrowUp / ArrowDown | Move to previous/next editable current row in the same component; no wrap. |
| ArrowLeft / ArrowRight | Native caret/selection movement in text inputs; no horizontal cell traversal in this task. |
| Modifier or IME composition | Grid shortcut handler does nothing. `isComposing` and composition events both guard movement. |
| Blur / HTMX beforeRequest | Freeze the single attempted edit, enter `SAVING`, and rely on HTMX's existing serialized blur request. A repeated blur of the frozen node does not queue another request. |
| Confirmed HTMX swap | Accept server-normalized cell and OOB totals. Reconcile active styling by logical key. Restore focus to its replacement only if it was still focused at swap and no newer focus intent exists. |
| 422/409/419, authorization denial, 500, transport failure, or 200 without success header | Block swap, retain exact typed value, clear read-only freeze, set `ERROR` and `aria-invalid`, and show an associated error. Focusing and blurring unchanged text may retry. |
| Rapid focus moves A → B → C | Focus revision and active key advance. Older responses cannot reclaim focus; table-level HTMX queue serializes saves. |

Navigation uses an index of current-row score keys built once from the rendered table. HTMX cell replacement keeps keys stable, so no full document scan on every keydown. Targets are re-resolved from the current DOM and must still be editable inputs in current rows. Historical/read-only cells have no input and are never destinations. `scrollIntoView` uses nearest positioning without animation. There is no range, anchor, clipboard, paste, fill, new endpoint, schema change, or permission change.

## Implementation and verification

`htdocs/assets/gradebook.js` owns all delegated focus, composition, keyboard, blur, and HTMX lifecycle handling. It indexes current-row score keys once, resolves a fresh input by stable ID for each navigation target, and rejects frozen, disabled, detached, or non-current targets. An old cell's HTMX fragment cannot become the active identity. Enter and Shift+Enter move vertically; at a boundary they blur without wrapping. Up/Down move vertically and stay put at boundaries. Left/Right and native Tab traversal remain text/browser operations. Meta/Ctrl/Alt, key repeat, and IME composition do not trigger grid movement. There is no horizontal navigation or range state.

Blur alone triggers the existing per-cell HTMX POST. A capture listener marks the input read-only at the blur boundary, including time spent in `closest table:queue all`; another blur of the frozen input is suppressed. `beforeRequest` preserves that freeze. `beforeSwap` rejects any response without both HTTP 200 and `X-Gradebook-Saved: 1`, including a login page returned with 200. A confirmed response replaces the cell with server-normalized text and updates totals through the existing OOB summary. If the old input was still the intended focus at swap, a stable-key/focus-revision check permits restoration to the replacement after settle only while the user has not focused elsewhere. Responses to older cells therefore cannot steal focus from newer destinations. No score normalization or total calculation occurs in JavaScript.

On 422/409, 419, 403, 500, a missing success header, or abort, the fragment is not swapped. The exact typed value remains in an editable input with `aria-invalid=true` and associated live feedback. A later blur retries even if the value has not changed. The server's live permission and resource checks remain authoritative after page load. Empty input still saves as NULL, while `0` receives the server's `0.00` fragment. Historical rows have display text only and are never indexed as editable targets.

The view puts student identity, score-item names and maxima, matrix, and server totals ahead of teacher-assignment details. Teacher details remain in a native disclosure after the grid; component code remains secondary in each header. The existing sticky student identity and header, internal scroll region, focus outline, and read-only wording remain. The active cell adds an outline and inset stroke, so shape as well as color identifies it. Keyboard movement uses nearest scrolling with no animation; normal caret movement does not scroll the grid.

Browser fixture: **80 interaction assertions** passed, including active identity and focus restoration after replacement, rapid Enter, Shift+Enter and arrow boundaries, composition/modifier guards, serialized queue, error/retry paths, blank and zero, historical rows, stale-response focus, and duplicate request checks. A real browser Tab/Shift+Tab check moved across score inputs, out of the grid to the teacher disclosure, and backwards through the grid region to the setup link. Responsive fixture: **44 cases / 680 checks** passed at 390, 768, 1024, and 1440 px, including editable, active, error, read-only, historical, setup, empty, and no-JS states.

Focused database-backed regressions passed: Gradebook score **222 tests / 4,685 assertions**; Gradebook read and landing **52 / 1,256**; Tasks 1–4 classroom workspace, navigation, roster, and subjects **97 / 1,100**; Task 5 component setup **165 / 4,944**; authorization, workspace navigation, dashboard, and UI **215 / 3,410**. The focused Gradebook UX/UI group passed **25 / 1,159**. The full PHPUnit suite passed **2,939 tests / 66,820 assertions**. PHP syntax: **208 application/test files** clean. JavaScript syntax: **17 application/test files** clean. `git diff --check` passed.

No authenticated MAMP session or credentials were available, so an authenticated real-MAMP workflow was not performed. The isolated browser fixture and database-backed HTTP/service tests supply the behavioral evidence. There is no migration, schema/seed change, new permission, new score-write endpoint, range selection, clipboard, paste, fill, or batch write. Task 7 is explicitly deferred.
