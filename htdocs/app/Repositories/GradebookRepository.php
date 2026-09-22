<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class GradebookRepository
{
    private const CURRENT_ROW = "e.status = 'ACTIVE' AND EXISTS (
        SELECT 1 FROM student_classroom_placements pl
        WHERE pl.school_id = e.school_id AND pl.academic_year_id = e.academic_year_id
          AND pl.enrollment_id = e.id AND pl.classroom_id = o.classroom_id AND pl.status = 'ACTIVE'
    )";

    public function __construct(private PDO $pdo) {}

    public function listRoster(int $schoolId, int $offeringId): array
    {
        // History depends on row existence, including NULL scores and inactive components.
        $statement = $this->pdo->prepare('SELECT e.id AS enrollment_id, e.status AS enrollment_status,
                s.student_code, s.prefix_th, s.first_name_th, s.last_name_th,
                (' . self::CURRENT_ROW . ') AS is_current
            FROM subject_offerings o
            JOIN student_enrollments e ON e.school_id = o.school_id AND e.academic_year_id = o.academic_year_id
            JOIN students s ON s.id = e.student_id AND s.school_id = e.school_id
            WHERE o.school_id = ? AND o.id = ? AND ((' . self::CURRENT_ROW . ') OR EXISTS (
                SELECT 1 FROM gradebook_scores gs
                WHERE gs.school_id = e.school_id AND gs.academic_year_id = e.academic_year_id
                  AND gs.subject_offering_id = o.id AND gs.enrollment_id = e.id
            ))
            ORDER BY s.student_code ASC, e.id ASC');
        $statement->execute([$schoolId, $offeringId]);

        return $statement->fetchAll();
    }

    public function listActiveScores(int $schoolId, int $offeringId): array
    {
        $statement = $this->pdo->prepare("SELECT gs.enrollment_id, gs.component_id, gs.score
            FROM gradebook_scores gs
            JOIN gradebook_components c ON c.id = gs.component_id AND c.school_id = gs.school_id
                AND c.academic_year_id = gs.academic_year_id AND c.subject_offering_id = gs.subject_offering_id
            WHERE gs.school_id = ? AND gs.subject_offering_id = ? AND c.status = 'ACTIVE'
            ORDER BY gs.enrollment_id ASC, gs.component_id ASC");
        $statement->execute([$schoolId, $offeringId]);

        return $statement->fetchAll();
    }

    public function listTeachers(int $schoolId, int $offeringId): array
    {
        // Display context only. Resource authorization belongs to AuthorizationService.
        $statement = $this->pdo->prepare("SELECT ps.id AS teaching_assignment_id, u.display_name, ps.status
            FROM permission_scopes ps
            JOIN subject_offerings o ON o.id = ps.subject_offering_id AND o.school_id = ps.school_id
                AND o.academic_year_id = ps.academic_year_id
            JOIN user_role_assignments ura ON ura.id = ps.user_role_assignment_id AND ura.school_id = ps.school_id
            JOIN roles r ON r.id = ura.role_id
            JOIN school_memberships sm ON sm.school_id = ura.school_id AND sm.user_id = ura.user_id
            JOIN users u ON u.id = ura.user_id
            WHERE ps.school_id = ? AND ps.subject_offering_id = ? AND ps.status = 'ACTIVE'
              AND ura.status = 'ACTIVE' AND ura.academic_year_id IS NULL
              AND r.code = 'SUBJECT_TEACHER' AND r.scope_type = 'SCHOOL' AND r.status = 'ACTIVE'
              AND sm.status = 'ACTIVE' AND u.status = 'ACTIVE'
            ORDER BY u.display_name ASC, ps.id ASC");
        $statement->execute([$schoolId, $offeringId]);

        return $statement->fetchAll();
    }
}
