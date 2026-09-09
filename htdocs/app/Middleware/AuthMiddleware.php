<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Repositories\UserRepository;
use App\Support\View;

final class AuthMiddleware
{
    public function __construct(private Session $session, private UserRepository $users) {}

    public function handle(Request $request, callable $next): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        if (!$this->users->isActiveById($userId)) {
            return new Response(View::render('errors/403'), 403);
        }

        return $next($request);
    }
}
