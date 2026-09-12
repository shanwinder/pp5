<?php
declare(strict_types=1);

use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StudentPermissionSeedTest extends TestCase
{
    private PDO $pdo;
    private const PERMISSIONS = ['ENROLLMENT_MANAGE', 'STUDENT_IMPORT', 'STUDENT_MANAGE', 'STUDENT_VIEW'];

    protected function setUp(): void
    {
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = Database::connect($config);
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) { $this->pdo->rollBack(); }
    }

    public function test_exact_four_permissions_extend_the_existing_fourteen(): void
    {
        self::assertSame(self::PERMISSIONS, $this->pdo->query("SELECT code FROM permissions WHERE code NOT IN (
            'ACADEMIC_SETUP_VIEW', 'ACADEMIC_YEAR_MANAGE', 'CLASSROOM_MANAGE', 'SUBJECT_MANAGE', 'SUBJECT_OFFERING_MANAGE',
            'SCHOOL_MEMBERSHIP_STATUS_MANAGE', 'SCHOOL_PASSWORD_RESET', 'SCHOOL_ROLE_MANAGE', 'SCHOOL_USER_CREATE',
            'SCHOOL_USER_UPDATE', 'SCHOOL_USER_VIEW', 'SYSTEM_SCHOOL_CREATE', 'SYSTEM_SCHOOL_STATUS_MANAGE', 'SYSTEM_SCHOOL_VIEW'
        ) ORDER BY code")->fetchAll(PDO::FETCH_COLUMN));
    }

    #[DataProvider('mappings')]
    public function test_only_school_and_academic_administrators_receive_student_permissions(string $role, array $expected): void
    {
        $q = $this->pdo->prepare("SELECT p.code FROM permissions p JOIN role_permissions rp ON rp.permission_id=p.id
            JOIN roles r ON r.id=rp.role_id WHERE r.code=? AND p.code IN ('STUDENT_VIEW','STUDENT_MANAGE','ENROLLMENT_MANAGE','STUDENT_IMPORT') ORDER BY p.code");
        $q->execute([$role]); self::assertSame($expected, $q->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function mappings(): array
    {
        return [
            ['SCHOOL_ADMIN', self::PERMISSIONS], ['ACADEMIC_ADMIN', self::PERMISSIONS], ['SYSTEM_ADMIN', []],
            ['HOMEROOM_TEACHER', []], ['SUBJECT_TEACHER', []], ['EXECUTIVE', []], ['VIEWER', []],
        ];
    }

    public function test_exact_seeded_baseline_and_migration_ledger(): void
    {
        foreach (['roles' => 7, 'permissions' => 18, 'role_permissions' => 27, 'grade_levels' => 6] as $table => $expected) {
            self::assertSame($expected, (int) $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn(), $table);
        }
        self::assertSame(['20260909_001_roles_permissions.sql', '20260910_001_academic_structure_reference.sql', '20260912_001_student_core_permissions.sql'],
            $this->pdo->query('SELECT seed FROM seed_migrations ORDER BY seed')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function test_reapplying_seed_twice_preserves_ids_mappings_reference_data_and_entities(): void
    {
        $path = dirname(__DIR__, 2) . '/database/seeds/20260912_001_student_core_permissions.sql';
        self::assertFileExists($path);
        $sql = file_get_contents($path); self::assertIsString($sql);
        $before = $this->snapshot();
        $this->pdo->exec($sql); $this->pdo->exec($sql);
        self::assertSame($before, $this->snapshot());
    }

    private function snapshot(): array
    {
        $result = [];
        foreach (['roles', 'permissions', 'grade_levels', 'users', 'schools', 'school_memberships', 'user_role_assignments',
            'academic_years', 'classrooms', 'subjects', 'subject_offerings', 'students', 'student_enrollments',
            'student_classroom_placements', 'student_import_batches', 'student_import_rows', 'audit_logs'] as $table) {
            $result[$table] = $this->pdo->query("SELECT * FROM {$table} ORDER BY id")->fetchAll();
        }
        $result['mappings'] = $this->pdo->query('SELECT * FROM role_permissions ORDER BY role_id, permission_id')->fetchAll();
        $result['seeds'] = $this->pdo->query('SELECT * FROM seed_migrations ORDER BY seed')->fetchAll();
        return $result;
    }
}
