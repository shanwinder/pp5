<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Repositories\AcademicYearRepository;
use App\Repositories\ClassroomRepository;
use App\Repositories\GradeLevelRepository;
use App\Services\ClassroomAdministrationService;
use App\Support\Csrf;
use App\Support\View;
use DomainException;

final class ClassroomController
{
    public function __construct(
        private ClassroomAdministrationService $administration,
        private ClassroomRepository $classrooms,
        private AcademicYearRepository $years,
        private GradeLevelRepository $grades,
        private Session $session,
        private Csrf $csrf
    ) {}

    public function index(Request $request): Response
    {
        try {
            $year = $this->queryYear($request);
        } catch (DomainException) {
            return new Response(View::render('errors/404'), 404);
        }

        return new Response(View::render('academic/classrooms/index', [
            'classrooms' => $this->classrooms->listForSchool($this->session->get('school_id'), $year['id'] ?? null),
        ]));
    }

    public function create(Request $request): Response
    {
        try {
            $year = $this->queryYear($request);
            if ($year !== null && !in_array($year['status'], ['DRAFT', 'ACTIVE'], true)) {
                return new Response(View::render('errors/404'), 404);
            }
        } catch (DomainException) {
            return new Response(View::render('errors/404'), 404);
        }

        return $this->createForm(['academic_year_id' => $year['id'] ?? null]);
    }

    public function store(Request $request): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        $values = [];
        try {
            $values = $this->details($request);
            $values['academic_year_id'] = $this->positiveId($request->post('academic_year_id'));
            $this->administration->createClassroom(
                $this->session->get('school_id'), $this->session->get('user_id'), $values['academic_year_id'],
                $values['grade_level_id'], $values['code'], $values['name_th'], $this->ipAddress($request)
            );
        } catch (DomainException $exception) {
            return $this->createForm($values, $exception->getMessage(), 422);
        }

        return Response::redirect('/academic/classrooms');
    }

    public function edit(int $classroomId): Response
    {
        $target = $this->classrooms->findForSchool($this->session->get('school_id'), $classroomId);
        if ($target === null) {
            return new Response(View::render('errors/404'), 404);
        }

        return new Response(View::render('academic/classrooms/edit', [
            'target' => $target,
            'grades' => $this->grades->listActive(),
            'csrfToken' => $this->csrf->token($this->session),
            'error' => null,
        ]));
    }

    public function update(Request $request, int $classroomId): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        try {
            $values = $this->details($request);
            $this->administration->updateClassroom(
                $this->session->get('school_id'), $this->session->get('user_id'), $classroomId,
                $values['grade_level_id'], $values['code'], $values['name_th'], $this->ipAddress($request)
            );
        } catch (DomainException $exception) {
            return $this->mutationError($exception->getMessage());
        }

        return Response::redirect('/academic/classrooms/' . $classroomId . '/edit');
    }

    public function changeStatus(Request $request, int $classroomId): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        $status = $request->post('status');
        if (!is_string($status)) {
            return $this->mutationError('สถานะห้องเรียนไม่ถูกต้อง');
        }
        try {
            $this->administration->changeStatus(
                $this->session->get('school_id'), $this->session->get('user_id'), $classroomId,
                $status, $this->ipAddress($request)
            );
        } catch (DomainException $exception) {
            return $this->mutationError($exception->getMessage());
        }

        return Response::redirect('/academic/classrooms/' . $classroomId . '/edit');
    }

    private function queryYear(Request $request): ?array
    {
        $value = $request->query('academic_year_id');
        if ($value === null) {
            return null;
        }
        $year = $this->years->findForSchool($this->session->get('school_id'), $this->positiveId($value));
        if ($year === null) {
            throw new DomainException('ไม่พบปีการศึกษาในโรงเรียนนี้');
        }

        return $year;
    }

    private function positiveId(mixed $value): int
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/\A[0-9]+\z/', (string) $value)) {
            throw new DomainException('กรุณาเลือกปีการศึกษาหรือระดับชั้นที่ถูกต้อง');
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new DomainException('กรุณาเลือกปีการศึกษาหรือระดับชั้นที่ถูกต้อง');
        }

        return $id;
    }

    private function details(Request $request): array
    {
        $code = $request->post('code');
        $name = $request->post('name_th');
        if (!is_string($code) || !is_string($name)) {
            throw new DomainException('กรุณาระบุรหัสและชื่อห้องเรียนเป็นข้อความ');
        }

        return ['grade_level_id' => $this->positiveId($request->post('grade_level_id')), 'code' => $code, 'name_th' => $name];
    }

    private function validCsrf(Request $request): bool
    {
        $token = $request->post('_token');

        return $this->csrf->verify($this->session, is_string($token) ? $token : null);
    }

    private function ipAddress(Request $request): ?string
    {
        $address = $request->server('REMOTE_ADDR');

        return is_string($address) && filter_var($address, FILTER_VALIDATE_IP) !== false ? $address : null;
    }

    private function createForm(array $values = [], ?string $error = null, int $status = 200): Response
    {
        return new Response(View::render('academic/classrooms/create', [
            'values' => $values,
            'years' => array_values(array_filter($this->years->listForSchool($this->session->get('school_id')),
                static fn (array $year): bool => in_array($year['status'], ['DRAFT', 'ACTIVE'], true))),
            'grades' => $this->grades->listActive(),
            'csrfToken' => $this->csrf->token($this->session),
            'error' => $error,
        ]), $status);
    }

    private function mutationError(string $error): Response
    {
        return new Response(View::render('academic/classrooms/edit', ['target' => null, 'error' => $error]), 422);
    }
}
