<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Http\Session;
use App\Services\GradebookReadService;
use App\Support\View;

final class GradebookController
{
    public function __construct(private GradebookReadService $gradebooks, private Session $session) {}

    public function show(int $offeringId): Response
    {
        $gradebook = $this->gradebooks->getGradebook($this->session->get('user_id'), $this->session->get('context_type'),
            $this->session->get('school_id'), $offeringId);
        if ($gradebook === null) { return new Response(View::render('errors/404'), 404); }

        return new Response(View::render('gradebook/view', ['gradebook' => $gradebook]));
    }
}
