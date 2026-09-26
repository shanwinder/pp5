<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ClassroomRepository;
use App\Repositories\SchoolRepository;
use App\Support\AccessContext;

/** Read projection over existing domains; capabilities never authorize a write. */
final class ClassroomWorkspaceReadService
{
    public function __construct(
        private ClassroomRepository $classrooms,
        private SchoolRepository $schools,
        private AuthorizationService $authorization,
        private GradebookReadService $gradebooks
    ) {}

    /** Actor/context/school must come from authenticated SchoolContext, never request fields. */
    public function getOverview(int $userId, string $contextType, int $schoolId, int $classroomId): ?array
    {
        if ($contextType !== AccessContext::SCHOOL || $userId <= 0 || $schoolId <= 0 || $classroomId <= 0) {
            return null;
        }
        $classroom = $this->classrooms->findForSchool($schoolId, $classroomId);
        if ($classroom === null) { return null; }

        $capabilities = [];
        foreach (['students' => 'STUDENT_VIEW', 'subjects' => 'ACADEMIC_SETUP_VIEW', 'teaching' => 'TEACHING_ASSIGNMENT_MANAGE'] as $key => $permission) {
            $capabilities[$key] = $this->authorization->hasPermission($userId, $contextType, $schoolId, $permission);
        }
        $gradebooks = [];
        // Reuse the live offering checks, including historical read access. Do not load a roster or scores.
        foreach ($this->gradebooks->listAccessibleOfferings($userId, $contextType, $schoolId) as $offering) {
            if ((int) $offering['classroom_id'] !== (int) $classroom['id']) { continue; }
            $gradebooks[] = [
                'id' => (int) $offering['id'], 'subject_code' => $offering['subject_code'],
                'subject_name' => $offering['subject_name'], 'term_no' => (int) $offering['term_no'],
                'status' => $offering['status'],
            ];
        }
        $capabilities['scores'] = $gradebooks !== [];
        if (!in_array(true, $capabilities, true)) { return null; }
        $school = $this->schools->findActiveById((int) $classroom['school_id']);
        if ($school === null) { return null; }

        // Only existing read destinations. Legacy subject/teaching lists are year-wide.
        $yearQuery = http_build_query(['academic_year_id' => $classroom['academic_year_id']]);
        $links = [];
        if ($capabilities['students']) {
            $links[] = ['label' => 'ดูนักเรียนในห้องนี้', 'url' => '/academic/enrollments?' . http_build_query([
                'academic_year_id' => $classroom['academic_year_id'], 'grade_level_id' => $classroom['grade_level_id'],
                'classroom_id' => $classroom['id'],
            ])];
        }
        if ($capabilities['subjects']) {
            $links[] = ['label' => 'ดูรายวิชาในปีการศึกษานี้', 'url' => '/academic/offerings?' . $yearQuery];
        }
        if ($capabilities['teaching']) {
            $links[] = ['label' => 'ดูครูผู้สอนในปีการศึกษานี้', 'url' => '/academic/teaching-assignments?' . $yearQuery];
        }

        // Explicit fields keep unrelated metadata and student PII outside this read model.
        return [
            'school' => ['id' => (int) $school['id'], 'name' => $school['name_th']],
            'classroom' => ['id' => (int) $classroom['id'], 'code' => $classroom['code'], 'name' => $classroom['name_th'], 'status' => $classroom['status']],
            'academicYear' => ['id' => (int) $classroom['academic_year_id'], 'year_be' => (int) $classroom['year_be'], 'status' => $classroom['academic_year_status']],
            'gradeLevel' => ['id' => (int) $classroom['grade_level_id'], 'name' => $classroom['grade_level_name']],
            'capabilities' => ['overview' => true] + $capabilities,
            'gradebooks' => $gradebooks, 'links' => $links,
        ];
    }
}
