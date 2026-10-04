# Milestone 6.5 Addendum — Spreadsheet Grid Evaluation and Production Direction

**Date:** 2026-10-01  
**Milestone:** 6.5 — Classroom Workspace + Spreadsheet Workflow  
**Status:** accepted plan addendum; Task 10.6f reproduced the pending-single clear regression, repaired command ordering, and added the behavior lock; product-owner manual acceptance remains separate
**Production branch:** `milestone/6-5-classroom-workspace`

---

## Why this addendum exists

The original Milestone 6.5 plan defined Tasks 1–12 and intentionally used a lightweight, server-rendered + Vanilla JavaScript approach for Gradebook spreadsheet behavior.

After Tasks 1–10 were implemented and reviewed, the product owner manually evaluated the Gradebook and concluded that the behavior was functionally spreadsheet-like but the visible work surface still felt materially farther from Google Sheets / Excel Online than intended by the product direction:

> **Online first, Excel familiar.**

Rather than harden the existing renderer in Task 11 and potentially replace it immediately afterward, Milestone 6.5 was intentionally paused after Task 10 for an isolated grid-engine evaluation.

This work was **not part of the original numbered plan**, so it is recorded here to prevent future agents or maintainers from assuming that Tasks 10.5/10.5b/10.5c/10.6 were part of the original plan or accidentally dropping their conclusions.

This addendum is authoritative for the remainder of Milestone 6.5 where it explicitly amends the original plan.

---

# Plan deviation summary

Original sequence after Task 10:

~~~text
Task 10
-> Task 11 hardening
-> Task 12 final verification
~~~

Revised sequence:

~~~text
Task 10
-> Task 10.5  grid-engine evaluation spike
-> Task 10.5b normalized spreadsheet interaction evaluation
-> Task 10.5c natural spreadsheet interaction parity
-> Task 10.6 production Gradebook migration to selected grid engine
-> Task 10.6a editor visual correction
-> Task 10.6b focus correction (implemented; product-owner parity acceptance failed)
-> Task 10.6c full spreadsheet interaction audit and restoration
-> Task 10.6d direct-entry Arrow navigation correction
-> Task 10.6e direct-entry visual stability correction
-> Task 10.6f Gradebook interaction behavior registry and golden journey lock
-> Task 11 hardening of the actual production grid
-> Task 12 final verification / real MAMP smoke / documentation
~~~

Tasks 11 and 12 remain part of Milestone 6.5 and are **not cancelled**. They are deliberately postponed until the production Gradebook renderer is finalized so that hardening and final verification are not performed twice against a renderer that is about to be replaced.

## Corrective production history after Task 10.6

- **Task 10.6a:** the single-cell editor visual correction was accepted. Its single visible boundary and stable cell geometry remain requirements.
- **Task 10.6b:** focus correction was implemented and isolated fixture checks passed, but product-owner real-use spreadsheet interaction acceptance failed. The fixture counts are not product acceptance. Task 10.6b is superseded by the 10.6c audit and restoration.
- **Task 10.6c:** audits the accepted 10.5b/10.5c behavior against production and actual browser use, restores score-cell interaction parity, and strengthens regression evidence. The [pre-implementation parity audit](../notes/2026-10-02-m6-5-task-10-6c-parity-audit.md) records the gaps. The canonical behavior source is the [Gradebook spreadsheet interaction contract](../specs/2026-10-02-pp5-gradebook-spreadsheet-interaction-contract.md). Future Task 11 must use it as mandatory input. Product-owner manual acceptance remains separate from implementation and automated tests.
- **Task 10.6d:** Task 10.6c implemented full parity restoration, but product-owner acceptance still failed: click, type a score, then ArrowLeft/Right moved the caret instead of committing and moving to the next score cell. The canonical contract had treated all open editors alike. Task 10.6d clarifies direct replacement entry versus deliberate editing of an existing value; direct-entry Arrows commit and move, while explicit-edit Left/Right remain caret keys. Product-owner manual acceptance remains the final gate.
- **Task 10.6e:** The product owner confirmed that 10.6d's direct-entry Arrow navigation works, but initially withheld spreadsheet-feel acceptance because repeated entry visibly flickered or shifted. The correction targeted page and grid viewport stability, frozen-column visibility, status layout, and redundant focus/repaint while retaining server-authoritative scores and summaries. The product owner subsequently confirmed that direct-entry visual stability is comfortable.
- **Task 10.6f (actual regression fix):** After the visual-stability confirmation, the product owner reported that continuous direct entry, Arrow, pointer drag, and multi-cell Delete/Backspace could lose the clear command. A controlled delayed-single fixture reproduced the failure: the 3×3 range and host keydown were correct, but `readyForBatch()` rejected while `pendingSingles.size=1` and no batch was sent. The adapter now queues a valid range command behind the earlier single-save queue and sends exactly one batch after it settles; uncertain outcomes still block without retry. The [machine-readable behavior registry](../specs/2026-10-02-pp5-gradebook-behavior-registry.json) has 124 accepted IDs, source-to-ID validation, and real-browser golden journeys A–M. Authenticated MAMP `/gradebook/12` passed the continuous workflow and test-data restoration; product-owner manual acceptance remains separate.

