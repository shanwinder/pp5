INSERT INTO permissions (code, name_th) VALUES
('STUDENT_VIEW', 'ดูข้อมูลนักเรียน'),
('STUDENT_MANAGE', 'จัดการข้อมูลนักเรียน'),
('ENROLLMENT_MANAGE', 'จัดการการลงทะเบียนเรียน'),
('STUDENT_IMPORT', 'นำเข้าข้อมูลนักเรียน')
ON DUPLICATE KEY UPDATE
  name_th = VALUES(name_th);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.code IN ('SCHOOL_ADMIN', 'ACADEMIC_ADMIN')
  AND p.code IN (
    'STUDENT_VIEW',
    'STUDENT_MANAGE',
    'ENROLLMENT_MANAGE',
    'STUDENT_IMPORT'
  );
