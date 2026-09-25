<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Http\Session;
use App\Repositories\SchoolRepository;
use App\Services\AppUiContextService;
use App\Support\View;

final class DashboardController
{
    public function __construct(
        private Session $session,
        private SchoolRepository $schools,
        private AppUiContextService $ui
    ) {}

    public function index(): Response
    {
        $school = $this->schools->findActiveById((int) $this->session->get('school_id'));

        if ($school === null) {
            return new Response(View::error(403), 403);
        }

        $ui = $this->ui->build('dashboard', true);
        return new Response(View::page('dashboard/index', ['ui' => $ui], [
            'documentTitle' => 'แดชบอร์ด — ระบบ ปพ.5',
            'pageTitle' => 'แดชบอร์ด',
            'ui' => $ui,
        ]));
    }
}
