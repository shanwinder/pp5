<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/** Scoped reads for internal B1 callers. Mutations belong to the audited service. */
final class GradebookAnnualCourseRepository
{
    public function __construct(private PDO $pdo) {}

    public function find(int $schoolId, int $yearId, int $classroomId, string $courseKey): ?array
    {
        $q = $this->pdo->prepare('SELECT * FROM gradebook_annual_courses WHERE school_id=? AND academic_year_id=? AND classroom_id=? AND course_key=?');
        $q->execute([$schoolId, $yearId, $classroomId, $courseKey]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function links(int $schoolId, int $yearId, int $classroomId, int $annualId): array
    {
        $q = $this->pdo->prepare('SELECT * FROM gradebook_annual_course_offerings WHERE school_id=? AND academic_year_id=? AND classroom_id=? AND annual_course_id=? ORDER BY subject_offering_id');
        $q->execute([$schoolId, $yearId, $classroomId, $annualId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
