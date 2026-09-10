<?php
declare(strict_types=1);

namespace App;

use App\Controllers\AcademicYearController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\SchoolUserController;
use App\Controllers\SystemSchoolController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\SchoolContextMiddleware;
use App\Repositories\AcademicYearRepository;
use App\Repositories\AuthorizationRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\RoleAssignmentRepository;
use App\Repositories\RoleRepository;
use App\Repositories\SchoolMembershipRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\UserRepository;
use App\Services\AcademicYearAdministrationService;
use App\Services\AuthenticationService;
use App\Services\AuthorizationService;
use App\Services\SchoolUserAdministrationService;
use App\Services\SystemSchoolAdministrationService;
use App\Support\AccessContext;
use App\Support\Csrf;
use App\Support\Database;
use App\Support\View;
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
            return new Response(View::render('errors/404'), 404);
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
        $csrf = new Csrf();
        $authorization = new AuthorizationRepository($pdo);
        $controller = new AuthController(
            new AuthenticationService($users, $memberships, $authorization),
            $session,
            $csrf
        );
        $dashboard = new DashboardController($session, $schools, $csrf, new AuthorizationService($authorization));
        $systemSchools = new SystemSchoolController(
            new SystemSchoolAdministrationService($pdo, $schools, $users, $memberships,
                new RoleRepository($pdo), new RoleAssignmentRepository($pdo), new AuditLogRepository($pdo)),
            $schools,
            $session,
            $csrf
        );
        $schoolUsers = new SchoolUserController(
            new SchoolUserAdministrationService($pdo, $users, $memberships,
                new RoleRepository($pdo), new RoleAssignmentRepository($pdo), new AuditLogRepository($pdo), $authorization),
            $memberships,
            new RoleRepository($pdo),
            new RoleAssignmentRepository($pdo),
            $session,
            $csrf
        );
        $years = new AcademicYearRepository($pdo);
        $academicYears = new AcademicYearController(
            new AcademicYearAdministrationService($pdo, $schools, $years, new AuditLogRepository($pdo)),
            $years,
            $session,
            $csrf
        );
        $routeId = filter_var($routeInfo[2]['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $routeId = $routeId === false ? 0 : $routeId;
        $next = match ($handler['action']) {
            'showLogin' => static fn (Request $request): Response => $controller->showLogin(),
            'login' => static fn (Request $request): Response => $controller->login($request),
            'logout' => static fn (Request $request): Response => $controller->logout($request),
            'dashboard.index' => static fn (Request $request): Response => $dashboard->index(),
            'system.schools.index' => static fn (Request $request): Response => $systemSchools->index(),
            'system.schools.create' => static fn (Request $request): Response => $systemSchools->create(),
            'system.schools.store' => static fn (Request $request): Response => $systemSchools->store($request),
            'system.schools.changeStatus' => static fn (Request $request): Response => $systemSchools->changeStatus($request, $routeId),
            'admin.users.index' => static fn (Request $request): Response => $schoolUsers->index(),
            'admin.users.create' => static fn (Request $request): Response => $schoolUsers->create(),
            'admin.users.store' => static fn (Request $request): Response => $schoolUsers->store($request),
            'admin.users.edit' => static fn (Request $request): Response => $schoolUsers->edit($routeId),
            'admin.users.updateProfile' => static fn (Request $request): Response => $schoolUsers->updateProfile($request, $routeId),
            'admin.users.changeMembershipStatus' => static fn (Request $request): Response => $schoolUsers->changeMembershipStatus($request, $routeId),
            'admin.users.replaceRoles' => static fn (Request $request): Response => $schoolUsers->replaceRoles($request, $routeId),
            'admin.users.resetPassword' => static fn (Request $request): Response => $schoolUsers->resetPassword($request, $routeId),
            'academic.years.index' => static fn (Request $request): Response => $academicYears->index(),
            'academic.years.create' => static fn (Request $request): Response => $academicYears->create(),
            'academic.years.store' => static fn (Request $request): Response => $academicYears->store($request),
            'academic.years.edit' => static fn (Request $request): Response => $academicYears->edit($routeId),
            'academic.years.update' => static fn (Request $request): Response => $academicYears->update($request, $routeId),
            'academic.years.changeStatus' => static fn (Request $request): Response => $academicYears->changeStatus($request, $routeId),
        };

        if ($handler['protected'] ?? false) {
            $auth = new AuthMiddleware($session, $users);
            $context = $handler['context'] ?? AccessContext::SCHOOL;
            if ($context === AccessContext::SYSTEM) {
                $handlerNext = $next;
                $next = static fn (Request $request): Response => $session->get('context_type') === AccessContext::SYSTEM
                    ? $handlerNext($request)
                    : new Response(View::render('errors/403'), 403);
            }
            if (isset($handler['permission'])) {
                $permission = new PermissionMiddleware($session, new AuthorizationService($authorization), $handler['permission']);
                $permissionNext = $next;
                $next = static fn (Request $request): Response => $permission->handle($request, $permissionNext);
            }
            if ($context === AccessContext::SCHOOL) {
                $schoolContext = new SchoolContextMiddleware($session, $memberships, $schools);
                $schoolNext = $next;
                $next = static fn (Request $request): Response => $schoolContext->handle($request, $schoolNext);
            }

            return $auth->handle($request, $next);
        }

        return $next($request);
    }
}
