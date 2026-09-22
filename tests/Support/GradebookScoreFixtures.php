<?php
declare(strict_types=1);

require_once __DIR__ . '/GradebookReadFixtures.php';

use App\Repositories\{AcademicYearRepository, AuditLogRepository, AuthorizationRepository, GradebookComponentRepository,
    GradebookScoreRepository, SchoolRepository, StudentEnrollmentRepository, StudentClassroomPlacementRepository, SubjectOfferingRepository};
use App\Services\{AuthorizationService, GradebookScoreService};

trait GradebookScoreFixtures
{
    use GradebookReadFixtures;

    private function scoreService(): GradebookScoreService
    {
        return new GradebookScoreService($this->pdo, new SchoolRepository($this->pdo), new AcademicYearRepository($this->pdo),
            new SubjectOfferingRepository($this->pdo), new GradebookComponentRepository($this->pdo),
            new StudentEnrollmentRepository($this->pdo), new StudentClassroomPlacementRepository($this->pdo),
            new AuthorizationService(new AuthorizationRepository($this->pdo)), new GradebookScoreRepository($this->pdo), new AuditLogRepository($this->pdo));
    }

    private function setCell(?string $score, string $role = 'SCHOOL_ADMIN', string $enrollment = 'current', string $component = 'componentA', string $offering = 'A'): array
    {
        return $this->scoreService()->setScore($this->f['schoolA'], $this->users[$role]['user'], $this->f['offering' . $offering],
            $this->f[$component], $this->f['enrollment_' . $enrollment], $score, '127.0.0.1');
    }

    private function scoreAudits(): array
    {
        return $this->rows("SELECT * FROM audit_logs WHERE action='GRADEBOOK_SCORE_CHANGED' ORDER BY id");
    }

    private function cellRows(string $enrollment = 'current'): array
    {
        return $this->rows('SELECT * FROM gradebook_scores WHERE school_id=? AND academic_year_id=? AND subject_offering_id=? AND enrollment_id=? AND component_id=?',
            [$this->f['schoolA'], $this->f['yearA'], $this->f['offeringA'], $this->f['enrollment_' . $enrollment], $this->f['componentA']]);
    }

    private function assertNoScoreWrites(): void
    {
        self::assertSame([], array_values(array_filter($this->pdo->queries,
            fn ($sql) => preg_match('/\A(?:UPDATE|INSERT INTO|DELETE FROM) gradebook_scores\b/', $sql))));
    }

    private function structureSnapshot(): array
    {
        $state = [];
        foreach (['student_enrollments','student_classroom_placements','subject_offerings','gradebook_components','permission_scopes'] as $table) {
            $state[$table] = $this->rows("SELECT * FROM {$table} ORDER BY id");
        }
        return $state;
    }

    private function revokeScore(string $kind): void
    {
        if ($kind === 'permission') {
            $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id
                WHERE r.code='SUBJECT_TEACHER' AND p.code='GRADEBOOK_SCORE_ENTER'");
        } else { $this->revoke($kind); }
    }

    private function changePrerequisite(string $kind): void
    {
        if (in_array($kind, ['scope','assignment','membership','school','role','permission'], true)) { $this->revokeScore($kind); return; }
        [$table, $field, $value, $key] = match ($kind) {
            'year' => ['academic_years','status','CLOSED','yearA'],
            'offering' => ['subject_offerings','status','INACTIVE','offeringA'],
            'component' => ['gradebook_components','status','INACTIVE','componentA'],
            'enrollment' => ['student_enrollments','status','TRANSFERRED','enrollment_current'],
            'placement' => ['student_classroom_placements','classroom_id',$this->f['roomOther'],null],
        };
        if ($kind === 'placement') {
            $this->pdo->prepare("UPDATE {$table} SET {$field}=? WHERE enrollment_id=? AND status='ACTIVE'")->execute([$value,$this->f['enrollment_current']]);
        } else { $this->pdo->prepare("UPDATE {$table} SET {$field}=? WHERE id=?")->execute([$value,$this->f[$key]]); }
    }
}
