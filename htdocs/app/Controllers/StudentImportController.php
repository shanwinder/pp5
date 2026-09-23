<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\{Request, Response, Session};
use App\Repositories\{AcademicYearRepository, StudentImportBatchRepository, StudentImportRowRepository};
use App\Services\StudentImportService;
use App\Support\{CanonicalStudentCsvReader, Csrf, View};
use DomainException;
use Throwable;

final class StudentImportController
{
    public function __construct(
        private StudentImportService $service,
        private StudentImportBatchRepository $batches,
        private StudentImportRowRepository $rows,
        private AcademicYearRepository $years,
        private CanonicalStudentCsvReader $reader,
        private Session $session,
        private Csrf $csrf
    ) {}

    public function index(): Response
    {
        try { $this->service->expirePreviews($this->school()); return $this->form(); }
        catch (DomainException $exception) { return $this->error($exception->getMessage()); }
        catch (Throwable) { return $this->error('ไม่สามารถเปิดหน้านำเข้าได้'); }
    }

    public function preview(Request $request): Response
    {
        if (!$this->validCsrf($request)) { return new Response('CSRF token mismatch', 419); }
        try {
            $year = $this->positiveInt($request->post('academic_year_id'));
            $file = $request->file('student_file');
            if (!is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)
                || !is_string($file['name'] ?? null)) { throw new DomainException('กรุณาเลือกไฟล์ CSV ที่อัปโหลดสมบูรณ์'); }
            $size = $this->positiveInt($file['size'] ?? null);
            $name = basename($file['name']);
            if ($name === '' || !mb_check_encoding($name, 'UTF-8') || preg_match('/\p{Cc}/u', $name) || mb_strlen($name, 'UTF-8') > 190) {
                throw new DomainException('ชื่อไฟล์ไม่ถูกต้องหรือยาวเกิน 190 ตัวอักษร');
            }
            $rows = $this->reader->read($file['tmp_name'], $size);
            $hash = @hash_file('sha256', $file['tmp_name']);
            if ($hash === false) { throw new DomainException('ไม่สามารถอ่านไฟล์นำเข้าได้'); }
            $batchId = $this->service->preview($this->school(), $this->session->get('user_id'), $year, $name, $hash, $rows, $this->ip($request));
        } catch (DomainException $exception) { return $this->error($exception->getMessage()); }
        catch (Throwable) { return $this->error('ไม่สามารถอ่านไฟล์นำเข้าได้'); }
        return Response::redirect('/academic/student-import/' . $batchId);
    }

    public function show(int $batchId): Response
    {
        try {
            $this->service->expirePreviews($this->school());
            $batch = $this->batches->findForSchool($this->school(), $batchId);
            if ($batch === null) { return new Response(View::error(404), 404); }
            return new Response(View::render('academic/student-import/preview', [
                'batch' => $batch, 'rows' => $this->rows->listForBatch($this->school(), $batchId),
                'csrfToken' => $this->csrf->token($this->session), 'error' => null,
            ]));
        } catch (DomainException $exception) { return $this->error($exception->getMessage()); }
        catch (Throwable) { return $this->error('ไม่สามารถอ่านรายการนำเข้าได้'); }
    }

    public function apply(Request $request, int $batchId): Response
    {
        if (!$this->validCsrf($request)) { return new Response('CSRF token mismatch', 419); }
        try { $this->service->apply($this->school(), $this->session->get('user_id'), $batchId, $this->ip($request)); }
        catch (DomainException $exception) { return $this->error($exception->getMessage()); }
        return Response::redirect('/academic/student-import/' . $batchId);
    }

    public function cancel(Request $request, int $batchId): Response
    {
        if (!$this->validCsrf($request)) { return new Response('CSRF token mismatch', 419); }
        try { $this->service->cancel($this->school(), $this->session->get('user_id'), $batchId); }
        catch (DomainException $exception) { return $this->error($exception->getMessage()); }
        return Response::redirect('/academic/student-import/' . $batchId);
    }

    private function form(): Response
    {
        return new Response(View::render('academic/student-import/index', [
            'years' => array_values(array_filter($this->years->listForSchool($this->school()), static fn ($y) => in_array($y['status'], ['DRAFT', 'ACTIVE'], true))),
            'csrfToken' => $this->csrf->token($this->session),
        ]));
    }
    private function error(string $message): Response
    {
        return new Response(View::render('academic/student-import/preview', ['batch' => null, 'rows' => [], 'error' => $message]), 422);
    }
    private function school(): int { return $this->session->get('school_id'); }
    private function positiveInt(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/\A[0-9]+\z/', (string)$value)) { throw new DomainException('กรุณาระบุข้อมูลนำเข้าที่ถูกต้อง'); }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) { throw new DomainException('กรุณาระบุข้อมูลนำเข้าที่ถูกต้อง'); }
        return $id;
    }
    private function validCsrf(Request $request): bool
    {
        $token = $request->post('_token');
        return $this->csrf->verify($this->session, is_string($token) ? $token : null);
    }
    private function ip(Request $request): ?string
    {
        $ip = $request->server('REMOTE_ADDR');
        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }
}
