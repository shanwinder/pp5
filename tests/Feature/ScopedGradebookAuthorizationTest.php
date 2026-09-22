<?php
declare(strict_types=1);

use App\Repositories\AuthorizationRepository;
use App\Services\AuthorizationService;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScopedGradebookAuthorizationTest extends TestCase
{
    private PDO $pdo;
    private array $f;

    protected function setUp(): void
    {
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = Database::connect($config);
        $this->pdo->beginTransaction();
        $this->f = [];
        $this->f['user'] = $this->insert('users', ['username' => 'scoped-auth-user', 'password_hash' => 'unused', 'display_name' => 'Teacher']);
        $grade = $this->pdo->query("SELECT id FROM grade_levels WHERE code='P1'")->fetchColumn();
        foreach (['A', 'B'] as $key) {
            $school = $this->f['school' . $key] = $this->insert('schools', ['school_code' => 'scoped-auth-' . $key, 'name_th' => 'โรงเรียนทดสอบ']);
            $year = $this->f['year' . $key] = $this->insert('academic_years', ['school_id' => $school, 'year_be' => 2569]);
            $room = $this->insert('classrooms', ['school_id' => $school, 'academic_year_id' => $year, 'grade_level_id' => $grade, 'code' => 'P1', 'name_th' => 'ห้องทดสอบ']);
            $subject = $this->insert('subjects', ['school_id' => $school, 'code' => 'ท11101', 'name_th' => 'ภาษาไทย']);
            $offering = ['school_id' => $school, 'academic_year_id' => $year, 'classroom_id' => $room, 'subject_id' => $subject, 'term_no' => 1];
            $this->f['offering' . $key] = $this->insert('subject_offerings', $offering);
            if ($key === 'A') {
                $this->f['otherOffering'] = $this->insert('subject_offerings', array_replace($offering, ['term_no' => 2]));
            }
        }
        $this->f['membership'] = $this->insert('school_memberships', ['school_id' => $this->f['schoolA'], 'user_id' => $this->f['user']]);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) { $this->pdo->rollBack(); }
    }

    #[DataProvider('genericGrants')]
    public function test_generic_school_permission_never_promotes_a_scoped_grant(string $role, string $permission, bool $expected): void
    {
        $assignment = $this->assign($role);
        $this->scope($assignment);
        $service = $this->service();
        self::assertSame($expected, $service->hasPermission($this->f['user'], 'SCHOOL', $this->f['schoolA'], $permission));
        self::assertSame($expected, (new AuthorizationRepository($this->pdo))->hasSchoolPermission($this->f['user'], $this->f['schoolA'], $permission));
    }

    public static function genericGrants(): array
    {
        return [
            ['SCHOOL_ADMIN', 'GRADEBOOK_VIEW', true], ['ACADEMIC_ADMIN', 'GRADEBOOK_SCORE_ENTER', true],
            ['SUBJECT_TEACHER', 'GRADEBOOK_VIEW', false], ['SUBJECT_TEACHER', 'GRADEBOOK_SCORE_ENTER', false],
            ['SCHOOL_ADMIN', 'SCHOOL_USER_VIEW', true], ['ACADEMIC_ADMIN', 'ACADEMIC_SETUP_VIEW', true],
            ['SCHOOL_ADMIN', 'STUDENT_VIEW', true],
        ];
    }

    #[DataProvider('schoolWideGrants')]
    public function test_school_wide_grants_need_no_scope_but_require_the_exact_tenant(string $role, string $permission, bool $expected): void
    {
        $this->assign($role);
        $service = $this->service();
        self::assertSame($expected, $this->allows($service, $permission));
        self::assertFalse($this->allows($service, $permission, $this->f['offeringB']));
        self::assertFalse($this->allows($service, $permission, 0));
        self::assertFalse($service->hasSubjectOfferingPermission($this->f['user'], 'SCHOOL', $this->f['schoolB'], $this->f['offeringB'], $permission));
    }

    public static function schoolWideGrants(): array
    {
        return [
            ['SCHOOL_ADMIN', 'GRADEBOOK_VIEW', true], ['SCHOOL_ADMIN', 'GRADEBOOK_SCORE_ENTER', true],
            ['ACADEMIC_ADMIN', 'GRADEBOOK_VIEW', true], ['ACADEMIC_ADMIN', 'GRADEBOOK_SCORE_ENTER', true],
            ['EXECUTIVE', 'GRADEBOOK_VIEW', true], ['EXECUTIVE', 'GRADEBOOK_SCORE_ENTER', false],
            ['SYSTEM_ADMIN', 'GRADEBOOK_VIEW', false], ['HOMEROOM_TEACHER', 'GRADEBOOK_VIEW', false],
            ['VIEWER', 'GRADEBOOK_VIEW', false], ['SCHOOL_ADMIN', 'UNKNOWN', false],
        ];
    }

    #[DataProvider('revocations')]
    public function test_scoped_grants_observe_each_revocation_and_restoration_immediately(string $table, string $field, mixed $value, string $target): void
    {
        $assignment = $this->f['assignment'] = $this->assign('SUBJECT_TEACHER');
        $this->f['scope'] = $this->scope($assignment);
        $this->f['role'] = $this->role('SUBJECT_TEACHER');
        $service = $this->service();
        foreach (['GRADEBOOK_VIEW', 'GRADEBOOK_SCORE_ENTER'] as $permission) {
            self::assertTrue($this->allows($service, $permission));
            self::assertFalse($this->allows($service, $permission, $this->f['otherOffering']));
            self::assertFalse($this->allows($service, $permission, $this->f['offeringB']));
        }
        $this->pdo->exec('SAVEPOINT before_revocation');
        $this->pdo->prepare("UPDATE {$table} SET {$field}=? WHERE id=?")->execute([$value === 'year' ? $this->f['yearA'] : $value, $this->f[$target]]);
        foreach (['GRADEBOOK_VIEW', 'GRADEBOOK_SCORE_ENTER'] as $permission) { self::assertFalse($this->allows($service, $permission)); }
        $this->pdo->exec('ROLLBACK TO SAVEPOINT before_revocation');
        foreach (['GRADEBOOK_VIEW', 'GRADEBOOK_SCORE_ENTER'] as $permission) { self::assertTrue($this->allows($service, $permission)); }
    }

    public static function revocations(): array
    {
        return [
            'scope inactive' => ['permission_scopes', 'status', 'INACTIVE', 'scope'],
            'assignment inactive' => ['user_role_assignments', 'status', 'INACTIVE', 'assignment'],
            'year-bound assignment' => ['user_role_assignments', 'academic_year_id', 'year', 'assignment'],
            'membership inactive' => ['school_memberships', 'status', 'INACTIVE', 'membership'],
            'membership suspended' => ['school_memberships', 'status', 'SUSPENDED', 'membership'],
            'role inactive' => ['roles', 'status', 'INACTIVE', 'role'],
            'role system scope' => ['roles', 'scope_type', 'SYSTEM', 'role'],
            'school inactive' => ['schools', 'status', 'INACTIVE', 'schoolA'],
            'school suspended' => ['schools', 'status', 'SUSPENDED', 'schoolA'],
        ];
    }

    public function test_permission_revocation_and_scope_metadata_changes_are_live_and_data_driven(): void
    {
        $assignment = $this->assign('SUBJECT_TEACHER');
        $this->scope($assignment);
        $service = $this->service();
        $role = $this->role('SUBJECT_TEACHER');
        $permission = (int) $this->pdo->query("SELECT id FROM permissions WHERE code='GRADEBOOK_VIEW'")->fetchColumn();
        self::assertTrue($this->allows($service));
        $this->pdo->prepare('DELETE FROM role_permissions WHERE role_id=? AND permission_id=?')->execute([$role, $permission]);
        self::assertFalse($this->allows($service));
        self::assertTrue($this->allows($service, 'GRADEBOOK_SCORE_ENTER'));
        $this->insert('role_permissions', ['role_id' => $role, 'permission_id' => $permission, 'resource_scope_type' => 'SUBJECT_OFFERING']);
        self::assertTrue($this->allows($service));
        foreach (['UNKNOWN', 'CLASSROOM', ''] as $scope) {
            $this->pdo->prepare('UPDATE role_permissions SET resource_scope_type=? WHERE role_id=? AND permission_id=?')->execute([$scope, $role, $permission]);
            self::assertFalse($this->allows($service));
            self::assertFalse($service->hasPermission($this->f['user'], 'SCHOOL', $this->f['schoolA'], 'GRADEBOOK_VIEW'));
        }
        $this->pdo->prepare('UPDATE role_permissions SET resource_scope_type=NULL WHERE role_id=? AND permission_id=?')->execute([$role, $permission]);
        self::assertTrue($this->allows($service, 'GRADEBOOK_VIEW', $this->f['otherOffering']));
        self::assertTrue($service->hasPermission($this->f['user'], 'SCHOOL', $this->f['schoolA'], 'GRADEBOOK_VIEW'));
        // Even a renamed/custom role must work: permission metadata is the algorithm.
        $this->pdo->prepare("UPDATE roles SET code='CUSTOM_SCOPED_ROLE' WHERE id=?")->execute([$role]);
        $this->pdo->prepare("UPDATE role_permissions SET resource_scope_type='SUBJECT_OFFERING' WHERE role_id=? AND permission_id=?")->execute([$role, $permission]);
        self::assertTrue($this->allows($service));
        self::assertFalse($this->allows($service, 'GRADEBOOK_VIEW', $this->f['otherOffering']));
    }

    public function test_scope_cannot_be_borrowed_from_an_unrelated_permissionless_role_assignment(): void
    {
        $granting = $this->assign('SUBJECT_TEACHER');
        $unrelated = $this->assign('VIEWER');
        $this->scope($unrelated);
        $service = $this->service();
        self::assertFalse($this->allows($service));
        self::assertFalse($this->allows($service, 'GRADEBOOK_SCORE_ENTER'));
        $scope = $this->scope($granting);
        self::assertTrue($this->allows($service));
        self::assertTrue($this->allows($service, 'GRADEBOOK_SCORE_ENTER'));
        $this->pdo->prepare("UPDATE permission_scopes SET status='INACTIVE' WHERE id=?")->execute([$scope]);
        self::assertFalse($this->allows($service));
        // Another active assignment of the same role does not inherit a revoked assignment's scope.
        $this->pdo->prepare("UPDATE permission_scopes SET status='ACTIVE' WHERE id=?")->execute([$scope]);
        $this->pdo->prepare("UPDATE user_role_assignments SET status='INACTIVE' WHERE id=?")->execute([$granting]);
        $this->assign('SUBJECT_TEACHER');
        self::assertFalse($this->allows($service));
    }

    public function test_unscoped_grant_can_authorize_even_when_another_role_is_off_scope(): void
    {
        $this->assign('SUBJECT_TEACHER');
        $this->assign('EXECUTIVE');
        self::assertTrue($this->allows($this->service()));
        self::assertFalse($this->allows($this->service(), 'GRADEBOOK_SCORE_ENTER'));
    }

    #[DataProvider('invalidContexts')]
    public function test_offering_resolver_denies_invalid_context_before_grant_resolution(string $context, ?int $school): void
    {
        $this->assign('SCHOOL_ADMIN');
        self::assertFalse($this->service()->hasSubjectOfferingPermission($this->f['user'], $context,
            $school === 1 ? $this->f['schoolA'] : $school, $this->f['offeringA'], 'GRADEBOOK_VIEW'));
    }

    public static function invalidContexts(): array
    {
        return [['SYSTEM', null], ['SYSTEM', 1], ['UNKNOWN', 1], ['school', 1], ['SCHOOL', null], ['SCHOOL', 0], ['SCHOOL', -1]];
    }

    #[DataProvider('historyRoles')]
    public function test_closed_year_and_inactive_offering_do_not_erase_read_authorization(string $role): void
    {
        $assignment = $this->assign($role);
        if ($role === 'SUBJECT_TEACHER') { $this->scope($assignment); }
        $this->pdo->prepare("UPDATE academic_years SET status='CLOSED' WHERE id=?")->execute([$this->f['yearA']]);
        $this->pdo->prepare("UPDATE subject_offerings SET status='INACTIVE' WHERE id=?")->execute([$this->f['offeringA']]);
        self::assertTrue($this->allows($this->service()));
    }
    public static function historyRoles(): array { return [['SCHOOL_ADMIN'], ['SUBJECT_TEACHER'], ['EXECUTIVE']]; }

    public function test_offering_resolver_fails_closed_without_exposing_database_errors(): void
    {
        $service = new AuthorizationService(new AuthorizationRepository(new ScopedAuthorizationFailurePDO()));
        self::assertFalse($service->hasSubjectOfferingPermission($this->f['user'], 'SCHOOL', $this->f['schoolA'], $this->f['offeringA'], 'GRADEBOOK_VIEW'));
    }

    private function service(): AuthorizationService
    {
        return new AuthorizationService(new AuthorizationRepository($this->pdo));
    }
    private function allows(AuthorizationService $service, string $permission = 'GRADEBOOK_VIEW', ?int $offering = null): bool
    {
        self::assertTrue(method_exists($service, 'hasSubjectOfferingPermission'), 'Missing concrete subject-offering permission resolver');
        return $service->hasSubjectOfferingPermission($this->f['user'], 'SCHOOL', $this->f['schoolA'], $offering ?? $this->f['offeringA'], $permission);
    }
    private function role(string $code): int
    {
        $q = $this->pdo->prepare('SELECT id FROM roles WHERE code=?'); $q->execute([$code]); return (int) $q->fetchColumn();
    }
    private function assign(string $role): int
    {
        return $this->insert('user_role_assignments', ['school_id' => $this->f['schoolA'], 'user_id' => $this->f['user'], 'role_id' => $this->role($role)]);
    }
    private function scope(int $assignment): int
    {
        return $this->insert('permission_scopes', ['school_id' => $this->f['schoolA'], 'academic_year_id' => $this->f['yearA'],
            'user_role_assignment_id' => $assignment, 'subject_offering_id' => $this->f['offeringA'], 'assigned_by' => $this->f['user']]);
    }
    private function insert(string $table, array $values): int
    {
        $q = $this->pdo->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($values)) . ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')');
        $q->execute(array_values($values)); return (int) $this->pdo->lastInsertId();
    }
}

final class ScopedAuthorizationFailurePDO extends PDO
{
    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        throw new PDOException('SQLSTATE private-db-details /Applications/MAMP/ credentials');
    }
}
