# PP5 Future Assessment + Reporting Requirements Ledger

> **Status:** Durable product requirement record. This is **not** an implementation plan for Milestone 8, Milestone 14, or any other future milestone.
>
> **Purpose:** Preserve product decisions that must survive chat/session changes and must be read before planning future assessment, competency, final-result, document-composition, or reporting work.
>
> **Rule for future planning:** Before creating or revising Milestone 8, Milestone 14, or adjacent milestones, read this document together with the then-current technical architecture, completed milestone plans, and current code. Do not treat this document as sufficient implementation detail by itself.

---

## 1. Product direction that must not be lost

PP5 Online is not intended to become a generic school administration CRUD system.

The product goal is to preserve the speed and familiarity of the existing Excel-based PP5 workflow while adding the advantages of an online multi-user system:

- centralized authoritative data;
- authentication and authorization;
- school/tenant isolation;
- resource-scoped teacher access;
- transactions;
- audit history;
- lifecycle controls;
- historical reproducibility;
- safer handling of personal data;
- concurrent use;
- server-authoritative calculations;
- consistent reports and printable records.

The working product principle is:

> **Online first, Excel familiar.**

Excel is a behavioral reference, not an architecture template. Preserve its workflow strengths; do not copy its formulas, macros, hidden cells, fixed geometry, or unrestricted editing model.

---

## 2. PP5 Online must include the full assessment family

The online system is expected to bring assessment work that was previously split across PP5 and separate workbooks into one classroom/year workspace.

The assessment area must ultimately support at least:

1. **การอ่าน คิด วิเคราะห์ และเขียน**
2. **คุณลักษณะอันพึงประสงค์**
3. **สมรรถนะสำคัญของผู้เรียน**
4. **กิจกรรมพัฒนาผู้เรียน**

`สมรรถนะสำคัญของผู้เรียน` is a first-class PP5 Online domain even though the legacy PP5 workbook did not contain it. The reason is practical: teachers have historically had to maintain it in a separate workbook, and the online system should remove that duplication.

The currently supplied competency workbook is a behavioral/reference source for future planning. Its exact calculation rules, scoring ranges, competency structure, tie behavior, and official wording must be re-validated during the future assessment milestone before implementation is locked.

Do not assume that the current workbook's formulas are automatically the final business rules.

---

## 3. Assessment input should follow a matrix/spreadsheet interaction model

Assessment entry should not be designed as one form per student or one CRUD page per criterion.

The dominant interaction model should be:

~~~text
student rows × assessment criteria columns
~~~

The same interaction family should be reusable across:

- subject score entry;
- attendance/time entry;
- reading/thinking/writing assessment;
- desirable-characteristic assessment;
- key-competency assessment;
- learner-development-activity assessment where appropriate.

Expected future interaction capabilities include:

- direct cell entry;
- keyboard navigation;
- efficient repeated entry;
- range selection where appropriate;
- copy/paste of tabular values from Excel or Google Sheets;
- bulk fill for repeated values;
- server-side validation;
- visible but unobtrusive save/error status;
- clear distinction between unentered/unknown values and real zero/failing values where the domain requires it.

JavaScript may improve the interaction, but security rules, validation, calculation, permissions, and authoritative writes stay on the server.

---

## 4. Reference content is part of the official record, not merely UI help

Legacy PP5 workbooks contain detailed reference material that is needed when the PP5 book is printed and filed. PP5 Online must preserve this function.

The system must ultimately be able to store, retrieve, display, and print the reference content associated with the assessment and subject records, including as applicable:

- curriculum standards;
- subject indicators / ตัวชี้วัด;
- assessment criteria;
- rubrics;
- performance descriptors;
- desirable characteristics;
- competency domains;
- competency indicators;
- behavior indicators / พฤติกรรมบ่งชี้;
- reading/thinking/writing criteria;
- grade/quality interpretation ranges;
- pass/fail interpretation rules;
- learner-development-activity reference content;
- other explanatory pages required to form the PP5 record/booklet.

