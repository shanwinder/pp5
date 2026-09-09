<?php
declare(strict_types=1);

namespace App;

use App\Controllers\AuthController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\SchoolContextMiddleware;
use App\Repositories\SchoolMembershipRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\UserRepository;
use App\Services\AuthenticationService;
use App\Support\Csrf;
use App\Support\Database;
use FastRoute\Dispatcher;
use PDO;
use Throwable;
use function FastRoute\simpleDispatcher;

final class Application
{
    public function __construct(private ?PDO $pdo = null) {}

    public function handle(Request $request): Response
    {
        try {
            return $this->dispatch($request);
        } catch (Throwable) {
            return new Response('Internal Server Error', 500);
        }
    }

    private function dispatch(Request $request): Response
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
        if (is_callable($handler)) {
            $result = $handler($routeInfo[2]);

            return $result instanceof Response ? $result : new Response((string) $result);
        }

        $pdo = $this->pdo ??= Database::connect(
            require dirname(__DIR__) . '/config/database.php'
        );
        $users = new UserRepository($pdo);
        $memberships = new SchoolMembershipRepository($pdo);
        $schools = new SchoolRepository($pdo);
        $session = new Session();
        $controller = new AuthController(
            new AuthenticationService($users, $memberships),
            $session,
            new Csrf()
        );
        $next = match ($handler['action']) {
            'showLogin' => static fn (Request $request): Response => $controller->showLogin(),
            'login' => static fn (Request $request): Response => $controller->login($request),
            'logout' => static fn (Request $request): Response => $controller->logout($request),
            // Task 6 protected endpoint; dashboard content is added in Task 7.
            'dashboard' => static fn (Request $request): Response => new Response('PP5'),
        };

        if ($handler['protected'] ?? false) {
            $auth = new AuthMiddleware($session, $users);
            $schoolContext = new SchoolContextMiddleware($session, $memberships, $schools);

            return $auth->handle(
                $request,
                static fn (Request $request): Response => $schoolContext->handle($request, $next)
            );
        }

        return $next($request);
    }
}
