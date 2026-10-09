# PP5 repository instructions for coding agents

These instructions apply to the entire repository. The highest-risk area is the Gradebook spreadsheet. **Do not treat current implementation behavior or a green test report as permission to change an accepted Gradebook interaction.**

## Authoritative Gradebook rules

Before touching the Gradebook or a shared file capable of changing its layout, focus, key events or navigation, read in this order:

1. The product owner's explicitly accepted interaction decisions.
2. `docs/superpowers/specs/2026-10-02-pp5-gradebook-behavior-registry.json` (124 accepted behavior IDs).
3. `docs/superpowers/specs/2026-10-02-pp5-gradebook-spreadsheet-interaction-contract.md`.
4. `docs/superpowers/specs/2026-10-10-pp5-gradebook-interaction-lock.md` (Thai working reference; summarizes, never overrides the first three).
5. Golden Journeys A–M and their mapped focused tests.

**Default is LOCKED.** Do not quietly change existing Gradebook interaction, test expectations, registry IDs or mappings, saved-score semantics, renderer version, or score service rules. A behavior change requires an explicit product-owner decision, then a single reviewed change updating the contract, registry mapping, golden/test evidence and change history together. Do not delete or loosen tests to claim a pass. If the instruction is unclear or a rule conflicts, STOP and ask for the decision.

## Non-negotiable editor distinction

- Single click: select/focus a score; plain arrow keys navigate score cells outside the editor.
- Typing a printable character on a selected writable cell: **direct entry** replaces the old value. ArrowLeft/ArrowRight/ArrowUp/ArrowDown commit and move to a writable score cell. Do not cross into identity/summary cells.
- Double click, Enter or F2 on a writable score: **explicit edit** opens the existing value. ArrowLeft/ArrowRight move the text **caret inside the same input**, without a save, focus loss or cell change. ArrowUp/ArrowDown, Enter and Tab commit and navigate according to the existing contract.
- Escape in an editor cancels with NO request and returns focus to the score cell. Escape outside the editor clears the selected range, not the score.
- Editor copy/paste/delete are native text operations. Outside-editor range copy/paste/clear/fill retain the accepted atomic, rectangular, score-only semantics.
- Historical/read-only scores remain selectable, navigable and copyable but not writable.

Do not mistake caret movement for score-cell navigation. When a possible defect is reported, reproduce with actual pointer and keyboard actions and record `document.activeElement`, same input identity, caret indices, active cell and network requests. An isolated fixture PASS cannot replace authenticated MAMP evidence; distinguish both and report blocked coverage honestly. Do not ship a speculative change when the defect is unconfirmed.

## Gradebook release gate

For ANY change affecting Gradebook JS, Tabulator, CSS, views, DOM structure, shared keyboard/focus handling, layout, loaded assets or relevant tests:

- Validate the 124-ID behavior registry and unchanged approved mappings.
- Run Golden Journeys A–M with trusted real-browser pointer/keyboard/clipboard input.
- Run all relevant Gradebook focused suites: direct-entry, explicit edit, selection, range, clipboard, errors, races, parity, visual stability, layout, workload and read-only/historical modes.
- Run focused and full PHPUnit, syntax checks and `git diff --check`.
- Verify authenticated MAMP without modifying real scores; use isolated disposable fixtures for writes.
- Distinguish registry validation, synthetic browser checks, trusted browser checks and authenticated MAMP proof. Any required blocked/failing gate is NOT a pass.
- Check the real browser's caret/focus/network state, not merely `event.defaultPrevented` or presence of an input.

For an unrelated task with zero plausible Gradebook effect, scope tests proportionately; document why. Shared stylesheet/layout changes are presumed to have potential impact until proven otherwise.

## Other repository safeguards

Honor authenticated session tenant context, live permissions, CSRF, server-authoritative validation/audit and lifecycle. No new schema, permissions, endpoints or frameworks under a presentation-only task. Do not use live student/school data as disposable test fixtures. One task, one reviewed commit and only the explicitly named milestone branch when requested; no automatic next task/PR/merge.

**Lock meaning:** these are binding review/instruction rules for coding agents, backed by tests; they are not GitHub branch protection or an immutable technical lock. Never claim the repository is mechanically protected unless a separate enforced CI/branch rule actually exists.
