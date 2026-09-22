<?php
declare(strict_types=1);

use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TeachingGradebookPermissionSeedTest extends TestCase
{
    private PDO $pdo;
    private const PERMISSIONS = [
        'GRADEBOOK_COMPONENT_MANAGE' => 'จัดการโครงสร้างคะแนน',
        'GRADEBOOK_SCORE_ENTER' => 'บันทึกคะแนน',
        'GRADEBOOK_VIEW' => 'ดูสมุดคะแนน',
        'TEACHING_ASSIGNMENT_MANAGE' => 'จัดการการมอบหมายครูประจำวิชา',
    ];
    private const FILTER = "'GRADEBOOK_COMPONENT_MANAGE','GRADEBOOK_SCORE_ENTER','GRADEBOOK_VIEW','TEACHING_ASSIGNMENT_MANAGE'";

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

    public function test_exactly_four_new_permissions_have_clear_thai_names(): void
    {
        self::assertSame(self::PERMISSIONS, $this->pdo->query("SELECT code, name_th FROM permissions WHERE code NOT IN (
            'ACADEMIC_SETUP_VIEW','ACADEMIC_YEAR_MANAGE','CLASSROOM_MANAGE','SUBJECT_MANAGE','SUBJECT_OFFERING_MANAGE',
            'ENROLLMENT_MANAGE','STUDENT_IMPORT','STUDENT_MANAGE','STUDENT_VIEW',
            'SCHOOL_MEMBERSHIP_STATUS_MANAGE','SCHOOL_PASSWORD_RESET','SCHOOL_ROLE_MANAGE','SCHOOL_USER_CREATE',
            'SCHOOL_USER_UPDATE','SCHOOL_USER_VIEW','SYSTEM_SCHOOL_CREATE','SYSTEM_SCHOOL_STATUS_MANAGE','SYSTEM_SCHOOL_VIEW'
        ) ORDER BY code")->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    #[DataProvider('mappings')]
    public function test_role_receives_exact_gradebook_grants_and_scope_metadata(string $role, array $expected): void
    {
        // This assertion makes a missing column a clear schema failure before querying it.
        self::assertContains('resource_scope_type', $this->pdo->query('SHOW COLUMNS FROM role_permissions')->fetchAll(PDO::FETCH_COLUMN));
        $q = $this->pdo->prepare('SELECT p.code, rp.resource_scope_type FROM role_permissions rp
            JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id
            WHERE r.code=? AND p.code IN (' . self::FILTER . ') ORDER BY p.code');
        $q->execute([$role]);
        self::assertSame($expected, $q->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    public static function mappings(): array
    {
        $admin = array_fill_keys(array_keys(self::PERMISSIONS), null);
        return [
            'school administrator' => ['SCHOOL_ADMIN', $admin],
            'academic administrator' => ['ACADEMIC_ADMIN', $admin],
            'subject teacher' => ['SUBJECT_TEACHER', ['GRADEBOOK_SCORE_ENTER' => 'SUBJECT_OFFERING', 'GRADEBOOK_VIEW' => 'SUBJECT_OFFERING']],
            'executive' => ['EXECUTIVE', ['GRADEBOOK_VIEW' => null]],
            'homeroom teacher' => ['HOMEROOM_TEACHER', []],
            'viewer' => ['VIEWER', []],
            'system administrator' => ['SYSTEM_ADMIN', []],
        ];
    }

    public function test_all_twenty_seven_existing_grants_remain_unscoped(): void
    {
        self::assertContains('resource_scope_type', $this->pdo->query('SHOW COLUMNS FROM role_permissions')->fetchAll(PDO::FETCH_COLUMN));
        $rows = $this->pdo->query('SELECT rp.resource_scope_type FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id
            WHERE p.code NOT IN (' . self::FILTER . ')')->fetchAll(PDO::FETCH_COLUMN);
        self::assertCount(27, $rows);
        foreach ($rows as $scope) {
            self::assertNull($scope);
        }
    }

    public function test_final_seed_counts_and_complete_ledger(): void
    {
        foreach (['roles' => 7, 'permissions' => 22, 'role_permissions' => 38, 'grade_levels' => 6] as $table => $expected) {
            self::assertSame($expected, (int) $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn(), $table);
        }
        self::assertSame([
            '20260909_001_roles_permissions.sql', '20260910_001_academic_structure_reference.sql',
            '20260912_001_student_core_permissions.sql', '20260915_001_teaching_gradebook_permissions.sql',
        ], $this->pdo->query('SELECT seed FROM seed_migrations ORDER BY seed')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function test_seed_reruns_preserve_all_ids_grants_metadata_and_entities(): void
    {
        $sql = $this->seedSql();
        $before = $this->snapshot();
        $this->pdo->exec($sql);
        $this->pdo->exec($sql);
        self::assertSame($before, $this->snapshot());
        // Replaying the entire historical seed chain must also preserve scoped grants.
        foreach (glob(dirname(__DIR__, 2) . '/database/seeds/*.sql') ?: [] as $path) {
            $this->pdo->exec(file_get_contents($path));
        }
        self::assertSame($before, $this->snapshot());
    }

    public function test_seed_repairs_existing_scoped_and_unscoped_metadata_deterministically(): void
    {
        $sql = $this->seedSql();
        $before = $this->snapshot();
        $this->pdo->exec("UPDATE role_permissions rp JOIN permissions p ON p.id=rp.permission_id
            SET rp.resource_scope_type=CASE WHEN rp.resource_scope_type IS NULL THEN 'SUBJECT_OFFERING' ELSE NULL END
            WHERE p.code IN (" . self::FILTER . ')');
        self::assertNotSame($before, $this->snapshot());
        $this->pdo->exec($sql);
        self::assertSame($before, $this->snapshot());
        $this->pdo->exec($sql);
        self::assertSame($before, $this->snapshot());
    }

    private function seedSql(): string
    {
        $path = dirname(__DIR__, 2) . '/database/seeds/20260915_001_teaching_gradebook_permissions.sql';
        self::assertFileExists($path);
        $sql = file_get_contents($path);
        self::assertIsString($sql);
        return $sql;
    }

    private function snapshot(): array
    {
        $result = [];
        foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $order = match ($table) {
                'schema_migrations' => 'migration', 'seed_migrations' => 'seed',
                'role_permissions' => 'role_id, permission_id', default => 'id',
            };
            $result[$table] = $this->pdo->query("SELECT * FROM `{$table}` ORDER BY {$order}")->fetchAll();
        }
        return $result;
    }
}
