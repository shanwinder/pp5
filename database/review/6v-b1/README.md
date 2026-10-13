# B1 DDL review package — NOT a production migration release

Authority: `docs/superpowers/notes/2026-10-13-m6-6-task-6v-product-owner-decision.md`.

These ten additive SQL steps implement the proposed S1–S10 sequence (eight new tables). They deliberately live outside `database/migrations/*.sql`: the unchanged existing runner must not discover them on the real MAMP database. No activation, bootstrap, score/component update, permission seed or trigger is included. Moving these files into the automatic migration directory requires a separately reviewed deployment decision.

## Pre-DDL review, 13 October 2026

- Actual MAMP metadata read in a READ ONLY transaction: MySQL 8.0.40, strict SQL mode; `pp5`, port 8889. No application rows read. Candidate indexes on classrooms, subject_offerings, gradebook_components and users match v1.2. The new six-column Offering unique index is absent, as expected.
- Disposable target: a freshly initialized MySQL 8.0.40 process, separate data directory/socket, dedicated port 13396. Before each test connection writes, verify `@@datadir`, `@@port`, `DATABASE()` and task ownership marker. Never use the real application configuration for migration/test writes.
- All FK tuple orders match the referenced candidates. Every new FK is RESTRICT/RESTRICT with a leading index. No component or score table alteration.
- Nullable ratio, freeze, receipt and adoption CHECK branches use explicit IS NULL/IS NOT NULL. SQL UNKNOWN must not permit a partial pair. Native JSON and enforced CHECK support are available in 8.0.40; actual negative inserts remain a verification gate.
- UUID/hash fields are ASCII binary; labels use repository utf8mb4_unicode_ci. Canonical identifier/reason validation remains a server responsibility. Actor FKs reference global users(id) only; they do not establish tenant membership or live permission.
- S3 creates governance without receipt FKs; S9 creates scoped receipt candidates; S10 finalizes both governance receipt FKs. Intermediate schemas are not complete.
- Existing runner uses filename ledger after SQL execution, without schema attestation or DDL rollback. Do not replay a completed/unledgered step: compare complete SHOW CREATE metadata, then explicitly reconcile only an exact step. A mismatch blocks recovery. No IF NOT EXISTS masks inconsistencies in these steps.
- This is a source/metadata review, not a claim that migration, negative constraints, recovery or B1 implementation passed. Actual evidence belongs in the B1 report.

## Review inventory

S1 Offering candidate index; S2 school cutovers; S3 offering governance; S4 annual courses; S5 explicit offering links; S6 sections; S7 component memberships; S8 private JSON drafts; S9 immutable publish receipts; S10 scoped first/latest receipt foreign keys.

No production rollout or rollback is authorized. MySQL DDL commits implicitly. Preserve all provenance after future adoption/activation; a baseline application rollback would then be unsafe. B3 authorization lock inversion remains unresolved and blocks activation.
