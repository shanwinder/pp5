<?php
declare(strict_types=1);

use FastRoute\RouteCollector;

return static function (RouteCollector $r): void {
    $r->addRoute('GET', '/', static fn (): string => 'PP5');
};
