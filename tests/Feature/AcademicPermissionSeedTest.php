<?php
declare(strict_types=1);

use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AcademicPermissionSeedTest extends TestCase
{
    private PDO $pdo;
    private const ACADEMIC_PERMISSIONS = [
        'ACADEMIC_SETUP_VIEW', 'ACADEMIC_YEAR_MANAGE', 'CLASSROOM_MANAGE', 'SUBJECT_MANAGE', 'SUBJECT_OFFERING_MANAGE',
    ];

    protected function setUp(): void
    {
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = Database::connect($config);
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function test_exact_primary_grade_reference_names_order_and_active_status(): void
    {
        self::assertContains('grade_levels', $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame([
            ['code' => 'P1', 'name_th' => 'ประถมศึกษาปีที่ 1', 'sort_order' => 10, 'status' => 'ACTIVE'],
            ['code' => 'P2', 'name_th' => 'ประถมศึกษาปีที่ 2', 'sort_order' => 20, 'status' => 'ACTIVE'],
            ['code' => 'P3', 'name_th' => 'ประถมศึกษาปีที่ 3', 'sort_order' => 30, 'status' => 'ACTIVE'],
            ['code' => 'P4', 'name_th' => 'ประถมศึกษาปีที่ 4', 'sort_order' => 40, 'status' => 'ACTIVE'],
            ['code' => 'P5', 'name_th' => 'ประถมศึกษาปีที่ 5', 'sort_order' => 50, 'status' => 'ACTIVE'],
            ['code' => 'P6', 'name_th' => 'ประถมศึกษาปีที่ 6', 'sort_order' => 60, 'status' => 'ACTIVE'],
        ], $this->pdo->query('SELECT code, name_th, sort_order, status FROM grade_levels ORDER BY sort_order, code')->fetchAll());
    }

    public function test_exactly_five_new_permissions_extend_the_original_nine(): void
    {
        self::assertSame(self::ACADEMIC_PERMISSIONS, $this->pdo->query(
            "SELECT code FROM permissions WHERE code NOT IN (
                'SCHOOL_MEMBERSHIP_STATUS_MANAGE', 'SCHOOL_PASSWORD_RESET', 'SCHOOL_ROLE_MANAGE',
                'SCHOOL_USER_CREATE', 'SCHOOL_USER_UPDATE', 'SCHOOL_USER_VIEW',
                'SYSTEM_SCHOOL_CREATE', 'SYSTEM_SCHOOL_STATUS_MANAGE', 'SYSTEM_SCHOOL_VIEW',
                'ENROLLMENT_MANAGE', 'STUDENT_IMPORT', 'STUDENT_MANAGE', 'STUDENT_VIEW'
            ) ORDER BY code"
        )->fetchAll(PDO::FETCH_COLUMN));
    }

    #[DataProvider('rolePermissions')]
    public function test_exact_permissions_for_each_role(string $role, array $expected): void
    {
        $statement = $this->pdo->prepare(
            'SELECT p.code FROM role_permissions rp JOIN roles r ON r.id = rp.role_id
             JOIN permissions p ON p.id = rp.permission_id WHERE r.code = ? ORDER BY p.code'
        );
        $statement->execute([$role]);
        self::assertSame($expected, $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function rolePermissions(): array
    {
        return [
            'system administrator' => ['SYSTEM_ADMIN', ['SYSTEM_SCHOOL_CREATE', 'SYSTEM_SCHOOL_STATUS_MANAGE', 'SYSTEM_SCHOOL_VIEW']],
            'school administrator' => ['SCHOOL_ADMIN', [
                'ACADEMIC_SETUP_VIEW', 'ACADEMIC_YEAR_MANAGE', 'CLASSROOM_MANAGE', 'ENROLLMENT_MANAGE',
                'SCHOOL_MEMBERSHIP_STATUS_MANAGE', 'SCHOOL_PASSWORD_RESET', 'SCHOOL_ROLE_MANAGE',
                'SCHOOL_USER_CREATE', 'SCHOOL_USER_UPDATE', 'SCHOOL_USER_VIEW',
                'STUDENT_IMPORT', 'STUDENT_MANAGE', 'STUDENT_VIEW', 'SUBJECT_MANAGE', 'SUBJECT_OFFERING_MANAGE',
            ]],
            'academic administrator' => ['ACADEMIC_ADMIN', [
                'ACADEMIC_SETUP_VIEW', 'ACADEMIC_YEAR_MANAGE', 'CLASSROOM_MANAGE', 'ENROLLMENT_MANAGE',
                'STUDENT_IMPORT', 'STUDENT_MANAGE', 'STUDENT_VIEW', 'SUBJECT_MANAGE', 'SUBJECT_OFFERING_MANAGE',
            ]],
            'homeroom teacher' => ['HOMEROOM_TEACHER', []],
            'subject teacher' => ['SUBJECT_TEACHER', []],
            'executive' => ['EXECUTIVE', []],
            'viewer' => ['VIEWER', []],
        ];
    }

    public function test_seeded_baseline_has_exact_counts(): void
    {
        self::assertSame(7, (int) $this->pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn());
        self::assertSame(18, (int) $this->pdo->query('SELECT COUNT(*) FROM permissions')->fetchColumn());
        self::assertSame(27, (int) $this->pdo->query('SELECT COUNT(*) FROM role_permissions')->fetchColumn());
        self::assertSame(6, (int) $this->pdo->query('SELECT COUNT(*) FROM grade_levels')->fetchColumn());
    }

    public function test_academic_seed_is_recorded_exactly_once(): void
    {
        self::assertSame([
            '20260909_001_roles_permissions.sql', '20260910_001_academic_structure_reference.sql',
            '20260912_001_student_core_permissions.sql',
        ], $this->pdo->query('SELECT seed FROM seed_migrations ORDER BY seed')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function test_reapplying_academic_sql_twice_preserves_reference_ids_mappings_and_accounts(): void
    {
        $path = dirname(__DIR__, 2) . '/database/seeds/20260910_001_academic_structure_reference.sql';
        self::assertFileExists($path);
        $sql = file_get_contents($path);
        self::assertIsString($sql);
        $before = $this->snapshot();
        $this->pdo->exec($sql);
        $this->pdo->exec($sql);
        self::assertSame($before, $this->snapshot());
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['grade_levels', 'roles', 'permissions', 'users', 'schools', 'school_memberships', 'user_role_assignments',
            'academic_years', 'classrooms', 'subjects', 'subject_offerings'] as $table) {
            $snapshot[$table] = $this->pdo->query("SELECT * FROM {$table} ORDER BY id")->fetchAll();
        }
        $snapshot['mappings'] = $this->pdo->query('SELECT * FROM role_permissions ORDER BY role_id, permission_id')->fetchAll();
        $snapshot['seeds'] = $this->pdo->query('SELECT * FROM seed_migrations ORDER BY seed')->fetchAll();
        return $snapshot;
    }
}
