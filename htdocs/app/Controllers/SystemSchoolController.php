<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Repositories\SchoolRepository;
use App\Services\SystemSchoolAdministrationService;
use App\Support\Csrf;
use App\Support\View;
use DomainException;

final class SystemSchoolController
{
    public function __construct(
        private SystemSchoolAdministrationService $administration,
        private SchoolRepository $schools,
        private Session $session,
        private Csrf $csrf
    ) {}

    public function index(): Response
    {
        return $this->schoolList();
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

        $values = [];
        foreach (['school_code', 'name_th', 'admin_username', 'admin_display_name', 'admin_email'] as $field) {
            $value = $request->post($field, '');
            if (!is_string($value)) {
                return $this->createForm($values, 'ข้อมูลโรงเรียนหรือผู้ดูแลไม่ถูกต้อง', 422);
            }
            $values[$field] = $value;
        }
        $password = $request->post('admin_password', '');
        if (!is_string($password)) {
            return $this->createForm($values, 'ข้อมูลรหัสผ่านไม่ถูกต้อง', 422);
        }

        try {
            $this->administration->createSchoolWithAdmin(
                $values['school_code'], $values['name_th'], $values['admin_username'],
                $values['admin_display_name'], $values['admin_email'], $password,
                $this->session->get('user_id'), $this->ipAddress($request)
            );
        } catch (DomainException $exception) {
            return $this->createForm($values, $exception->getMessage(), 422);
        }

        return Response::redirect('/system/schools');
    }

    public function changeStatus(Request $request, int $schoolId): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        $status = $request->post('status');
        if (!is_string($status)) {
            return $this->schoolList('สถานะโรงเรียนไม่ถูกต้อง', 422);
        }
        try {
            $this->administration->changeSchoolStatus($schoolId, $status, $this->session->get('user_id'), $this->ipAddress($request));
        } catch (DomainException $exception) {
            return $this->schoolList($exception->getMessage(), 422);
        }

        return Response::redirect('/system/schools');
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

    private function schoolList(?string $error = null, int $status = 200): Response
    {
        return new Response(View::render('system/schools/index', [
            'schools' => $error === null ? $this->schools->all() : null,
            'displayName' => (string) $this->session->get('display_name', ''),
            'csrfToken' => $this->csrf->token($this->session),
            'error' => $error,
        ]), $status);
    }

    private function createForm(array $values = [], ?string $error = null, int $status = 200): Response
    {
        return new Response(View::render('system/schools/create', [
            'values' => $values,
            'csrfToken' => $this->csrf->token($this->session),
            'error' => $error,
        ]), $status);
    }
}
