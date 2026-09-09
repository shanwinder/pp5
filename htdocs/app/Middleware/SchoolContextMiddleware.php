<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Repositories\SchoolMembershipRepository;
use App\Repositories\SchoolRepository;
use App\Support\AccessContext;
use App\Support\View;

final class SchoolContextMiddleware
{
    public function __construct(
        private Session $session,
        private SchoolMembershipRepository $memberships,
        private SchoolRepository $schools
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        if ($this->session->get('context_type') !== AccessContext::SCHOOL) {
            return new Response(View::render('errors/403'), 403);
        }

        $userId = $this->session->get('user_id');
        $schoolId = $this->session->get('school_id');
        $membershipId = $this->session->get('school_membership_id');

        if (!is_int($userId) || !is_int($schoolId) || !is_int($membershipId)) {
            return Response::redirect('/login');
        }

        if (!$this->memberships->isActiveMembership($membershipId, $userId, $schoolId)
            || $this->schools->findActiveById($schoolId) === null) {
            return new Response(View::render('errors/403'), 403);
        }

        return $next($request);
    }
}
