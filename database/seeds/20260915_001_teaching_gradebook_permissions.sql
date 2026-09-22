INSERT INTO permissions (code, name_th) VALUES
('TEACHING_ASSIGNMENT_MANAGE', 'จัดการการมอบหมายครูประจำวิชา'),
('GRADEBOOK_VIEW', 'ดูสมุดคะแนน'),
('GRADEBOOK_COMPONENT_MANAGE', 'จัดการโครงสร้างคะแนน'),
('GRADEBOOK_SCORE_ENTER', 'บันทึกคะแนน')
ON DUPLICATE KEY UPDATE
  name_th = VALUES(name_th);

INSERT INTO role_permissions (role_id, permission_id, resource_scope_type)
SELECT r.id, p.id, NULL
FROM roles r
CROSS JOIN permissions p
WHERE (r.code IN ('SCHOOL_ADMIN', 'ACADEMIC_ADMIN')
       AND p.code IN ('TEACHING_ASSIGNMENT_MANAGE', 'GRADEBOOK_VIEW', 'GRADEBOOK_COMPONENT_MANAGE', 'GRADEBOOK_SCORE_ENTER'))
   OR (r.code = 'EXECUTIVE' AND p.code = 'GRADEBOOK_VIEW')
ON DUPLICATE KEY UPDATE
  resource_scope_type = NULL;

INSERT INTO role_permissions (role_id, permission_id, resource_scope_type)
SELECT r.id, p.id, 'SUBJECT_OFFERING'
FROM roles r
CROSS JOIN permissions p
WHERE r.code = 'SUBJECT_TEACHER'
  AND p.code IN ('GRADEBOOK_VIEW', 'GRADEBOOK_SCORE_ENTER')
ON DUPLICATE KEY UPDATE
  resource_scope_type = 'SUBJECT_OFFERING';
