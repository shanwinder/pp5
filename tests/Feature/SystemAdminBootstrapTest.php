<?php
declare(strict_types=1);

use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SystemAdminBootstrapTest extends TestCase
{
    private BootstrapTestPDO $pdo;
    private string $username;
    private array $committedIds = [];
    private const PASSWORD = 'bootstrap-test-secret-12';

    protected function setUp(): void
    {
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new BootstrapTestPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->username = 'bootstrap-' . bin2hex(random_bytes(8));
        $this->pdo->beginTransaction();
        self::assertFileExists($this->tool(), 'Task 8 CLI bootstrap implementation is missing');
        require_once $this->tool();
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return;
        }
        while ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        foreach ($this->committedIds as $id) {
            $this->pdo->prepare('DELETE FROM user_role_assignments WHERE user_id = ?')->execute([$id]);
            $this->pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        }
    }

    #[DataProvider('emails')]
    public function test_success_creates_only_active_global_administrator(?string $email): void
    {
        $before = $this->counts();
        $options = $this->options();
        if ($email !== null) {
            $options['email'] = $email;
        }
        self::assertSame($this->username, \PP5\Bootstrap\createAdministrator($this->pdo, $options));
        self::assertSame([$before[0] + 1, $before[1] + 1, $before[2]], $this->counts());
        $user = $this->user();
        self::assertSame('ACTIVE', $user['status']);
        self::assertSame($this->username, $user['username']);
        self::assertSame('Bootstrap Admin', $user['display_name']);
        self::assertSame($email === null || trim($email) === '' ? null : trim($email), $user['email']);
        self::assertTrue(password_verify(self::PASSWORD, $user['password_hash']));
        self::assertNotSame(self::PASSWORD, $user['password_hash']);
        self::assertNotContains(self::PASSWORD, array_values($user));
        $statement = $this->pdo->prepare('SELECT a.*, r.code FROM user_role_assignments a JOIN roles r ON r.id = a.role_id WHERE a.user_id = ?');
        $statement->execute([$user['id']]);
        $rows = $statement->fetchAll();
        self::assertCount(1, $rows);
        self::assertSame($user['id'], $rows[0]['user_id']);
        self::assertSame('SYSTEM_ADMIN', $rows[0]['code']);
        self::assertSame('ACTIVE', $rows[0]['status']);
        foreach (['school_id', 'academic_year_id', 'assigned_by'] as $field) {
            self::assertNull($rows[0][$field]);
        }
    }

    public static function emails(): array
    {
        return [[null], ['  '], [' bootstrap-admin@example.test ']];
    }

    #[DataProvider('roleChanges')]
    public function test_unavailable_seed_role_fails_without_partial_rows(string $change): void
    {
        $this->pdo->exec($change);
        $before = $this->counts();
        try {
            \PP5\Bootstrap\createAdministrator($this->pdo, $this->options());
            self::fail('Unavailable SYSTEM_ADMIN must fail');
        } catch (DomainException $exception) {
            self::assertStringContainsString('SYSTEM_ADMIN', $exception->getMessage());
            self::assertStringNotContainsString(self::PASSWORD, $exception->getMessage());
        }
        self::assertSame($before, $this->counts());
    }

    public static function roleChanges(): array
    {
        return [
            ["UPDATE roles SET code = 'BOOTSTRAP_MISSING' WHERE code = 'SYSTEM_ADMIN'"],
            ["UPDATE roles SET status = 'INACTIVE' WHERE code = 'SYSTEM_ADMIN'"],
            ["UPDATE roles SET scope_type = 'SCHOOL' WHERE code = 'SYSTEM_ADMIN'"],
        ];
    }

    #[DataProvider('duplicates')]
    public function test_duplicate_does_not_promote_existing_user(bool $duplicateEmail): void
    {
        $options = $this->options();
        $options['email'] = $this->username . '@example.test';
        $this->pdo->prepare('INSERT INTO users (username, email, password_hash, display_name) VALUES (?, ?, ?, ?)')->execute([
            $duplicateEmail ? $this->username . '-old' : $this->username, $options['email'], 'unused', 'Existing',
        ]);
        $before = $this->counts();
        try {
            \PP5\Bootstrap\createAdministrator($this->pdo, $options);
            self::fail('Duplicate must fail');
        } catch (DomainException $exception) {
            self::assertStringNotContainsString('SQL', $exception->getMessage());
        }
        self::assertSame($before, $this->counts());
    }

    public static function duplicates(): array { return [[false], [true]]; }

    public function test_assignment_failure_rolls_back_created_user_and_hides_database_details(): void
    {
        $before = $this->counts();
        $this->pdo->failAssignment = true;
        try {
            \PP5\Bootstrap\createAdministrator($this->pdo, $this->options());
            self::fail('Assignment failure must fail bootstrap');
        } catch (DomainException $exception) {
            self::assertStringNotContainsString('internal-secret', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame($before, $this->counts());
    }

    #[DataProvider('invalidArguments')]
    public function test_invalid_cli_arguments_fail_safely_without_rows(array $changes, array $extra): void
    {
        $before = $this->counts();
        $options = array_replace($this->options(), $changes);
        $options = array_filter($options, static fn ($value): bool => $value !== null);
        [$status, $output] = $this->cli($options, $extra);
        self::assertNotSame(0, $status);
        self::assertStringStartsWith('Error: ', $output);
        foreach ([self::PASSWORD, 'SQLSTATE', 'Stack trace', 'PDOException', 'password_hash', 'secret-invalid-email'] as $secret) {
            self::assertStringNotContainsString($secret, $output);
        }
        self::assertSame($before, $this->counts());
    }

    public static function invalidArguments(): array
    {
        return [
            [['username' => null], []], [['display-name' => null], []], [['password' => null], []],
            [['username' => ''], []], [['username' => 'ab'], []], [['username' => 'bad name'], []],
            [['username' => str_repeat('a', 101)], []], [['display-name' => '  '], []],
            [['display-name' => str_repeat('ก', 191)], []], [['email' => 'secret-invalid-email'], []],
            [['password' => 'short'], []], [[], ['--unknown=value']], [[], ['--username=again']],
            [[], ['--password']], [[], ['positional']], [['database' => 'pp5_test;invalid'], []],
        ];
    }

    public function test_real_cli_success_and_duplicate_have_safe_exact_output(): void
    {
        $this->pdo->rollBack();
        $options = $this->options();
        $options['email'] = $this->username . '@example.test';
        [$status, $output] = $this->cli($options);
        $user = $this->user();
        if ($user !== false) {
            $this->committedIds[] = $user['id'];
        }
        self::assertSame(0, $status, $output);
        self::assertSame('Created SYSTEM_ADMIN user: ' . $this->username . "\n", $output);
        self::assertTrue(password_verify(self::PASSWORD, $user['password_hash']));
        $before = $this->counts();
        [$status, $output] = $this->cli($options);
        self::assertNotSame(0, $status);
        self::assertStringStartsWith('Error: ', $output);
        foreach ([self::PASSWORD, $user['password_hash'], $options['email'], 'SQLSTATE', 'Stack trace'] as $secret) {
            self::assertStringNotContainsString($secret, $output);
        }
        self::assertSame($before, $this->counts());
    }

    private function options(): array
    {
        return ['username' => $this->username, 'display-name' => ' Bootstrap Admin ', 'password' => self::PASSWORD, 'database' => 'pp5_test'];
    }

    private function tool(): string { return dirname(__DIR__, 2) . '/tools/bootstrap_system_admin.php'; }

    private function cli(array $options, array $extra = []): array
    {
        $arguments = [PHP_BINARY, $this->tool()];
        foreach ($options as $key => $value) {
            $arguments[] = '--' . $key . '=' . $value;
        }
        $process = proc_open([...$arguments, ...$extra], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $output];
    }

    private function user(): array|false
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE username = ?');
        $statement->execute([$this->username]);
        return $statement->fetch();
    }

    private function counts(): array
    {
        return array_map(fn (string $table): int => (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn(), ['users', 'user_role_assignments', 'school_memberships']);
    }
}

final class BootstrapTestPDO extends PDO
{
    private int $depth = 0;
    public bool $failAssignment = false;

    public function beginTransaction(): bool
    {
        if ($this->depth === 0) {
            parent::beginTransaction();
        } else {
            parent::exec('SAVEPOINT bootstrap_' . $this->depth);
        }
        $this->depth++;
        return true;
    }

    public function commit(): bool
    {
        $this->depth--;
        return $this->depth === 0 ? parent::commit() : parent::exec('RELEASE SAVEPOINT bootstrap_' . $this->depth) !== false;
    }

    public function rollBack(): bool
    {
        $this->depth--;
        return $this->depth === 0 ? parent::rollBack() : parent::exec('ROLLBACK TO SAVEPOINT bootstrap_' . $this->depth) !== false;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->failAssignment && str_contains($query, 'INSERT INTO user_role_assignments')) {
            throw new PDOException('SQLSTATE internal-secret');
        }
        return parent::prepare($query, $options);
    }
}