---

# Accepted state before the spike

Task 10 was accepted on:

- branch: `milestone/6-5-classroom-workspace`
- SHA: `83d38139f166f7808730378d725d341d75597885`
- commit: `feat: add my teaching fast entry`

Tasks 1–10 remained accepted and their server/business/security contracts were not reopened by the spike.

In particular, the following Gradebook contracts remain valuable and must survive Task 10.6:

- offering/resource authorization;
- tenant isolation;
- live permission/scope revalidation;
- single-cell score write contract;
- Task 8 transactional batch score endpoint;
- all-or-nothing multi-cell validation;
- Task 9 explicit range fill semantics;
- `NULL != 0`;
- server-authoritative totals/summaries;
- audit records for committed changes;
- lifecycle/current/history rules;
- safe stale/uncertain response handling.

The spike evaluated only the rendering/interaction layer and did not weaken or replace these contracts.

---

# Task 10.5 — Grid engine evaluation spike

A dedicated disposable branch was created from the accepted Task 10 SHA:

- branch: `spike/m6-5-spreadsheet-grid-evaluation`
- base: `83d38139f166f7808730378d725d341d75597885`
- commit: `169b61355aaff46099113d089c8a4a81aea75b13`
- message: `spike: compare spreadsheet grid engines`

Candidates:

1. **Tabulator 6.6.0** — MIT
2. **Jspreadsheet CE 4.6.0 + jSuites 4.17.7** — MIT evaluation stack

The spike used only synthetic in-memory data and local vendored assets. It did not read/write PP5 production data, call Gradebook write endpoints, alter permissions, create migrations, or modify the production Gradebook.

The same representative fixture was used for both engines:

- normal: 35 rows, 20 score columns, 4 read-only summary columns;
- stress: 100 rows, 40 score columns;
- current and historical rows;
- blank, zero, integer, and decimal values;
- Thai labels/names;
- frozen identity/header behavior;
- range selection, keyboard, copy, paste, read-only cells, and responsive checks.

Detailed spike evidence remains on the spike branch in:

`docs/superpowers/notes/2026-10-01-m6-5-task-10-5-grid-evaluation.md`

The spike branch is evidence/prototype history only and **must not be merged wholesale into the milestone branch**.

---

# Task 10.5b — Normalized interaction evaluation

The first comparison exposed an unfair configuration difference: the original Tabulator prototype used `editTriggerEvent:'dblclick'`, while Jspreadsheet behaved more naturally for type-to-edit.

Task 10.5b normalized both candidates toward one PP5 interaction contract instead of comparing library defaults.

- commit: `7c0910f3d9ed6ebf0a58808c5e48b437be1ac8cc`
- message: `spike: normalize spreadsheet interactions`

Normalized behavior included:

