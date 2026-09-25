<?php
declare(strict_types=1);

namespace App\Services;

use App\Http\Session;
use App\Repositories\SchoolRepository;
use App\Support\AccessContext;
use App\Support\Csrf;
use LogicException;

/** Presentation only. Call after authentication/context middleware; never use this as a route gate. */
final class AppUiContextService
{
    public function __construct(
        private Session $session,
        private SchoolRepository $schools,
        private AuthorizationService $authorization,
        private GradebookReadService $gradebooks,
        private Csrf $csrf
    ) {}

    /**
     * Build afresh on every request. Keys/URLs are server-defined, labels are plain text.
     * No request parameters, role names, permission cache, SQL, or domain writes belong here.
     *
     * @return array{contextType: string, schoolName: ?string, displayName: string, csrfToken: string,
     *     currentKey: string, sections: array, permissions: array<string, bool>, gradebooks: array}
     */
    public function build(string $currentKey, bool $includeGradebooks = false): array
    {
        $userId = $this->session->get('user_id');
        $context = $this->session->get('context_type');
        $schoolId = $this->session->get('school_id');
        if (!is_int($userId) || $userId <= 0
            || !in_array($context, [AccessContext::SCHOOL, AccessContext::SYSTEM], true)
            || ($context === AccessContext::SCHOOL && (!is_int($schoolId) || $schoolId <= 0))
            || ($context === AccessContext::SYSTEM && $schoolId !== null)) {
            throw new LogicException('Authenticated UI context required');
        }
        $school = $context === AccessContext::SCHOOL ? $this->schools->findActiveById($schoolId) : null;
        if ($context === AccessContext::SCHOOL && $school === null) {
            throw new LogicException('Active school context required');
        }
        $permissions = $this->permissions($userId, $context, $schoolId);
        $sections = [];
        $gradebooks = [];
        if ($context === AccessContext::SYSTEM) {
            $items = [];
            if ($permissions['SYSTEM_SCHOOL_VIEW']) { $items[] = $this->item('system.schools', 'รายการโรงเรียน', '/system/schools'); }
            if ($permissions['SYSTEM_SCHOOL_CREATE']) { $items[] = $this->item('system.schools.create', 'เพิ่มโรงเรียน', '/system/schools/create'); }
            $this->section($sections, 'schools', 'โรงเรียน', $items);
        } else {
            $this->section($sections, 'overview', 'ภาพรวม', [$this->item('dashboard', 'แดชบอร์ด', '/dashboard')]);
            $items = [];
            if ($permissions['STUDENT_VIEW']) {
                $items[] = $this->item('students', 'รายชื่อนักเรียน', '/students');
                $items[] = $this->item('enrollments', 'การลงทะเบียน', '/academic/enrollments');
            }
            if ($permissions['STUDENT_IMPORT']) { $items[] = $this->item('student-import', 'นำเข้านักเรียน', '/academic/student-import'); }
            $this->section($sections, 'students', 'นักเรียน', $items);
            $items = [];
            if ($permissions['ACADEMIC_SETUP_VIEW']) {
                foreach (['years'=>'ปีการศึกษา', 'classrooms'=>'ห้องเรียน', 'subjects'=>'รายวิชา', 'offerings'=>'การเปิดรายวิชา'] as $key=>$label) {
                    $items[] = $this->item('academic.'.$key, $label, '/academic/'.$key);
                }
            }
            if ($permissions['TEACHING_ASSIGNMENT_MANAGE']) {
                $items[] = $this->item('teaching-assignments', 'การมอบหมายครูประจำวิชา', '/academic/teaching-assignments');
            }
            $this->section($sections, 'academic', 'วิชาการ', $items);
            $items = [];
            // Page and shell reuse one live result within this build, never across requests.
            if ($includeGradebooks) {
                $gradebooks = $this->gradebooks->listAccessibleOfferings($userId, $context, $schoolId);
                $hasGradebooks = $gradebooks !== [];
            } else {
                $hasGradebooks = $this->gradebooks->hasAccessibleOffering($userId, $context, $schoolId);
            }
            if ($hasGradebooks) { $items[] = $this->item('gradebooks', 'สมุดคะแนน', '/gradebooks'); }
            $this->section($sections, 'teaching', 'การเรียนการสอน', $items);
            $this->section($sections, 'management', 'การจัดการ', $permissions['SCHOOL_USER_VIEW']
                ? [$this->item('users', 'ผู้ใช้งาน', '/admin/users')] : []);
        }

        return ['contextType'=>$context, 'schoolName'=>$school['name_th'] ?? null,
            'displayName'=>(string) $this->session->get('display_name', ''), 'csrfToken'=>$this->csrf->token($this->session),
            'currentKey'=>$currentKey, 'sections'=>$sections, 'permissions'=>$permissions, 'gradebooks'=>$gradebooks];
    }

    /** @return array<string, bool> */
    private function permissions(int $userId, string $context, ?int $schoolId): array
    {
        $codes = $context === AccessContext::SYSTEM
            ? ['SYSTEM_SCHOOL_VIEW', 'SYSTEM_SCHOOL_CREATE', 'SYSTEM_SCHOOL_STATUS_MANAGE']
            : ['STUDENT_VIEW', 'STUDENT_IMPORT', 'ACADEMIC_SETUP_VIEW', 'TEACHING_ASSIGNMENT_MANAGE', 'SCHOOL_USER_VIEW', 'ACADEMIC_YEAR_MANAGE',
                'SCHOOL_USER_CREATE', 'SCHOOL_USER_UPDATE', 'SCHOOL_MEMBERSHIP_STATUS_MANAGE', 'SCHOOL_ROLE_MANAGE',
                'SCHOOL_PASSWORD_RESET', 'CLASSROOM_MANAGE', 'SUBJECT_MANAGE', 'SUBJECT_OFFERING_MANAGE'];
        $permissions = [];
        foreach ($codes as $code) {
            $permissions[$code] = $this->authorization->hasPermission($userId, $context, $schoolId, $code);
        }
        return $permissions;
    }

    private function item(string $key, string $label, string $url, ?string $detail = null): array
    {
        return compact('key', 'label', 'url', 'detail');
    }

    private function section(array &$sections, string $key, string $label, array $items): void
    {
        if ($items !== []) { $sections[] = compact('key', 'label', 'items'); }
    }
}