This information has two separate presentation responsibilities:

### Operational UI

The data-entry screen should remain compact and easy to use. Long rubric/reference text may be collapsed, shown on demand, or opened in a drawer/popover/reference panel.

Example concept:

~~~text
1.1.1  ⓘ   [ score/value ]
~~~

### Printed record

The complete wording must be available to the document-composition/reporting layer when the corresponding section is included in the PP5 book.

The system must never rely on the abbreviated UI label as the only stored printable content.

---

## 5. Reference frameworks must be versioned for historical reproducibility

This is a hard future requirement.

Curriculum indicators, rubrics, behavior indicators, competency descriptions, quality thresholds, and related reference content may change over time.

A later edit to the current framework must **not** silently alter the meaning or printable content of a previous academic year's PP5 record.

Future architecture must therefore provide a versioned reference-framework concept capable of answering:

~~~text
Which exact assessment/curriculum/reference framework applied to
School X / Grade Y / Academic Year Z when these results were produced?
~~~

The solution may use immutable versions, snapshots, effective-date versions, or another rigorously defined model chosen during the appropriate future milestone.

Whatever model is selected must preserve these invariants:

- historical PP5 data can be reproduced with the reference wording that applied at that time;
- finalized/historical results cannot silently inherit later rubric edits;
- report generation does not hard-code current rubric text inside PDF templates;
- framework changes are auditable or otherwise traceable according to the future domain's risk level;
- school-specific or policy-specific variations, if allowed later, must not overwrite historical meaning.

Do not implement framework versioning casually as a mutable "settings" table.

---

## 6. Printed PP5 is a composed document set, not a screenshot of the web UI

The future reporting milestone must treat PP5 output as document composition from authoritative data.

The conceptual pipeline remains:

~~~text
Finalized/authoritative data
        +
versioned reference framework
        +
school/class/student metadata
        +
approved report configuration/signature metadata
        ↓
Report Data Service
        ↓
print-oriented HTML
        ↓
browser print and/or mPDF
~~~

Business logic must not live in the print templates.

A PP5 booklet/document set should ultimately be capable of including, where required:

- cover pages;
- school/class/year metadata;
- student roster;
- subject information;
- subject indicators/standards;
- subject score/learning-result pages;
- attendance/time-learning pages;
- reading/thinking/writing reference criteria and results;
- desirable-characteristic behavior indicators/criteria and results;
- key-competency domains, indicators/rubrics, detailed assessment and summary results;
- learner-development-activity details/results;
- overall summaries;
- completeness/finalization information where appropriate;
- signature/approval sections;
- other officially required supporting pages.

The future UI should support whole-book printing and selected-section printing when this does not weaken official-record rules.

---

## 7. Competency assessment must integrate with the same student/class context

Teachers must not maintain a second independent roster for competencies.

Competency assessment must use the same authoritative:

- student master;
- academic year;
- enrollment;
- classroom placement/history;
- school context;
- lifecycle/finalization context;

that PP5 Online already uses.

A student transfer, historical placement, closed year, permission change, or other lifecycle event must be handled consistently with the rest of the PP5 system instead of creating a second isolated data island.

The competency workspace should eventually support both:

- an easy-to-read per-competency mode; and
- a dense/fast all-criteria entry mode for experienced users,

provided both write through the same server-authoritative domain rules.

---

## 8. Completeness and finalization must include the new assessment domains

When annual-result/finalization work is eventually implemented, the readiness check must not consider only subject scores.

The future completeness model must be capable of representing whether the required records are complete for:

~~~text
students
subjects / teaching context
subject scores
attendance / learning time
reading-thinking-writing assessment
desirable characteristics
key competencies
learner-development activities
other required annual-result inputs
~~~

The UI should express this in teacher language such as "ตรวจความพร้อม" rather than exposing an internal state machine as the primary workflow.

Exact finalization rules belong to the future finalization milestone and are not locked by this document.

---

