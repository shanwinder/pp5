<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Services\AuthenticationService;
use App\Support\AccessContext;
use App\Support\Csrf;
use App\Support\View;
use DomainException;

final class AuthController
{
    public function __construct(
        private AuthenticationService $auth,
        private Session $session,
        private Csrf $csrf
    ) {}

    public function showLogin(): Response
    {
        return $this->loginForm();
    }

    public function login(Request $request): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }

        $username = $request->post('username', '');
        $password = $request->post('password', '');
        if (!is_string($username) || !is_string($password)) {
            return $this->loginForm('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', 422);
        }

        try {
            $result = $this->auth->attempt($username, $password);
        } catch (DomainException $exception) {
            return $this->loginForm($exception->getMessage(), 422);
        }

        $this->session->regenerate();
        $this->session->put('user_id', $result['user_id']);
        $this->session->put('context_type', $result['context_type']);
        $this->session->put('display_name', $result['display_name']);
        $this->session->put('last_activity', time());

        if ($result['context_type'] === AccessContext::SYSTEM) {
            $this->session->forget('school_id');
            $this->session->forget('school_membership_id');

            return Response::redirect('/system/schools');
        }

        $this->session->put('school_id', $result['school_id']);
        $this->session->put('school_membership_id', $result['school_membership_id']);

        return Response::redirect('/dashboard');
    }

    public function logout(Request $request): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }

        $this->session->clear();
        $this->session->regenerate();

        return Response::redirect('/login');
    }

    private function validCsrf(Request $request): bool
    {
        $token = $request->post('_token');

        return $this->csrf->verify($this->session, is_string($token) ? $token : null);
    }

    private function loginForm(?string $error = null, int $status = 200): Response
    {
        return new Response(View::render('auth/login', [
            'csrfToken' => $this->csrf->token($this->session),
            'error' => $error,
        ]), $status);
    }
}
