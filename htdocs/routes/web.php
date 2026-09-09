<?php
declare(strict_types=1);

use FastRoute\RouteCollector;

return static function (RouteCollector $r): void {
    $r->addRoute('GET', '/', static fn (): string => 'PP5');
    $r->addRoute('GET', '/login', ['action' => 'showLogin']);
    $r->addRoute('POST', '/login', ['action' => 'login']);
    $r->addRoute('POST', '/logout', ['action' => 'logout']);
    $r->addRoute('GET', '/dashboard', ['action' => 'dashboard', 'protected' => true]);
};
