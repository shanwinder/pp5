<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ClassroomRepository;
use App\Repositories\StudentClassroomPlacementRepository;
use App\Repositories\StudentEnrollmentRepository;

/** A roster-scoped presentation model. The roster's live permission and tenant check runs first. */
final class ClassroomStudentContextReadService
{
    public function __construct(
        private ClassroomRosterReadService $rosters,
        private StudentEnrollmentRepository $enrollments,
        private StudentClassroomPlacementRepository $placements,
        private ClassroomRepository $classrooms
    ) {}

    public function get(int $userId, string $contextType, int $schoolId, int $classroomId, int $enrollmentId): ?array
    {
        $roster = $this->rosters->getRoster($userId, $contextType, $schoolId, $classroomId);
        if ($roster === null) { return null; }
        $listed = null;
        foreach ($roster['students'] as $student) {
            if ($student['enrollmentId'] === $enrollmentId) { $listed = $student; break; }
        }
        if ($listed === null) { return null; }

        $enrollment = null;
        $enrollmentHistory = $this->enrollments->listForStudent($schoolId, $listed['studentId']);
        foreach ($enrollmentHistory as $row) {
            if ((int) $row['id'] === $enrollmentId && (int) $row['classroom_id'] === $classroomId
                && $row['status'] === 'ACTIVE') { $enrollment = $row; break; }
        }
        if ($enrollment === null) { return null; }

        $rooms = array_values(array_filter($this->classrooms->listForSchool($schoolId, $enrollment['academic_year_id']),
            static fn (array $room): bool => (int) $room['grade_level_id'] === (int) $enrollment['grade_level_id']
                && $room['status'] === 'ACTIVE' && (int) $room['id'] !== $classroomId));

        return ['roster' => $roster, 'student' => $listed, 'enrollment' => $enrollment,
            'enrollmentHistory' => $enrollmentHistory,
            'placementHistory' => $this->placements->listForEnrollment($schoolId, $enrollmentId),
            'rooms' => $rooms];
    }
}
