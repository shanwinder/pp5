<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Services\AuthorizationService;
use App\Support\View;

final class PermissionMiddleware
{
    public function __construct(
        private Session $session,
        private AuthorizationService $authorization,
        private string $permissionCode
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        $userId = $this->session->get('user_id');
        $contextType = $this->session->get('context_type');
        $schoolId = $this->session->get('school_id');

        if (!is_int($userId) || !is_string($contextType) || $contextType === ''
            || ($schoolId !== null && !is_int($schoolId))
            || ($contextType === 'SCHOOL' && $schoolId === null)) {
            return Response::redirect('/login');
        }

        if (!$this->authorization->hasPermission($userId, $contextType, $schoolId, $this->permissionCode)) {
            return new Response(View::render('errors/403'), 403);
        }

        return $next($request);
    }
}
