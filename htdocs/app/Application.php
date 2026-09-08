<?php
declare(strict_types=1);

namespace App;

use App\Http\Request;
use App\Http\Response;
use FastRoute\Dispatcher;
use function FastRoute\simpleDispatcher;

final class Application
{
    public function handle(Request $request): Response
    {
        $routes = require dirname(__DIR__) . '/routes/web.php';
        $dispatcher = simpleDispatcher($routes);
        $routeInfo = $dispatcher->dispatch($request->method(), $request->path());

        if ($routeInfo[0] === Dispatcher::NOT_FOUND) {
            return new Response('Not Found', 404);
        }

        if ($routeInfo[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            return new Response('Method Not Allowed', 405);
        }

        $handler = $routeInfo[1];
        $result = $handler($routeInfo[2]);

        return $result instanceof Response
            ? $result
            : new Response((string) $result);
    }
}
