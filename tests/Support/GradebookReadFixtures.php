<?php
declare(strict_types=1);

require_once __DIR__ . '/GradebookComponentFixtures.php';

trait GradebookReadFixtures
{
    use GradebookComponentFixtures { setUp as private componentSetUp; }

    private const NATIONAL_MARKER = '1234567890123';

    protected function setUp(): void
    {
        $this->componentSetUp();
        $room = $this->row('classrooms', $this->f['roomA']);
        unset($room['id'], $room['created_at'], $room['updated_at']); $room['code'] = 'OTHER_ROOM';
        $this->f['roomOther'] = $this->insert('classrooms', $room);
        $this->pdo->prepare('UPDATE subject_offerings SET classroom_id=? WHERE id=?')->execute([$this->f['roomOther'], $this->f['offeringOther']]);
        $this->pdo->prepare('UPDATE gradebook_components SET code=? WHERE id=?')->execute(['OTHER_COMPONENT', $this->f['componentOther']]);
        $this->f['componentSecond'] = $this->insert('gradebook_components', ['school_id' => $this->f['schoolA'], 'academic_year_id' => $this->f['yearA'],
            'subject_offering_id' => $this->f['offeringA'], 'code' => 'WORK', 'name_th' => 'งาน', 'max_score' => '15.50', 'sort_order' => 5]);
        $this->f['scopeA'] = $this->scope($this->users['SUBJECT_TEACHER']['assignment'], 'A');
        $teacherRole = $this->rows("SELECT id FROM roles WHERE code='SUBJECT_TEACHER'")[0]['id'];
        $foreignAssignment = $this->insert('user_role_assignments', ['school_id' => $this->f['schoolB'], 'user_id' => $this->users['FOREIGN']['user'], 'role_id' => $teacherRole]);
        $this->pdo->prepare('UPDATE users SET display_name=? WHERE id=?')->execute(['FOREIGN_SECRET_TEACHER', $this->users['FOREIGN']['user']]);
        $this->scope($foreignAssignment, 'B');
        // Deliberately insert out of display order.
        foreach ([
            'moved' => ['05-MOVED', 'A', 'Other', 'ACTIVE'],
            'current' => ['01-CURRENT', 'A', 'A', 'ACTIVE'],
            'zero' => ['02-ZERO', 'A', 'A', 'ACTIVE'],
            'null' => ['03-NULL', 'A', 'A', 'ACTIVE'],
            'complete' => ['04-COMPLETE', 'A', 'A', 'ACTIVE'],
            'exited' => ['06-EXITED', 'A', 'A', 'TRANSFERRED'],
            'nullHistory' => ['07-NULL-HISTORY', 'A', null, 'ACTIVE'],
            'oldHistory' => ['08-OLD-HISTORY', 'A', null, 'ACTIVE'],
            'wrongRoom' => ['SECRET_WRONG_ROOM', 'A', 'Other', 'ACTIVE'],
            'wrongYear' => ['SECRET_WRONG_YEAR', 'Next', 'Next', 'ACTIVE'],
            'ended' => ['SECRET_ENDED', 'A', null, 'ACTIVE'],
            'inactive' => ['SECRET_INACTIVE', 'A', 'A', 'TRANSFERRED'],
            'foreign' => ['FOREIGN_SECRET_STUDENT', 'B', 'B', 'ACTIVE'],
        ] as $key => [$code, $year, $room, $status]) {
            $this->f['enrollment_' . $key] = $this->student($code, $year, $room, $status);
        }
        foreach (['moved','nullHistory','oldHistory','ended'] as $key) { $this->placement($this->f['enrollment_' . $key], 'A', 'INACTIVE'); }
        $current = $this->row('student_enrollments', $this->f['enrollment_current']);
        $this->pdo->prepare('UPDATE students SET national_id=? WHERE id=?')->execute([self::NATIONAL_MARKER, $current['student_id']]);
        foreach ([['zero','componentA','0.00'], ['null','componentA',null], ['complete','componentA','5.00'], ['complete','componentSecond','0.00'],
            ['moved','componentA','2.25'], ['exited','componentA','4.00'], ['nullHistory','componentA',null], ['oldHistory','inactiveComponent','9.00']] as [$student,$component,$value]) {
            $this->scoreCell($this->f['enrollment_' . $student], $this->f[$component], $value);
        }
        $this->scoreCell($this->f['enrollment_wrongRoom'], $this->f['componentOther'], '18.75');
        $this->scoreCell($this->f['enrollment_foreign'], $this->f['componentB'], '17.65');
        $this->pdo->queries = [];
    }

