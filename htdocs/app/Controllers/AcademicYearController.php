<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Repositories\AcademicYearRepository;
use App\Services\AcademicYearAdministrationService;
use App\Support\Csrf;
use App\Support\View;
use DomainException;

final class AcademicYearController
{
    public function __construct(
        private AcademicYearAdministrationService $administration,
        private AcademicYearRepository $years,
        private Session $session,
        private Csrf $csrf
    ) {}

    public function index(): Response
    {
        return new Response(View::render('academic/years/index', [
            'years' => $this->years->listForSchool($this->session->get('school_id')),
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
            $values = $this->formValues($request);
            $this->administration->createYear(
                $this->session->get('school_id'), $this->session->get('user_id'),
                $this->yearBe($values['year_be']), $values['start_date'], $values['end_date'],
                $this->ipAddress($request)
            );
        } catch (DomainException $exception) {
            return $this->createForm($values, $exception->getMessage(), 422);
        }

        return Response::redirect('/academic/years');
    }

    public function edit(int $academicYearId): Response
    {
        $target = $this->years->findForSchool($this->session->get('school_id'), $academicYearId);
        if ($target === null) {
            return new Response(View::render('errors/404'), 404);
        }

        return new Response(View::render('academic/years/edit', [
            'target' => $target,
            'csrfToken' => $this->csrf->token($this->session),
            'error' => null,
        ]));
    }

    public function update(Request $request, int $academicYearId): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        try {
            $values = $this->formValues($request);
            $this->administration->updateYear(
                $this->session->get('school_id'), $this->session->get('user_id'), $academicYearId,
                $this->yearBe($values['year_be']), $values['start_date'], $values['end_date'],
                $this->ipAddress($request)
            );
        } catch (DomainException $exception) {
            return $this->mutationError($exception->getMessage());
        }

        return Response::redirect('/academic/years/' . $academicYearId . '/edit');
    }

    public function changeStatus(Request $request, int $academicYearId): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        $status = $request->post('status');
        if (!is_string($status)) {
            return $this->mutationError('สถานะปีการศึกษาไม่ถูกต้อง');
        }
        try {
            $this->administration->changeStatus(
                $this->session->get('school_id'), $this->session->get('user_id'), $academicYearId,
                $status, $this->ipAddress($request)
            );
        } catch (DomainException $exception) {
            return $this->mutationError($exception->getMessage());
        }

        return Response::redirect('/academic/years/' . $academicYearId . '/edit');
    }

    /** @return array{year_be: string, start_date: ?string, end_date: ?string} */
    private function formValues(Request $request): array
    {
        $yearBe = $request->post('year_be');
        $startDate = $request->post('start_date');
        $endDate = $request->post('end_date');
        if (!is_string($yearBe)) {
            throw new DomainException('กรุณาระบุปีการศึกษา พ.ศ. เป็นจำนวนเต็ม');
        }
        if (($startDate !== null && !is_string($startDate)) || ($endDate !== null && !is_string($endDate))) {
            throw new DomainException('กรุณาระบุวันที่เป็นข้อความรูปแบบ YYYY-MM-DD หรือเว้นว่าง');
        }

        return ['year_be' => $yearBe, 'start_date' => $startDate, 'end_date' => $endDate];
    }

    private function yearBe(string $value): int
    {
        $year = filter_var($value, FILTER_VALIDATE_INT);
        if (!preg_match('/\A[0-9]+\z/', $value) || $year === false) {
            throw new DomainException('กรุณาระบุปีการศึกษา พ.ศ. เป็นจำนวนเต็ม');
        }

        return $year;
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
        return new Response(View::render('academic/years/create', [
            'values' => $values,
            'csrfToken' => $this->csrf->token($this->session),
            'error' => $error,
        ]), $status);
    }

    private function mutationError(string $error): Response
    {
        return new Response(View::render('academic/years/edit', ['target' => null, 'error' => $error]), 422);
    }
}
