<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\RoleAssignmentRepository;
use App\Repositories\RoleRepository;
use App\Repositories\SchoolMembershipRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\UserRepository;
use DomainException;
use PDO;
use Throwable;

final class SystemSchoolAdministrationService
{
    public function __construct(
        private PDO $pdo,
        private SchoolRepository $schools,
        private UserRepository $users,
        private SchoolMembershipRepository $memberships,
        private RoleRepository $roles,
        private RoleAssignmentRepository $assignments,
        private AuditLogRepository $audit
    ) {}

    /** @return array{school_id: int, user_id: int, school_membership_id: int} */
    public function createSchoolWithAdmin(
        string $schoolCode,
        string $schoolName,
        string $adminUsername,
        string $adminDisplayName,
        ?string $adminEmail,
        #[\SensitiveParameter] string $adminPassword,
        int $actorUserId,
        ?string $ipAddress = null
    ): array {
        $schoolCode = trim($schoolCode);
        $schoolName = trim($schoolName);
        $adminUsername = trim($adminUsername);
        $adminDisplayName = trim($adminDisplayName);
        $adminEmail = $adminEmail === null ? null : trim($adminEmail);
        $adminEmail = $adminEmail === '' ? null : $adminEmail;

        if (strlen($schoolCode) < 2 || strlen($schoolCode) > 30 || !preg_match('/^[A-Za-z0-9._-]+$/D', $schoolCode)) {
            throw new DomainException('รหัสโรงเรียนต้องมี 2–30 ตัวอักษร และใช้เฉพาะ A-Z, a-z, 0-9, จุด, ขีดล่าง หรือขีดกลาง');
        }
        if (mb_strlen($schoolName, 'UTF-8') < 1 || mb_strlen($schoolName, 'UTF-8') > 190) {
            throw new DomainException('ชื่อโรงเรียนต้องมี 1–190 ตัวอักษร');
        }
        if (strlen($adminUsername) < 3 || strlen($adminUsername) > 100 || !preg_match('/^[A-Za-z0-9._-]+$/D', $adminUsername)) {
            throw new DomainException('ชื่อผู้ใช้ต้องมี 3–100 ตัวอักษร และใช้เฉพาะ A-Z, a-z, 0-9, จุด, ขีดล่าง หรือขีดกลาง');
        }
        if (mb_strlen($adminDisplayName, 'UTF-8') < 1 || mb_strlen($adminDisplayName, 'UTF-8') > 190) {
            throw new DomainException('ชื่อผู้ดูแลโรงเรียนต้องมี 1–190 ตัวอักษร');
        }
        if ($adminEmail !== null && filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('กรุณาระบุอีเมลที่ถูกต้อง');
        }
        if (strlen($adminPassword) < 12) {
            throw new DomainException('รหัสผ่านต้องมีอย่างน้อย 12 ตัวอักษร');
        }

        $transactionStarted = false;
        try {
            $this->pdo->beginTransaction();
            $transactionStarted = true;
            $schoolId = $this->schools->create($schoolCode, $schoolName);
            $userId = $this->users->create($adminUsername, $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT), $adminDisplayName);
            $membershipId = $this->memberships->create($userId, $schoolId, $actorUserId);
            $role = $this->roles->findActiveByCode('SCHOOL_ADMIN');
            if ($role === null || $role['scope_type'] !== 'SCHOOL') {
                throw new DomainException('ไม่พบบทบาทผู้ดูแลโรงเรียนที่ใช้งานได้ กรุณาติดต่อผู้ดูแลระบบ');
            }
            $this->assignments->assignSchoolRole($userId, $schoolId, (int) $role['id'], $actorUserId);
            $this->audit->record($schoolId, $actorUserId, 'SCHOOL_CREATED', 'schools', $schoolId, null, [
                'school_code' => $schoolCode,
                'name_th' => $schoolName,
                'status' => 'ACTIVE',
            ], null, $ipAddress);
            $this->audit->record($schoolId, $actorUserId, 'SCHOOL_ADMIN_CREATED', 'users', $userId, null, [
                'username' => $adminUsername,
                'email' => $adminEmail,
                'display_name' => $adminDisplayName,
                'status' => 'ACTIVE',
                'school_membership_id' => $membershipId,
                'role_code' => 'SCHOOL_ADMIN',
            ], null, $ipAddress);
            $this->pdo->commit();

            return ['school_id' => $schoolId, 'user_id' => $userId, 'school_membership_id' => $membershipId];
        } catch (Throwable $exception) {
            if ($transactionStarted && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException('ไม่สามารถสร้างโรงเรียนและผู้ดูแลได้ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ');
        }
    }

    public function changeSchoolStatus(int $schoolId, string $status, int $actorUserId, ?string $ipAddress = null): void
    {
        $transactionStarted = false;
        try {
            $this->pdo->beginTransaction();
            $transactionStarted = true;
            $school = $this->schools->findById($schoolId);
            if ($school === null) {
                throw new DomainException('ไม่พบโรงเรียนที่ต้องการเปลี่ยนสถานะ');
            }
            if (!in_array($status, ['ACTIVE', 'SUSPENDED', 'INACTIVE'], true)) {
                throw new DomainException('สถานะโรงเรียนไม่ถูกต้อง');
            }
            if ($school['status'] !== $status) {
                $this->schools->updateStatus($schoolId, $status);
                $this->audit->record($schoolId, $actorUserId, 'SCHOOL_STATUS_CHANGED', 'schools', $schoolId,
                    ['status' => $school['status']], ['status' => $status], null, $ipAddress);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($transactionStarted && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException('ไม่สามารถเปลี่ยนสถานะโรงเรียนได้ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ');
        }
    }
}
