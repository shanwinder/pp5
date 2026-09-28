<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\GradebookComponentRepository;
use App\Repositories\SubjectOfferingRepository;
use App\Repositories\TeachingAssignmentRepository;
use App\Support\AccessContext;

/** Composes existing offering and teaching resources for one authorized classroom. */
final class ClassroomSubjectsReadService
{
    public function __construct(
        private ClassroomWorkspaceReadService $workspaces,
        private SubjectOfferingRepository $offerings,
        private TeachingAssignmentRepository $assignments,
        private AuthorizationService $authorization,
        private GradebookComponentRepository $components
    ) {}

    public function getSubjects(int $userId, string $contextType, int $schoolId, int $classroomId): ?array
    {
        if ($contextType !== AccessContext::SCHOOL || $userId <= 0 || $schoolId <= 0 || $classroomId <= 0) {
            return null;
        }
        $workspace = $this->workspaces->getOverview($userId, $contextType, $schoolId, $classroomId);
        if ($workspace === null) { return null; }

        $canViewAll = $workspace['capabilities']['subjects'] || $workspace['capabilities']['teaching'];
        $gradebookIds = array_fill_keys(array_column($workspace['gradebooks'], 'id'), true);
        if (!$canViewAll && $gradebookIds === []) { return null; }

        $canManageOffering = $this->authorization->hasPermission($userId, $contextType, $schoolId, 'SUBJECT_OFFERING_MANAGE');
        $canManageSubjects = $this->authorization->hasPermission($userId, $contextType, $schoolId, 'SUBJECT_MANAGE');
        $canManageAssignment = $workspace['capabilities']['teaching'];
        $canManageComponents = $this->authorization->hasPermission($userId, $contextType, $schoolId, 'GRADEBOOK_COMPONENT_MANAGE');
        $openYear = in_array($workspace['academicYear']['status'], ['DRAFT', 'ACTIVE'], true);
        $rows = $this->offerings->listForClassroom($schoolId, $workspace['academicYear']['id'], $classroomId);
        $rows = array_values(array_filter($rows, static fn (array $row): bool => $canViewAll || isset($gradebookIds[(int) $row['id']])));
        $offeringIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $scoreSummaries = $this->components->summariesForOfferings($schoolId, $offeringIds);
        $teachers = [];
        // Existing Gradebook reads authorize teacher names per offering. Assignment managers also see this data.
        $teacherIds = $canManageAssignment ? $offeringIds : array_values(array_intersect($offeringIds, array_keys($gradebookIds)));
        foreach ($this->assignments->listActiveForOfferings($schoolId, $teacherIds) as $teacher) {
            $teachers[$teacher['subject_offering_id']][] = [
                'id' => $teacher['id'], 'name' => $teacher['display_name'],
            ];
        }

        $offerings = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $offerings[] = [
                'id' => $id, 'subjectId' => (int) $row['subject_id'],
                'code' => $row['subject_code'], 'name' => $row['subject_name'],
                'term' => (int) $row['term_no'], 'status' => $row['status'],
                'scoreSummary' => $scoreSummaries[$id] ?? ['active_count' => 0, 'inactive_count' => 0, 'active_max_total' => '0.00'],
                'teachers' => $teachers[$id] ?? [],
                'teacherNamesVisible' => $canManageAssignment || isset($gradebookIds[$id]),
                'canOpenGradebook' => isset($gradebookIds[$id]),
                'canSetup' => $canManageComponents,
                'canEdit' => $canManageOffering && $openYear,
                'canAssign' => $canManageAssignment && $openYear && $row['status'] === 'ACTIVE',
            ];
        }

        $teacherChoices = $canManageAssignment && $openYear && $offerings !== []
            ? $this->assignments->listSubjectTeachers($schoolId) : [];

        return compact('workspace', 'offerings', 'teacherChoices', 'openYear') + [
            'canOpenOffering' => $canManageOffering && $openYear && $workspace['classroom']['status'] === 'ACTIVE',
            'canManageSubjects' => $canManageSubjects && $openYear,
            'canManageAssignment' => $canManageAssignment,
            'canViewAll' => $canViewAll,
        ];
    }
}
