<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Repositories\AcademicYearRepository;
use App\Repositories\ClassroomRepository;
use App\Repositories\GradeLevelRepository;
use App\Repositories\StudentRepository;
use App\Repositories\StudentEnrollmentRepository;
use App\Repositories\StudentClassroomPlacementRepository;
use App\Services\AppUiContextService;
use App\Services\AuthorizationService;
use App\Services\EnrollmentAdministrationService;
use App\Support\AccessContext;
use App\Support\Csrf;
use App\Support\View;
use DomainException;

final class EnrollmentController
{
    public function __construct(
        private EnrollmentAdministrationService $administration,
        private StudentEnrollmentRepository $enrollments,
        private StudentClassroomPlacementRepository $placements,
        private StudentRepository $students,
        private AcademicYearRepository $years,
        private GradeLevelRepository $grades,
        private ClassroomRepository $classrooms,
        private Session $session,
        private Csrf $csrf,
        private AuthorizationService $authorization,
        private AppUiContextService $ui
    ) {}

    public function index(Request $request): Response
    {
        try {
            $year = $this->queryYear($request, true);
            $grade = $this->queryGrade($request);
            $classroomId = $this->optionalId($request->query('classroom_id'));
            if ($classroomId !== null) {
                $room = $this->classrooms->findForSchool($this->school(), $classroomId);
                if ($room === null || $year === null || $room['academic_year_id'] !== $year['id']
                    || ($grade !== null && $room['grade_level_id'] !== $grade['id'])) {
                    return $this->notFound();
                }
            }
        } catch (DomainException) {
            return $this->notFound();
        }
        $error = null;
        $search = null;
        $status = null;
        $rows = [];
        try {
            $status = $this->optionalText($request->query('status'));
            if ($status !== null && !in_array($status, ['ACTIVE', 'TRANSFERRED_OUT', 'WITHDRAWN'], true)) {
                throw new DomainException('สถานะการลงทะเบียนไม่ถูกต้อง');
            }
            $search = $this->search($request->query('q'));
            if ($year !== null) {
                $rows = $this->enrollments->listForSchoolYear($this->school(), $year['id'], $grade['id'] ?? null, $classroomId, $status, $search);
            }
        } catch (DomainException $exception) {
            $error = $exception->getMessage();
            $status = null;
        }

        return new Response($this->page('academic/enrollments/index', [
            'enrollments' => $rows, 'years' => $this->years->listForSchool($this->school()), 'grades' => $this->grades->listActive(),
            'classrooms' => $year === null ? [] : $this->roomChoices($year['id'], $grade['id'] ?? null, false),
            'yearId' => $year['id'] ?? null, 'gradeId' => $grade['id'] ?? null, 'classroomId' => $classroomId,
            // Retain safe search text without reflecting ID-shaped input.
            'search' => $search !== null && !preg_match('/[0-9]{13}/', $search) ? $search : '',
            'status' => $status, 'error' => $error, 'canManage' => $this->can('ENROLLMENT_MANAGE'),
        ], 'การลงทะเบียนนักเรียน'), $error === null ? 200 : 422);
    }

    public function create(Request $request): Response
    {
        try {
            $year = $this->queryYear($request);
            $grade = $this->queryGrade($request);
            if ($year !== null && !in_array($year['status'], ['DRAFT', 'ACTIVE'], true)) {
                return $this->notFound();
            }
        } catch (DomainException) {
            return $this->notFound();
        }

        return new Response($this->page('academic/enrollments/create', [
            'years' => array_values(array_filter($this->years->listForSchool($this->school()), static fn (array $y): bool => in_array($y['status'], ['DRAFT', 'ACTIVE'], true))),
            'students' => array_values(array_filter($this->students->listForSchool($this->school()), static fn (array $s): bool => $s['status'] === 'ACTIVE')),
            'grades' => $this->grades->listActive(), 'year' => $year, 'grade' => $grade,
            'classrooms' => $year !== null && $grade !== null ? $this->roomChoices($year['id'], $grade['id']) : [],
            'csrfToken' => $this->csrf->token($this->session), 'canView' => $this->can('STUDENT_VIEW'),
        ], 'เพิ่มการลงทะเบียน'));
    }

    public function store(Request $request): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        try {
            $yearId = $this->positiveId($request->post('academic_year_id'));
            $studentId = $this->positiveId($request->post('student_id'));
            $gradeId = $this->positiveId($request->post('grade_level_id'));
            $classroomId = $this->optionalId($request->post('classroom_id'));
            $entryDate = $this->optionalText($request->post('entry_date'));
            $id = $this->administration->createEnrollment($this->school(), $this->session->get('user_id'), $yearId, $studentId, $gradeId, $classroomId, $entryDate, $this->ipAddress($request));
        } catch (DomainException $exception) {
            return $this->mutationError($exception->getMessage());
        }