- click/select, then printable key starts editing immediately;
- rapid vertical entry such as `5 -> ArrowDown -> 6 -> ArrowDown -> 7`;
- ArrowUp/ArrowDown during score editing commits and moves vertically;
- ArrowLeft/ArrowRight while editing remain caret movement;
- Enter / Shift+Enter vertical movement;
- Tab / Shift+Tab horizontal movement with a predictable escape path;
- Escape cancellation;
- pointer range selection;
- keyboard range extension where supported;
- normal Cmd/Ctrl+C and Cmd/Ctrl+V;
- historical/selectable-but-not-writable behavior;
- IME/composition guards.

After normalization, the product owner manually found both candidates broadly usable and stable.

---

# Task 10.5c — Natural spreadsheet interaction parity

The product owner then found two remaining natural-workflow differences:

- Tabulator prototype did not clear selected cells with Delete/Backspace;
- Jspreadsheet cleared only one cell of a multi-cell selected range;
- summary fixture values did not change after edits because previous spike summaries were intentionally static.

Task 10.5c normalized these behaviors.

- commit: `f329e2e8800f0e2607d7d98d638aeec56f6452f2`
- message: `spike: add natural spreadsheet range clearing`

Both candidates were brought to the same intended semantics:

- Delete/Backspace clears one selected writable score cell;
- Delete/Backspace clears an entire selected writable rectangle;
- editor Backspace/Delete remain normal caret/text editing;
- mixed ranges containing history/summary/identity cells reject the **whole** clear operation;
- no partial mutation;
- selection/focus stays useful after clear;
- blank remains different from zero;
- copy/paste remains usable after clear;
- synthetic live summary values update after edit/paste/clear;
- delayed external-style summary reconciliation was simulated to observe focus/range/scroll stability.

The live summary calculation in the spike was **only a renderer demonstration**. It must not be copied into production as business authority. Production summaries continue to come from PP5 server responses.

Undo/Redo, formulas, spreadsheet calculation engines, drag-fill formulas, multi-sheet behavior, arbitrary context-menu spreadsheet editing, and offline editing remain outside M6.5.

---

# Human evaluation conclusion

After Task 10.5c, the product owner manually tested both candidates and considered both acceptable in ordinary teacher use. Neither candidate retained a decisive user-experience advantage once the interaction contract was normalized.

Therefore the production decision was made primarily on architecture, maintainability, integration risk, and long-term fit rather than visual preference alone.

---

# Production decision — Tabulator

**Selected engine for Task 10.6: Tabulator 6.6.0.**

This is a deliberate architecture decision for the PP5 Gradebook work surface, not a general mandate to replace other tables in the application.

Primary reasons:

## 1. Stable domain-ID mapping

PP5 writes are keyed by real domain identities such as:

- `enrollment_id`
- Gradebook component ID
- offering ID

Tabulator's row-data + column-field/component model maps more naturally to stable PP5 identifiers than a primarily coordinate-based spreadsheet model. Visual row/column positions must never become business authority.

## 2. Public row/cell/range APIs

The spike was able to implement selection, editing, range inspection, reconciliation, and atomic prevalidation mainly through documented/public Tabulator components.

Jspreadsheet CE could achieve the same user-facing behavior but required more coordinate mapping and some additional DOM/document-focus coupling in the evaluated version.

## 3. Server-authoritative batch operations

PP5 needs to capture a rectangle, validate every logical target, submit one atomic command, then reconcile from the server response.

Tabulator's range/row/field model is a good fit for:

- Task 8 atomic paste;
- range clear;
- Task 9 explicit fill;
- applying authoritative cell and summary responses.

Native library clearing/fill must not bypass PP5 validation.

## 4. Virtualization / scaling margin

Both candidates were acceptable for normal classroom data, but the spike showed that Tabulator's virtualized rendering has more headroom for wide/larger matrices. One-off spike timings are diagnostic only and must not be treated as formal benchmarks, but the rendering architecture is favorable for future matrix-heavy PP5 domains.

## 5. Smaller/simpler runtime dependency surface

The evaluated Tabulator runtime used one library package and materially fewer vendored JS/CSS bytes than the evaluated Jspreadsheet CE + jSuites stack.

