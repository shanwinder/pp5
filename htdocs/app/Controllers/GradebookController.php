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
        $work = $this->gradebooks->teachingWork((int) $this->session->get('user_id'), (string) $this->session->get('context_type'),
            (int) $this->session->get('school_id'), $ui['gradebooks']);
        return new Response(View::page('gradebook/index', ['offerings' => $work], [
            'documentTitle' => 'งานสอนของฉัน — ระบบ ปพ.5', 'pageTitle' => 'งานสอนของฉัน', 'ui' => $ui,
        ]));
    }

    public function show(int $offeringId): Response
    {
        $gradebook = $this->gradebooks->getGradebook($this->session->get('user_id'), $this->session->get('context_type'),
            $this->session->get('school_id'), $offeringId);
        if ($gradebook === null) { return new Response(View::error(404), 404); }

        $offering = $gradebook['offering'];
        $canScore = $this->gradebooks->canEnterScores((int) $this->session->get('user_id'),
            (string) $this->session->get('context_type'), (int) $this->session->get('school_id'), $offering);

        return new Response(View::page('gradebook/view', [
            'gradebook' => $gradebook, 'canScore' => $canScore,
            'canManageComponents' => $this->authorization->hasPermission($this->session->get('user_id'),
                $this->session->get('context_type'), $this->session->get('school_id'), 'GRADEBOOK_COMPONENT_MANAGE'),
            'csrfToken' => $canScore ? $this->csrf->token($this->session) : null,
        ], [
            'ui' => $this->ui->build('gradebooks', false, $this->ui->classroomWorkspace((int) $offering['classroom_id'])), 'documentTitle' => 'สมุดคะแนน — ระบบ ปพ.5', 'pageTitle' => 'สมุดคะแนน',
            'headAssets' => View::render($canScore ? 'gradebook/scoring-assets' : 'gradebook/selection-assets'),
        ]));
    }
}
