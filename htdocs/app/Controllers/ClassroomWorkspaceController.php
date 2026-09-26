<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Http\Session;
use App\Services\AppUiContextService;
use App\Services\ClassroomWorkspaceReadService;
use App\Support\View;

final class ClassroomWorkspaceController
{
    public function __construct(
        private ClassroomWorkspaceReadService $workspaces,
        private Session $session,
        private AppUiContextService $ui
    ) {}

    public function show(int $classroomId): Response
    {
        $workspace = $this->workspaces->getOverview($this->session->get('user_id'), $this->session->get('context_type'),
            $this->session->get('school_id'), $classroomId);
        if ($workspace === null) { return new Response(View::error(404), 404); }

        return new Response(View::page('workspaces/classroom/overview', ['workspace' => $workspace], [
            'ui' => $this->ui->build('workspaces.classrooms'),
            'documentTitle' => 'งานชั้นเรียน — ระบบ ปพ.5',
            'pageTitle' => 'งานชั้นเรียน · ' . $workspace['classroom']['name'],
        ]));
    }
}