This is not the deciding factor by itself, but it reduces deployment and upgrade surface.

## 6. Lower version/licensing ambiguity for the evaluated path

Tabulator 6.6.0 was evaluated under a clear MIT release path with no companion runtime dependency required for the selected functionality.

Jspreadsheet CE remained viable, but the CE 4.x/5.x package/dependency transition introduced additional version/dependency questions that PP5 does not need to carry when the teacher-facing result is otherwise comparable.

## 7. UX parity was proven sufficiently for the decision

The decision does **not** mean Jspreadsheet had poor UX. The opposite is important to remember: after Tasks 10.5b and 10.5c both candidates were considered usable by the product owner.

Tabulator was selected because the remaining differentiators favored PP5's architecture after UX ceased to be decisive.

---

# Intentional amendment to the original M6.5 dependency boundary

The original M6.5 plan explicitly deferred:

> `Node/npm/grid framework dependency`

Task 10.5 research demonstrated that a specialized grid renderer is justified to meet the intended spreadsheet work-surface quality without continuing to grow a custom grid engine inside `gradebook.js`.

This addendum intentionally narrows/amends that original constraint as follows:

## Allowed in M6.5 after this addendum

- **Tabulator 6.6.0** as a local vendored browser runtime for the production Gradebook;
- required MIT license/notice files;
- PP5-owned adapter/theme code;
- temporary package-manager/download tooling only if needed to obtain and verify pinned vendor distribution files.

## Still prohibited / unchanged

- no permanent Node/npm runtime requirement;
- no npm/Node production build pipeline;
- no React/Vue/Angular/SPA rewrite;
- no external CDN runtime dependency;
- no browser-side business-authority migration;
- no framework-driven replacement of PP5 backend/service architecture;
- no arbitrary adoption of additional grid frameworks elsewhere without a separate decision.

In other words:

> **M6.5 now permits one pinned, locally vendored Tabulator runtime for Gradebook rendering/interaction, while the application's build/runtime architecture remains PHP + server-rendered HTML + Vanilla JavaScript + local assets.**

Future agents must not interpret this addendum as permission to introduce a general frontend build ecosystem.

---

# Task 10.6 — Production Gradebook Grid Migration

Task 10.6 is added to Milestone 6.5 before Task 11.

Its purpose is to replace/refactor the production Gradebook rendering/interaction layer with Tabulator while preserving all accepted Tasks 6–9 backend and authorization contracts.

Task 10.6 must be implemented cleanly on the production milestone branch. **Do not merge the spike branch.** Reuse knowledge and interaction contracts from the spike, but add only the production files/assets actually required.

## Target architecture

~~~text
Tabulator renderer / interaction engine
             |
             v
      PP5 Gradebook adapter
             |
             +--> existing single-cell score endpoint
             |
             +--> existing Task 8 transactional batch endpoint
             |
             +--> Task 9 explicit fill semantics
             |
             v
 authorization / validation / lifecycle
 transaction / audit / persistence
             |
             v
 authoritative score + row summaries
             |
             v
     Tabulator reconciliation
~~~

Tabulator is **not** allowed to become the source of truth for:

- tenancy;
- permissions;
- lifecycle;
- enrollment validity;
- component validity;
- score limits;
- `NULL`/zero semantics;
- totals/completeness;
- transaction success;
- audit.

## Required preserved behaviors

At minimum Task 10.6 must retain/prove:

- current offering/resource authorization;
- historical/read-only safety;
- type-to-edit;
- vertical rapid teacher entry;
- caret-safe Left/Right editing;
- Enter / Shift+Enter;
- Tab / Shift+Tab with no keyboard trap;
- IME guards;
- active cell / range selection;
- range copy as TSV;
- batch paste through the existing server-authoritative endpoint;
- blank -> `NULL` request semantics;
- zero remains real zero;
- range fill through existing Task 8/9 infrastructure;
- Delete/Backspace range clear with all-or-nothing target validation;
- authoritative server summary reconciliation;
- invalid typed value remains visible on rejected single-cell save;
- stale/uncertain batch response safety;
- no duplicate write races between editor blur and range commands.

