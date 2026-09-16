<?php
declare(strict_types=1);

namespace App;

use App\Controllers\GradebookController;
use App\Controllers\GradebookScoreController;
use App\Controllers\TeachingAssignmentController;
use App\Controllers\GradebookComponentController;
use App\Controllers\AcademicYearController;
use App\Controllers\AuthController;
use App\Controllers\ClassroomController;
use App\Controllers\DashboardController;
use App\Controllers\SchoolUserController;
use App\Controllers\SubjectController;
use App\Controllers\StudentController;
use App\Controllers\EnrollmentController;
use App\Controllers\SubjectOfferingController;
use App\Controllers\SystemSchoolController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\SchoolContextMiddleware;
use App\Repositories\GradebookRepository;
use App\Repositories\GradebookScoreRepository;
use App\Repositories\TeachingAssignmentRepository;
use App\Repositories\GradebookComponentRepository;
use App\Repositories\AcademicYearRepository;
use App\Repositories\AuthorizationRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\ClassroomRepository;
use App\Repositories\GradeLevelRepository;
use App\Repositories\RoleAssignmentRepository;
use App\Repositories\RoleRepository;
use App\Repositories\SchoolMembershipRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\StudentRepository;
use App\Repositories\StudentEnrollmentRepository;
use App\Repositories\StudentClassroomPlacementRepository;
use App\Repositories\SubjectOfferingRepository;
use App\Repositories\UserRepository;
use App\Services\GradebookReadService;
use App\Services\GradebookScoreService;
use App\Services\TeachingAssignmentService;
use App\Services\GradebookComponentService;
use App\Services\AcademicYearAdministrationService;
use App\Services\AuthenticationService;
use App\Services\AuthorizationService;
use App\Services\ClassroomAdministrationService;
use App\Services\SchoolUserAdministrationService;
use App\Services\SubjectAdministrationService;
use App\Services\StudentAdministrationService;
use App\Services\EnrollmentAdministrationService;
use App\Services\SubjectOfferingAdministrationService;
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
        $offerings = new SubjectOfferingRepository($pdo);
        $gradebookComponents = new GradebookComponentRepository($pdo);
        $gradebookRead = new GradebookReadService(new AuthorizationService($authorization), $offerings,
            $gradebookComponents, new GradebookRepository($pdo));
        $gradebookController = new GradebookController($gradebookRead, $session, new AuthorizationService($authorization), $csrf);
        $dashboard = new DashboardController($session, $schools, $csrf, new AuthorizationService($authorization), $gradebookRead);
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
        $grades = new GradeLevelRepository($pdo);
        $classrooms = new ClassroomRepository($pdo);
        $classroomController = new ClassroomController(
            new ClassroomAdministrationService($pdo, $schools, $years, $grades, $classrooms, new AuditLogRepository($pdo)),
            $classrooms,
            $years,
            $grades,
            $session,
            $csrf
        );
        $subjects = new SubjectRepository($pdo);
        $subjectController = new SubjectController(
            new SubjectAdministrationService($pdo, $schools, $subjects, new AuditLogRepository($pdo)),
            $subjects,
            $session,
            $csrf
        );
        $offeringController = new SubjectOfferingController(
            new SubjectOfferingAdministrationService($pdo, $schools, $years, $classrooms, $subjects, $offerings, new AuditLogRepository($pdo)),
            $offerings,
            $years,
            $classrooms,
            $subjects,
            $session,
            $csrf,
            new AuthorizationService($authorization)
        );
        $componentController = new GradebookComponentController(
            new GradebookComponentService($pdo, $schools, $years, $offerings, $gradebookComponents, new AuditLogRepository($pdo)),
            $gradebookComponents, $offerings, $session, $csrf
        );
        $teachingAssignments = new TeachingAssignmentRepository($pdo);
        $teachingController = new TeachingAssignmentController(
            new TeachingAssignmentService($pdo, $schools, $years, $offerings, $teachingAssignments, new AuditLogRepository($pdo)),
            $teachingAssignments, $years, $offerings, $session, $csrf
        );
        $students = new StudentRepository($pdo);
        $enrollments = new StudentEnrollmentRepository($pdo);
        $placements = new StudentClassroomPlacementRepository($pdo);
        $scoreController = new GradebookScoreController(
            new GradebookScoreService($pdo, $schools, $years, $offerings, $gradebookComponents, $enrollments, $placements,
                new AuthorizationService($authorization), new GradebookScoreRepository($pdo), new AuditLogRepository($pdo)),
            $gradebookRead, $session, $csrf
        );
        $studentController = new StudentController(
            new StudentAdministrationService($pdo, $schools, $students, $enrollments, new AuditLogRepository($pdo)),
            $students,
            $session,
            $csrf,
            new AuthorizationService($authorization),
            $enrollments,
            $placements
        );
        $enrollmentController = new EnrollmentController(
            new EnrollmentAdministrationService($pdo, $schools, $years, $students, $grades, $classrooms, $enrollments, $placements, new AuditLogRepository($pdo)),
            $enrollments, $placements, $students, $years, $grades, $classrooms, $session, $csrf, new AuthorizationService($authorization)
        );
        $importBatches = new \App\Repositories\StudentImportBatchRepository($pdo);
        $importRows = new \App\Repositories\StudentImportRowRepository($pdo);
        $studentImport = new \App\Controllers\StudentImportController(
            new \App\Services\StudentImportService($pdo, $schools, $years, $students, $grades, $classrooms, $enrollments, $placements,
                $importBatches, $importRows, new AuditLogRepository($pdo)),
            $importBatches, $importRows, $years, new \App\Support\CanonicalStudentCsvReader(), $session, $csrf
        );
        $routeId = filter_var($routeInfo[2]['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $routeId = $routeId === false ? 0 : $routeId;
        $offeringId = filter_var($routeInfo[2]['offeringId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $offeringId = $offeringId === false ? 0 : $offeringId;
        $componentId = filter_var($routeInfo[2]['componentId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $componentId = $componentId === false ? 0 : $componentId;
        $enrollmentId = filter_var($routeInfo[2]['enrollmentId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $enrollmentId = $enrollmentId === false ? 0 : $enrollmentId;
        $next = match ($handler['action']) {
            'gradebook.scores.store' => static fn (Request $request): Response => $scoreController->store($request, $offeringId, $componentId, $enrollmentId),
            'gradebook.view' => static fn (Request $request): Response => $gradebookController->show($offeringId),
            'gradebook.components.setup' => static fn (Request $request): Response => $componentController->setup($offeringId),
            'gradebook.components.store' => static fn (Request $request): Response => $componentController->store($request, $offeringId),
            'gradebook.components.update' => static fn (Request $request): Response => $componentController->update($request, $offeringId, $componentId),
            'gradebook.components.changeStatus' => static fn (Request $request): Response => $componentController->changeStatus($request, $offeringId, $componentId),

            'studentImport.index' => static fn (Request $request): Response => $studentImport->index(),
            'studentImport.preview' => static fn (Request $request): Response => $studentImport->preview($request),
            'studentImport.show' => static fn (Request $request): Response => $studentImport->show($routeId),
            'studentImport.apply' => static fn (Request $request): Response => $studentImport->apply($request, $routeId),
            'studentImport.cancel' => static fn (Request $request): Response => $studentImport->cancel($request, $routeId),

            'enrollments.index' => static fn (Request $request): Response => $enrollmentController->index($request),
            'enrollments.create' => static fn (Request $request): Response => $enrollmentController->create($request),
            'enrollments.store' => static fn (Request $request): Response => $enrollmentController->store($request),
            'enrollments.edit' => static fn (Request $request): Response => $enrollmentController->edit($routeId),
            'enrollments.changePlacement' => static fn (Request $request): Response => $enrollmentController->changePlacement($request, $routeId),
            'enrollments.changeStatus' => static fn (Request $request): Response => $enrollmentController->changeStatus($request, $routeId),
            'students.index' => static fn (Request $request): Response => $studentController->index($request),
            'students.create' => static fn (Request $request): Response => $studentController->create(),
            'students.store' => static fn (Request $request): Response => $studentController->store($request),
            'students.show' => static fn (Request $request): Response => $studentController->show($routeId),
            'students.edit' => static fn (Request $request): Response => $studentController->edit($routeId),
            'students.update' => static fn (Request $request): Response => $studentController->update($request, $routeId),
            'students.changeStatus' => static fn (Request $request): Response => $studentController->changeStatus($request, $routeId),
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
            'academic.classrooms.index' => static fn (Request $request): Response => $classroomController->index($request),
            'academic.classrooms.create' => static fn (Request $request): Response => $classroomController->create($request),
            'academic.classrooms.store' => static fn (Request $request): Response => $classroomController->store($request),
            'academic.classrooms.edit' => static fn (Request $request): Response => $classroomController->edit($routeId),
            'academic.classrooms.update' => static fn (Request $request): Response => $classroomController->update($request, $routeId),
            'academic.classrooms.changeStatus' => static fn (Request $request): Response => $classroomController->changeStatus($request, $routeId),
            'academic.subjects.index' => static fn (Request $request): Response => $subjectController->index(),
            'academic.subjects.create' => static fn (Request $request): Response => $subjectController->create(),
            'academic.subjects.store' => static fn (Request $request): Response => $subjectController->store($request),
            'academic.subjects.edit' => static fn (Request $request): Response => $subjectController->edit($routeId),
            'academic.subjects.update' => static fn (Request $request): Response => $subjectController->update($request, $routeId),
            'academic.subjects.changeStatus' => static fn (Request $request): Response => $subjectController->changeStatus($request, $routeId),
            'academic.teachingAssignments.index' => static fn (Request $request): Response => $teachingController->index($request),
            'academic.teachingAssignments.store' => static fn (Request $request): Response => $teachingController->store($request),
            'academic.teachingAssignments.changeStatus' => static fn (Request $request): Response => $teachingController->changeStatus($request, $routeId),
            'academic.offerings.index' => static fn (Request $request): Response => $offeringController->index($request),
            'academic.offerings.create' => static fn (Request $request): Response => $offeringController->create($request),
            'academic.offerings.store' => static fn (Request $request): Response => $offeringController->store($request),
            'academic.offerings.edit' => static fn (Request $request): Response => $offeringController->edit($routeId),
            'academic.offerings.update' => static fn (Request $request): Response => $offeringController->update($request, $routeId),
            'academic.offerings.changeStatus' => static fn (Request $request): Response => $offeringController->changeStatus($request, $routeId),
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

            $response = $auth->handle($request, $next);
            // A gradebook target must not reveal existence through context/auth denial either.
            if ($handler['action'] === 'gradebook.view' && $response->status() === 403) {
                return new Response(View::render('errors/404'), 404);
            }

            return $response;
        }

        return $next($request);
    }
}
