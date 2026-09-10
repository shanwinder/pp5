<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Repositories\SubjectRepository;
use App\Services\SubjectAdministrationService;
use App\Support\Csrf;
use App\Support\View;
use DomainException;

final class SubjectController
{
    public function __construct(
        private SubjectAdministrationService $administration,
        private SubjectRepository $subjects,
        private Session $session,
        private Csrf $csrf
    ) {}

    public function index(): Response
    {
        return new Response(View::render('academic/subjects/index', [
            'subjects' => $this->subjects->listForSchool($this->session->get('school_id')),
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
        try {
            $values = $this->details($request);
            $this->administration->createSubject(
                $this->session->get('school_id'), $this->session->get('user_id'),
                $values['code'], $values['name_th'], $this->ipAddress($request)
            );
        } catch (DomainException $exception) {
            return $this->createForm($values, $exception->getMessage(), 422);
        }

        return Response::redirect('/academic/subjects');
    }

    public function edit(int $subjectId): Response
    {
        $target = $this->subjects->findForSchool($this->session->get('school_id'), $subjectId);
        if ($target === null) {
            return new Response(View::render('errors/404'), 404);
        }

        return new Response(View::render('academic/subjects/edit', [
            'target' => $target,
            'csrfToken' => $this->csrf->token($this->session),
            'error' => null,
        ]));
    }

    public function update(Request $request, int $subjectId): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        try {
            $values = $this->details($request);
            $this->administration->updateSubject(
                $this->session->get('school_id'), $this->session->get('user_id'), $subjectId,
                $values['code'], $values['name_th'], $this->ipAddress($request)
            );
        } catch (DomainException $exception) {
            return $this->mutationError($exception->getMessage());
        }

        return Response::redirect('/academic/subjects/' . $subjectId . '/edit');
    }

    public function changeStatus(Request $request, int $subjectId): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        $status = $request->post('status');
        if (!is_string($status)) {
            return $this->mutationError('สถานะรายวิชาไม่ถูกต้อง');
        }
        try {
            $this->administration->changeStatus(
                $this->session->get('school_id'), $this->session->get('user_id'), $subjectId,
                $status, $this->ipAddress($request)
            );
        } catch (DomainException $exception) {
            return $this->mutationError($exception->getMessage());
        }

        return Response::redirect('/academic/subjects/' . $subjectId . '/edit');
    }

    private function details(Request $request): array
    {
        $code = $request->post('code');
        $name = $request->post('name_th');
        if (!is_string($code) || !is_string($name)) {
            throw new DomainException('กรุณาระบุรหัสและชื่อรายวิชาเป็นข้อความ');
        }

        return ['code' => $code, 'name_th' => $name];
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
        return new Response(View::render('academic/subjects/create', [
            'values' => $values,
            'csrfToken' => $this->csrf->token($this->session),
            'error' => $error,
        ]), $status);
    }

    private function mutationError(string $error): Response
    {
        return new Response(View::render('academic/subjects/edit', ['target' => null, 'error' => $error]), 422);
    }
}
