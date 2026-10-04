# Task 10.6f — Gradebook behavior registry and immediate clear regression

**Classification:** Actual regression fix, with behavior-lock infrastructure. The production baseline was `cadb8204040972e5a90ffbf9841343470e04318b` on `milestone/6-5-classroom-workspace`. The product owner's continuous workflow failed when a single-score save remained pending. The adapter now serializes the already-issued range command after that save.

## Investigation and root cause

Ordinary forward and reverse 3×3 drag followed by Delete or Backspace passed in the isolated fixture and authenticated MAMP `/gradebook/12`. Those checks waited long enough for a fast single save to finish, so they did not test the reported continuous sequence.

The fixture was extended with a response-only 2-second delay for a single-score request and read-only diagnostics for `pendingSingles`, `editing`, `readyForBatch()` rejection, selected cells, focus, trusted host keydown, and request order. On the unchanged production adapter, native Chrome input performed `click → 5 → ArrowRight → immediate 3×3 pointer drag → Delete`. At Delete keydown, all 9 cells were selected, focus was in the grid, `pendingSingles.size=1`, `state.editing=false`, and the trusted Delete event reached the host. `readyForBatch()` rejected because of the pending single. No batch request was sent, and the page asked the user to try again. This is the reproduced UX regression; the range painter and event delivery were working.

The former `readyForBatch()` combined an active editor and an in-flight single save in one rejection branch. The command handler returned before submitting a batch. The repair accepts a valid range command while singles are pending, captures the existing `singleQueue`, marks the batch pending immediately, waits for the prior queue to settle, re-resolves writable target cells by stable enrollment/component IDs, then submits one batch. During that wait, new edits cannot overtake the queued batch. If a prior result is uncertain or permission is lost, the batch is not sent or retried. An editor still must be committed or cancelled before a range command is valid. Earlier single responses do not erase the queued-command status.

Review risks noted during the initial investigation—selection DOM-node caching, parallel pointer/mouse handlers, and missing `pointercancel` cleanup—were not causal in this trace and were not changed.

## Timing evidence

Golden journeys J and K use trusted Chrome pointer/keyboard input for `5 → ArrowRight → immediate 3×3 drag → Delete/Backspace`. With a 2-second delayed single response, both pass at Arrow-to-drag delays **0, 50, 100, 250, and 500 ms**. In every run: 9 cells were selected at clear keydown, the key reached the host, `pendingSingles.size=1`, `state.editing=false`, `readyForBatch()` rejected zero times, exactly one single and one batch were sent, the batch followed the single response, all 9 scores became blank, and focus returned to the top-left score. The focus field at the instant of keydown can be the pointer extent or anchor during a fast drag; final focus is consistently the top-left score.

Golden L and M repeat after rapid horizontal and vertical `5 → 6 → 7` entry. Each had `pendingSingles.size=3` when the clear key arrived, 9 selected cells, zero rejections, three ordered single requests, then one batch after all three responses. L uses Delete and M Backspace. The delayed-single `409` error fixture verifies that a queued clear sends **no** batch and performs **no** automatic retry when the prior result is uncertain.

Authenticated MAMP `/gradebook/12` was tested with the patched, versioned workspace asset and native Chrome pointer/keyboard actions. The writable 3×3 range was rows `9997`–`9999` and score fields `5`–`7`. Continuous `5 → ArrowRight → drag → Delete` and the Backspace variant passed at 0, 50, 100, 250, and 500 ms; rapid horizontal/vertical `5 → 6 → 7` variants also passed. Each run selected 9 cells, cleared the intended range, and restored focus to its top-left. A reload after each run confirmed server persistence. Rows `9997` and `9998` were returned to blank, and row `9999` was restored to `4.00`, `4.00`, `12.00`, blank, total `20.00`, `3 / 4`. MAMP's normal network completed too quickly to prove the pending branch; the delayed fixture provides that proof.

## Permanent behavior lock

The machine-readable [behavior registry](../specs/2026-10-02-pp5-gradebook-behavior-registry.json) contains 124 accepted IDs, including `GB-CLEAR-011` and `GB-CLEAR-012`. The [validator](../../../tests/Browser/gradebook-behavior-registry-check.php) requires unique IDs, accepted-inventory coverage, test mappings, golden-journey mappings, and pinned local vendor hashes. [Golden journeys A–M](../../../tests/Browser/gradebook-golden-journeys.js) cover direct entry, editing, pointer ranges, clear, copy/paste, fill, historical read-only behavior, visual stability, and immediate clear after pending singles. The [per-behavior audit](2026-10-04-m6-5-task-10-6f-registry-audit.md) records each ID and its evidence tier. The canonical interaction contract now states that a valid user command must be serialized when safe instead of dropped with “try again.”

## Verification

| Gate | Result |
| --- | --- |
| Registry validator | PASS, 124 accepted IDs |
| Real Chrome golden journeys | PASS A–M, including delayed timing J–M |
| Synthetic grid writable/read-only | PASS 60 / 12 checks |
| Synthetic parity writable/read-only | PASS 32 / 23 checks |
| Direct-entry and visual stability | PASS 54 / 64 checks; zero Gradebook layout shifts |
| Race/keyboard and paste limit | PASS 29 / 5 checks |
| Error scenarios | PASS all 19, including queued-clear uncertainty |
| Workload 35×20 / 100×40 | PASS 16 / 9 checks |
| Responsive Gradebook | PASS all 48 cases |
| Cross-screen | PASS 139 cases, 6,386 checks |
| Focused PHPUnit | PASS 872 tests, 19,864 assertions |
| Full PHPUnit | PASS 3,063 tests, 69,866 assertions |
| JS/PHP syntax and diff whitespace | PASS for changed first-party files |
| Authenticated MAMP workflow | PASS continuous timing matrix and rapid entry, with reload/restoration |

Product-owner manual acceptance remains separate. The normal MAMP requests were too fast to hold the single pending for 1–2 seconds, so the pending-state conclusion rests on the controlled fixture and trusted Chrome input, not an inference from MAMP's timing. Final MAMP reload loaded `/assets/gradebook.js?v=1791068088`, matching the workspace mtime, and confirmed the three test rows at their starting scores and totals.