        return Response::redirect('/academic/enrollments/' . $id . '/edit');
    }

    public function edit(int $enrollmentId): Response
    {
        $reference = $this->enrollments->findForSchool($this->school(), $enrollmentId);
        if ($reference === null) {
            return $this->notFound();
        }
        $rows = array_values(array_filter($this->enrollments->listForStudent($this->school(), $reference['student_id']),
            static fn (array $row): bool => $row['id'] === $enrollmentId));
        $target = $rows[0] ?? null;
        if ($target === null) {
            return $this->notFound();
        }
        $mutable = in_array($target['academic_year_status'], ['DRAFT', 'ACTIVE'], true) && $target['status'] === 'ACTIVE';

        return new Response($this->page('academic/enrollments/edit', [
            'target' => $target, 'history' => $this->placements->listForEnrollment($this->school(), $enrollmentId),
            'classrooms' => $mutable ? $this->roomChoices($target['academic_year_id'], $target['grade_level_id']) : [],
            'mutable' => $mutable, 'csrfToken' => $mutable ? $this->csrf->token($this->session) : null,
            'error' => null, 'canView' => $this->can('STUDENT_VIEW'),
        ], 'รายละเอียดการลงทะเบียน'));
    }

    public function changePlacement(Request $request, int $enrollmentId): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        try {
            $classroomId = $this->optionalId($request->post('classroom_id'));
            $this->administration->changePlacement($this->school(), $this->session->get('user_id'), $enrollmentId, $classroomId, $this->ipAddress($request));
        } catch (DomainException $exception) {
            return $this->mutationError($exception->getMessage());
        }

        return Response::redirect('/academic/enrollments/' . $enrollmentId . '/edit');
    }

    public function changeStatus(Request $request, int $enrollmentId): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        try {
            $status = $request->post('status');
            if (!is_string($status)) {
                throw new DomainException('กรุณาระบุสถานะการลงทะเบียนเป็นข้อความ');
            }
            $exitDate = $this->optionalText($request->post('exit_date'));
            $this->administration->changeStatus($this->school(), $this->session->get('user_id'), $enrollmentId, $status, $exitDate, $this->ipAddress($request));
        } catch (DomainException $exception) {
            return $this->mutationError($exception->getMessage());
        }

        return Response::redirect('/academic/enrollments/' . $enrollmentId . '/edit');
    }

    private function queryYear(Request $request, bool $default = false): ?array
    {
        $id = $this->optionalId($request->query('academic_year_id'));
        if ($id === null) {
            return $default ? ($this->years->findActiveForSchool($this->school()) ?? ($this->years->listForSchool($this->school())[0] ?? null)) : null;
        }
        $year = $this->years->findForSchool($this->school(), $id);
        if ($year === null) {
            throw new DomainException('ไม่พบปีการศึกษา');
        }

        return $year;
    }

    private function queryGrade(Request $request): ?array
    {
        $id = $this->optionalId($request->query('grade_level_id'));
        if ($id === null) {
            return null;
        }
        $grade = $this->grades->findActiveById($id);
        if ($grade === null) {
            throw new DomainException('ไม่พบระดับชั้น');
        }

        return $grade;
    }

    private function roomChoices(int $yearId, ?int $gradeId, bool $activeOnly = true): array
    {
        return array_values(array_filter($this->classrooms->listForSchool($this->school(), $yearId),
            static fn (array $room): bool => ($gradeId === null || $room['grade_level_id'] === $gradeId) && (!$activeOnly || $room['status'] === 'ACTIVE')));
    }

    private function positiveId(mixed $value): int
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/\A[0-9]+\z/', (string) $value)) {
            throw new DomainException('กรุณาเลือกรายการที่ถูกต้อง');
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new DomainException('กรุณาเลือกรายการที่ถูกต้อง');
        }

        return $id;
    }

    private function optionalId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : $this->positiveId($value);
    }

    private function optionalText(mixed $value): ?string
    {
        if ($value !== null && !is_string($value)) {
            throw new DomainException('กรุณาระบุข้อมูลเป็นข้อความ');
        }

        return $value === '' ? null : $value;
    }

    private function search(mixed $value): ?string
    {
        $value = $this->optionalText($value);
        if ($value === null) {
            return null;
        }
        if (!mb_check_encoding($value, 'UTF-8') || preg_match('/\p{Cc}/u', $value)) {
            throw new DomainException('คำค้นต้องเป็นข้อความ UTF-8 ที่ไม่มีอักขระควบคุม');
        }
        $value = preg_replace('/\A\s+|\s+\z/u', '', $value);
        if (mb_strlen($value, 'UTF-8') > 100) {
            throw new DomainException('คำค้นต้องไม่เกิน 100 ตัวอักษร');
        }

        return $value === '' ? null : $value;
    }

    private function school(): int { return $this->session->get('school_id'); }
    private function can(string $permission): bool
    {
        return $this->authorization->hasPermission($this->session->get('user_id'), AccessContext::SCHOOL, $this->school(), $permission);
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
    private function notFound(): Response { return new Response(View::error(404), 404); }
    private function mutationError(string $error): Response
    {
        return new Response($this->page('academic/enrollments/edit', ['target' => null, 'error' => $error, 'canView' => $this->can('STUDENT_VIEW')], 'รายละเอียดการลงทะเบียน'), 422);
    }

    private function page(string $template, array $data, string $title): string
    {
        return View::page($template, $data, [
            'ui' => $this->ui->build('enrollments'), 'documentTitle' => $title.' — ปพ.5', 'pageTitle' => $title,
        ]);
    }
}
