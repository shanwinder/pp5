ALTER TABLE classrooms
  ADD UNIQUE KEY uq_classroom_id_school_year_grade
    (id, school_id, academic_year_id, grade_level_id);

CREATE TABLE students (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id BIGINT UNSIGNED NOT NULL,
    student_code VARCHAR(50) NOT NULL,
    national_id CHAR(13) NULL,
    prefix_th VARCHAR(50) NOT NULL,
    first_name_th VARCHAR(100) NOT NULL,
    last_name_th VARCHAR(100) NOT NULL,
    gender_code VARCHAR(20) NULL,
    birth_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_students_school_code (school_id, student_code),
    UNIQUE KEY uq_students_school_national_id (school_id, national_id),
    UNIQUE KEY uq_students_id_school (id, school_id),
    CONSTRAINT fk_student_school
      FOREIGN KEY (school_id) REFERENCES schools(id)
      ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_enrollments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    grade_level_id BIGINT UNSIGNED NOT NULL,
    entry_date DATE NULL,
    exit_date DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_enrollment_school_year_student
      (school_id, academic_year_id, student_id),
    UNIQUE KEY uq_student_enrollment_id_school_year_grade
      (id, school_id, academic_year_id, grade_level_id),
    KEY idx_student_enrollment_year_grade_status
      (school_id, academic_year_id, grade_level_id, status),
    CONSTRAINT fk_student_enrollment_year_school
      FOREIGN KEY (academic_year_id, school_id)
      REFERENCES academic_years(id, school_id)
      ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_student_enrollment_student_school
      FOREIGN KEY (student_id, school_id)
      REFERENCES students(id, school_id)
      ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_student_enrollment_grade
      FOREIGN KEY (grade_level_id)
      REFERENCES grade_levels(id)
      ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_classroom_placements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    grade_level_id BIGINT UNSIGNED NOT NULL,
    enrollment_id BIGINT UNSIGNED NOT NULL,
    classroom_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_student_placement_enrollment_status
      (school_id, enrollment_id, status),
    CONSTRAINT fk_student_placement_enrollment_scope
      FOREIGN KEY (enrollment_id, school_id, academic_year_id, grade_level_id)
      REFERENCES student_enrollments(id, school_id, academic_year_id, grade_level_id)
      ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_student_placement_classroom_scope
      FOREIGN KEY (classroom_id, school_id, academic_year_id, grade_level_id)
      REFERENCES classrooms(id, school_id, academic_year_id, grade_level_id)
      ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_import_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    source_name VARCHAR(190) NOT NULL,
    source_sha256 CHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PREVIEW',
    row_count INT UNSIGNED NOT NULL DEFAULT 0,
    create_student_count INT UNSIGNED NOT NULL DEFAULT 0,
    create_enrollment_count INT UNSIGNED NOT NULL DEFAULT 0,
    noop_count INT UNSIGNED NOT NULL DEFAULT 0,
    error_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    applied_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_import_batch_id_school_year
      (id, school_id, academic_year_id),
    KEY idx_student_import_school_year_status
      (school_id, academic_year_id, status),
    KEY idx_student_import_school_status_expiry
      (school_id, status, expires_at),
    KEY idx_student_import_applied_hash
      (school_id, academic_year_id, source_sha256, status),
    CONSTRAINT fk_student_import_batch_year_school
      FOREIGN KEY (academic_year_id, school_id)
      REFERENCES academic_years(id, school_id)
      ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_student_import_batch_user
      FOREIGN KEY (created_by) REFERENCES users(id)
      ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_import_rows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id BIGINT UNSIGNED NOT NULL,
    school_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    row_no INT UNSIGNED NOT NULL,
    student_code VARCHAR(50) NOT NULL,
    national_id CHAR(13) NULL,
    prefix_th VARCHAR(50) NOT NULL,
    first_name_th VARCHAR(100) NOT NULL,
    last_name_th VARCHAR(100) NOT NULL,
    gender_code VARCHAR(20) NULL,
    birth_date DATE NULL,
    grade_level_code VARCHAR(20) NOT NULL,
    classroom_code VARCHAR(50) NULL,
    entry_date DATE NULL,
    matched_student_id BIGINT UNSIGNED NULL,
    student_action VARCHAR(20) NOT NULL,
    enrollment_action VARCHAR(20) NOT NULL,
    error_code VARCHAR(50) NULL,
    error_message VARCHAR(500) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_import_row_batch_row (batch_id, row_no),
    KEY idx_student_import_row_batch (school_id, batch_id, row_no),
    CONSTRAINT fk_student_import_row_batch_scope
      FOREIGN KEY (batch_id, school_id, academic_year_id)
      REFERENCES student_import_batches(id, school_id, academic_year_id)
      ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_student_import_row_matched_student
      FOREIGN KEY (matched_student_id, school_id)
      REFERENCES students(id, school_id)
      ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
