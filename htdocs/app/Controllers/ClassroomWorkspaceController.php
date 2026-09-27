<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Http\Session;
use App\Services\AppUiContextService;
use App\Services\ClassroomWorkspaceReadService;
use App\Services\ClassroomRosterReadService;
use App\Support\View;

final class ClassroomWorkspaceController
{
    public function __construct(
        private ClassroomWorkspaceReadService $workspaces,
        private Session $session,
        private AppUiContextService $ui,
        private ClassroomRosterReadService $rosters
    ) {}

    public function students(int $classroomId): Response
    {
        $roster = $this->rosters->getRoster($this->session->get('user_id'), $this->session->get('context_type'),
            $this->session->get('school_id'), $classroomId);
        if ($roster === null) { return new Response(View::error(404), 404); }

        return new Response(View::page('workspaces/classroom/students', $roster, [
            'ui' => $this->ui->build('workspaces.classrooms.students', false, $roster['workspace']),
            'documentTitle' => 'นักเรียนในห้อง — ระบบ ปพ.5',
            'pageTitle' => 'นักเรียน · ' . $roster['workspace']['classroom']['name'],
        ]));
    }

    public function show(int $classroomId): Response
    {
        $workspace = $this->workspaces->getOverview($this->session->get('user_id'), $this->session->get('context_type'),
            $this->session->get('school_id'), $classroomId);
        if ($workspace === null) { return new Response(View::error(404), 404); }

        return new Response(View::page('workspaces/classroom/overview', ['workspace' => $workspace], [
            'ui' => $this->ui->build('workspaces.classrooms', false, $workspace),
            'documentTitle' => 'งานชั้นเรียน — ระบบ ปพ.5',
            'pageTitle' => 'งานชั้นเรียน · ' . $workspace['classroom']['name'],
        ]));
    }
}
