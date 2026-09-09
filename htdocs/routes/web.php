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
};
