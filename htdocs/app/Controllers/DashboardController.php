<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Http\Session;
use App\Repositories\SchoolRepository;
use App\Services\AuthorizationService;
use App\Services\GradebookReadService;
use App\Support\AccessContext;
use App\Support\Csrf;
use App\Support\View;

final class DashboardController
{
    public function __construct(
        private Session $session,
        private SchoolRepository $schools,
        private Csrf $csrf,
        private AuthorizationService $authorization,
        private GradebookReadService $gradebooks
    ) {}

    public function index(): Response
    {
        $school = $this->schools->findActiveById((int) $this->session->get('school_id'));

        if ($school === null) {
            return new Response(View::render('errors/403'), 403);
        }

        return new Response(View::render('dashboard/index', [
            'school' => $school,
            'gradebookOfferings' => $this->gradebooks->listAccessibleOfferings(
                $this->session->get('user_id'), $this->session->get('context_type'), $this->session->get('school_id')
            ),
            'displayName' => (string) $this->session->get('display_name', ''),
            'csrfToken' => $this->csrf->token($this->session),
            'canManageUsers' => $this->authorization->hasPermission(
                $this->session->get('user_id'), AccessContext::SCHOOL,
                $this->session->get('school_id'), 'SCHOOL_USER_VIEW'
            ),
            'canViewAcademicSetup' => $this->authorization->hasPermission(
                $this->session->get('user_id'), AccessContext::SCHOOL,
                $this->session->get('school_id'), 'ACADEMIC_SETUP_VIEW'
            ),
            'canImportStudents' => $this->authorization->hasPermission(
                $this->session->get('user_id'), AccessContext::SCHOOL,
                $this->session->get('school_id'), 'STUDENT_IMPORT'
            ),
            'canManageTeachingAssignments' => $this->authorization->hasPermission(
                $this->session->get('user_id'), AccessContext::SCHOOL,
                $this->session->get('school_id'), 'TEACHING_ASSIGNMENT_MANAGE'
            ),
            'canViewStudents' => $this->authorization->hasPermission(
                $this->session->get('user_id'), AccessContext::SCHOOL,
                $this->session->get('school_id'), 'STUDENT_VIEW'
            ),
        ]));
    }
}
