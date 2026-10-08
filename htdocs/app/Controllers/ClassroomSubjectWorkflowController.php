<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Services\AppUiContextService;
use App\Services\ClassroomOfferingContextReadService;
use App\Services\GradebookComponentService;
use App\Services\TeachingAssignmentService;
use App\Support\Csrf;
use App\Support\View;
use DomainException;

/** Classroom adapter: live context and permissions here, locked domain writes in existing services. */
final class ClassroomSubjectWorkflowController
{
    public function __construct(
        private ClassroomOfferingContextReadService $contexts,
        private TeachingAssignmentService $assignments,
        private GradebookComponentService $components,
        private Session $session,
        private Csrf $csrf,
        private AppUiContextService $ui
    ) {}

    public function panel(int $classroomId, int $offeringId): Response
    {
        $context = $this->context($classroomId, $offeringId);
        return $context === null ? new Response(View::error(404), 404)
            : new Response($this->panelHtml($context));
    }

    public function assignment(Request $request, int $classroomId, int $offeringId): Response
    {
        return $this->mutate($request, $classroomId, $offeringId, 'assignment');
    }

    public function assignmentStatus(Request $request, int $classroomId, int $offeringId, int $assignmentId): Response
    {
        return $this->mutate($request, $classroomId, $offeringId, 'assignmentStatus', $assignmentId);
    }

    public function component(Request $request, int $classroomId, int $offeringId): Response
    {
        return $this->mutate($request, $classroomId, $offeringId, 'component');
    }

    public function componentUpdate(Request $request, int $classroomId, int $offeringId, int $componentId): Response
    {
        return $this->mutate($request, $classroomId, $offeringId, 'componentUpdate', $componentId);
    }

    public function componentStatus(Request $request, int $classroomId, int $offeringId, int $componentId): Response
    {
        return $this->mutate($request, $classroomId, $offeringId, 'componentStatus', $componentId);
    }

