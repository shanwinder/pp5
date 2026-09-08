<?php
declare(strict_types=1);

use App\Support\Database;
use PHPUnit\Framework\TestCase;

final class FoundationSchemaTest extends TestCase
{
    public function test_foundation_tables_exist(): void
    {
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $pdo = Database::connect($config);

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

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
}
