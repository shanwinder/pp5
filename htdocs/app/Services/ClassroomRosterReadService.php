<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\StudentEnrollmentRepository;
use App\Support\AccessContext;

/** Classroom presentation only; mutations continue through EnrollmentAdministrationService. */
final class ClassroomRosterReadService
{
    public function __construct(
        private ClassroomWorkspaceReadService $workspaces,
        private StudentEnrollmentRepository $enrollments,
        private AuthorizationService $authorization
    ) {}

    public function getRoster(int $userId, string $contextType, int $schoolId, int $classroomId): ?array
    {
        // Check roster authority before composing context or reading any student rows.
        if ($contextType !== AccessContext::SCHOOL || $userId <= 0 || $schoolId <= 0 || $classroomId <= 0
            || !$this->authorization->hasPermission($userId, $contextType, $schoolId, 'STUDENT_VIEW')) {
            return null;
        }
        $workspace = $this->workspaces->getOverview($userId, $contextType, $schoolId, $classroomId);
        if ($workspace === null) { return null; }

        // Existing tenant/year/grade joins and ACTIVE placement semantics, with no profile PII.
        $rows = $this->enrollments->listForSchoolYear($schoolId, $workspace['academicYear']['id'],
            $workspace['gradeLevel']['id'], $workspace['classroom']['id'], 'ACTIVE');
        $students = array_map(static fn (array $row): array => [
            'studentId' => (int) $row['student_id'], 'enrollmentId' => (int) $row['id'],
            'code' => $row['student_code'],
            'name' => implode(' ', [$row['prefix_th'], $row['first_name_th'], $row['last_name_th']]),
            'status' => $row['status'],
        ], $rows);
        // Presentation hints only. The existing forms/services recheck live authority and lifecycle.
        $openYear = in_array($workspace['academicYear']['status'], ['DRAFT', 'ACTIVE'], true);
        $canManage = $openYear && $this->authorization->hasPermission($userId, $contextType, $schoolId, 'ENROLLMENT_MANAGE');
        $canAdd = $canManage && $workspace['classroom']['status'] === 'ACTIVE';
        $canImport = $openYear && $workspace['classroom']['status'] === 'ACTIVE'
            && $this->authorization->hasPermission($userId, $contextType, $schoolId, 'STUDENT_IMPORT');

        return compact('workspace', 'students', 'openYear', 'canManage', 'canAdd', 'canImport');
    }
}
