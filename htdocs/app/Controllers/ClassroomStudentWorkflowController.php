<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Services\AppUiContextService;
use App\Services\ClassroomRosterReadService;
use App\Services\ClassroomStudentContextReadService;
use App\Services\EnrollmentAdministrationService;
use App\Support\Csrf;
use App\Support\View;
use DomainException;

/** Thin workspace adapter. All domain writes and audit events belong to EnrollmentAdministrationService. */
final class ClassroomStudentWorkflowController
{
    public function __construct(
        private ClassroomStudentContextReadService $contexts,
        private ClassroomRosterReadService $rosters,
        private EnrollmentAdministrationService $administration,
        private Session $session,
        private Csrf $csrf,
        private AppUiContextService $ui
    ) {}

    public function panel(int $classroomId, int $enrollmentId): Response
    {
        $context = $this->context($classroomId, $enrollmentId);
        if ($context === null) { return new Response(View::error(404), 404); }
        return new Response($this->panelHtml($context));
    }

    public function placement(Request $request, int $classroomId, int $enrollmentId): Response
    {
        return $this->mutate($request, $classroomId, $enrollmentId, 'placement');
    }

    public function status(Request $request, int $classroomId, int $enrollmentId): Response
    {
        return $this->mutate($request, $classroomId, $enrollmentId, 'status');
    }

    private function mutate(Request $request, int $classroomId, int $enrollmentId, string $kind): Response
    {
        $context = $this->context($classroomId, $enrollmentId);
        if ($context === null) { return new Response(View::error(404), 404); }
        $roster = $context['roster'];
        $token = $request->post('_token');
        if (!$this->csrf->verify($this->session, is_string($token) ? $token : null)) {
            return $this->workspace($request, $roster, $context, 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง', null, 419);
        }
        if (!$roster['canManage']) {
            return $this->workspace($request, $roster, $context, 'ไม่สามารถดำเนินการในขณะนี้ กรุณาโหลดหน้าใหม่', null, 403);
        }
        try {
            if ($kind === 'placement') {
                $targetId = $this->positiveId($request->post('classroom_id'));
                $choice = null;
                foreach ($context['rooms'] as $room) {
                    if ((int) $room['id'] === $targetId) { $choice = $room; break; }
                }
                if ($choice === null) { throw new DomainException('ห้องเรียนไม่ถูกต้อง'); }
                $this->administration->changePlacement($this->school(), $this->user(), $enrollmentId, $targetId,
                    $this->ipAddress($request), $classroomId);
                $message = 'ย้าย ' . $context['student']['name'] . ' ไป ' . $choice['name_th'] . ' เรียบร้อยแล้ว';
            } else {
                $status = $request->post('status');
                if (!is_string($status) || !in_array($status, ['TRANSFERRED_OUT', 'WITHDRAWN'], true)) {
                    throw new DomainException('สถานะไม่ถูกต้อง');
                }
                $exitDate = $request->post('exit_date');
                if (!is_string($exitDate) || $exitDate === '') { throw new DomainException('วันที่ไม่ถูกต้อง'); }
                $this->administration->changeStatus($this->school(), $this->user(), $enrollmentId, $status,
                    $exitDate, $this->ipAddress($request), $classroomId);
                $message = 'บันทึกสถานะ' . ($status === 'TRANSFERRED_OUT' ? 'ย้ายออก' : 'ลาออก') . 'ให้ ' . $context['student']['name'] . ' เรียบร้อยแล้ว';
            }
        } catch (DomainException) {
            $fresh = $this->context($classroomId, $enrollmentId);
            $roster = $this->roster($classroomId) ?? $roster;
            return $this->workspace($request, $roster, $fresh,
                'บันทึกไม่สำเร็จ กรุณาตรวจสอบข้อมูลและลองอีกครั้ง หากข้อมูลเปลี่ยนไปให้โหลดหน้าใหม่', null, 422);
        }
        $freshRoster = $this->roster($classroomId);
        if ($freshRoster === null) { return new Response(View::error(404), 404); }
        return $this->workspace($request, $freshRoster, null, null, $message, 200);
    }

    private function workspace(Request $request, array $roster, ?array $context, ?string $error, ?string $success, int $status): Response
    {
        $data = $roster + ['selectedPanel' => $context === null ? null : $this->panelHtml($context, $error),
            'workflowError' => $context === null ? $error : null, 'workflowSuccess' => $success];
        if ($request->server('HTTP_HX_REQUEST') === 'true') {
            return new Response(View::render('workspaces/classroom/students', $data), $status);
        }
        if ($success !== null) {
            return Response::redirect('/workspaces/classrooms/' . $roster['workspace']['classroom']['id'] . '/students');
        }
        return new Response(View::page('workspaces/classroom/students', $data, [
            'ui' => $this->ui->build('workspaces.classrooms.students', false, $roster['workspace']),
            'documentTitle' => 'นักเรียนในห้อง — ระบบ ปพ.5',
            'pageTitle' => 'นักเรียน · ' . $roster['workspace']['classroom']['name'],
            'bodyClass' => 'pp5-classroom-pilot',
            'headAssets' => View::render('workspaces/classroom/student-assets'),
        ]), $status);
    }

    private function panelHtml(array $context, ?string $error = null): string
    {
        return View::render('workspaces/classroom/student-panel', $context + [
            'csrfToken' => $this->csrf->token($this->session), 'error' => $error,
        ]);
    }

    private function context(int $classroomId, int $enrollmentId): ?array
    {
        return $this->contexts->get($this->user(), $this->session->get('context_type'), $this->school(), $classroomId, $enrollmentId);
    }

    private function roster(int $classroomId): ?array
    {
        return $this->rosters->getRoster($this->user(), $this->session->get('context_type'), $this->school(), $classroomId);
    }

    private function positiveId(mixed $value): int
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/\A[0-9]+\z/', (string) $value)) {
            throw new DomainException('รายการไม่ถูกต้อง');
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) { throw new DomainException('รายการไม่ถูกต้อง'); }
        return $id;
    }

    private function school(): int { return $this->session->get('school_id'); }
    private function user(): int { return $this->session->get('user_id'); }
    private function ipAddress(Request $request): ?string
    {
        $address = $request->server('REMOTE_ADDR');
        return is_string($address) && filter_var($address, FILTER_VALIDATE_IP) !== false ? $address : null;
    }
}
