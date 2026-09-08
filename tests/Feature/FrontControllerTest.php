<?php
declare(strict_types=1);

use App\Application;
use App\Http\Request;
use PHPUnit\Framework\TestCase;

final class FrontControllerTest extends TestCase
{
    public function test_unknown_route_returns_404(): void
    {
        $app = new Application();
        $response = $app->handle(new Request('GET', '/missing', [], [], []));

        self::assertSame(404, $response->status());
    }

    public function test_root_route_returns_200(): void
    {
        $app = new Application();
        $response = $app->handle(new Request('GET', '/', [], [], []));

        self::assertSame(200, $response->status());
        self::assertSame('PP5', $response->body());
    }
}