## Clear/delete production direction

Multi-cell Delete/Backspace must map to the existing Task 8 transactional batch infrastructure with an explicit rectangle of blank values.

For a 1x1 selected-range Delete, Task 10.6 must choose and test one of:

1. existing single-cell endpoint with blank score; or
2. the same batch endpoint used by larger rectangle clears.

The preferred direction is consistent range-command semantics, but the final implementation choice must account for blur/autosave race behavior and existing response contracts under tests.

## Native features that should remain disabled or non-authoritative

Do not enable library features merely because Tabulator provides them.

Keep out of M6.5 unless separately justified:

- formulas;
- client-authoritative totals;
- workbook/multi-sheet concepts;
- unrestricted native range clearing that bypasses PP5 validation;
- drag-fill/series behavior that bypasses Task 9 semantics;
- spreadsheet row/column insertion/deletion;
- arbitrary user-defined formulas;
- Undo/Redo transaction model;
- offline editing;
- real-time collaborative locking/presence.

---

# Task 11 impact

Task 11 remains:

**Cross-Screen Consistency, Responsive, Accessibility, and Security Hardening**

but must now harden the **Tabulator-based production Gradebook**, not the pre-Task-10.6 custom table renderer.

Task 11 must pay particular attention to:

- virtualized grid accessibility semantics;
- visible focus independent of color;
- keyboard escape/no trap;
- Thai IME behavior;
- sticky/frozen identity/header behavior;
- internal vs document horizontal overflow;
- read-only/history semantics;
- range selection announcements/labels where feasible;
- reduced motion;
- local-only runtime assets;
- permission hiding plus direct backend denial;
- PII/error leakage;
- compatibility of legacy/admin pages;
- batch uncertainty messaging identified during Task 8/9.

Do not treat the spike's browser checks as Task 11 accessibility/security certification.

---

# Task 12 impact

Task 12 remains the full Milestone 6.5 verification gate.

Its real MAMP workflow must exercise the Tabulator production surface and prove:

- single-cell entry;
- range copy;
- multi-cell paste;
- Delete/Backspace clear;
- blank vs zero;
- authoritative summary updates;
- invalid matrix atomic rollback;
- live scope revocation;
- representative personas/resources;
- clean tenant/permission regression.

Task 12 must still update final documentation and report honestly whether screen-reader, multi-browser, and reduced-motion manual testing were actually performed.

---

# Source-of-truth / evidence hierarchy

For future agents:

1. The original M6.5 plan remains authoritative for Tasks 1–12 except where this addendum explicitly amends it.
2. This addendum is authoritative for the inserted Tasks 10.5/10.5b/10.5c/10.6 and the Tabulator decision.
3. The spike evaluation note on `spike/m6-5-spreadsheet-grid-evaluation` contains detailed prototype evidence but is **not production code**.
4. Accepted production service/security contracts from Tasks 1–10 remain authoritative unless Task 10.6 explicitly proves a compatible refactor under tests.
5. The spike branch must not be merged wholesale.

If future implementation evidence conflicts with assumptions recorded here, surface the conflict explicitly rather than silently changing the decision or dropping a requirement.

---

# Current milestone status at this addendum

Accepted production tasks:

~~~text
Task 1  ✅
Task 2  ✅
Task 3  ✅
Task 4  ✅
Task 5  ✅
Task 6  ✅
Task 7  ✅
Task 8  ✅
Task 9  ✅
Task 10 ✅
~~~

Completed evaluation-only spike:

~~~text
Task 10.5  ✅ spike only
Task 10.5b ✅ spike only
Task 10.5c ✅ spike only
Selected engine: Tabulator 6.6.0
~~~

Remaining production work:

~~~text
Task 10.6 — Production Gradebook Grid Migration to Tabulator
Task 11   — Cross-Screen / Responsive / Accessibility / Security Hardening
Task 12   — Full Verification + Real MAMP Workflow Smoke + Documentation
~~~

No Milestone 7 implementation should start before these are completed and reviewed.