    private function readService(): App\Services\GradebookReadService
    {
        return new App\Services\GradebookReadService(new App\Services\AuthorizationService(new App\Repositories\AuthorizationRepository($this->pdo)),
            new App\Repositories\SubjectOfferingRepository($this->pdo), new App\Repositories\GradebookComponentRepository($this->pdo),
            new App\Repositories\GradebookRepository($this->pdo));
    }
    private function gradebook(string $role = 'SCHOOL_ADMIN', string $offering = 'A'): ?array
    {
        return $this->readService()->getGradebook($this->users[$role]['user'], $role === 'SYSTEM_ADMIN' ? 'SYSTEM' : 'SCHOOL', $this->f['schoolA'], $this->f['offering' . $offering]);
    }
    private function accessible(string $role): array
    {
        return $this->readService()->listAccessibleOfferings($this->users[$role]['user'], $role === 'SYSTEM_ADMIN' ? 'SYSTEM' : 'SCHOOL', $this->f['schoolA']);
    }
    private function readPath(string $key = 'A'): string { return '/gradebook/' . $this->f['offering' . $key]; }
    private function scope(int $assignment, string $key): int
    {
        $offering = $this->row('subject_offerings', $this->f['offering' . $key]);
        return $this->insert('permission_scopes', ['school_id' => $offering['school_id'], 'academic_year_id' => $offering['academic_year_id'],
            'subject_offering_id' => $offering['id'], 'user_role_assignment_id' => $assignment, 'assigned_by' => $this->users['SCHOOL_ADMIN']['user']]);
    }
    private function student(string $code, string $year = 'A', ?string $room = 'A', string $status = 'ACTIVE'): int
    {
        $school = $this->f[$year === 'B' ? 'schoolB' : 'schoolA'];
        $student = $this->insert('students', ['school_id' => $school, 'student_code' => $code, 'prefix_th' => 'ด.ช.', 'first_name_th' => $code, 'last_name_th' => 'ทดสอบ']);
        $grade = $this->row('classrooms', $this->f['room' . $year])['grade_level_id'];
        $enrollment = $this->insert('student_enrollments', ['school_id' => $school, 'academic_year_id' => $this->f['year' . $year], 'student_id' => $student, 'grade_level_id' => $grade, 'status' => $status]);
        if ($room !== null) { $this->placement($enrollment, $room); }
        return $enrollment;
    }
    private function placement(int $enrollment, string $room, string $status = 'ACTIVE'): int
    {
        $e = $this->row('student_enrollments', $enrollment);
        return $this->insert('student_classroom_placements', ['school_id' => $e['school_id'], 'academic_year_id' => $e['academic_year_id'], 'grade_level_id' => $e['grade_level_id'],
            'enrollment_id' => $enrollment, 'classroom_id' => $this->f['room' . $room], 'status' => $status]);
    }
    private function scoreCell(int $enrollment, int $component, ?string $score): int
    {
        $c = $this->row('gradebook_components', $component);
        return $this->insert('gradebook_scores', ['school_id' => $c['school_id'], 'academic_year_id' => $c['academic_year_id'], 'subject_offering_id' => $c['subject_offering_id'],
            'component_id' => $component, 'enrollment_id' => $enrollment, 'score' => $score, 'updated_by' => $this->users['SCHOOL_ADMIN']['user']]);
    }
    private function modelRow(array $model, string $key): array
    {
        return array_column($model['rows'], null, 'enrollment_id')[$this->f['enrollment_' . $key]];
    }
    private function readSnapshot(): array
    {
        return $this->snapshot() + ['enrollments' => $this->rows('SELECT * FROM student_enrollments ORDER BY id'),
            'placements' => $this->rows('SELECT * FROM student_classroom_placements ORDER BY id')];
    }
    private function assertReadSafe(string $text): void
    {
        $this->assertSafe($text);
        foreach ([self::NATIONAL_MARKER, 'birth_date', 'SECRET_WRONG_ROOM', 'SECRET_WRONG_YEAR', 'SECRET_ENDED', 'SECRET_INACTIVE', 'OTHER_COMPONENT'] as $marker) {
            self::assertStringNotContainsString($marker, $text);
        }
    }
    private function revoke(string $kind): void
    {
        match ($kind) {
            'scope' => $this->pdo->prepare("UPDATE permission_scopes SET status='INACTIVE' WHERE id=?")->execute([$this->f['scopeA']]),
            'assignment' => $this->pdo->prepare("UPDATE user_role_assignments SET status='INACTIVE' WHERE id=?")->execute([$this->users['SUBJECT_TEACHER']['assignment']]),
            'membership' => $this->pdo->prepare("UPDATE school_memberships SET status='SUSPENDED' WHERE id=?")->execute([$this->users['SUBJECT_TEACHER']['membership']]),
            'school' => $this->pdo->prepare("UPDATE schools SET status='SUSPENDED' WHERE id=?")->execute([$this->f['schoolA']]),
            'role' => $this->pdo->exec("UPDATE roles SET status='INACTIVE' WHERE code='SUBJECT_TEACHER'"),
            'permission' => $this->pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='SUBJECT_TEACHER' AND p.code='GRADEBOOK_VIEW'"),
        };
    }
}
