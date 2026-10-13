-- REVIEW ONLY: B1 disposable database; not in the automatic migration glob.
ALTER TABLE subject_offerings ADD UNIQUE KEY uq_offering_annual_scope (id, school_id, academic_year_id, classroom_id, subject_id, term_no);
