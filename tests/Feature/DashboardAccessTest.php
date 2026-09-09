<?php
declare(strict_types=1);

use App\Application;
use App\Controllers\DashboardController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Repositories\SchoolRepository;
use App\Support\Csrf;
use App\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DashboardAccessTest extends TestCase
{
    private PDO $pdo;
    private Application $app;
    private int $userId;
    private int $schoolId;
    private int $otherSchoolId;
    private int $membershipId;

    protected function setUp(): void
    {
        $_SESSION = [];
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = Database::connect($config);
        $this->pdo->beginTransaction();
        $this->userId = $this->insert(
            'INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)',
            ['dashboard-task7-user', password_hash('fixture-password', PASSWORD_DEFAULT), 'ครูทดสอบ Dashboard']
        );
        $this->schoolId = $this->insert(
            'INSERT INTO schools (school_code, name_th) VALUES (?, ?)',
            ['dashboard-task7-a', 'โรงเรียนที่ได้รับมอบหมาย A']
        );
        $this->otherSchoolId = $this->insert(
            'INSERT INTO schools (school_code, name_th) VALUES (?, ?)',
            ['dashboard-task7-b', 'โรงเรียนอื่น B']
        );
        $this->membershipId = $this->insert(
            'INSERT INTO school_memberships (user_id, school_id) VALUES (?, ?)',
            [$this->userId, $this->schoolId]
        );
        $this->app = new Application($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $_SESSION = [];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        self::assertEquals(Response::redirect('/login'), $this->dashboard());
    }

    public function test_dashboard_shows_assigned_school_and_user_without_school_chooser(): void
    {
        $this->authenticate();

        $response = $this->dashboard();

        self::assertSame(200, $response->status());
        self::assertStringContainsString('โรงเรียนที่ได้รับมอบหมาย A', $response->body());
        self::assertStringContainsString('ครูทดสอบ Dashboard', $response->body());
        self::assertStringNotContainsString('โรงเรียนอื่น B', $response->body());
        self::assertStringNotContainsString('<select', $response->body());
        self::assertStringNotContainsString('เลือกโรงเรียน', $response->body());
        self::assertStringNotContainsString('school_id', $response->body());
    }

    public function test_logout_form_submits_a_valid_csrf_token(): void
    {
        $this->authenticate();
        $html = $this->dashboard()->body();

        self::assertMatchesRegularExpression('/<form\b[^>]*method="post"[^>]*action="\/logout"/i', $html);
        self::assertSame(1, preg_match('/name="_token"\s+value="([^"]+)"/', $html, $matches));
        $token = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        self::assertTrue((new Csrf())->verify(new Session(), $token));
        $response = $this->app->handle(new Request('POST', '/logout', [], ['_token' => $token], []));
        self::assertEquals(Response::redirect('/login'), $response);
        self::assertSame([], $_SESSION);
    }

    public function test_browser_school_identifier_cannot_select_another_tenant(): void
    {
        $this->authenticate();

        $response = $this->app->handle(new Request('GET', '/dashboard', [
            'school_id' => $this->otherSchoolId,
        ], [], []));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('โรงเรียนที่ได้รับมอบหมาย A', $response->body());
        self::assertStringNotContainsString('โรงเรียนอื่น B', $response->body());
    }

    public function test_tampered_school_session_returns_friendly_forbidden_page(): void
    {
        $this->authenticate();
        $_SESSION['school_id'] = $this->otherSchoolId;

        $response = $this->dashboard();

        self::assertSame(403, $response->status());
        self::assertStringContainsString('ไม่มีสิทธิ์เข้าใช้งาน', $response->body());
        self::assertStringNotContainsString('โรงเรียนอื่น B', $response->body());
    }

    #[DataProvider('disabledEntities')]
    public function test_disabled_identity_cannot_view_dashboard(string $entity, string $status): void
    {
        $this->authenticate();
        self::assertSame(200, $this->dashboard()->status());
        [$sql, $id] = match ($entity) {
            'membership' => ['UPDATE school_memberships SET status = ? WHERE id = ?', $this->membershipId],
            'school' => ['UPDATE schools SET status = ? WHERE id = ?', $this->schoolId],
            'user' => ['UPDATE users SET status = ? WHERE id = ?', $this->userId],
        };
        $this->pdo->prepare($sql)->execute([$status, $id]);

        $response = $this->dashboard();

        self::assertSame(403, $response->status());
        self::assertStringContainsString('ไม่มีสิทธิ์เข้าใช้งาน', $response->body());
        self::assertStringNotContainsString('ครูทดสอบ Dashboard', $response->body());
    }

    public static function disabledEntities(): array
    {
        return [
            'membership suspended' => ['membership', 'SUSPENDED'],
            'school suspended' => ['school', 'SUSPENDED'],
            'school inactive' => ['school', 'INACTIVE'],
            'user suspended' => ['user', 'SUSPENDED'],
            'user inactive' => ['user', 'INACTIVE'],
        ];
    }

    public function test_dynamic_school_and_user_names_are_escaped(): void
    {
        $this->authenticate();
        $_SESSION['display_name'] = '<script>"user"</script>';
        $this->pdo->prepare('UPDATE schools SET name_th = ? WHERE id = ?')
            ->execute(['<img src=x onerror="alert(1)">', $this->schoolId]);

        $html = $this->dashboard()->body();

        self::assertStringContainsString('&lt;script&gt;&quot;user&quot;&lt;/script&gt;', $html);
        self::assertStringContainsString('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('<img', $html);
    }

    public function test_unknown_route_renders_friendly_page_without_request_details(): void
    {
        $response = $this->app->handle(new Request('GET', '/missing-private-path', [], [], []));

        self::assertSame(404, $response->status());
        self::assertStringContainsString('ไม่พบหน้า', $response->body());
        self::assertStringNotContainsString('missing-private-path', $response->body());
        self::assertStringNotContainsString('/Applications/', $response->body());
        self::assertStringNotContainsString('SQLSTATE', $response->body());
        self::assertStringNotContainsString('Stack trace', $response->body());
    }

    public function test_controller_uses_friendly_denial_if_school_is_no_longer_active(): void
    {
        $this->authenticate();
        $this->pdo->prepare('UPDATE schools SET status = ? WHERE id = ?')->execute(['INACTIVE', $this->schoolId]);
        $controller = new DashboardController(new Session(), new SchoolRepository($this->pdo), new Csrf());

        $response = $controller->index();

        self::assertSame(403, $response->status());
        self::assertStringContainsString('ไม่มีสิทธิ์เข้าใช้งาน', $response->body());
    }

    private function authenticate(): void
    {
        $_SESSION = [
            'user_id' => $this->userId,
            'context_type' => 'SCHOOL',
            'school_id' => $this->schoolId,
            'school_membership_id' => $this->membershipId,
            'display_name' => 'ครูทดสอบ Dashboard',
        ];
    }

    private function dashboard(): Response
    {
        return $this->app->handle(new Request('GET', '/dashboard', [], [], []));
    }

    private function insert(string $sql, array $parameters): int
    {
        $this->pdo->prepare($sql)->execute($parameters);

        return (int) $this->pdo->lastInsertId();
    }
}
