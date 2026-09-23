<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Http\Session;
use App\Services\GradebookReadService;
use App\Services\AppUiContextService;
use App\Services\AuthorizationService;
use App\Support\Csrf;
use App\Support\View;

final class GradebookController
{
    public function __construct(
        private GradebookReadService $gradebooks,
        private Session $session,
        private AuthorizationService $authorization,
        private Csrf $csrf,
        private AppUiContextService $ui
    ) {}

    public function index(): Response
    {
        $ui = $this->ui->build('gradebooks', true);
        return new Response(View::page('gradebook/index', ['offerings' => $ui['gradebooks']], [
            'documentTitle' => 'สมุดคะแนน — ระบบ ปพ.5', 'pageTitle' => 'สมุดคะแนน', 'ui' => $ui,
        ]));
    }

    public function show(int $offeringId): Response
    {
        $gradebook = $this->gradebooks->getGradebook($this->session->get('user_id'), $this->session->get('context_type'),
            $this->session->get('school_id'), $offeringId);
        if ($gradebook === null) { return new Response(View::error(404), 404); }

        $offering = $gradebook['offering'];
        $canScore = $offering['status'] === 'ACTIVE' && in_array($offering['academic_year_status'], ['DRAFT', 'ACTIVE'], true)
            && $this->authorization->hasSubjectOfferingPermission($this->session->get('user_id'), $this->session->get('context_type'),
                $this->session->get('school_id'), $offeringId, 'GRADEBOOK_SCORE_ENTER');

        return new Response(View::render('gradebook/view', [
            'gradebook' => $gradebook, 'canScore' => $canScore,
            'csrfToken' => $canScore ? $this->csrf->token($this->session) : null,
        ]));
    }
}