    private function mutate(Request $request, int $classroomId, int $offeringId, string $kind, ?int $resourceId = null): Response
    {
        $context = $this->context($classroomId, $offeringId);
        if ($context === null) { return new Response(View::error(404), 404); }
        $token = $request->post('_token');
        if (!$this->csrf->verify($this->session, is_string($token) ? $token : null)) {
            return $this->workspace($request, $context, 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง', null, 419);
        }
        $assignmentAction = str_starts_with($kind, 'assignment');
        if (!($assignmentAction ? $context['canMutateAssignments'] : $context['canMutateComponents'])) {
            return $this->workspace($request, $context, 'ไม่สามารถแก้ไขรายวิชานี้ในขณะนี้', null, 422);
        }
        if ($kind === 'assignmentStatus' && !$this->listedTeacher($context, $resourceId)) {
            return new Response(View::error(404), 404);
        }
        if (in_array($kind, ['componentUpdate', 'componentStatus'], true) && $this->listedComponent($context, $resourceId) === null) {
            return new Response(View::error(404), 404);
        }
        $schoolId = $this->school();
        $actorId = $this->user();
        $ip = $this->ipAddress($request);
        try {
            switch ($kind) {
                case 'assignment':
                    $this->assignments->createAssignment($schoolId, $actorId,
                        $this->positiveId($request->post('user_role_assignment_id')), $offeringId, $ip, $classroomId);
                    $message = 'บันทึกการมอบหมายครูเรียบร้อยแล้ว';
                    break;
                case 'assignmentStatus':
                    if ($request->post('status') !== 'INACTIVE') {
                        throw new DomainException('ไม่พบครูที่กำลังสอนในรายวิชานี้');
                    }
                    $this->assignments->changeStatus($schoolId, $actorId, $resourceId, 'INACTIVE', $ip, $offeringId, $classroomId, 'ACTIVE');
                    $message = 'หยุดการมอบหมายครูเรียบร้อยแล้ว';
                    break;
                case 'component':
                    $this->components->createScoreItem($schoolId, $actorId, $offeringId,
                        $this->field($request, 'name_th'), $this->field($request, 'max_score'), $ip, $classroomId);
                    $message = 'เพิ่มรายการคะแนนเรียบร้อยแล้ว';
                    break;
                case 'componentUpdate':
                    $this->components->updateScoreItem($schoolId, $actorId, $offeringId, $resourceId,
                        $this->field($request, 'name_th'), $this->field($request, 'max_score'), $ip, $classroomId);
                    $message = 'แก้ไขรายการคะแนนเรียบร้อยแล้ว';
                    break;
                case 'componentStatus':
                    $component = $this->listedComponent($context, $resourceId);
                    $status = $request->post('status');
                    if ($component === null || !is_string($status) || !in_array($status, ['ACTIVE', 'INACTIVE'], true)
                        || $component['status'] === $status) { throw new DomainException('สถานะรายการคะแนนไม่ถูกต้อง'); }
                    $this->components->changeStatus($schoolId, $actorId, $offeringId, $resourceId, $status, $ip, $classroomId, $component['status']);
                    $message = $status === 'ACTIVE' ? 'เปิดใช้งานรายการคะแนนเรียบร้อยแล้ว' : 'ปิดใช้งานรายการคะแนนเรียบร้อยแล้ว';
                    break;
                default:
                    throw new DomainException('การทำงานไม่ถูกต้อง');
            }
        } catch (DomainException) {
            $fresh = $this->context($classroomId, $offeringId);
            if ($fresh === null) { return new Response(View::error(404), 404); }
            return $this->workspace($request, $fresh,
                'บันทึกไม่สำเร็จ กรุณาตรวจสอบข้อมูลและลองอีกครั้ง หากข้อมูลเปลี่ยนไปให้โหลดหน้าใหม่', null, 422,
                ['kind' => $kind, 'resourceId' => $resourceId,
                    'name_th' => $request->post('name_th'), 'max_score' => $request->post('max_score')]);
        }
        $fresh = $this->context($classroomId, $offeringId);
        if ($fresh === null) { return new Response(View::error(404), 404); }
        return $this->workspace($request, $fresh, null, $message, 200);
    }

    private function workspace(Request $request, array $context, ?string $error, ?string $success, int $status, array $old = []): Response
    {
        $work = $context['work'];
        $work['selectedPanel'] = $this->panelHtml($context, $error, $success, $old);
        if ($request->server('HTTP_HX_REQUEST') === 'true') {
            return new Response(View::render('workspaces/classroom/subjects', $work), $status);
        }
        $roomId = $work['workspace']['classroom']['id'];
        if ($success !== null) {
            return Response::redirect('/workspaces/classrooms/' . $roomId . '/subjects?offering_id=' . $context['offering']['id']);
        }
        return new Response(View::page('workspaces/classroom/subjects', $work, [
            'ui' => $this->ui->build('workspaces.classrooms.subjects', false, $work['workspace']),
            'headAssets' => View::render('workspaces/classroom/subject-assets'),
            'documentTitle' => 'รายวิชาและครู — ระบบ ปพ.5',
            'pageTitle' => 'รายวิชาและครู · ' . $work['workspace']['classroom']['name'],
        ]), $status);
    }

    public function panelHtml(array $context, ?string $error = null, ?string $success = null, array $old = []): string
    {
        return View::render('workspaces/classroom/subject-panel', $context + [
            'csrfToken' => $this->csrf->token($this->session), 'error' => $error, 'success' => $success, 'old' => $old,
        ]);
    }

    private function context(int $classroomId, int $offeringId): ?array
    {
        return $this->contexts->get($this->user(), (string) $this->session->get('context_type'),
            $this->school(), $classroomId, $offeringId);
    }

    private function listedTeacher(array $context, ?int $id): bool
    {
        foreach ($context['offering']['teachers'] as $teacher) {
            if ($teacher['id'] === $id) { return true; }
        }
        return false;
    }

    private function listedComponent(array $context, ?int $id): ?array
    {
        foreach ($context['components'] as $component) {
            if ((int) $component['id'] === $id) { return $component; }
        }
        return null;
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

    private function field(Request $request, string $name): string
    {
        $value = $request->post($name);
        if (!is_string($value)) { throw new DomainException('กรุณากรอกข้อมูลให้ถูกต้อง'); }
        return $value;
    }

    private function school(): int { return (int) $this->session->get('school_id'); }
    private function user(): int { return (int) $this->session->get('user_id'); }
    private function ipAddress(Request $request): ?string
    {
        $value = $request->server('REMOTE_ADDR');
        return is_string($value) && filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : null;
    }
}
