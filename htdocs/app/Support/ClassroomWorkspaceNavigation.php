<?php
declare(strict_types=1);

namespace App\Support;

/** Presentation of an authorized read model. Route keys are supplied by controllers. */
final class ClassroomWorkspaceNavigation
{
    public static function items(array $workspace, string $currentKey): array
    {
        $base = '/workspaces/classrooms/' . $workspace['classroom']['id'];
        $items = [['key' => 'overview', 'label' => 'ภาพรวม', 'url' => $base]];
        $labels = ['students' => 'นักเรียน', 'subjects' => 'รายวิชาในปีนี้', 'teaching' => 'ครูผู้สอนในปีนี้'];
        foreach ($workspace['links'] as $link) {
            $items[] = ['key' => $link['key'], 'label' => $labels[$link['key']], 'url' => $link['url']];
        }
        if ($workspace['capabilities']['scores']) {
            $items[] = ['key' => 'scores', 'label' => 'คะแนน', 'url' => $base . '#workspace-scores'];
        }
        // Descendant keys inherit their implemented section, without registering speculative routes.
        $active = 'overview';
        foreach (['enrollments' => 'students', 'academic.offerings' => 'subjects',
            'teaching-assignments' => 'teaching', 'gradebooks' => 'scores'] as $prefix => $section) {
            if ($currentKey === $prefix || str_starts_with($currentKey, $prefix . '.')) { $active = $section; }
        }
        if (str_starts_with($currentKey, 'workspaces.classrooms.')) {
            $section = explode('.', substr($currentKey, strlen('workspaces.classrooms.')))[0];
            if (in_array($section, array_column($items, 'key'), true)) { $active = $section; }
        }
        foreach ($items as &$item) { $item['active'] = $item['key'] === $active; }
        return $items;
    }
}
