<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\{Request, Response, Session};
use App\Services\{GradebookReadService, GradebookScoreService};
use App\Support\{Csrf, View};
use App\Services\GradebookBatchException;
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

    public function storeBatch(Request $request, int $offeringId): Response
    {
        $token = $request->post('_token');
        if (!$this->csrf->verify($this->session, is_string($token) ? $token : null)) {
            return $this->batchResponse(['committed' => false, 'message' => 'เซสชันหมดอายุ กรุณาโหลดหน้าใหม่ก่อนวางคะแนน'], 419);
        }
        $body = $request->post('batch');
        if (!is_string($body) || strlen($body) > GradebookScoreService::MAX_BATCH_BYTES) {
            return $this->batchResponse(['committed' => false, 'message' => 'ข้อมูลตารางคะแนนไม่ถูกต้องหรือมีขนาดใหญ่เกินไป'], 422);
        }
        try {
            $decoded = json_decode($body, false, 16, JSON_THROW_ON_ERROR);
            if (!$decoded instanceof \stdClass) { throw new \JsonException(); }
            $matrix = get_object_vars($decoded);
        } catch (\JsonException) {
            return $this->batchResponse(['committed' => false, 'message' => 'รูปแบบตารางคะแนนไม่ถูกต้อง'], 422);
        }
        $schoolId = $this->session->get('school_id');
        $actorUserId = $this->session->get('user_id');
        $ip = $request->server('REMOTE_ADDR');
        $ip = is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
        try {
            $result = $this->scores->setScoresBatch($schoolId, $actorUserId, $offeringId, $matrix, $ip);
        } catch (DomainException $exception) {
            $error = ['committed' => false, 'message' => $exception->getMessage()];
            if ($exception instanceof GradebookBatchException) { $error['location'] = $exception->location; }
            return $this->batchResponse($error, 422);
        }
        // Commit has completed. Failure below must never claim rollback or invite an automatic retry.
        try {
            $rows = $this->gradebooks->getAffectedRows($actorUserId, $this->session->get('context_type'), $schoolId, $offeringId, $matrix['enrollment_ids']);
            if ($rows === null || count($rows) !== $result['row_count']) { throw new \UnexpectedValueException(); }
            $byId = array_column($rows, null, 'enrollment_id');
            foreach ($result['cells'] as &$cell) {
                $row = $byId[$cell['enrollment_id']] ?? null;
                if ($row === null || !array_key_exists($cell['component_id'], $row['scores'])) { throw new \UnexpectedValueException(); }
                $cell['score'] = $row['scores'][$cell['component_id']];
                unset($cell['score_id'], $cell['subject_offering_id']);
            }
            unset($cell);
            $result['rows'] = [];
            foreach ($matrix['enrollment_ids'] as $id) {
                $row = $byId[$id];
                $result['rows'][] = array_intersect_key($row, array_flip(['enrollment_id', 'entered_score_total', 'configured_max_total',
                    'entered_component_count', 'active_component_count', 'complete']));
            }
            return $this->batchResponse(['committed' => true] + $result, 200, true);
        } catch (Throwable) {
            return $this->batchResponse(['committed' => true,
                'message' => 'บันทึกคะแนนแล้ว แต่โหลดผลล่าสุดไม่สำเร็จ กรุณาโหลดหน้าใหม่เพื่อตรวจสอบคะแนนก่อนแก้ไขต่อ'], 409);
        }
    }

    private function batchResponse(array $body, int $status, bool $saved = false): Response
    {
        $headers = ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store'];
        if ($saved) { $headers['X-Gradebook-Batch-Saved'] = '1'; }
        return new Response(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR), $status, $headers);
    }

    private function error(string $message, int $status): Response
    {
        return new Response(View::render('gradebook/score-error', ['message' => $message]), $status);
    }
}
