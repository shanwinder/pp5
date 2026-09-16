<?php
declare(strict_types=1);

use FastRoute\RouteCollector;
use App\Support\AccessContext;

return static function (RouteCollector $r): void {
    $r->addRoute('GET', '/academic/student-import', [
        'action' => 'studentImport.index', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'STUDENT_IMPORT',
    ]);
    $r->addRoute('POST', '/academic/student-import/preview', [
        'action' => 'studentImport.preview', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'STUDENT_IMPORT',
    ]);
    $r->addRoute('GET', '/academic/student-import/{id:\d+}', [
        'action' => 'studentImport.show', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'STUDENT_IMPORT',
    ]);
    $r->addRoute('POST', '/academic/student-import/{id:\d+}/apply', [
        'action' => 'studentImport.apply', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'STUDENT_IMPORT',
    ]);
    $r->addRoute('POST', '/academic/student-import/{id:\d+}/cancel', [
        'action' => 'studentImport.cancel', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'STUDENT_IMPORT',
    ]);
    $r->addRoute('GET', '/academic/enrollments', [
        'action' => 'enrollments.index', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'STUDENT_VIEW',
    ]);
    $r->addRoute('GET', '/academic/enrollments/create', [
        'action' => 'enrollments.create', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'ENROLLMENT_MANAGE',
    ]);
    $r->addRoute('POST', '/academic/enrollments', [
        'action' => 'enrollments.store', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'ENROLLMENT_MANAGE',
    ]);
    $r->addRoute('GET', '/academic/enrollments/{id:\d+}/edit', [
        'action' => 'enrollments.edit', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'ENROLLMENT_MANAGE',
    ]);
    $r->addRoute('POST', '/academic/enrollments/{id:\d+}/placement', [
        'action' => 'enrollments.changePlacement', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'ENROLLMENT_MANAGE',
    ]);
    $r->addRoute('POST', '/academic/enrollments/{id:\d+}/status', [
        'action' => 'enrollments.changeStatus', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'ENROLLMENT_MANAGE',
    ]);
    $r->addRoute('GET', '/students', [
        'action' => 'students.index', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'STUDENT_VIEW',
    ]);
    $r->addRoute('GET', '/students/create', [
        'action' => 'students.create', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'STUDENT_MANAGE',
    ]);
    $r->addRoute('POST', '/students', [
        'action' => 'students.store', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'STUDENT_MANAGE',
    ]);
    $r->addRoute('GET', '/students/{id:\d+}', [
        'action' => 'students.show', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'STUDENT_VIEW',
    ]);
    $r->addRoute('GET', '/students/{id:\d+}/edit', [
        'action' => 'students.edit', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'STUDENT_MANAGE',
    ]);
    $r->addRoute('POST', '/students/{id:\d+}', [
        'action' => 'students.update', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'STUDENT_MANAGE',
    ]);
    $r->addRoute('POST', '/students/{id:\d+}/status', [
        'action' => 'students.changeStatus', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'STUDENT_MANAGE',
    ]);
    $r->addRoute('GET', '/', static fn (): string => 'PP5');
    $r->addRoute('GET', '/login', ['action' => 'showLogin']);
    $r->addRoute('POST', '/login', ['action' => 'login']);
    $r->addRoute('POST', '/logout', ['action' => 'logout']);
    $r->addRoute('GET', '/dashboard', ['action' => 'dashboard.index', 'protected' => true, 'context' => AccessContext::SCHOOL]);
    $r->addRoute('GET', '/system/schools', [
        'action' => 'system.schools.index', 'protected' => true,
        'context' => AccessContext::SYSTEM, 'permission' => 'SYSTEM_SCHOOL_VIEW',
    ]);
    $r->addRoute('GET', '/system/schools/create', [
        'action' => 'system.schools.create', 'protected' => true,
        'context' => AccessContext::SYSTEM, 'permission' => 'SYSTEM_SCHOOL_CREATE',
    ]);
    $r->addRoute('POST', '/system/schools', [
        'action' => 'system.schools.store', 'protected' => true,
        'context' => AccessContext::SYSTEM, 'permission' => 'SYSTEM_SCHOOL_CREATE',
    ]);
    $r->addRoute('POST', '/system/schools/{id:\d+}/status', [
        'action' => 'system.schools.changeStatus', 'protected' => true,
        'context' => AccessContext::SYSTEM, 'permission' => 'SYSTEM_SCHOOL_STATUS_MANAGE',
    ]);
    $r->addRoute('GET', '/admin/users', [
        'action' => 'admin.users.index', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'SCHOOL_USER_VIEW',
    ]);
    $r->addRoute('GET', '/admin/users/create', [
        'action' => 'admin.users.create', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'SCHOOL_USER_CREATE',
    ]);
    $r->addRoute('POST', '/admin/users', [
        'action' => 'admin.users.store', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'SCHOOL_USER_CREATE',
    ]);
    $r->addRoute('GET', '/admin/users/{id:\d+}/edit', [
        'action' => 'admin.users.edit', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'SCHOOL_USER_VIEW',
    ]);
    $r->addRoute('POST', '/admin/users/{id:\d+}/profile', [
        'action' => 'admin.users.updateProfile', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'SCHOOL_USER_UPDATE',
    ]);
    $r->addRoute('POST', '/admin/users/{id:\d+}/membership-status', [
        'action' => 'admin.users.changeMembershipStatus', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'SCHOOL_MEMBERSHIP_STATUS_MANAGE',
    ]);
    $r->addRoute('POST', '/admin/users/{id:\d+}/roles', [
        'action' => 'admin.users.replaceRoles', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'SCHOOL_ROLE_MANAGE',
    ]);
    $r->addRoute('POST', '/admin/users/{id:\d+}/reset-password', [
        'action' => 'admin.users.resetPassword', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'SCHOOL_PASSWORD_RESET',
    ]);
    $r->addRoute('GET', '/academic/years', [
        'action' => 'academic.years.index', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'ACADEMIC_SETUP_VIEW',
    ]);
    $r->addRoute('GET', '/academic/years/create', [
        'action' => 'academic.years.create', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'ACADEMIC_YEAR_MANAGE',
    ]);
    $r->addRoute('POST', '/academic/years', [
        'action' => 'academic.years.store', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'ACADEMIC_YEAR_MANAGE',
    ]);
    $r->addRoute('GET', '/academic/years/{id:\d+}/edit', [
        'action' => 'academic.years.edit', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'ACADEMIC_YEAR_MANAGE',
    ]);
    $r->addRoute('POST', '/academic/years/{id:\d+}', [
        'action' => 'academic.years.update', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'ACADEMIC_YEAR_MANAGE',
    ]);
    $r->addRoute('POST', '/academic/years/{id:\d+}/status', [
        'action' => 'academic.years.changeStatus', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'ACADEMIC_YEAR_MANAGE',
    ]);
    $r->addRoute('GET', '/academic/classrooms', [
        'action' => 'academic.classrooms.index', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'ACADEMIC_SETUP_VIEW',
    ]);
    $r->addRoute('GET', '/academic/classrooms/create', [
        'action' => 'academic.classrooms.create', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'CLASSROOM_MANAGE',
    ]);
    $r->addRoute('POST', '/academic/classrooms', [
        'action' => 'academic.classrooms.store', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'CLASSROOM_MANAGE',
    ]);
    $r->addRoute('GET', '/academic/classrooms/{id:\d+}/edit', [
        'action' => 'academic.classrooms.edit', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'CLASSROOM_MANAGE',
    ]);
    $r->addRoute('POST', '/academic/classrooms/{id:\d+}', [
        'action' => 'academic.classrooms.update', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'CLASSROOM_MANAGE',
    ]);
    $r->addRoute('POST', '/academic/classrooms/{id:\d+}/status', [
        'action' => 'academic.classrooms.changeStatus', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'CLASSROOM_MANAGE',
    ]);
    $r->addRoute('GET', '/academic/subjects', [
        'action' => 'academic.subjects.index', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'ACADEMIC_SETUP_VIEW',
    ]);
    $r->addRoute('GET', '/academic/subjects/create', [
        'action' => 'academic.subjects.create', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'SUBJECT_MANAGE',
    ]);
    $r->addRoute('POST', '/academic/subjects', [
        'action' => 'academic.subjects.store', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'SUBJECT_MANAGE',
    ]);
    $r->addRoute('GET', '/academic/subjects/{id:\d+}/edit', [
        'action' => 'academic.subjects.edit', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'SUBJECT_MANAGE',
    ]);
    $r->addRoute('POST', '/academic/subjects/{id:\d+}', [
        'action' => 'academic.subjects.update', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'SUBJECT_MANAGE',
    ]);
    $r->addRoute('POST', '/academic/subjects/{id:\d+}/status', [
        'action' => 'academic.subjects.changeStatus', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'SUBJECT_MANAGE',
    ]);
    $r->addRoute('GET', '/academic/offerings', [
        'action' => 'academic.offerings.index', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'ACADEMIC_SETUP_VIEW',
    ]);
    $r->addRoute('GET', '/academic/offerings/create', [
        'action' => 'academic.offerings.create', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'SUBJECT_OFFERING_MANAGE',
    ]);
    $r->addRoute('POST', '/academic/offerings', [
        'action' => 'academic.offerings.store', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'SUBJECT_OFFERING_MANAGE',
    ]);
    $r->addRoute('GET', '/academic/offerings/{id:\d+}/edit', [
        'action' => 'academic.offerings.edit', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'SUBJECT_OFFERING_MANAGE',
    ]);
    $r->addRoute('POST', '/academic/offerings/{id:\d+}', [
        'action' => 'academic.offerings.update', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'SUBJECT_OFFERING_MANAGE',
    ]);
    $r->addRoute('POST', '/academic/offerings/{id:\d+}/status', [
        'action' => 'academic.offerings.changeStatus', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'SUBJECT_OFFERING_MANAGE',
    ]);
    $r->addRoute('GET', '/academic/teaching-assignments', [
        'action' => 'academic.teachingAssignments.index',
        'protected' => true,
        'context' => AccessContext::SCHOOL,
        'permission' => 'TEACHING_ASSIGNMENT_MANAGE',
    ]);
    $r->addRoute('POST', '/academic/teaching-assignments', [
        'action' => 'academic.teachingAssignments.store',
        'protected' => true,
        'context' => AccessContext::SCHOOL,
        'permission' => 'TEACHING_ASSIGNMENT_MANAGE',
    ]);
    $r->addRoute('POST', '/academic/teaching-assignments/{id:\d+}/status', [
        'action' => 'academic.teachingAssignments.changeStatus',
        'protected' => true,
        'context' => AccessContext::SCHOOL,
        'permission' => 'TEACHING_ASSIGNMENT_MANAGE',
    ]);
    $r->addRoute('GET', '/gradebook/{offeringId:\d+}/setup', [
        'action' => 'gradebook.components.setup', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'GRADEBOOK_COMPONENT_MANAGE',
    ]);
    $r->addRoute('POST', '/gradebook/{offeringId:\d+}/components', [
        'action' => 'gradebook.components.store', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'GRADEBOOK_COMPONENT_MANAGE',
    ]);
    $r->addRoute('POST', '/gradebook/{offeringId:\d+}/components/{componentId:\d+}', [
        'action' => 'gradebook.components.update', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'GRADEBOOK_COMPONENT_MANAGE',
    ]);
    $r->addRoute('POST', '/gradebook/{offeringId:\d+}/components/{componentId:\d+}/status', [
        'action' => 'gradebook.components.changeStatus', 'protected' => true,
        'context' => AccessContext::SCHOOL, 'permission' => 'GRADEBOOK_COMPONENT_MANAGE',
    ]);
};
