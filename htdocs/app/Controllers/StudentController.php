<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Repositories\StudentRepository;
use App\Repositories\StudentEnrollmentRepository;
use App\Repositories\StudentClassroomPlacementRepository;
use App\Services\AppUiContextService;
use App\Services\AuthorizationService;
use App\Services\StudentAdministrationService;
use App\Support\AccessContext;
use App\Support\Csrf;
use App\Support\View;
use DomainException;

final class StudentController
{
    public function __construct(
        private StudentAdministrationService $administration,
        private StudentRepository $students,
        private Session $session,
        private Csrf $csrf,
        private AuthorizationService $authorization,
        private StudentEnrollmentRepository $enrollments,
        private StudentClassroomPlacementRepository $placements,
        private AppUiContextService $ui
    ) {}

    public function index(Request $request): Response
    {
        try {
            $search = $this->search($request);
        } catch (DomainException $exception) {
            return new Response($this->page('students/index', [
                'students' => [], 'search' => '', 'canManage' => $this->can('STUDENT_MANAGE'), 'error' => $exception->getMessage(),
            ], 'รายชื่อนักเรียน'), 422);
        }

        return new Response($this->page('students/index', [
            'students' => $this->students->listForSchool($this->session->get('school_id'), $search),
            // Keep ID-shaped input out of HTML; the validated repository search is unchanged.
            'search' => $search !== null && !preg_match('/[0-9]{13}/', $search) ? $search : '',
            'canManage' => $this->can('STUDENT_MANAGE'), 'error' => null,
        ], 'รายชื่อนักเรียน'));
    }

    public function create(): Response
    {
        return $this->createForm();
    }

    public function store(Request $request): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        try {
            $values = $this->details($request);
            $id = $this->administration->createStudent(
                $this->session->get('school_id'), $this->session->get('user_id'),
                ...[...$values, $this->ipAddress($request)]
            );
        } catch (DomainException $exception) {
            // Do not reflect submitted identity data in an error response.
            return $this->createForm($exception->getMessage(), 422);
        }

        return Response::redirect('/students/' . $id . '/edit');
    }

    public function show(int $studentId): Response
    {
        $target = $this->students->findForSchool($this->session->get('school_id'), $studentId);
        if ($target === null) {
            return new Response(View::error(404), 404);
        }
        $maskedNationalId = $target['national_id'] === null ? null : '*********' . substr($target['national_id'], -4);
        unset($target['national_id']);
        $history = $this->enrollments->listForStudent($this->session->get('school_id'), $studentId);
        foreach ($history as &$enrollment) {
            $enrollment['placements'] = $this->placements->listForEnrollment($this->session->get('school_id'), $enrollment['id']);
        }
        unset($enrollment);

        return new Response($this->page('students/show', [
            'target' => $target, 'maskedNationalId' => $maskedNationalId, 'canManage' => $this->can('STUDENT_MANAGE'),
            'history' => $history, 'canManageEnrollment' => $this->can('ENROLLMENT_MANAGE'),
        ], 'รายละเอียดนักเรียน'));
    }

    public function edit(int $studentId): Response
    {
        $target = $this->students->findForSchool($this->session->get('school_id'), $studentId);
        if ($target === null) {
            return new Response(View::error(404), 404);
        }

        return new Response($this->page('students/edit', [
            'target' => $target, 'csrfToken' => $this->csrf->token($this->session),
            'canView' => $this->can('STUDENT_VIEW'), 'error' => null,
        ], 'แก้ไขข้อมูลนักเรียน'));
    }

    public function update(Request $request, int $studentId): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        try {
            $values = $this->details($request);
            $this->administration->updateStudent(
                $this->session->get('school_id'), $this->session->get('user_id'), $studentId,
                ...[...$values, $this->ipAddress($request)]
            );
        } catch (DomainException $exception) {
            return $this->mutationError($exception->getMessage());
        }

        return Response::redirect('/students/' . $studentId . '/edit');
    }

    public function changeStatus(Request $request, int $studentId): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        $status = $request->post('status');
        if (!is_string($status)) {
            return $this->mutationError('กรุณาระบุสถานะนักเรียนเป็นข้อความ');
        }
        try {
            $this->administration->changeStatus(
                $this->session->get('school_id'), $this->session->get('user_id'), $studentId,
                $status, $this->ipAddress($request)
            );
        } catch (DomainException $exception) {
            return $this->mutationError($exception->getMessage());
        }

        return Response::redirect('/students/' . $studentId . '/edit');
    }

    private function details(Request $request): array
    {
        $values = [];
        foreach (['student_code', 'national_id', 'prefix_th', 'first_name_th', 'last_name_th', 'gender_code', 'birth_date'] as $field) {
            $value = $request->post($field);
            $optional = in_array($field, ['national_id', 'gender_code', 'birth_date'], true);
            if (!is_string($value) && !($optional && $value === null)) {
                throw new DomainException('กรุณาระบุข้อมูลนักเรียนเป็นข้อความ');
            }
            $values[] = $value;
        }

        return $values;
    }

    private function search(Request $request): ?string
    {
        $search = $request->query('q');
        if ($search === null) {
            return null;
        }
        if (!is_string($search) || !mb_check_encoding($search, 'UTF-8') || preg_match('/\p{Cc}/u', $search)) {
            throw new DomainException('คำค้นต้องเป็นข้อความ UTF-8 ที่ไม่มีอักขระควบคุม');
        }
        $search = preg_replace('/\A\s+|\s+\z/u', '', $search);
        if (mb_strlen($search, 'UTF-8') > 100) {
            throw new DomainException('คำค้นต้องไม่เกิน 100 ตัวอักษร');
        }

        return $search === '' ? null : $search;
    }

    private function can(string $permission): bool
    {
        return $this->authorization->hasPermission(
            $this->session->get('user_id'), AccessContext::SCHOOL, $this->session->get('school_id'), $permission
        );
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

    private function createForm(?string $error = null, int $status = 200): Response
    {
        return new Response($this->page('students/create', [
            'csrfToken' => $this->csrf->token($this->session), 'canView' => $this->can('STUDENT_VIEW'), 'error' => $error,
        ], 'เพิ่มนักเรียน'), $status);
    }

    private function mutationError(string $error): Response
    {
        return new Response($this->page('students/edit', [
            'target' => null, 'canView' => $this->can('STUDENT_VIEW'), 'error' => $error,
        ], 'แก้ไขข้อมูลนักเรียน'), 422);
    }

    private function page(string $template, array $data, string $title): string
    {
        return View::page($template, $data, [
            'ui' => $this->ui->build('students'), 'documentTitle' => $title.' — ปพ.5', 'pageTitle' => $title,
        ]);
    }
}
