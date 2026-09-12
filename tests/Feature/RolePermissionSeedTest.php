<?php
declare(strict_types=1);

use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RolePermissionSeedTest extends TestCase
{
    private PDO $pdo;

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

    public function test_seven_active_roles_have_the_expected_scopes(): void
    {
        self::assertSame([
            ['code' => 'ACADEMIC_ADMIN', 'scope_type' => 'SCHOOL', 'status' => 'ACTIVE'],
            ['code' => 'EXECUTIVE', 'scope_type' => 'SCHOOL', 'status' => 'ACTIVE'],
            ['code' => 'HOMEROOM_TEACHER', 'scope_type' => 'SCHOOL', 'status' => 'ACTIVE'],
            ['code' => 'SCHOOL_ADMIN', 'scope_type' => 'SCHOOL', 'status' => 'ACTIVE'],
            ['code' => 'SUBJECT_TEACHER', 'scope_type' => 'SCHOOL', 'status' => 'ACTIVE'],
            ['code' => 'SYSTEM_ADMIN', 'scope_type' => 'SYSTEM', 'status' => 'ACTIVE'],
            ['code' => 'VIEWER', 'scope_type' => 'SCHOOL', 'status' => 'ACTIVE'],
        ], $this->pdo->query('SELECT code, scope_type, status FROM roles ORDER BY code')->fetchAll());
    }

    public function test_nine_administration_permissions_are_seeded(): void
    {
        self::assertSame([
            'SCHOOL_MEMBERSHIP_STATUS_MANAGE',
            'SCHOOL_PASSWORD_RESET',
            'SCHOOL_ROLE_MANAGE',
            'SCHOOL_USER_CREATE',
            'SCHOOL_USER_UPDATE',
            'SCHOOL_USER_VIEW',
            'SYSTEM_SCHOOL_CREATE',
            'SYSTEM_SCHOOL_STATUS_MANAGE',
            'SYSTEM_SCHOOL_VIEW',
        ], $this->pdo->query("SELECT code FROM permissions WHERE code NOT IN (
            'ACADEMIC_SETUP_VIEW', 'ACADEMIC_YEAR_MANAGE', 'CLASSROOM_MANAGE', 'SUBJECT_MANAGE', 'SUBJECT_OFFERING_MANAGE'
        ) ORDER BY code")->fetchAll(PDO::FETCH_COLUMN));
    }

    #[DataProvider('rolePermissions')]
    public function test_each_role_has_only_its_expected_permissions(string $roleCode, array $expected): void
    {
        self::assertSame($expected, $this->permissionCodesForRole($roleCode));
    }

    public static function rolePermissions(): array
    {
        return [
            'system administrator' => ['SYSTEM_ADMIN', [
                'SYSTEM_SCHOOL_CREATE',
                'SYSTEM_SCHOOL_STATUS_MANAGE',
                'SYSTEM_SCHOOL_VIEW',
            ]],
            'school administrator' => ['SCHOOL_ADMIN', [
                'ACADEMIC_SETUP_VIEW',
                'ACADEMIC_YEAR_MANAGE',
                'CLASSROOM_MANAGE',
                'SCHOOL_MEMBERSHIP_STATUS_MANAGE',
                'SCHOOL_PASSWORD_RESET',
                'SCHOOL_ROLE_MANAGE',
                'SCHOOL_USER_CREATE',
                'SCHOOL_USER_UPDATE',
                'SCHOOL_USER_VIEW',
                'SUBJECT_MANAGE',
                'SUBJECT_OFFERING_MANAGE',
            ]],
            'academic administrator' => ['ACADEMIC_ADMIN', [
                'ACADEMIC_SETUP_VIEW',
                'ACADEMIC_YEAR_MANAGE',
                'CLASSROOM_MANAGE',
                'SUBJECT_MANAGE',
                'SUBJECT_OFFERING_MANAGE',
            ]],
            'homeroom teacher' => ['HOMEROOM_TEACHER', []],
            'subject teacher' => ['SUBJECT_TEACHER', []],
            'executive' => ['EXECUTIVE', []],
            'viewer' => ['VIEWER', []],
        ];
    }

    public function test_seeded_baseline_has_exact_role_permission_and_mapping_counts(): void
    {
        self::assertSame(7, (int) $this->pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn());
        self::assertSame(14, (int) $this->pdo->query('SELECT COUNT(*) FROM permissions')->fetchColumn());
        self::assertSame(19, (int) $this->pdo->query('SELECT COUNT(*) FROM role_permissions')->fetchColumn());
    }

    public function test_seed_is_recorded_once(): void
    {
        self::assertSame(
            ['20260909_001_roles_permissions.sql', '20260910_001_academic_structure_reference.sql'],
            $this->pdo->query('SELECT seed FROM seed_migrations ORDER BY seed')->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    public function test_reapplying_sql_preserves_rows_ids_and_does_not_create_accounts(): void
    {
        $path = dirname(__DIR__, 2) . '/database/seeds/20260909_001_roles_permissions.sql';
        self::assertFileExists($path);
        $sql = file_get_contents($path);
        self::assertIsString($sql);
        $before = $this->snapshot();

        $this->pdo->exec($sql);
        $this->pdo->exec($sql);

        self::assertSame($before, $this->snapshot());
    }

    private function permissionCodesForRole(string $roleCode): array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.code
             FROM role_permissions rp
             INNER JOIN roles r ON r.id = rp.role_id
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE r.code = ?
             ORDER BY p.code'
        );
        $statement->execute([$roleCode]);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    private function snapshot(): array
    {
        return [
            'roles' => $this->pdo->query('SELECT * FROM roles ORDER BY id')->fetchAll(),
            'permissions' => $this->pdo->query('SELECT * FROM permissions ORDER BY id')->fetchAll(),
            'mappings' => $this->pdo->query('SELECT * FROM role_permissions ORDER BY role_id, permission_id')->fetchAll(),
            'users' => (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'assignments' => (int) $this->pdo->query('SELECT COUNT(*) FROM user_role_assignments')->fetchColumn(),
        ];
    }
}
