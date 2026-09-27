# M6.5 Task 4 — Subjects, Offerings, and Teaching Assignments Work Surface

## Baseline and scope

- Branch: `milestone/6-5-classroom-workspace`.
- Starting local and fetched origin SHA: `3aa09015b8f4188d833bc9d4da77cba3933bd8c2`; tree clean before edits.
- Task 4 only. No Task 5 score structure redesign, schema, migration, seed, new permission code, or combined multi-write endpoint.

## Work surface

`GET /workspaces/classrooms/{classroomId}/subjects` composes the existing school/year/classroom authority with offerings and active teaching assignments. The page shows offerings in subject-code and term order, with separate records for each term. It shows code, subject, term, status, currently eligible active teacher names where authorized, and contextual actions. An empty classroom and a closed year remain readable states.

The overview and local navigation now use one `รายวิชาและครู` destination. Gradebook-only readers can reach this page, but see only offerings from the live accessible-offering projection. Broad offering rows require `ACADEMIC_SETUP_VIEW`, `SUBJECT_OFFERING_MANAGE`, or `TEACHING_ASSIGNMENT_MANAGE`. Teacher names are projected only for a teaching-assignment manager or an offering the user can already view in Gradebook. The teacher list uses one batched query for the authorized offering IDs and the same current teacher-role/membership eligibility conditions as Gradebook. No student or score rows are read by this surface.

The new classroom route resolves its identity from the URL and authenticated SchoolContext. Browser query fields cannot replace school, year, room, subject, or permission authority. Foreign, missing, malformed, revoked, and SYSTEM access fail with safe 404 responses. Links and controls follow fresh permission and offering-scope checks on each request.

## Actions and existing domain contracts

- Opening an offering links to the existing offering-create form with authoritative year and room preselected. The original POST still checks active school/year/classroom/subject, term validity, uniqueness, and audit in one transaction.
- Assignment and stop-assignment forms submit to the existing POST routes with CSRF. The original teaching service still validates the teacher role, membership, offering/year scope, status, and audit transaction.
- Offering status uses the existing edit/status page. Score setup and Gradebook use their existing resource URLs and permission checks.
- Successful contextual offering and assignment POSTs return to this classroom only after the posted resource is re-read and matched to the revalidated classroom/year locator. Legacy POSTs retain their original redirects. A forged or stale return locator cannot choose a different resource or grant write authority.

There is no advertised combined opening-plus-assignment operation and no new write service. Term 1/term 2 uniqueness and separate records, permission-scope resource relationships, audit actions, CSRF, and direct POST authorization remain unchanged.

## Verification

- New `ClassroomSubjectsTest.php`: authorization, scoped and broad rows, term separation, live revocation, closed year, contextual prefill, existing POST and audit/rollback behavior, and cross-school safe failures.
- Focused subject/assignment/workspace regression: **154 tests / 3,351 assertions passed**.
- Full PHPUnit: **2,929 tests / 66,752 assertions passed**.
- Synthetic browser matrix: **107 cases / 5,374 checks passed** at 1440×900, 1024×768, 768×1024, and 390×844. New normal, limited, empty, and read-only subject views cover document overflow, named table scrolling, action reachability, focus visibility, local active state, and escaped long Thai/hostile labels.
- PHP syntax for **194** first-party app, route, view, feature-test, and browser-fixture files; JavaScript syntax for **12** asset/browser-test files; `git diff --check`; and final diff review completed before commit.

The browser matrix uses synthetic display data. An authenticated real-MAMP smoke session was not available. The create/edit/status pages remain full-page legacy forms; Task 4 adds their classroom entry and return path without redesigning Task 5 score setup.
