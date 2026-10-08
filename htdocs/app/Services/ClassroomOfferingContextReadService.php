<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\GradebookComponentRepository;
use App\Repositories\SubjectOfferingRepository;

/** Detail projection starts from the live, authorized classroom offering list. */
final class ClassroomOfferingContextReadService
{
    public function __construct(
        private ClassroomSubjectsReadService $subjects,
        private SubjectOfferingRepository $offerings,
        private GradebookComponentRepository $components
    ) {}

    public function get(int $userId, string $contextType, int $schoolId, int $classroomId, int $offeringId): ?array
    {
        $work = $this->subjects->getSubjects($userId, $contextType, $schoolId, $classroomId);
        if ($work === null) { return null; }
        $selected = null;
        foreach ($work['offerings'] as $offering) {
            if ($offering['id'] === $offeringId) { $selected = $offering; break; }
        }
        if ($selected === null) { return null; }
        $live = $this->offerings->findForSchool($schoolId, $offeringId);
        if ($live === null || (int) $live['classroom_id'] !== $classroomId
            || (int) $live['academic_year_id'] !== (int) $work['workspace']['academicYear']['id']) {
            return null;
        }

        $canReadComponents = $selected['canSetup'] || $selected['canOpenGradebook'];
        $components = $canReadComponents ? $this->components->listForOffering($schoolId, $offeringId) : [];
        if (!$selected['canSetup']) {
            $components = array_values(array_filter($components, static fn (array $item): bool => $item['status'] === 'ACTIVE'));
        }
        return [
            'work' => $work,
            'offering' => $selected,
            'components' => $components,
            'historyIds' => $selected['canSetup'] ? $this->components->historyIdsForOffering($schoolId, $offeringId) : [],
            'canReadComponents' => $canReadComponents,
            'canMutateComponents' => $selected['canSetup'] && $work['openYear'] && $selected['status'] === 'ACTIVE',
            'canMutateAssignments' => $selected['canAssign'],
        ];
    }
}
