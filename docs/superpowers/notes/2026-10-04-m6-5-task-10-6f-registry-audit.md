# Task 10.6f — Per-behavior evidence audit

This table walks all 124 accepted registry IDs against the patched adapter. `PASS` in the automated column means the mapped source file was present, carried that ID, and its relevant suite passed. `Fixture PASS` means trusted Chrome pointer/keyboard/clipboard input on the isolated fixture; golden journeys A–M passed. MAMP results are from authenticated `/gradebook/12` with real browser input and reload; only exact behaviors observed are marked PASS. `Pending` means that specific MAMP behavior was not exercised here. The immediate clear regression was reproduced at baseline with a delayed single save and fixed by serializing the requested range command.

| ID | Automated | Real browser | MAMP | Limitation |
| --- | --- | --- | --- | --- |
| GB-CLEAR-001 | PASS | Not required | Pending | Not exercised on MAMP in this attempt |
| GB-CLEAR-002 | PASS | Not required | Pending | Not exercised on MAMP in this attempt |
| GB-CLEAR-003 | PASS | Fixture PASS D; Chrome D/E PASS | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-CLEAR-004 | PASS | Fixture PASS D; Chrome D/E PASS | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-CLEAR-005 | PASS | Fixture PASS E; Chrome D/E PASS | Partial: reverse Backspace | Reverse Delete passed fixture, not MAMP |
| GB-CLEAR-006 | PASS | Fixture PASS H | Pending | Not exercised on MAMP in this attempt |
| GB-CLEAR-007 | PASS | Fixture PASS D,E,H; Chrome D/E PASS | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-CLEAR-008 | PASS | Fixture PASS D; Chrome D/E PASS | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-CLEAR-009 | PASS | Fixture PASS D; Chrome D/E PASS | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-CLEAR-010 | PASS | Fixture PASS D; Chrome D/E PASS | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-CLEAR-011 | PASS | Chrome J/L PASS; 0–500 ms delayed matrix | PASS | MAMP network was fast; fixture proves the pending branch |
| GB-CLEAR-012 | PASS | Chrome K/M PASS; 0–500 ms delayed matrix | PASS | MAMP network was fast; fixture proves the pending branch |
| GB-COPY-001 | PASS | Not required | Not required | None in mapped suite |
| GB-COPY-002 | PASS | Fixture PASS F | Not required | Isolated fixture evidence |
| GB-COPY-003 | PASS | Fixture PASS F | Not required | Isolated fixture evidence |
| GB-COPY-004 | PASS | Fixture PASS F | Not required | Isolated fixture evidence |
| GB-COPY-005 | PASS | Not required | Not required | None in mapped suite |
| GB-COPY-006 | PASS | Fixture PASS F | Not required | Isolated fixture evidence |
| GB-DIRECT-001 | PASS | Fixture PASS A | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-DIRECT-002 | PASS | Fixture PASS A,I | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-DIRECT-003 | PASS | Not required | Pending | Not exercised on MAMP in this attempt |
| GB-DIRECT-004 | PASS | Fixture PASS B,I | Pending | Not exercised on MAMP in this attempt |
| GB-DIRECT-005 | PASS | Not required | Pending | Not exercised on MAMP in this attempt |
| GB-DIRECT-006 | PASS | Not required | Pending | Not exercised on MAMP in this attempt |
| GB-DIRECT-007 | PASS | Not required | Pending | Not exercised on MAMP in this attempt |
| GB-DIRECT-008 | PASS | Not required | Pending | Not exercised on MAMP in this attempt |
| GB-DIRECT-009 | PASS | Fixture PASS A,I | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-DIRECT-010 | PASS | Fixture PASS B | Pending | Not exercised on MAMP in this attempt |
| GB-EDIT-001 | PASS | Fixture PASS C | Not required | Isolated fixture evidence |
| GB-EDIT-002 | PASS | Not required | Not required | None in mapped suite |
| GB-EDIT-003 | PASS | Not required | Not required | None in mapped suite |
| GB-EDIT-004 | PASS | Fixture PASS C | Not required | Isolated fixture evidence |
| GB-EDIT-005 | PASS | Fixture PASS C | Not required | Isolated fixture evidence |
| GB-EDIT-006 | PASS | Fixture PASS C | Not required | Isolated fixture evidence |
| GB-EDIT-007 | PASS | Not required | Not required | None in mapped suite |
| GB-EDIT-008 | PASS | Not required | Not required | None in mapped suite |
| GB-EDIT-009 | PASS | Fixture PASS C | Not required | Isolated fixture evidence |
| GB-EDIT-010 | PASS | Not required | Not required | None in mapped suite |
| GB-FILL-001 | PASS | Fixture PASS G | Not required | Isolated fixture evidence |
| GB-FILL-002 | PASS | Fixture PASS G | Not required | Isolated fixture evidence |
| GB-FILL-003 | PASS | Not required | Not required | None in mapped suite |
| GB-FILL-004 | PASS | Fixture PASS G | Not required | Isolated fixture evidence |
| GB-FILL-005 | PASS | Fixture PASS G | Not required | Isolated fixture evidence |
| GB-HIST-001 | PASS | Fixture PASS H | Not required | Isolated fixture evidence |
| GB-HIST-002 | PASS | Fixture PASS H | Not required | Isolated fixture evidence |
| GB-HIST-003 | PASS | Fixture PASS H | Not required | Isolated fixture evidence |
| GB-HIST-004 | PASS | Fixture PASS H | Not required | Isolated fixture evidence |
| GB-NAV-001 | PASS | Not required | Not required | None in mapped suite |
| GB-NAV-002 | PASS | Not required | Not required | None in mapped suite |
| GB-NAV-003 | PASS | Not required | Not required | None in mapped suite |
| GB-NAV-004 | PASS | Not required | Not required | None in mapped suite |
| GB-NAV-005 | PASS | Not required | Not required | None in mapped suite |
| GB-NAV-006 | PASS | Not required | Not required | None in mapped suite |
| GB-NAV-007 | PASS | Not required | Not required | None in mapped suite |
| GB-PASTE-001 | PASS | Not required | Not required | None in mapped suite |
| GB-PASTE-002 | PASS | Not required | Not required | None in mapped suite |
| GB-PASTE-003 | PASS | Not required | Not required | None in mapped suite |
| GB-PASTE-004 | PASS | Fixture PASS F | Not required | Isolated fixture evidence |
| GB-PASTE-005 | PASS | Not required | Not required | None in mapped suite |
| GB-PASTE-006 | PASS | Fixture PASS F | Not required | Isolated fixture evidence |
| GB-PASTE-007 | PASS | Fixture PASS F | Not required | Isolated fixture evidence |
| GB-PASTE-008 | PASS | Not required | Not required | None in mapped suite |
| GB-PASTE-009 | PASS | Not required | Not required | None in mapped suite |
| GB-PASTE-010 | PASS | Fixture PASS F | Not required | Isolated fixture evidence |
| GB-PASTE-011 | PASS | Not required | Not required | None in mapped suite |
| GB-RANGE-001 | PASS | Fixture PASS D,F,G,H; Chrome D/E PASS | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-RANGE-002 | PASS | Fixture PASS E; Chrome D/E PASS | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-RANGE-003 | PASS | Fixture PASS D; Chrome D/E PASS | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-RANGE-004 | PASS | Fixture PASS D; Chrome D/E PASS | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-RANGE-005 | PASS | Fixture PASS D; Chrome D/E PASS | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-RANGE-006 | PASS | Fixture PASS D; Chrome D/E PASS | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-RANGE-007 | PASS | Fixture PASS D; Chrome D/E PASS | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-RANGE-008 | PASS | Not required | Pending | Not exercised on MAMP in this attempt |
| GB-RANGE-101 | PASS | Not required | Pending | Not exercised on MAMP in this attempt |
| GB-RANGE-102 | PASS | Not required | Pending | Not exercised on MAMP in this attempt |
| GB-RANGE-103 | PASS | Not required | Pending | Not exercised on MAMP in this attempt |
| GB-RANGE-104 | PASS | Not required | Pending | Not exercised on MAMP in this attempt |
| GB-RANGE-105 | PASS | Not required | Pending | Not exercised on MAMP in this attempt |
| GB-RO-001 | PASS | Fixture PASS H | Not required | Isolated fixture evidence |
| GB-RO-002 | PASS | Fixture PASS H | Not required | Isolated fixture evidence |
| GB-RO-003 | PASS | Fixture PASS H | Not required | Isolated fixture evidence |
| GB-RO-004 | PASS | Fixture PASS H | Not required | Isolated fixture evidence |
| GB-RO-005 | PASS | Fixture PASS H | Not required | Isolated fixture evidence |
| GB-RO-006 | PASS | Fixture PASS H | Not required | Isolated fixture evidence |
| GB-RUNTIME-001 | PASS | Not required | Pending | Not exercised on MAMP in this attempt |
| GB-RUNTIME-002 | PASS | Not required | Pending | Not exercised on MAMP in this attempt |
| GB-RUNTIME-003 | PASS | Not required | Pending | Not exercised on MAMP in this attempt |
| GB-RUNTIME-004 | PASS | Not required | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-RUNTIME-005 | PASS | Not required | Pending | Not exercised on MAMP in this attempt |
| GB-RUNTIME-006 | PASS | Not required | Not required | None in mapped suite |
| GB-SCALE-001 | PASS | Not required | Not required | None in mapped suite |
| GB-SCALE-002 | PASS | Not required | Not required | None in mapped suite |
| GB-SCALE-003 | PASS | Not required | Not required | None in mapped suite |
| GB-SCALE-004 | PASS | Not required | Not required | None in mapped suite |
| GB-SCALE-005 | PASS | Not required | Not required | None in mapped suite |
| GB-SCALE-006 | PASS | Not required | Not required | None in mapped suite |
| GB-SCALE-007 | PASS | Not required | Not required | None in mapped suite |
| GB-SEL-001 | PASS | Fixture PASS A | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-SEL-002 | PASS | Fixture PASS A | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-SEL-003 | PASS | Fixture PASS H | Not required | Isolated fixture evidence |
| GB-SEL-004 | PASS | Not required | Not required | None in mapped suite |
| GB-SEL-005 | PASS | Fixture PASS H | Not required | Isolated fixture evidence |
| GB-SEL-006 | PASS | Not required | Not required | None in mapped suite |
| GB-SERVER-001 | PASS | Not required | Not required | None in mapped suite |
| GB-SERVER-002 | PASS | Not required | Not required | None in mapped suite |
| GB-SERVER-003 | PASS | Not required | Not required | None in mapped suite |
| GB-SERVER-004 | PASS | Not required | Not required | None in mapped suite |
| GB-SERVER-005 | PASS | Not required | Not required | None in mapped suite |
| GB-SERVER-006 | PASS | Not required | Not required | None in mapped suite |
| GB-SERVER-007 | PASS | Not required | Not required | None in mapped suite |
| GB-SERVER-008 | PASS | Not required | Not required | None in mapped suite |
| GB-SERVER-009 | PASS | Not required | Not required | None in mapped suite |
| GB-SERVER-010 | PASS | Not required | Not required | None in mapped suite |
| GB-SERVER-011 | PASS | Not required | Not required | None in mapped suite |
| GB-VIS-001 | PASS | Fixture PASS I | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-VIS-002 | PASS | Fixture PASS I | Pending | Not exercised on MAMP in this attempt |
| GB-VIS-003 | PASS | Fixture PASS I | PASS | Baseline pending-save defect fixed; observed behavior passes |
| GB-VIS-004 | PASS | Not required | Pending | Not exercised on MAMP in this attempt |
| GB-VIS-005 | PASS | Fixture PASS I | Pending | Not exercised on MAMP in this attempt |
| GB-VIS-006 | PASS | Fixture PASS I | Pending | Not exercised on MAMP in this attempt |
| GB-VIS-007 | PASS | Fixture PASS I | Pending | Not exercised on MAMP in this attempt |
| GB-VIS-008 | PASS | Fixture PASS I | Pending | Not exercised on MAMP in this attempt |
| GB-VIS-009 | PASS | Fixture PASS I | Pending | Not exercised on MAMP in this attempt |
| GB-VIS-010 | PASS | Fixture PASS I | Pending | Not exercised on MAMP in this attempt |

Total: **124** IDs; automated mapping PASS **124**; real-browser required **62**; MAMP if safe **50**; MAMP direct PASS **23**, partial **1**; unexplained automated failures **0**. The user-reported continuous timing failure was reproduced and fixed; product-owner manual acceptance remains separate.
