# M6.5 Task 4 — Subjects, Offerings, and Teaching Assignments Work Surface

## Baseline and scope

- Branch: `milestone/6-5-classroom-workspace`.
- Starting local and fetched origin SHA: `3aa09015b8f4188d833bc9d4da77cba3933bd8c2`; tree clean before edits.
- Task 4 only. No Task 5 score structure redesign, schema, migration, seed, new permission code, or combined multi-write endpoint.

## Work surface

### Existing architecture and authoritative identities

- `SubjectRepository` and `SubjectAdministrationService` own the school-level subject master (`subjects`: school, code, Thai name, status). The workspace does not create a classroom-specific subject. A user with `SUBJECT_MANAGE` can open the existing school-level create page from an open-year workspace.
- `SubjectOfferingRepository` and `SubjectOfferingAdministrationService` own each opened subject in a particular school, academic year, classroom, and term (`subject_offerings`). The actual database key is `uq_offering_identity (school_id, academic_year_id, classroom_id, subject_id, term_no)` in `20260910_001_academic_structure.sql`. The classroom and subject composite foreign keys preserve school/year relationships. Term 1 and Term 2 therefore have different offering IDs, statuses, Gradebooks, and score structures.
- `TeachingAssignmentRepository` and `TeachingAssignmentService` own the assignment through `permission_scopes`, connecting one eligible teacher role assignment to one offering. `uq_permission_scope_assignment_offering (user_role_assignment_id, subject_offering_id)` prevents duplicate pairs. The service validates teacher role/membership, offering and year lifecycle, and current school identity; it writes the assignment and audit in its existing transaction. A term's assignment does not apply to the other term.
- No annual offering, curriculum hours, credits, weights, subject type, workload, or course description is inferred. The work surface projects existing factual fields and leaves these distinct domain models intact.

`GET /workspaces/classrooms/{classroomId}/subjects` composes the existing school/year/classroom authority with offerings and active teaching assignments. The page shows offerings in subject-code and term order, with separate records for each term. It shows code, subject, term, status, currently eligible active teacher names where authorized, and contextual actions. An empty classroom and a closed year remain readable states.

The overview and local navigation now use one `รายวิชาและครู` destination. Gradebook-only readers can reach this page, but see only offerings from the live accessible-offering projection. Broad offering rows require `ACADEMIC_SETUP_VIEW`, `SUBJECT_OFFERING_MANAGE`, or `TEACHING_ASSIGNMENT_MANAGE`. A read-only academic user sees offerings without write controls; an offering manager gets only offering controls; an assignment manager gets assignment controls; `SUBJECT_MANAGE` alone does not grant classroom-wide read. `GRADEBOOK_COMPONENT_MANAGE` independently gates setup links, and `GRADEBOOK_VIEW` through the existing offering-scoped authorization gates each Gradebook link. Score entry retains its separate existing permission and POST contract.

Teacher names are projected only for a teaching-assignment manager or an offering the user can already view in Gradebook. The teacher list uses one batched query for the authorized offering IDs and the same current teacher-role/membership eligibility conditions as Gradebook. The classroom offering query is constrained to authoritative school/year/classroom; broad workspace readers obtain their Gradebook link projection from a room-scoped query. The pre-existing global Gradebook sidebar check still scans school offerings for an existence result, and a scope-only reader's workspace switcher still needs a school-wide accessible-offering projection to find other authorized rooms. Neither projection loads student or score rows.

The new classroom route resolves its identity from the URL and authenticated SchoolContext. Browser query fields cannot replace school, year, room, subject, or permission authority. Foreign, missing, malformed, revoked, and SYSTEM access fail with safe 404 responses. Links and controls follow fresh permission and offering-scope checks on each request. Revoking offering, assignment, component, or Gradebook scope access removes only its corresponding affordance on the next request; unrelated valid academic read can remain. Direct POST is guarded independently by its existing middleware/service and CSRF.

## Actions and existing domain contracts

- Opening an offering links to the existing offering-create form with authoritative year and room preselected. The original POST still checks active school/year/classroom/subject, term validity, uniqueness, and audit in one transaction. A duplicate term fails before any offering, teacher scope, or audit change.
- Assignment and stop-assignment forms submit to the existing POST routes with CSRF. The original teaching service still validates the teacher role, membership, offering/year scope, status, and audit transaction.
- Offering status uses the existing edit/status page. Score setup and Gradebook use their existing resource URLs and permission checks.
- Successful contextual offering and assignment POSTs return to this classroom only after the posted resource is re-read and matched to the revalidated classroom/year locator. Legacy POSTs retain their original redirects. A forged or stale return locator cannot choose a different resource or grant write authority.

There is no advertised combined opening-plus-assignment operation and no new write service. Term 1/term 2 uniqueness and separate records, permission-scope resource relationships, audit actions, CSRF, and direct POST authorization remain unchanged. Inactive offerings and closed-year records remain visible to authorized readers. A closed year has no workspace opening/assignment controls, and existing write services still reject closed-year mutations. Existing Gradebook history remains read-only under its own contract.

## Accessibility and boundaries

The page uses the Task 2 classroom header, switcher, local navigation and `aria-current="page"`. Subject and term are explicit table columns, with one table body per subject, visible text statuses, a named scroll region, keyboard-accessible links/buttons and descriptive action labels. The synthetic browser matrix checks focus appearance and document overflow at four viewport sizes; no screen-reader certification is claimed. No raw workbook or student PII is selected or rendered. The Gradebook score write endpoint, score component setup UX and all Task 5 spreadsheet behaviors are unchanged.

## Verification

- New `ClassroomSubjectsTest.php`: authorization, scoped and broad rows, independent permissions and live revocation, term separation, Gradebook/setup links, closed year, contextual prefill, CSRF and foreign-resource denial, existing POST and audit/rollback behavior, and cross-school safe failures.
- Task 4 focused: **12 tests / 117 assertions passed**.
- Task 1: **23 / 496**; Task 2: **29 / 211**; Task 3: **32 / 269**.
- Subject/offering/teaching: **489 / 12,423**; authorization/resource scope: **81 / 397**; Gradebook read/access: **52 / 1,256**; components/setup: **161 / 4,908**; dashboard/UI: **95 / 2,236**. All passed.
- Full PHPUnit: **2,934 tests / 66,790 assertions passed**.
- Synthetic browser matrix: **107 cases / 5,414 checks passed**, no warnings, at 1440×900, 1024×768, 768×1024, and 390×844. Normal, limited, empty, and read-only subject views cover document overflow, named table scrolling, action reachability, focus visibility, local active state, and escaped long Thai/hostile labels.
- PHP syntax for **194** first-party app, route, view, feature-test, and browser-fixture files; JavaScript syntax for **12** asset/browser-test files; `git diff --check`; and final diff review completed before commit.

The browser matrix uses synthetic display data. An authenticated real-MAMP smoke session was not available. The create/edit/status pages remain full-page legacy forms; Task 4 adds their classroom entry and return path without redesigning Task 5 score setup.
