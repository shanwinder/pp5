<?php
declare(strict_types=1);

use App\Repositories\SchoolMembershipRepository;
use App\Repositories\UserRepository;
use App\Services\AuthenticationService;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthenticationTest extends TestCase
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

    #[DataProvider('previousLoginTimes')]
    public function test_login_resolves_assigned_school_and_records_login(?string $previousLogin): void
    {
        $userId = $this->createUser(lastLogin: $previousLogin);
        $otherUserId = $this->createUser('auth-task5-other');
        $schoolId = $this->createSchool();
        $membershipId = $this->createMembership($userId, $schoolId);
        $before = $this->databaseTime();

        $result = $this->service()->attempt('auth-task5-teacher', 'correct-password');

        self::assertSame([
            'user_id' => $userId,
            'school_id' => $schoolId,
            'school_membership_id' => $membershipId,
            'display_name' => 'ครูทดสอบ',
        ], $result);
        self::assertArrayNotHasKey('password_hash', $result);
        $lastLogin = $this->lastLogin($userId);
        self::assertNotNull($lastLogin);
        self::assertNotSame($previousLogin, $lastLogin);
        self::assertGreaterThanOrEqual($before, $lastLogin);
        self::assertLessThanOrEqual($this->databaseTime(), $lastLogin);
        self::assertSame('2000-01-01 00:00:00', $this->lastLogin($otherUserId));
    }

    public static function previousLoginTimes(): array
    {
        return [
            'first login' => [null],
            'subsequent login' => ['2000-01-01 00:00:00'],
        ];
    }

    public function test_username_is_trimmed_before_authentication(): void
    {
        $userId = $this->createUser();
        $schoolId = $this->createSchool();
        $membershipId = $this->createMembership($userId, $schoolId);

        $result = $this->service()->attempt(" \tauth-task5-teacher\n ", 'correct-password');

        self::assertSame($userId, $result['user_id']);
        self::assertSame($schoolId, $result['school_id']);
        self::assertSame($membershipId, $result['school_membership_id']);
    }

    #[DataProvider('deniedLogins')]
    public function test_denied_login_does_not_update_last_login(
        string $username,
        string $password,
        string $userStatus,
        array $memberships
    ): void {
        $userId = $this->createUser(status: $userStatus);
        foreach ($memberships as $index => [$membershipStatus, $schoolStatus]) {
            $schoolId = $this->createSchool('auth-task5-school-' . $index, $schoolStatus);
            $this->createMembership($userId, $schoolId, $membershipStatus);
        }

        $service = $this->service();
        try {
            $service->attempt($username, $password);
            self::fail('Expected authentication to be denied.');
        } catch (DomainException $exception) {
            self::assertNotSame('', $exception->getMessage());
            self::assertSame('2000-01-01 00:00:00', $this->lastLogin($userId));
        }
    }

    public static function deniedLogins(): array
    {
        return [
            'wrong password' => ['auth-task5-teacher', 'wrong-password', 'ACTIVE', [['ACTIVE', 'ACTIVE']]],
            'unknown username' => ['auth-task5-missing', 'correct-password', 'ACTIVE', [['ACTIVE', 'ACTIVE']]],
            'inactive user' => ['auth-task5-teacher', 'correct-password', 'INACTIVE', [['ACTIVE', 'ACTIVE']]],
            'no membership' => ['auth-task5-teacher', 'correct-password', 'ACTIVE', []],
            'suspended membership' => ['auth-task5-teacher', 'correct-password', 'ACTIVE', [['SUSPENDED', 'ACTIVE']]],
            'inactive membership' => ['auth-task5-teacher', 'correct-password', 'ACTIVE', [['INACTIVE', 'ACTIVE']]],
            'suspended school' => ['auth-task5-teacher', 'correct-password', 'ACTIVE', [['ACTIVE', 'SUSPENDED']]],
            'inactive school' => ['auth-task5-teacher', 'correct-password', 'ACTIVE', [['ACTIVE', 'INACTIVE']]],
            'two active memberships' => ['auth-task5-teacher', 'correct-password', 'ACTIVE', [['ACTIVE', 'ACTIVE'], ['ACTIVE', 'ACTIVE']]],
        ];
    }

    public function test_unknown_username_and_wrong_password_have_the_same_error(): void
    {
        $this->createUser();
        $service = $this->service();
        $messages = [];

        foreach ([['auth-task5-missing', 'correct-password'], ['auth-task5-teacher', 'wrong-password']] as [$username, $password]) {
            try {
                $service->attempt($username, $password);
                self::fail('Expected invalid credentials to be denied.');
            } catch (DomainException $exception) {
                $messages[] = $exception->getMessage();
            }
        }

        self::assertCount(2, $messages);
        self::assertNotSame('', $messages[0]);
        self::assertSame($messages[0], $messages[1]);
    }

    public function test_only_active_memberships_in_active_schools_count_for_this_user(): void
    {
        $userId = $this->createUser();
        $schoolId = $this->createSchool();
        $membershipId = $this->createMembership($userId, $schoolId);
        $this->createMembership($userId, $this->createSchool('auth-task5-old'), 'INACTIVE');
        $this->createMembership($userId, $this->createSchool('auth-task5-closed', 'INACTIVE'));
        $otherUserId = $this->createUser('auth-task5-other');
        $this->createMembership($otherUserId, $schoolId);

        $result = $this->service()->attempt('auth-task5-teacher', 'correct-password');

        self::assertSame($schoolId, $result['school_id']);
        self::assertSame($membershipId, $result['school_membership_id']);
    }

    #[DataProvider('membershipChecks')]
    public function test_membership_validation_checks_identity_and_status(
        string $membershipStatus,
        string $schoolStatus,
        string $mismatch,
        bool $expected
    ): void {
        $userId = $this->createUser();
        $schoolId = $this->createSchool(status: $schoolStatus);
        $membershipId = $this->createMembership($userId, $schoolId, $membershipStatus);
        $repository = new SchoolMembershipRepository($this->pdo);

        self::assertSame($expected, $repository->isActiveMembership(
            $mismatch === 'membership' ? 0 : $membershipId,
            $mismatch === 'user' ? $this->createUser('auth-task5-other') : $userId,
            $mismatch === 'school' ? $this->createSchool('auth-task5-other') : $schoolId
        ));
    }

    public static function membershipChecks(): array
    {
        return [
            'matching active membership' => ['ACTIVE', 'ACTIVE', '', true],
            'missing membership' => ['ACTIVE', 'ACTIVE', 'membership', false],
            'wrong user' => ['ACTIVE', 'ACTIVE', 'user', false],
            'wrong school' => ['ACTIVE', 'ACTIVE', 'school', false],
            'inactive membership' => ['INACTIVE', 'ACTIVE', '', false],
            'suspended membership' => ['SUSPENDED', 'ACTIVE', '', false],
            'inactive school' => ['ACTIVE', 'INACTIVE', '', false],
            'suspended school' => ['ACTIVE', 'SUSPENDED', '', false],
        ];
    }

    private function service(): AuthenticationService
    {
        return new AuthenticationService(
            new UserRepository($this->pdo),
            new SchoolMembershipRepository($this->pdo)
        );
    }

    private function createUser(
        string $username = 'auth-task5-teacher',
        string $status = 'ACTIVE',
        ?string $lastLogin = '2000-01-01 00:00:00'
    ): int {
        return $this->insert(
            'INSERT INTO users (username, password_hash, display_name, status, last_login_at) VALUES (?, ?, ?, ?, ?)',
            [$username, password_hash('correct-password', PASSWORD_DEFAULT), 'ครูทดสอบ', $status, $lastLogin]
        );
    }

    private function createSchool(string $code = 'auth-task5-school', string $status = 'ACTIVE'): int
    {
        return $this->insert(
            'INSERT INTO schools (school_code, name_th, status) VALUES (?, ?, ?)',
            [$code, 'โรงเรียนทดสอบ', $status]
        );
    }

    private function createMembership(int $userId, int $schoolId, string $status = 'ACTIVE'): int
    {
        return $this->insert(
            'INSERT INTO school_memberships (user_id, school_id, status) VALUES (?, ?, ?)',
            [$userId, $schoolId, $status]
        );
    }

    private function insert(string $sql, array $parameters): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return (int) $this->pdo->lastInsertId();
    }

    private function lastLogin(int $userId): ?string
    {
        $statement = $this->pdo->prepare('SELECT last_login_at FROM users WHERE id = ?');
        $statement->execute([$userId]);

        return $statement->fetchColumn();
    }

    private function databaseTime(): string
    {
        $statement = $this->pdo->prepare('SELECT CURRENT_TIMESTAMP');
        $statement->execute();

        return $statement->fetchColumn();
    }
}
