<?php
declare(strict_types=1);

use FastRoute\RouteCollector;
use App\Support\AccessContext;

return static function (RouteCollector $r): void {
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
};
