<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\GradebookComponentRepository;
use App\Repositories\GradebookRepository;
use App\Repositories\SubjectOfferingRepository;
use App\Support\AccessContext;
use UnexpectedValueException;

final class GradebookReadService
{
    public function __construct(
        private AuthorizationService $authorization,
        private SubjectOfferingRepository $offerings,
        private GradebookComponentRepository $components,
        private GradebookRepository $gradebooks
    ) {}

    public function getGradebook(int $userId, string $contextType, int $schoolId, int $subjectOfferingId): ?array
    {
        if (!$this->authorization->hasSubjectOfferingPermission($userId, $contextType, $schoolId, $subjectOfferingId, 'GRADEBOOK_VIEW')) {
            return null;
        }
        $offering = $this->offerings->findForSchool($schoolId, $subjectOfferingId);
        if ($offering === null) { return null; }
        $components = array_values(array_filter($this->components->listForOffering($schoolId, $subjectOfferingId),
            static fn (array $component): bool => $component['status'] === 'ACTIVE'));
        $configuredMax = '0.00';
        foreach ($components as $component) { $configuredMax = $this->add($configuredMax, $component['max_score']); }
        $roster = $this->gradebooks->listRoster($schoolId, $subjectOfferingId);
        $scores = [];
        foreach ($this->gradebooks->listActiveScores($schoolId, $subjectOfferingId) as $cell) {
            $scores[$cell['enrollment_id']][$cell['component_id']] = $cell['score'];
        }
        $rows = [];
        $activeCount = count($components);
        foreach ($roster as $student) {
            $cells = [];
            $total = '0.00';
            $entered = 0;
            foreach ($components as $component) {
                $score = $scores[$student['enrollment_id']][$component['id']] ?? null;
                $cells[$component['id']] = $score;
                if ($score !== null) { $total = $this->add($total, $score); ++$entered; }
            }
            $rows[] = [
                'enrollment_id' => (int) $student['enrollment_id'],
                'student_code' => $student['student_code'],
                'display_name' => $student['prefix_th'] . $student['first_name_th'] . ' ' . $student['last_name_th'],
                'enrollment_status' => $student['enrollment_status'],
                'row_type' => (bool) $student['is_current'] ? 'CURRENT' : 'HISTORICAL',
                'scores' => $cells,
                'configured_max_total' => $configuredMax,
                'entered_score_total' => $total,
                'entered_component_count' => $entered,
                'active_component_count' => $activeCount,
                'complete' => $activeCount > 0 && $entered === $activeCount,
            ];
        }

        return ['offering' => $offering, 'teachers' => $this->gradebooks->listTeachers($schoolId, $subjectOfferingId),
            'components' => $components, 'configured_max_total' => $configuredMax, 'active_component_count' => $activeCount, 'rows' => $rows];
    }

    public function listAccessibleOfferings(int $userId, string $contextType, int $schoolId): array
    {
        if ($contextType !== AccessContext::SCHOOL || $schoolId <= 0) { return []; }
        $accessible = [];
        foreach ($this->offerings->listForSchool($schoolId) as $offering) {
            if ($this->authorization->hasSubjectOfferingPermission($userId, $contextType, $schoolId, (int) $offering['id'], 'GRADEBOOK_VIEW')) {
                $accessible[] = $offering;
            }
        }

        return $accessible;
    }

    /** Existence only for shell navigation; keep live scoped checks and stop at the first match. */
    public function hasAccessibleOffering(int $userId, string $contextType, int $schoolId): bool
    {
        if ($contextType !== AccessContext::SCHOOL || $schoolId <= 0) { return false; }
        foreach ($this->offerings->listForSchool($schoolId) as $offering) {
            if ($this->authorization->hasSubjectOfferingPermission($userId, $contextType, $schoolId, (int) $offering['id'], 'GRADEBOOK_VIEW')) {
                return true;
            }
        }
        return false;
    }

    /** Add canonical, nonnegative DECIMAL strings without integer-total overflow or rounding. */
    private function add(string $left, string $right): string
    {
        foreach ([$left, $right] as $value) {
            if (!preg_match('/\A[0-9]+\.[0-9]{2}\z/', $value)) { throw new UnexpectedValueException('Invalid stored decimal'); }
        }
        $left = str_replace('.', '', $left);
        $right = str_replace('.', '', $right);
        $carry = 0;
        $sum = '';
        for ($i = strlen($left) - 1, $j = strlen($right) - 1; $i >= 0 || $j >= 0 || $carry !== 0; --$i, --$j) {
            $digit = ($i >= 0 ? (int) $left[$i] : 0) + ($j >= 0 ? (int) $right[$j] : 0) + $carry;
            $sum = (string) ($digit % 10) . $sum;
            $carry = intdiv($digit, 10);
        }
        $sum = str_pad(ltrim($sum, '0'), 3, '0', STR_PAD_LEFT);

        return substr($sum, 0, -2) . '.' . substr($sum, -2);
    }
}
