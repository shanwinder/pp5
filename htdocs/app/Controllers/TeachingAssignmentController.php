<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AppUiContextService;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Repositories\AcademicYearRepository;
use App\Repositories\SubjectOfferingRepository;
use App\Repositories\TeachingAssignmentRepository;
use App\Services\TeachingAssignmentService;
use App\Support\Csrf;
use App\Support\View;
use DomainException;

final class TeachingAssignmentController
{
    private const PATH = '/academic/teaching-assignments';
    private const INVALID = 'ไม่สามารถบันทึกการมอบหมายได้ กรุณาตรวจสอบครู รายวิชา และสถานะปีการศึกษา';

    public function __construct(
        private TeachingAssignmentService $teaching,
        private TeachingAssignmentRepository $assignments,
        private AcademicYearRepository $years,
        private SubjectOfferingRepository $offerings,
        private Session $session,
        private Csrf $csrf,
        private AppUiContextService $ui
    ) {}

    public function index(Request $request): Response
    {
        $year = null;
        try {
            if ($request->query('academic_year_id') !== null) {
                $year = $this->years->findForSchool($this->session->get('school_id'), $this->positiveId($request->query('academic_year_id')));
                if ($year === null) { throw new DomainException(self::INVALID); }
            }
        } catch (DomainException) {
            return new Response(View::error(404), 404);
        }

        return $this->page($year);
    }

    public function store(Request $request): Response
    {
        if (!$this->validCsrf($request)) { return new Response('CSRF token mismatch', 419); }
        try {
            $teacher = $this->positiveId($request->post('user_role_assignment_id'));
            $offering = $this->positiveId($request->post('subject_offering_id'));
            $this->teaching->createAssignment($this->session->get('school_id'), $this->session->get('user_id'),
                $teacher, $offering, $this->ipAddress($request));
        } catch (DomainException) {
            return $this->page(null, self::INVALID, 422);
        }

        return Response::redirect(self::PATH);
    }

    public function changeStatus(Request $request, int $teachingAssignmentId): Response
    {
        if (!$this->validCsrf($request)) { return new Response('CSRF token mismatch', 419); }
        try {
            $status = $request->post('status');
            if (!is_string($status) || !in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
                throw new DomainException(self::INVALID);
            }
            $this->teaching->changeStatus($this->session->get('school_id'), $this->session->get('user_id'),
                $teachingAssignmentId, $status, $this->ipAddress($request));
        } catch (DomainException) {
            return $this->page(null, self::INVALID, 422);
        }

        return Response::redirect(self::PATH);
    }

    private function page(?array $selectedYear = null, ?string $error = null, int $status = 200): Response
    {
        $schoolId = $this->session->get('school_id');
        $canCreate = $selectedYear !== null && in_array($selectedYear['status'], ['DRAFT', 'ACTIVE'], true);

        $ui = $this->ui->build('teaching-assignments');
        return new Response(View::page('academic/teaching-assignments/index', [
            'permissions' => $ui['permissions'],
            'years' => $this->years->listForSchool($schoolId),
            'selectedYear' => $selectedYear,
            'assignments' => $this->assignments->listDetailedForSchool($schoolId, $selectedYear['id'] ?? null),
            'canCreate' => $canCreate,
            'teachers' => $canCreate ? $this->teaching->listSubjectTeachers($schoolId) : [],
            'offerings' => $canCreate ? array_values(array_filter($this->offerings->listForSchool($schoolId, $selectedYear['id']),
                static fn (array $offering): bool => $offering['status'] === 'ACTIVE')) : [],
            'csrfToken' => $this->csrf->token($this->session),
            'error' => $error,
        ], ['ui' => $ui, 'documentTitle' => 'การมอบหมายครูประจำวิชา — ระบบ ปพ.5', 'pageTitle' => 'การมอบหมายครูประจำวิชา']), $status);
    }

    private function positiveId(mixed $value): int
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/\A[0-9]+\z/', (string) $value)) {
            throw new DomainException(self::INVALID);
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) { throw new DomainException(self::INVALID); }

        return $id;
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
}
