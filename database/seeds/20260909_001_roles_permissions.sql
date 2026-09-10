INSERT INTO roles (code, name_th, scope_type, status) VALUES
('SYSTEM_ADMIN', 'ผู้ดูแลระบบส่วนกลาง', 'SYSTEM', 'ACTIVE'),
('SCHOOL_ADMIN', 'ผู้ดูแลระบบโรงเรียน', 'SCHOOL', 'ACTIVE'),
('ACADEMIC_ADMIN', 'ผู้ดูแลงานวิชาการ', 'SCHOOL', 'ACTIVE'),
('HOMEROOM_TEACHER', 'ครูประจำชั้น', 'SCHOOL', 'ACTIVE'),
('SUBJECT_TEACHER', 'ครูประจำวิชา', 'SCHOOL', 'ACTIVE'),
('EXECUTIVE', 'ผู้บริหาร', 'SCHOOL', 'ACTIVE'),
('VIEWER', 'ผู้ดูข้อมูล', 'SCHOOL', 'ACTIVE')
ON DUPLICATE KEY UPDATE
  name_th = VALUES(name_th),
  scope_type = VALUES(scope_type),
  status = VALUES(status);

INSERT INTO permissions (code, name_th) VALUES
('SYSTEM_SCHOOL_VIEW', 'ดูรายชื่อโรงเรียน'),
('SYSTEM_SCHOOL_CREATE', 'สร้างโรงเรียนและผู้ดูแลโรงเรียนคนแรก'),
('SYSTEM_SCHOOL_STATUS_MANAGE', 'จัดการสถานะโรงเรียน'),
('SCHOOL_USER_VIEW', 'ดูผู้ใช้ในโรงเรียน'),
('SCHOOL_USER_CREATE', 'สร้างผู้ใช้ในโรงเรียน'),
('SCHOOL_USER_UPDATE', 'แก้ไขข้อมูลผู้ใช้ในโรงเรียน'),
('SCHOOL_MEMBERSHIP_STATUS_MANAGE', 'จัดการสถานะสมาชิกโรงเรียน'),
('SCHOOL_ROLE_MANAGE', 'จัดการบทบาทในโรงเรียน'),
('SCHOOL_PASSWORD_RESET', 'ตั้งรหัสผ่านใหม่ให้ผู้ใช้ในโรงเรียน')
ON DUPLICATE KEY UPDATE
  name_th = VALUES(name_th);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.code = 'SYSTEM_ADMIN'
  AND p.code IN (
    'SYSTEM_SCHOOL_VIEW',
    'SYSTEM_SCHOOL_CREATE',
    'SYSTEM_SCHOOL_STATUS_MANAGE'
  );

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.code = 'SCHOOL_ADMIN'
  AND p.code IN (
    'SCHOOL_USER_VIEW',
    'SCHOOL_USER_CREATE',
    'SCHOOL_USER_UPDATE',
    'SCHOOL_MEMBERSHIP_STATUS_MANAGE',
    'SCHOOL_ROLE_MANAGE',
    'SCHOOL_PASSWORD_RESET'
  );
