<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\GradebookAnnualCourseRepository;
use App\Repositories\GradebookGovernanceRepository;
use DomainException;
use PDO;
use Throwable;

/**
 * B1 internal, unwired foundation. No HTTP route, container wiring or feature flag.
 * Creates an unconfigured annual identity and explicit links atomically; never publishes/adopts.
 * Caller supplies authenticated server context and a server-issued stable UUID for retries.
 * Authorization lock-order proof remains a B3 activation gate (Condition C).
 */
final class GradebookAnnualCourseFoundationService
{
    public function __construct(private PDO $pdo) {}

    public function create(int $schoolId, int $actorId, int $yearId, int $classroomId,
        string $courseKey, string $name, array $confirmedOfferingIds, string $reason): int
    {
        GradebookFoundationIdentity::uuid($courseKey);
        $name = GradebookFoundationIdentity::text($name, 190);
        $reason = GradebookFoundationIdentity::text($reason, 1000);
        if ($confirmedOfferingIds === [] || count($confirmedOfferingIds) > 2
            || count(array_unique($confirmedOfferingIds, SORT_REGULAR)) !== count($confirmedOfferingIds)) {
            throw new DomainException('Confirm one or two explicit actual offerings');
        }
        foreach ($confirmedOfferingIds as $id) {
            if (!is_int($id) || $id <= 0) { throw new DomainException('Invalid offering ID'); }
        }
        sort($confirmedOfferingIds, SORT_NUMERIC);
        if ($this->pdo->inTransaction()) { throw new DomainException('Service owns the transaction'); }
        $this->pdo->beginTransaction();
        try {
            $school = $this->row('SELECT id, status FROM schools WHERE id=? FOR UPDATE', [$schoolId]);
            if (!$school || $school['status'] !== 'ACTIVE') { throw new DomainException('School unavailable'); }
            $governance = new GradebookGovernanceRepository($this->pdo);
            $cutover = $governance->lockCutover($schoolId);
            // B1 cannot be used as a prematurely enabled workflow in an activated school.
            if ($cutover['phase'] !== 'PREPARING') { throw new DomainException('B1 requires inactive PREPARING cutover'); }
            $year = $this->row('SELECT * FROM academic_years WHERE id=? AND school_id=? FOR UPDATE', [$yearId, $schoolId]);
            if (!$year || !in_array($year['status'], ['DRAFT', 'ACTIVE'], true)) { throw new DomainException('Year is not writable'); }
            $offerings = []; $terms = [];
            foreach ($confirmedOfferingIds as $id) {
                $o = $this->row('SELECT * FROM subject_offerings WHERE id=? AND school_id=? FOR UPDATE', [$id, $schoolId]);
                if (!$o || $o['status'] !== 'ACTIVE') { throw new DomainException('Offering unavailable'); }
                GradebookFoundationIdentity::scope($o, $schoolId, $yearId, $classroomId);
                if (!in_array((int) $o['term_no'], [1, 2], true) || isset($terms[$o['term_no']])) { throw new DomainException('Duplicate or invalid term'); }
                $terms[$o['term_no']] = true; $offerings[] = $o;
            }
            $room = $this->row('SELECT * FROM classrooms WHERE id=? AND school_id=? AND academic_year_id=? FOR UPDATE', [$classroomId, $schoolId, $yearId]);
            if (!$room || $room['status'] !== 'ACTIVE') { throw new DomainException('Classroom unavailable'); }
            $subjects = array_unique(array_column($offerings, 'subject_id')); sort($subjects, SORT_NUMERIC);
            foreach ($subjects as $id) {
                $subject = $this->row('SELECT * FROM subjects WHERE id=? AND school_id=? FOR UPDATE', [$id, $schoolId]);
                if (!$subject || $subject['status'] !== 'ACTIVE') { throw new DomainException('Subject unavailable'); }
            }
            foreach ($offerings as $o) {
                $g = $governance->lockOffering($schoolId, $yearId, (int) $o['id'], $cutover['epoch_key']);
                if ($g['origin'] !== 'PRE_ACTIVATION' || $g['state'] !== 'LEGACY') {
                    throw new DomainException('B1 does not bind activated or published offerings');
                }
            }
            $this->lockAuthority($schoolId, $actorId);
            $repository = new GradebookAnnualCourseRepository($this->pdo);
            $existing = $repository->find($schoolId, $yearId, $classroomId, $courseKey);
            if ($existing !== null) {
                $links = $repository->links($schoolId, $yearId, $classroomId, (int) $existing['id']);
                $audit = $this->row("SELECT reason FROM audit_logs WHERE school_id=? AND user_id=? AND entity_type='gradebook_annual_courses' AND entity_id=? AND action='GRADEBOOK_ANNUAL_CREATED' ORDER BY id LIMIT 1", [$schoolId, $actorId, $existing['id']]);
                if ($existing['name_th'] !== $name || (int) $existing['created_by'] !== $actorId
                    || (int) $existing['revision'] !== 1 || $existing['status'] !== 'ACTIVE'
                    || $existing['coursework_percent'] !== null || $existing['final_percent'] !== null
                    || $existing['ratio_frozen_at'] !== null || $existing['ratio_frozen_by'] !== null
                    || array_map('intval', array_column($links, 'subject_offering_id')) !== $confirmedOfferingIds
                    || ($audit['reason'] ?? null) !== $reason) {
                    throw new DomainException('Annual creation retry conflicts with persisted operation', 409);
                }
                $this->pdo->commit();
                return (int) $existing['id'];
            }
            foreach ($confirmedOfferingIds as $id) {
                if ($this->row('SELECT id FROM gradebook_annual_course_offerings WHERE subject_offering_id=? FOR UPDATE', [$id])) {
                    throw new DomainException('Offering already belongs to an annual course', 409);
                }
            }
            $q = $this->pdo->prepare('INSERT INTO gradebook_annual_courses (school_id,academic_year_id,classroom_id,course_key,name_th,created_by,updated_by) VALUES (?,?,?,?,?,?,?)');
            $q->execute([$schoolId, $yearId, $classroomId, $courseKey, $name, $actorId, $actorId]);
            $annualId = (int) $this->pdo->lastInsertId();
            $q = $this->pdo->prepare('INSERT INTO gradebook_annual_course_offerings (annual_course_id,school_id,academic_year_id,classroom_id,subject_id,term_no,subject_offering_id,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,?)');
            foreach ($offerings as $o) {
                $q->execute([$annualId, $schoolId, $yearId, $classroomId, $o['subject_id'], $o['term_no'], $o['id'], $actorId, $actorId]);
            }
            (new AuditLogRepository($this->pdo))->record($schoolId, $actorId, 'GRADEBOOK_ANNUAL_CREATED', 'gradebook_annual_courses', $annualId, null,
                ['course_key' => $courseKey, 'academic_year_id' => $yearId, 'classroom_id' => $classroomId, 'name_th' => $name,
                    'offerings' => array_map(static fn (array $o): array => array_intersect_key($o, array_flip(['id', 'subject_id', 'term_no'])), $offerings),
                    'coursework_percent' => null, 'final_percent' => null, 'revision' => 1], $reason, null);
            $this->pdo->commit();
            return $annualId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
    }

    private function lockAuthority(int $schoolId, int $actorId): void
    {
        // Existing unscoped SCHOOL grant semantics, with current user/grant locks.
        // Deliberately not wired into or substituted for any accepted authorization path.
        $grant = $this->row("SELECT ura.id FROM user_role_assignments ura
            JOIN users u ON u.id=ura.user_id AND u.status='ACTIVE'
            JOIN roles r ON r.id=ura.role_id AND r.status='ACTIVE' AND r.scope_type='SCHOOL'
            JOIN role_permissions rp ON rp.role_id=r.id AND rp.resource_scope_type IS NULL
            JOIN permissions p ON p.id=rp.permission_id AND p.code='GRADEBOOK_COMPONENT_MANAGE'
            JOIN school_memberships sm ON sm.user_id=ura.user_id AND sm.school_id=ura.school_id AND sm.status='ACTIVE'
            WHERE ura.user_id=? AND ura.school_id=? AND ura.academic_year_id IS NULL AND ura.status='ACTIVE'
            LIMIT 1 FOR UPDATE", [$actorId, $schoolId]);
        if (!$grant) { throw new DomainException('Live unscoped component management permission required', 403); }
    }

    private function row(string $sql, array $parameters): ?array
    {
        $q = $this->pdo->prepare($sql); $q->execute($parameters);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
