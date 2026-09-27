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

        $capabilities = $this->capabilities($userId, $contextType, $schoolId);
        $accessible = $this->gradebooks->listAccessibleOfferings($userId, $contextType, $schoolId);
        $gradebooks = [];
        // Reuse the live offering checks, including historical read access. Do not load a roster or scores.
        foreach ($accessible as $offering) {
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
        $yearQuery = http_build_query(['academic_year_id' => $classroom['academic_year_id'], 'workspace_classroom_id' => $classroom['id']]);
        $links = [];
        if ($capabilities['students']) {
            $links[] = ['key' => 'students', 'label' => 'ดูนักเรียนในห้องนี้', 'url' => '/academic/enrollments?' . http_build_query([
                'academic_year_id' => $classroom['academic_year_id'], 'grade_level_id' => $classroom['grade_level_id'],
                'classroom_id' => $classroom['id'], 'workspace_classroom_id' => $classroom['id'],
            ])];
        }
        if ($capabilities['subjects']) {
            $links[] = ['key' => 'subjects', 'label' => 'ดูรายวิชาในปีการศึกษานี้', 'url' => '/academic/offerings?' . $yearQuery];
        }
        if ($capabilities['teaching']) {
            $links[] = ['key' => 'teaching', 'label' => 'ดูครูผู้สอนในปีการศึกษานี้', 'url' => '/academic/teaching-assignments?' . $yearQuery];
        }

        // Explicit fields keep unrelated metadata and student PII outside this read model.
        return [
            'school' => ['id' => (int) $school['id'], 'name' => $school['name_th']],
            'classroom' => ['id' => (int) $classroom['id'], 'code' => $classroom['code'], 'name' => $classroom['name_th'], 'status' => $classroom['status']],
            'academicYear' => ['id' => (int) $classroom['academic_year_id'], 'year_be' => (int) $classroom['year_be'], 'status' => $classroom['academic_year_status']],
            'gradeLevel' => ['id' => (int) $classroom['grade_level_id'], 'name' => $classroom['grade_level_name']],
            'capabilities' => ['overview' => true] + $capabilities,
            'gradebooks' => $gradebooks, 'links' => $links,
            'switchTargets' => $this->switchTargets($schoolId, $capabilities, $accessible),
        ];
    }

    /** Fresh navigation projection; no roster, counts or per-classroom authorization queries. */
    public function listAccessibleClassrooms(int $userId, string $contextType, int $schoolId): array
    {
        if ($contextType !== AccessContext::SCHOOL || $userId <= 0 || $schoolId <= 0) { return []; }
        $capabilities = $this->capabilities($userId, $contextType, $schoolId);
        $accessible = in_array(true, $capabilities, true) ? []
            : $this->gradebooks->listAccessibleOfferings($userId, $contextType, $schoolId);
        return $this->switchTargets($schoolId, $capabilities, $accessible);
    }

    private function capabilities(int $userId, string $contextType, int $schoolId): array
    {
        $capabilities = [];
        foreach (['students' => 'STUDENT_VIEW', 'subjects' => 'ACADEMIC_SETUP_VIEW', 'teaching' => 'TEACHING_ASSIGNMENT_MANAGE'] as $key => $permission) {
            $capabilities[$key] = $this->authorization->hasPermission($userId, $contextType, $schoolId, $permission);
        }
        return $capabilities;
    }

    private function switchTargets(int $schoolId, array $capabilities, array $accessible): array
    {
        $rooms = [];
        if ($capabilities['students'] || $capabilities['subjects'] || $capabilities['teaching']) {
            foreach ($this->classrooms->listForSchool($schoolId) as $room) {
                $rooms[(int) $room['id']] = ['id' => (int) $room['id'], 'name' => $room['name_th'],
                    'code' => $room['code'], 'year_be' => (int) $room['year_be']];
            }
        } else {
            // The Gradebook projection already enforces school/year/classroom relationships.
            foreach ($accessible as $offering) {
                $rooms[(int) $offering['classroom_id']] = ['id' => (int) $offering['classroom_id'],
                    'name' => $offering['classroom_name'], 'code' => $offering['classroom_code'],
                    'year_be' => (int) $offering['year_be']];
            }
        }
        $rooms = array_values($rooms);
        usort($rooms, static fn (array $a, array $b): int => ($b['year_be'] <=> $a['year_be'])
            ?: strcmp($a['code'], $b['code']) ?: ($a['id'] <=> $b['id']));
        return array_map(static fn (array $room): array => $room + ['url' => '/workspaces/classrooms/' . $room['id']], $rooms);
    }
}
