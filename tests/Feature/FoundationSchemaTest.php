<?php
declare(strict_types=1);

use App\Support\Database;
use PHPUnit\Framework\TestCase;

final class FoundationSchemaTest extends TestCase
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

    public function test_foundation_tables_exist(): void
    {
        $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        foreach ([
            'schools',
            'users',
            'school_memberships',
            'roles',
            'permissions',
            'role_permissions',
            'user_role_assignments',
            'audit_logs',
        ] as $table) {
            self::assertContains($table, $tables);
        }
    }

    public function test_school_role_assignment_accepts_an_existing_membership(): void
    {
        $userId = $this->createUser();
        $schoolId = $this->createSchool('school-a');
        $roleId = $this->createRole('SCHOOL_ADMIN', 'SCHOOL');
        $this->insert(
            'INSERT INTO school_memberships (user_id, school_id) VALUES (?, ?)',
            [$userId, $schoolId]
        );

        $assignmentId = $this->insert(
            'INSERT INTO user_role_assignments (user_id, school_id, role_id) VALUES (?, ?, ?)',
            [$userId, $schoolId, $roleId]
        );

        $statement = $this->pdo->prepare(
            'SELECT user_id, school_id, role_id FROM user_role_assignments WHERE id = ?'
        );
        $statement->execute([$assignmentId]);
        self::assertSame(
            ['user_id' => $userId, 'school_id' => $schoolId, 'role_id' => $roleId],
            $statement->fetch()
        );
    }

    public function test_school_role_assignment_rejects_a_school_without_membership(): void
    {
        $userId = $this->createUser();
        $memberSchoolId = $this->createSchool('school-a');
        $otherSchoolId = $this->createSchool('school-b');
        $roleId = $this->createRole('SCHOOL_ADMIN', 'SCHOOL');
        $this->insert(
            'INSERT INTO school_memberships (user_id, school_id) VALUES (?, ?)',
            [$userId, $memberSchoolId]
        );

        try {
            $this->insert(
                'INSERT INTO user_role_assignments (user_id, school_id, role_id) VALUES (?, ?, ?)',
                [$userId, $otherSchoolId, $roleId]
            );
        } catch (PDOException $exception) {
            self::assertSame('23000', $exception->errorInfo[0]);
            self::assertSame(1452, $exception->errorInfo[1]);
            self::assertStringContainsString('fk_assignment_membership', $exception->getMessage());
            $statement = $this->pdo->prepare(
                'SELECT COUNT(*) FROM user_role_assignments WHERE user_id = ?'
            );
            $statement->execute([$userId]);
            self::assertSame(0, (int) $statement->fetchColumn());
            return;
        }

        self::fail('Expected a foreign-key violation for a school without membership.');
    }

    public function test_platform_role_assignment_accepts_null_school_without_membership(): void
    {
        $userId = $this->createUser();
        $roleId = $this->createRole('SYSTEM_ADMIN', 'PLATFORM');

        $assignmentId = $this->insert(
            'INSERT INTO user_role_assignments (user_id, school_id, role_id) VALUES (?, ?, ?)',
            [$userId, null, $roleId]
        );

        $statement = $this->pdo->prepare(
            'SELECT user_id, school_id, role_id FROM user_role_assignments WHERE id = ?'
        );
        $statement->execute([$assignmentId]);
        self::assertSame(
            ['user_id' => $userId, 'school_id' => null, 'role_id' => $roleId],
            $statement->fetch()
        );
    }

    private function createUser(): int
    {
        return $this->insert(
            'INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)',
            ['schema-test-user', 'unused-test-hash', 'Schema Test User']
        );
    }

    private function createSchool(string $code): int
    {
        return $this->insert(
            'INSERT INTO schools (school_code, name_th) VALUES (?, ?)',
            [$code, 'โรงเรียนทดสอบ']
        );
    }

    private function createRole(string $code, string $scope): int
    {
        return $this->insert(
            'INSERT INTO roles (code, name_th, scope_type) VALUES (?, ?, ?)',
            [$code, 'บทบาททดสอบ', $scope]
        );
    }

    private function insert(string $sql, array $parameters): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return (int) $this->pdo->lastInsertId();
    }
}
