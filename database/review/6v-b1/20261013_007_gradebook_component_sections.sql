-- REVIEW ONLY: B1 disposable database; not in the automatic migration glob.
CREATE TABLE gradebook_component_sections (
    component_id BIGINT UNSIGNED NOT NULL,
    school_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    subject_offering_id BIGINT UNSIGNED NOT NULL,
    section_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (component_id),
    KEY ix_membership_component (component_id, school_id, academic_year_id, subject_offering_id),
    KEY ix_membership_section (section_id, school_id, academic_year_id, subject_offering_id),
    KEY ix_membership_offering (school_id, subject_offering_id, component_id),
    CONSTRAINT fk_membership_component FOREIGN KEY (component_id, school_id, academic_year_id, subject_offering_id) REFERENCES gradebook_components (id, school_id, academic_year_id, subject_offering_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_membership_section FOREIGN KEY (section_id, school_id, academic_year_id, subject_offering_id) REFERENCES gradebook_score_sections (id, school_id, academic_year_id, subject_offering_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    KEY ix_membership_created_by (created_by),
    CONSTRAINT fk_membership_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    KEY ix_membership_updated_by (updated_by),
    CONSTRAINT fk_membership_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
