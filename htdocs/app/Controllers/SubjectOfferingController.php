<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Repositories\AcademicYearRepository;
use App\Repositories\ClassroomRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\SubjectOfferingRepository;
use App\Services\SubjectOfferingAdministrationService;
use App\Support\Csrf;
use App\Support\View;
use DomainException;

final class SubjectOfferingController
{
    public function __construct(
        private SubjectOfferingAdministrationService $administration,
        private SubjectOfferingRepository $offerings,
        private AcademicYearRepository $years,
        private ClassroomRepository $classrooms,
        private SubjectRepository $subjects,
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

        return new Response(View::render('academic/offerings/index', [
            'offerings' => $this->offerings->listForSchool($this->session->get('school_id'), $year['id'] ?? null),
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
            $this->administration->createOffering(
                $this->session->get('school_id'), $this->session->get('user_id'), $values['academic_year_id'],
                $values['classroom_id'], $values['subject_id'], $values['term_no'], $this->ipAddress($request)
            );
        } catch (DomainException $exception) {
            return $this->createForm($values, $exception->getMessage(), 422);
        }

        return Response::redirect('/academic/offerings');
    }

    public function edit(int $offeringId): Response
    {
        $target = $this->offerings->findForSchool($this->session->get('school_id'), $offeringId);
        if ($target === null) {
            return new Response(View::render('errors/404'), 404);
        }

        return new Response(View::render('academic/offerings/edit', [
            'target' => $target,
            'classrooms' => $this->activeClassrooms($target['academic_year_id']),
            'subjects' => $this->activeSubjects(),
            'csrfToken' => $this->csrf->token($this->session),
            'error' => null,
        ]));
    }

    public function update(Request $request, int $offeringId): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        try {
            $values = $this->details($request);
            $this->administration->updateOffering(
                $this->session->get('school_id'), $this->session->get('user_id'), $offeringId,
                $values['classroom_id'], $values['subject_id'], $values['term_no'], $this->ipAddress($request)
            );
        } catch (DomainException $exception) {
            return $this->mutationError($exception->getMessage());
        }

        return Response::redirect('/academic/offerings/' . $offeringId . '/edit');
    }

    public function changeStatus(Request $request, int $offeringId): Response
    {
        if (!$this->validCsrf($request)) {
            return new Response('CSRF token mismatch', 419);
        }
        $status = $request->post('status');
        if (!is_string($status)) {
            return $this->mutationError('สถานะการเปิดรายวิชาไม่ถูกต้อง');
        }
        try {
            $this->administration->changeStatus(
                $this->session->get('school_id'), $this->session->get('user_id'), $offeringId,
                $status, $this->ipAddress($request)
            );
        } catch (DomainException $exception) {
            return $this->mutationError($exception->getMessage());
        }

        return Response::redirect('/academic/offerings/' . $offeringId . '/edit');
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
            throw new DomainException('กรุณาเลือกปีการศึกษา ห้องเรียน รายวิชา และภาคเรียนให้ถูกต้อง');
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new DomainException('กรุณาเลือกปีการศึกษา ห้องเรียน รายวิชา และภาคเรียนให้ถูกต้อง');
        }

        return $id;
    }

    private function details(Request $request): array
    {
        return [
            'classroom_id' => $this->positiveId($request->post('classroom_id')),
            'subject_id' => $this->positiveId($request->post('subject_id')),
            'term_no' => $this->positiveId($request->post('term_no')),
        ];
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

    private function activeClassrooms(int $yearId): array
    {
        return array_values(array_filter($this->classrooms->listForSchool($this->session->get('school_id'), $yearId),
            static fn (array $room): bool => $room['status'] === 'ACTIVE'));
    }

    private function activeSubjects(): array
    {
        return array_values(array_filter($this->subjects->listForSchool($this->session->get('school_id')),
            static fn (array $subject): bool => $subject['status'] === 'ACTIVE'));
    }

    private function createForm(array $values = [], ?string $error = null, int $status = 200): Response
    {
        $years = array_values(array_filter($this->years->listForSchool($this->session->get('school_id')),
            static fn (array $year): bool => in_array($year['status'], ['DRAFT', 'ACTIVE'], true)));
        $selectedYear = null;
        foreach ($years as $year) {
            if ($year['id'] === ($values['academic_year_id'] ?? null)) {
                $selectedYear = $year;
                break;
            }
        }

        return new Response(View::render('academic/offerings/create', [
            'values' => $values,
            'years' => $years,
            'selectedYear' => $selectedYear,
            'classrooms' => $selectedYear === null ? [] : $this->activeClassrooms($selectedYear['id']),
            'subjects' => $this->activeSubjects(),
            'csrfToken' => $this->csrf->token($this->session),
            'error' => $error,
        ]), $status);
    }

    private function mutationError(string $error): Response
    {
        return new Response(View::render('academic/offerings/edit', ['target' => null, 'error' => $error]), 422);
    }
}
