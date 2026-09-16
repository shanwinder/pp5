<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\{Request, Response, Session};
use App\Services\{GradebookReadService, GradebookScoreService};
use App\Support\{Csrf, View};
use DomainException;
use Throwable;

final class GradebookScoreController
{
    public function __construct(
        private GradebookScoreService $scores,
        private GradebookReadService $gradebooks,
        private Session $session,
        private Csrf $csrf
    ) {}

    public function store(Request $request, int $offeringId, int $componentId, int $enrollmentId): Response
    {
        $token = $request->post('_token');
        if (!$this->csrf->verify($this->session, is_string($token) ? $token : null)) {
            return new Response('CSRF token mismatch', 419);
        }
        $score = $request->post('score');
        if (!is_string($score)) { return $this->error('กรุณากรอกคะแนนให้ถูกต้อง', 422); }
        $schoolId = $this->session->get('school_id');
        $actorUserId = $this->session->get('user_id');
        $ip = $request->server('REMOTE_ADDR');
        $ip = is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
        try {
            $result = $this->scores->setScore($schoolId, $actorUserId, $offeringId, $componentId, $enrollmentId,
                $score === '' ? null : $score, $ip);
        } catch (DomainException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        // Reuse the authorized, batched read model; never reconstruct totals from the POST.
        // A refresh failure after the transaction must not claim that the write was rolled back.
        try {
            $gradebook = $this->gradebooks->getGradebook($actorUserId, $this->session->get('context_type'), $schoolId, $offeringId);
            foreach ($gradebook['rows'] ?? [] as $row) {
                if ($row['enrollment_id'] !== $enrollmentId) { continue; }
                return new Response(View::render('gradebook/score-cell', [
                    'offeringId' => $offeringId, 'componentId' => $componentId, 'enrollmentId' => $enrollmentId,
                    'score' => $result['score'], 'saved' => true,
                ]) . View::render('gradebook/row-summary', ['offeringId' => $offeringId, 'row' => $row, 'outOfBand' => true]),
                    200, ['Content-Type' => 'text/html; charset=UTF-8', 'X-Gradebook-Saved' => '1', 'Cache-Control' => 'no-store']);
            }
        } catch (Throwable) {
            // The transaction has completed, but the browser cannot confirm its fresh summary.
        }

        return $this->error('ส่งบันทึกแล้วแต่โหลดผลล่าสุดไม่สำเร็จ กรุณาโหลดหน้าใหม่เพื่อตรวจสอบคะแนน', 409);
    }

    private function error(string $message, int $status): Response
    {
        return new Response(View::render('gradebook/score-error', ['message' => $message]), $status);
    }
}