## 9. Current roadmap anchors (direction only, not plans)

The following roadmap interpretation is intentionally recorded so it is not forgotten, but detailed milestone plans must be created later from the current codebase at that time.

### Milestone 8 — future direction

Think of M8 as approximately:

> **Assessment Framework + Evaluation + Competencies**

It should not be reduced to "add a few result columns". Planning must explicitly consider:

- framework/reference-content model;
- versioning/historical reproducibility;
- reading/thinking/writing;
- desirable characteristics;
- key competencies;
- behavior indicators;
- rubrics/criteria;
- matrix-style entry;
- calculations/interpretation rules;
- permissions and classroom/resource scope;
- audit/history expectations;
- future print requirements.

This is **not** authorization to create the M8 implementation plan now.

### Milestone 14 — future direction

Think of M14 as approximately:

> **PP5 Document Composition + Reports + PP6/PDF**

Planning must explicitly consider:

- official/printable PP5 composition;
- printable reference pages;
- subject indicators;
- rubrics;
- behavior indicators;
- competency reference material and results;
- historical framework version selection;
- report data services;
- browser-print/mPDF rendering;
- document section selection where appropriate;
- signatures/approval metadata;
- finalized-data-only rules where applicable.

This is **not** authorization to create the M14 implementation plan now.

---

## 10. Open decisions intentionally deferred

Do not prematurely lock the following items. They must be researched and decided during the appropriate future milestone using the then-current regulations, school requirements, reference workbooks, and implementation state:

- the authoritative competency framework/version to support first;
- exact competency scoring formula and aggregation rule;
- handling of ties or ambiguous aggregate results;
- whether schools may customize rubrics/indicators and within what limits;
- which reference content is national/master data versus school-owned data;
- exact version/snapshot mechanics;
- assessment edit/unlock behavior after finalization;
- who may assess each domain and at what scope;
- exact required PP5 booklet ordering;
- page size, margins, font and signature requirements;
- which pages are mandatory versus optional;
- exact PP6/report relationships;
- whether any official export formats beyond print/PDF are required.

A future plan must resolve these explicitly rather than inherit accidental behavior from Excel formulas.

---

## 11. Privacy and source-workbook handling

User-supplied Excel/XLSB workbooks may contain real student data, including personally identifiable information.

Do not commit those raw workbooks to the public repository merely to preserve the requirements.

Preserve only the architectural/product decisions and sanitized structural knowledge needed for implementation.

The web application must continue to minimize exposure of national IDs and other sensitive student data. Printable documents may show only the fields required by the relevant document/report rules.

---

## 12. Relationship to Milestone 6.5

Milestone 6.5 should prepare the interaction foundation for these future domains without implementing them prematurely.

Specifically, the classroom workspace and spreadsheet/matrix interaction patterns created in M6.5 should be reusable later for:

- attendance matrices;
- reading/thinking/writing matrices;
- desirable-characteristic matrices;
- competency matrices;
- activity-result matrices;
- future summary/completeness screens.

M6.5 may expose only implemented capabilities. Do not add dead navigation or fake placeholder screens for M8/M14 domains.

---

## 13. How future agents/chats should use this file

When a future conversation begins and the user asks to continue PP5 work, inspect the repository and search for this file before planning assessment/reporting work.

Treat the requirements in this ledger as durable product intent unless a later dated, explicitly approved specification supersedes them.

If a future proposal conflicts with this ledger, surface the conflict explicitly instead of silently dropping the requirement.

In particular, do not forget these four decisions:

1. **สมรรถนะสำคัญของผู้เรียน belongs inside PP5 Online, not in a separate external workbook workflow.**
2. **ตัวชี้วัด / rubric / พฤติกรรมบ่งชี้ / เกณฑ์ต่าง ๆ are printable record content, not merely UI hints.**
3. **Reference content must be versioned so historical PP5 books remain reproducible.**
4. **Future M8/M14 plans must be created later from the then-current system; this document preserves requirements, not implementation details.**
