<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Http\Session;
use App\Services\AppUiContextService;
use App\Services\ClassroomWorkspaceReadService;
use App\Services\ClassroomRosterReadService;
use App\Services\ClassroomSubjectsReadService;
use App\Services\ClassroomOfferingContextReadService;
use App\Support\View;

final class ClassroomWorkspaceController
{
    public function __construct(
        private ClassroomWorkspaceReadService $workspaces,
        private Session $session,
        private AppUiContextService $ui,
        private ClassroomRosterReadService $rosters,
        private ClassroomSubjectsReadService $subjects,
        private ClassroomOfferingContextReadService $offeringContexts,
        private ClassroomSubjectWorkflowController $subjectWorkflow
    ) {}

    public function students(int $classroomId): Response
    {
        $roster = $this->rosters->getRoster($this->session->get('user_id'), $this->session->get('context_type'),
            $this->session->get('school_id'), $classroomId);
        if ($roster === null) { return new Response(View::error(404), 404); }

        return new Response(View::page('workspaces/classroom/students', $roster, [
            'ui' => $this->ui->build('workspaces.classrooms.students', false, $roster['workspace']),
            'headAssets' => View::render('workspaces/classroom/student-assets'),
            'documentTitle' => 'นักเรียนในห้อง — ระบบ ปพ.5',
            'pageTitle' => 'นักเรียน · ' . $roster['workspace']['classroom']['name'],
            'bodyClass' => 'pp5-classroom-pilot',
        ]));
    }

    public function subjects(\App\Http\Request $request, int $classroomId): Response
    {
        $subjectWork = $this->subjects->getSubjects($this->session->get('user_id'), $this->session->get('context_type'),
            $this->session->get('school_id'), $classroomId);
        if ($subjectWork === null) { return new Response(View::error(404), 404); }

        $selectedPanel = null;
        if ($request->query('offering_id') !== null) {
            $id = filter_var($request->query('offering_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) { return new Response(View::error(404), 404); }
            $context = $this->offeringContexts->get((int) $this->session->get('user_id'),
                (string) $this->session->get('context_type'), (int) $this->session->get('school_id'), $classroomId, $id);
            if ($context === null) { return new Response(View::error(404), 404); }
            $selectedPanel = $this->subjectWorkflow->panelHtml($context);
        }
        $ui = $this->ui->build('workspaces.classrooms.subjects', false, $subjectWork['workspace']);
        return new Response(View::page('workspaces/classroom/subjects', $subjectWork + ['selectedPanel' => $selectedPanel], [
            'ui' => $ui,
            'headAssets' => View::render('workspaces/classroom/subject-assets'),
            'documentTitle' => 'รายวิชาและครู — ระบบ ปพ.5',
            'pageTitle' => 'รายวิชาและครู · ' . $subjectWork['workspace']['classroom']['name'],
        ]));
    }

    public function show(int $classroomId): Response
    {
        $workspace = $this->workspaces->getOverview($this->session->get('user_id'), $this->session->get('context_type'),
            $this->session->get('school_id'), $classroomId);
        if ($workspace === null) { return new Response(View::error(404), 404); }

        $studentCount = null;
        if ($workspace['capabilities']['students']) {
            $roster = $this->rosters->getRoster($this->session->get('user_id'), $this->session->get('context_type'),
                $this->session->get('school_id'), $classroomId);
            $studentCount = $roster === null ? null : count($roster['students']);
        }
        $offeringCount = null;
        $configuredCount = null;
        if ($workspace['capabilities']['subjects'] || $workspace['capabilities']['teaching'] || $workspace['capabilities']['scores']) {
            $subjectWork = $this->subjects->getSubjects($this->session->get('user_id'), $this->session->get('context_type'),
                $this->session->get('school_id'), $classroomId);
            if ($subjectWork !== null) {
                $offeringCount = count($subjectWork['offerings']);
                $configuredCount = count(array_filter($subjectWork['offerings'],
                    static fn (array $offering): bool => (int) $offering['scoreSummary']['active_count'] > 0));
            }
        }

        return new Response(View::page('workspaces/classroom/overview', compact('workspace', 'studentCount', 'offeringCount', 'configuredCount'), [
            'ui' => $this->ui->build('workspaces.classrooms', false, $workspace),
            'documentTitle' => 'งานชั้นเรียน — ระบบ ปพ.5',
            'pageTitle' => 'งานชั้นเรียน · ' . $workspace['classroom']['name'],
            'bodyClass' => 'pp5-classroom-pilot',
        ]));
    }
}
