<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AcademicYearRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\SubjectOfferingRepository;
use App\Repositories\TeachingAssignmentRepository;
use DomainException;
use PDO;
use Throwable;

final class TeachingAssignmentService
{
    public function __construct(
        private PDO $pdo,
        private SchoolRepository $schools,
        private AcademicYearRepository $years,
        private SubjectOfferingRepository $offerings,
        private TeachingAssignmentRepository $assignments,
        private AuditLogRepository $audit
    ) {}

    public function listSubjectTeachers(int $schoolId): array
    {
        try {
            return $this->assignments->listSubjectTeachers($schoolId);
        } catch (Throwable) {
            throw new DomainException('ไม่สามารถอ่านรายชื่อครูประจำวิชาได้ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ');
        }
    }

    /** School and actor IDs come from the authenticated, authorized server context. */
    public function createAssignment(
        int $schoolId,
        int $actorUserId,
        int $userRoleAssignmentId,
        int $subjectOfferingId,
        ?string $ipAddress = null
    ): int {
        return $this->transaction(function () use ($schoolId, $actorUserId, $userRoleAssignmentId, $subjectOfferingId, $ipAddress): int {
            $this->lockSchool($schoolId);
            $offering = $this->lockOpenOffering($schoolId, $subjectOfferingId);
            $this->eligibleTeacher($schoolId, $userRoleAssignmentId, $offering);
            $existing = $this->assignments->lockPair($schoolId, $userRoleAssignmentId, $subjectOfferingId);
            if ($existing !== null) {
                $this->setStatus($schoolId, $actorUserId, $existing, 'ACTIVE', $ipAddress);
                return (int) $existing['id'];
            }
            $yearId = (int) $offering['academic_year_id'];
            $id = $this->assignments->create($schoolId, $yearId, $userRoleAssignmentId, $subjectOfferingId, $actorUserId);
            $this->audit->record($schoolId, $actorUserId, 'TEACHING_ASSIGNMENT_CREATED', 'permission_scopes', $id, null,
                ['user_role_assignment_id' => $userRoleAssignmentId, 'subject_offering_id' => $subjectOfferingId,
                    'academic_year_id' => $yearId, 'status' => 'ACTIVE'], null, $ipAddress);

            return $id;
        });
    }

    /** School and actor IDs come from the authenticated, authorized server context. */
    public function changeStatus(int $schoolId, int $actorUserId, int $teachingAssignmentId, string $status, ?string $ipAddress = null): void
    {
        $this->transaction(function () use ($schoolId, $actorUserId, $teachingAssignmentId, $status, $ipAddress): void {
            $this->lockSchool($schoolId);
            if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
                throw new DomainException('สถานะการมอบหมายครูไม่ถูกต้อง');
            }
            // Discovery only. Re-read under locks after school -> year -> offering -> teacher.
            $hint = $this->assignments->findForSchool($schoolId, $teachingAssignmentId);
            if ($hint === null) { throw new DomainException('ไม่พบการมอบหมายครูในโรงเรียนนี้'); }
            $offering = $this->lockOpenOffering($schoolId, (int) $hint['subject_offering_id']);
            if ($status === 'ACTIVE') {
                $this->eligibleTeacher($schoolId, (int) $hint['user_role_assignment_id'], $offering);
            }
            $target = $this->assignments->lockForSchool($schoolId, $teachingAssignmentId);
            if ($target === null
                || $target['subject_offering_id'] !== (int) $offering['id']
                || $target['academic_year_id'] !== (int) $offering['academic_year_id']
                || $target['user_role_assignment_id'] !== $hint['user_role_assignment_id']) {
                throw new DomainException('ไม่พบการมอบหมายครูในโรงเรียนนี้');
            }
            // Turning OFF does not depend on the teacher/offering remaining active.
            $this->setStatus($schoolId, $actorUserId, $target, $status, $ipAddress);
        });
    }

    private function lockSchool(int $schoolId): void
    {
        if ($this->schools->lockActiveById($schoolId) === null) {
            throw new DomainException('ไม่พบโรงเรียนที่เปิดใช้งาน');
        }
    }

    private function lockOpenOffering(int $schoolId, int $offeringId): array
    {
        $hint = $this->offerings->findForSchool($schoolId, $offeringId);
        if ($hint === null) { throw new DomainException('ไม่พบการเปิดรายวิชาในโรงเรียนนี้'); }
        $yearId = (int) $hint['academic_year_id'];
        $year = $this->years->lockForSchool($schoolId, $yearId);
        if ($year === null || !in_array($year['status'], ['DRAFT', 'ACTIVE'], true)) {
            throw new DomainException('ไม่สามารถแก้ไขการมอบหมายครูในปีการศึกษาที่ปิดแล้ว');
        }
        $offering = $this->offerings->lockForSchool($schoolId, $offeringId);
        if ($offering === null || (int) $offering['academic_year_id'] !== $yearId) {
            throw new DomainException('ไม่พบการเปิดรายวิชาในโรงเรียนนี้');
        }

        return $offering;
    }

    private function eligibleTeacher(int $schoolId, int $userRoleAssignmentId, array $offering): void
    {
        if ($offering['status'] !== 'ACTIVE') {
            throw new DomainException('กรุณาเลือกการเปิดรายวิชาที่เปิดใช้งาน');
        }
        if ($this->assignments->lockEligibleSubjectTeacher($schoolId, $userRoleAssignmentId) === null) {
            throw new DomainException('ไม่พบครูประจำวิชาที่มีสิทธิ์รับการมอบหมายในโรงเรียนนี้');
        }
    }

    private function setStatus(int $schoolId, int $actorUserId, array $target, string $status, ?string $ipAddress): void
    {
        if (!in_array($target['status'], ['ACTIVE', 'INACTIVE'], true)) {
            throw new DomainException('สถานะการมอบหมายครูไม่ถูกต้อง');
        }
        if ($target['status'] === $status) { return; }
        $id = (int) $target['id'];
        $this->assignments->updateStatus($schoolId, $id, $status);
        $this->audit->record($schoolId, $actorUserId, 'TEACHING_ASSIGNMENT_STATUS_CHANGED', 'permission_scopes', $id,
            ['status' => $target['status']], ['status' => $status], null, $ipAddress);
    }

    private function transaction(callable $operation): mixed
    {
        $started = false;
        try {
            $this->pdo->beginTransaction();
            $started = true;
            $result = $operation();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($started && $this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            if ($exception instanceof DomainException) { throw $exception; }
            throw new DomainException('ไม่สามารถบันทึกการมอบหมายครูได้ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ');
        }
    }
}
