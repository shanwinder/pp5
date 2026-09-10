INSERT INTO grade_levels (code, name_th, sort_order, status) VALUES
('P1', 'ประถมศึกษาปีที่ 1', 10, 'ACTIVE'),
('P2', 'ประถมศึกษาปีที่ 2', 20, 'ACTIVE'),
('P3', 'ประถมศึกษาปีที่ 3', 30, 'ACTIVE'),
('P4', 'ประถมศึกษาปีที่ 4', 40, 'ACTIVE'),
('P5', 'ประถมศึกษาปีที่ 5', 50, 'ACTIVE'),
('P6', 'ประถมศึกษาปีที่ 6', 60, 'ACTIVE')
ON DUPLICATE KEY UPDATE
  name_th = VALUES(name_th),
  sort_order = VALUES(sort_order),
  status = VALUES(status);

INSERT INTO permissions (code, name_th) VALUES
('ACADEMIC_SETUP_VIEW', 'ดูโครงสร้างวิชาการ'),
('ACADEMIC_YEAR_MANAGE', 'จัดการปีการศึกษา'),
('CLASSROOM_MANAGE', 'จัดการห้องเรียน'),
('SUBJECT_MANAGE', 'จัดการรายวิชา'),
('SUBJECT_OFFERING_MANAGE', 'จัดการรายวิชาที่เปิดสอน')
ON DUPLICATE KEY UPDATE
  name_th = VALUES(name_th);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.code IN ('SCHOOL_ADMIN', 'ACADEMIC_ADMIN')
  AND p.code IN (
    'ACADEMIC_SETUP_VIEW',
    'ACADEMIC_YEAR_MANAGE',
    'CLASSROOM_MANAGE',
    'SUBJECT_MANAGE',
    'SUBJECT_OFFERING_MANAGE'
  );
