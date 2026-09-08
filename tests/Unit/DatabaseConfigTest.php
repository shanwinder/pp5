<?php
declare(strict_types=1);

use App\Support\Database;
use PHPUnit\Framework\TestCase;

final class DatabaseConfigTest extends TestCase
{
    public function test_mysql_dsn_uses_utf8mb4(): void
    {
        $config = [
            'host' => 'localhost',
            'port' => 8889,
            'database' => 'pp5_test',
            'username' => 'test_user',
            'password' => 'test-only-password',
            'charset' => 'utf8mb4',
        ];

        self::assertSame(
            'mysql:host=localhost;port=8889;dbname=pp5_test;charset=utf8mb4',
            Database::dsn($config)
        );
    }
}
