<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Repositories\RoleAssignmentRepository;
use App\Repositories\RoleRepository;
use App\Repositories\SchoolMembershipRepository;
use App\Services\SchoolUserAdministrationService;
use App\Support\Csrf;
use App\Support\View;
use DomainException;

final class SchoolUserController
{
    public function __construct(
        private SchoolUserAdministrationService $administration,
        private SchoolMembershipRepository $memberships,
        private RoleRepository $roles,
        private RoleAssignmentRepository $assignments,
        private Session $session,
        private Csrf $csrf
    ) {}

    public function index(): Response
    {
        $schoolId = $this->session->get('school_id');
        $members = $this->memberships->listForSchool($schoolId);
        foreach ($members as &$member) {
            $member['role_codes'] = $this->assignments->activeSchoolRoleCodes((int) $member['user_id'], $schoolId);
        }
        unset($member);

        return new Response(View::render('admin/users/index', [
            'members' => $members,
            'displayName' => (string) $this->session->get('display_name', ''),
            'csrfToken' => $this->csrf->token($this->session),
        ]));
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
        $valid = true;
        foreach (['username', 'display_name', 'email'] as $field) {
            $value = $request->post($field, '');
            if (!is_string($value)) {
                $valid = false;
                continue;
            }
            $values[$field] = $value;
        }
        $roleCodes = $request->post('role_codes', []);
        if (is_array($roleCodes)) {
            $values['role_codes'] = array_values(array_filter($roleCodes, 'is_string'));
        }
        $password = $request->post('password', '');
        if (!$valid || !is_string($password) || !is_array($roleCodes)) {
            return $this->createForm($values, 'ข้อมูลผู้ใช้หรือบทบาทไม่ถูกต้อง', 422);
        }
        try {
            $this->administration->createUser(
                $this->session->get('school_id'), $this->session->get('user_id'),
                $values['username'], $values['display_name'], $values['email'], $password,
                $roleCodes, $this->ipAddress($request)
            );
        } catch (DomainException $exception) {
            return $this->createForm($values, $exception->getMessage(), 422);
        }

        return Response::redirect('/admin/users');
    }

    public function edit(int $userId): Response
    {
        $schoolId = $this->session->get('school_id');
        $target = $this->memberships->findForSchoolUser($schoolId, $userId);
        if ($target === null) {
            return new Response(View::render('errors/404'), 404);
        }

        return new Response(View::render('admin/users/edit', [
            'target' => $target,
            'roles' => $this->roles->listActiveSchoolRoles(),
            'roleCodes' => $this->assignments->activeSchoolRoleCodes($userId, $schoolId),
            'csrfToken' => $this->csrf->token($this->session),
            'error' => null,
        ]));
    }

    public function updateProfile(Request $request, int $userId): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        $displayName = $request->post('display_name', '');
        $email = $request->post('email', '');
        if (!is_string($displayName) || !is_string($email)) {
            return $this->mutationError('ข้อมูลผู้ใช้ไม่ถูกต้อง');
        }
        try {
            $this->administration->updateProfile($this->session->get('school_id'), $this->session->get('user_id'),
                $userId, $displayName, $email, $this->ipAddress($request));
        } catch (DomainException $exception) {
            return $this->mutationError($exception->getMessage());
        }

        return Response::redirect('/admin/users/' . $userId . '/edit');
    }

    public function changeMembershipStatus(Request $request, int $userId): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        $status = $request->post('status');
        if (!is_string($status)) {
            return $this->mutationError('สถานะสมาชิกไม่ถูกต้อง');
        }
        try {
            $this->administration->changeMembershipStatus($this->session->get('school_id'), $this->session->get('user_id'),
                $userId, $status, $this->ipAddress($request));
        } catch (DomainException $exception) {
            return $this->mutationError($exception->getMessage());
        }

        return Response::redirect('/admin/users/' . $userId . '/edit');
    }

    public function replaceRoles(Request $request, int $userId): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        $roleCodes = $request->post('role_codes', []);
        if (!is_array($roleCodes)) {
            return $this->mutationError('ข้อมูลบทบาทไม่ถูกต้อง');
        }
        try {
            $this->administration->replaceRoles($this->session->get('school_id'), $this->session->get('user_id'),
                $userId, $roleCodes, $this->ipAddress($request));
        } catch (DomainException $exception) {
            return $this->mutationError($exception->getMessage());
        }

        return Response::redirect('/admin/users/' . $userId . '/edit');
    }

    public function resetPassword(Request $request, int $userId): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        $password = $request->post('password', '');
        if (!is_string($password)) {
            return $this->mutationError('ข้อมูลรหัสผ่านไม่ถูกต้อง');
        }
        try {
            $this->administration->resetPassword($this->session->get('school_id'), $this->session->get('user_id'),
                $userId, $password, $this->ipAddress($request));
        } catch (DomainException $exception) {
            return $this->mutationError($exception->getMessage());
        }

        return Response::redirect('/admin/users/' . $userId . '/edit');
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
        return new Response(View::render('admin/users/create', [
            'values' => $values,
            'roles' => $this->roles->listActiveSchoolRoles(),
            'csrfToken' => $this->csrf->token($this->session),
            'error' => $error,
        ]), $status);
    }

    private function mutationError(string $error): Response
    {
        // Mutation permissions do not grant permission to reload and view the target.
        return new Response(View::render('admin/users/edit', ['target' => null, 'error' => $error]), 422);
    }
}
