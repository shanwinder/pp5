<?php
declare(strict_types=1);

use App\Http\Session;
use App\Support\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function test_token_round_trip(): void
    {
        $session = new Session();
        $csrf = new Csrf();

        $token = $csrf->token($session);

        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $token);
        self::assertTrue($csrf->verify($session, $token));
    }

    public function test_token_is_reused_in_the_same_session(): void
    {
        $session = new Session();
        $csrf = new Csrf();

        $token = $csrf->token($session);

        self::assertSame($token, $csrf->token($session));
        self::assertSame($token, (new Csrf())->token(new Session()));
    }

    public function test_wrong_token_is_rejected(): void
    {
        $session = new Session();
        $csrf = new Csrf();
        $token = $csrf->token($session);
        $wrongToken = ($token[0] === 'a' ? 'b' : 'a') . substr($token, 1);

        self::assertFalse($csrf->verify($session, 'wrong'));
        self::assertFalse($csrf->verify($session, $wrongToken));
    }

    public function test_null_token_is_rejected(): void
    {
        $session = new Session();
        $csrf = new Csrf();
        $csrf->token($session);

        self::assertFalse($csrf->verify($session, null));
    }

    public function test_empty_token_is_rejected(): void
    {
        $session = new Session();
        $csrf = new Csrf();
        $csrf->token($session);

        self::assertFalse($csrf->verify($session, ''));
    }

    public function test_token_is_rejected_when_session_has_no_token(): void
    {
        $session = new Session();
        $csrf = new Csrf();

        self::assertFalse($csrf->verify($session, str_repeat('a', 64)));
        self::assertFalse($csrf->verify($session, null));
        self::assertFalse($csrf->verify($session, ''));
        self::assertSame([], $_SESSION);
    }

    public function test_session_put_and_get_preserve_values(): void
    {
        $session = new Session();
        $session->put('user_id', 42);
        $session->put('enabled', false);

        self::assertSame(42, $session->get('user_id'));
        self::assertFalse($session->get('enabled', true));
        self::assertNull($session->get('missing'));
        self::assertSame('fallback', $session->get('missing', 'fallback'));
    }

    public function test_forget_removes_only_the_requested_value(): void
    {
        $session = new Session();
        $session->put('user_id', 42);
        $session->put('school_id', 7);

        $session->forget('user_id');
        $session->forget('missing');

        self::assertNull($session->get('user_id'));
        self::assertSame(['school_id' => 7], $_SESSION);
    }

    public function test_clear_removes_all_session_values(): void
    {
        $session = new Session();
        $session->put('user_id', 42);
        $session->put('school_id', 7);

        $session->clear();

        self::assertSame([], $_SESSION);
        self::assertNull($session->get('user_id'));
        self::assertNull($session->get('school_id'));
    }

    public function test_cleared_session_rejects_old_token_and_issues_a_new_one(): void
    {
        $session = new Session();
        $csrf = new Csrf();
        $oldToken = $csrf->token($session);

        $session->clear();

        self::assertFalse($csrf->verify($session, $oldToken));
        $newToken = $csrf->token($session);
        self::assertNotSame($oldToken, $newToken);
        self::assertTrue($csrf->verify($session, $newToken));
        self::assertFalse($csrf->verify($session, $oldToken));
    }
}
