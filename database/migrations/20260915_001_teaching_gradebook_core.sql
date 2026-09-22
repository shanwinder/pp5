-- NULL grants remain school-wide; SUBJECT_OFFERING grants require a matching
-- active permission scope when resource authorization is implemented in Task 2.
ALTER TABLE role_permissions
  ADD COLUMN resource_scope_type VARCHAR(30) NULL DEFAULT NULL;

-- Explicit candidate keys for tenant/year-safe foreign keys. Existing keys stay intact.
ALTER TABLE user_role_assignments
  ADD UNIQUE KEY uq_assignment_id_school (id, school_id);

ALTER TABLE subject_offerings
  ADD UNIQUE KEY uq_offering_id_school_year (id, school_id, academic_year_id);

ALTER TABLE student_enrollments
  ADD UNIQUE KEY uq_student_enrollment_id_school_year (id, school_id, academic_year_id);

-- This concrete table represents SUBJECT_OFFERING scopes only.
-- Role eligibility, active membership and lifecycle checks belong to Task 2.
CREATE TABLE permission_scopes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    user_role_assignment_id BIGINT UNSIGNED NOT NULL,
    subject_offering_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    assigned_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permission_scope_assignment_offering (user_role_assignment_id, subject_offering_id),
    CONSTRAINT fk_permission_scope_assignment_school
      FOREIGN KEY (user_role_assignment_id, school_id)
      REFERENCES user_role_assignments(id, school_id)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_permission_scope_offering_school_year
      FOREIGN KEY (subject_offering_id, school_id, academic_year_id)
      REFERENCES subject_offerings(id, school_id, academic_year_id)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_permission_scope_assigner
      FOREIGN KEY (assigned_by) REFERENCES users(id)
      ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gradebook_components (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    subject_offering_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(50) NOT NULL,
    name_th VARCHAR(190) NOT NULL,
    max_score DECIMAL(7,2) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gradebook_component_code (school_id, subject_offering_id, code),
    UNIQUE KEY uq_gradebook_component_identity (id, school_id, academic_year_id, subject_offering_id),
    CONSTRAINT fk_gradebook_component_offering
      FOREIGN KEY (subject_offering_id, school_id, academic_year_id)
      REFERENCES subject_offerings(id, school_id, academic_year_id)
      ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gradebook_scores (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    subject_offering_id BIGINT UNSIGNED NOT NULL,
    enrollment_id BIGINT UNSIGNED NOT NULL,
    component_id BIGINT UNSIGNED NOT NULL,
    -- NULL is not entered/cleared; 0.00 is a real score.
    score DECIMAL(7,2) NULL DEFAULT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gradebook_score_cell (subject_offering_id, enrollment_id, component_id),
    -- The component FK also proves the offering exists in this school/year.
    CONSTRAINT fk_gradebook_score_component
      FOREIGN KEY (component_id, school_id, academic_year_id, subject_offering_id)
      REFERENCES gradebook_components(id, school_id, academic_year_id, subject_offering_id)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_gradebook_score_enrollment
      FOREIGN KEY (enrollment_id, school_id, academic_year_id)
      REFERENCES student_enrollments(id, school_id, academic_year_id)
      ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_gradebook_score_updater
      FOREIGN KEY (updated_by) REFERENCES users(id)
      ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
