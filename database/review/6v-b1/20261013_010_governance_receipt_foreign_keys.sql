-- REVIEW ONLY: B1 disposable database; not in the automatic migration glob.
ALTER TABLE gradebook_offering_governance
    ADD CONSTRAINT fk_governance_first_receipt FOREIGN KEY (first_publish_receipt_id, school_id, academic_year_id, subject_offering_id) REFERENCES gradebook_structure_publish_receipts (id, school_id, academic_year_id, subject_offering_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT fk_governance_latest_receipt FOREIGN KEY (latest_publish_receipt_id, school_id, academic_year_id, subject_offering_id) REFERENCES gradebook_structure_publish_receipts (id, school_id, academic_year_id, subject_offering_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
