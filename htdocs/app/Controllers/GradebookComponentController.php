<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Repositories\GradebookComponentRepository;
use App\Repositories\SubjectOfferingRepository;
use App\Services\GradebookComponentService;
use App\Services\AppUiContextService;
use App\Services\ClassroomWorkspaceReadService;
use App\Support\Csrf;
use App\Support\View;
use DomainException;

final class GradebookComponentController
{
    public function __construct(
        private GradebookComponentService $service,
        private GradebookComponentRepository $components,
        private SubjectOfferingRepository $offerings,
        private Session $session,
        private Csrf $csrf,
        private AppUiContextService $ui,
        private ClassroomWorkspaceReadService $workspaces
    ) {}

    public function setup(int $offeringId): Response
    {
        $offering = $this->offerings->findForSchool($this->session->get('school_id'), $offeringId);
        if ($offering === null) { return new Response(View::error(404), 404); }

        return $this->page($offering);
    }

    public function store(Request $request, int $offeringId): Response
    {
        return $this->mutate($request, $offeringId, function () use ($request, $offeringId): void {
            if ($request->post('code') === null && $request->post('sort_order') === null) {
                $this->service->createScoreItem($this->session->get('school_id'), $this->session->get('user_id'), $offeringId,
                    $this->stringField($request, 'name_th'), $this->stringField($request, 'max_score'), $this->ipAddress($request));
            } else {
                $this->service->createComponent($this->session->get('school_id'), $this->session->get('user_id'), $offeringId,
                    $this->stringField($request, 'code'), $this->stringField($request, 'name_th'), $this->stringField($request, 'max_score'),
                    $request->post('sort_order'), $this->ipAddress($request));
            }
        });
    }

    public function update(Request $request, int $offeringId, int $componentId): Response
    {
        return $this->mutate($request, $offeringId, function () use ($request, $offeringId, $componentId): void {
            if ($request->post('code') === null && $request->post('sort_order') === null) {
                $this->service->updateScoreItem($this->session->get('school_id'), $this->session->get('user_id'), $offeringId, $componentId,
                    $this->stringField($request, 'name_th'), $this->stringField($request, 'max_score'), $this->ipAddress($request));
            } else {
                $this->service->updateComponent($this->session->get('school_id'), $this->session->get('user_id'), $offeringId, $componentId,
                    $this->stringField($request, 'code'), $this->stringField($request, 'name_th'), $this->stringField($request, 'max_score'),
                    $request->post('sort_order'), $this->ipAddress($request));
            }
        });
    }

    public function changeStatus(Request $request, int $offeringId, int $componentId): Response
    {
        return $this->mutate($request, $offeringId, function () use ($request, $offeringId, $componentId): void {
            $this->service->changeStatus($this->session->get('school_id'), $this->session->get('user_id'), $offeringId, $componentId,
                $this->stringField($request, 'status'), $this->ipAddress($request));
        });
    }

    private function mutate(Request $request, int $offeringId, callable $operation): Response
    {
        $token = $request->post('_token');
        if (!$this->csrf->verify($this->session, is_string($token) ? $token : null)) { return new Response('CSRF token mismatch', 419); }
        try {
            $operation();
        } catch (DomainException $exception) {
            return $this->page($this->offerings->findForSchool($this->session->get('school_id'), $offeringId), $exception->getMessage(), 422);
        }

        return Response::redirect('/gradebook/' . $offeringId . '/setup');
    }

    private function page(?array $offering, ?string $error = null, int $status = 200): Response
    {
        $schoolId = (int) $this->session->get('school_id');
        $components = $offering === null ? [] : $this->components->listForOffering($schoolId, (int) $offering['id']);
        $summary = $offering === null ? null : ($this->components->summariesForOfferings($schoolId, [(int) $offering['id']])[(int) $offering['id']] ?? null);
        $historyIds = $offering === null ? [] : $this->components->historyIdsForOffering($schoolId, (int) $offering['id']);
        $workspace = $offering === null ? null : $this->workspaces->getOverview((int) $this->session->get('user_id'),
            (string) $this->session->get('context_type'), $schoolId, (int) $offering['classroom_id']);
        return new Response(View::page('gradebook/setup', [
            'offering' => $offering,
            'components' => $components,
            'summary' => $summary ?? ['active_count' => 0, 'inactive_count' => 0, 'active_max_total' => '0.00'],
            'historyIds' => $historyIds,
            'workspace' => $workspace,
            'canMutate' => $offering !== null && in_array($offering['academic_year_status'], ['DRAFT', 'ACTIVE'], true) && $offering['status'] === 'ACTIVE',
            'csrfToken' => $this->csrf->token($this->session),
            'error' => $error,
        ], [
            'ui' => $this->ui->build($workspace === null ? 'gradebooks' : 'workspaces.classrooms.subjects', false, $workspace),
            'documentTitle' => 'การเก็บคะแนน — ระบบ ปพ.5',
            'pageTitle' => 'การเก็บคะแนน' . ($offering === null ? '' : ' · ' . $offering['subject_name']),
        ]), $status);
    }

    private function stringField(Request $request, string $name): string
    {
        $value = $request->post($name);
        if (!is_string($value)) { throw new DomainException('กรุณากรอกข้อมูลโครงสร้างคะแนนให้ถูกต้อง'); }

        return $value;
    }

    private function ipAddress(Request $request): ?string
    {
        $value = $request->server('REMOTE_ADDR');

        return is_string($value) && filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : null;
    }
}
