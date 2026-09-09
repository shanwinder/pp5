<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\AuthorizationRepository;
use App\Repositories\RoleAssignmentRepository;
use App\Repositories\RoleRepository;
use App\Repositories\SchoolMembershipRepository;
use App\Repositories\UserRepository;
use DomainException;
use PDO;
use Throwable;

final class SchoolUserAdministrationService
{
    public function __construct(
        private PDO $pdo,
        private UserRepository $users,
        private SchoolMembershipRepository $memberships,
        private RoleRepository $roles,
        private RoleAssignmentRepository $assignments,
        private AuditLogRepository $audit,
        private AuthorizationRepository $authorization
    ) {}

    public function createUser(
        int $schoolId,
        int $actorUserId,
        string $username,
        string $displayName,
        ?string $email,
        #[\SensitiveParameter] string $password,
        array $roleCodes,
        ?string $ipAddress = null
    ): int {
        $username = trim($username);
        if (strlen($username) < 3 || strlen($username) > 100 || !preg_match('/^[A-Za-z0-9._-]+$/D', $username)) {
            throw new DomainException('ชื่อผู้ใช้ต้องมี 3–100 ตัวอักษร และใช้เฉพาะ A-Z, a-z, 0-9, จุด, ขีดล่าง หรือขีดกลาง');
        }
        [$displayName, $email] = $this->profile($displayName, $email);
        $this->validatePassword($password);
        $roleCodes = $this->normalizeRoleCodes($roleCodes);

        return $this->transaction(function () use ($schoolId, $actorUserId, $username, $displayName, $email, $password, $roleCodes, $ipAddress): int {
            $roles = $this->resolveRoles($roleCodes);
            $userId = $this->users->create($username, $email, password_hash($password, PASSWORD_DEFAULT), $displayName);
            $this->memberships->create($userId, $schoolId, $actorUserId);
            foreach ($roles as $role) {
                $this->assignments->activateSchoolRole($userId, $schoolId, (int) $role['id'], $actorUserId);
            }
            $this->audit->record($schoolId, $actorUserId, 'SCHOOL_USER_CREATED', 'users', $userId, null,
                ['username' => $username, 'display_name' => $displayName, 'email' => $email, 'status' => 'ACTIVE'], null, $ipAddress);
            $this->audit->record($schoolId, $actorUserId, 'SCHOOL_ROLES_CHANGED', 'users', $userId,
                ['role_codes' => []], ['role_codes' => $roleCodes], null, $ipAddress);

            return $userId;
        });
    }

    public function updateProfile(
        int $schoolId,
        int $actorUserId,
        int $userId,
        string $displayName,
        ?string $email,
        ?string $ipAddress = null
    ): void {
        $this->transaction(function () use ($schoolId, $actorUserId, $userId, $displayName, $email, $ipAddress): void {
            $target = $this->target($schoolId, $userId);
            [$displayName, $email] = $this->profile($displayName, $email);
            $this->users->updateProfile($schoolId, $userId, $displayName, $email);
            $this->audit->record($schoolId, $actorUserId, 'SCHOOL_USER_UPDATED', 'users', $userId,
                ['display_name' => $target['display_name'], 'email' => $target['email']],
                ['display_name' => $displayName, 'email' => $email], null, $ipAddress);
        });
    }

    public function changeMembershipStatus(
        int $schoolId,
        int $actorUserId,
        int $userId,
        string $status,
        ?string $ipAddress = null
    ): void {
        $this->transaction(function () use ($schoolId, $actorUserId, $userId, $status, $ipAddress): void {
            $target = $this->target($schoolId, $userId);
            if (!in_array($status, ['ACTIVE', 'SUSPENDED'], true)) {
                throw new DomainException('สถานะสมาชิกไม่ถูกต้อง');
            }
            if ($userId === $actorUserId && $status === 'SUSPENDED') {
                throw new DomainException('ไม่สามารถระงับสมาชิกของตนเองได้');
            }
            if ($target['status'] === $status) {
                return;
            }
            if ($status === 'ACTIVE'
                && ($this->memberships->findActiveRowsForUser($userId) !== []
                    || $this->authorization->hasActiveSystemAdmin($userId)
                    || $this->assignments->activeSchoolRoleCodes($userId, $schoolId) === [])) {
                throw new DomainException('ไม่สามารถเปิดใช้งานสมาชิกได้ กรุณาตรวจสอบโรงเรียนและบทบาทของบัญชี');
            }
            $this->memberships->updateStatus((int) $target['id'], $status);
            $this->audit->record($schoolId, $actorUserId, 'MEMBERSHIP_STATUS_CHANGED', 'school_memberships', (int) $target['id'],
                ['status' => $target['status']], ['status' => $status], null, $ipAddress);
        });
    }

    public function replaceRoles(
        int $schoolId,
        int $actorUserId,
        int $userId,
        array $roleCodes,
        ?string $ipAddress = null
    ): void {
        $this->transaction(function () use ($schoolId, $actorUserId, $userId, $roleCodes, $ipAddress): void {
            $this->target($schoolId, $userId);
            $roleCodes = $this->normalizeRoleCodes($roleCodes);
            $desiredRoles = $this->resolveRoles($roleCodes);
            if ($actorUserId === $userId && !in_array('SCHOOL_ADMIN', $roleCodes, true)) {
                throw new DomainException('ไม่สามารถนำบทบาทผู้ดูแลโรงเรียนของตนเองออกได้');
            }
            $oldCodes = $this->assignments->activeSchoolRoleCodes($userId, $schoolId);
            if ($oldCodes === $roleCodes) {
                return;
            }
            foreach (array_diff($oldCodes, $roleCodes) as $code) {
                $role = $this->roles->findActiveByCode($code);
                if ($role === null) {
                    throw new DomainException('ไม่พบบทบาทโรงเรียนที่ต้องการแก้ไข');
                }
                $this->assignments->deactivateSchoolRole($userId, $schoolId, (int) $role['id']);
            }
            foreach ($desiredRoles as $role) {
                $this->assignments->activateSchoolRole($userId, $schoolId, (int) $role['id'], $actorUserId);
            }
            $this->audit->record($schoolId, $actorUserId, 'SCHOOL_ROLES_CHANGED', 'users', $userId,
                ['role_codes' => $oldCodes], ['role_codes' => $roleCodes], null, $ipAddress);
        });
    }

    public function resetPassword(
        int $schoolId,
        int $actorUserId,
        int $userId,
        #[\SensitiveParameter] string $password,
        ?string $ipAddress = null
    ): void {
        $this->transaction(function () use ($schoolId, $actorUserId, $userId, $password, $ipAddress): void {
            $this->target($schoolId, $userId);
            $this->validatePassword($password);
            $this->users->updatePasswordHash($schoolId, $userId, password_hash($password, PASSWORD_DEFAULT));
            $this->audit->record($schoolId, $actorUserId, 'USER_PASSWORD_RESET', 'users', $userId,
                null, ['password_reset' => true], null, $ipAddress);
        });
    }

    private function target(int $schoolId, int $userId): array
    {
        $target = $this->memberships->findForSchoolUser($schoolId, $userId);
        if ($target === null || !in_array($target['status'], ['ACTIVE', 'SUSPENDED'], true)) {
            throw new DomainException('ไม่พบผู้ใช้ที่จัดการได้ในโรงเรียนนี้');
        }

        return $target;
    }

    private function profile(string $displayName, ?string $email): array
    {
        $displayName = trim($displayName);
        if (mb_strlen($displayName, 'UTF-8') < 1 || mb_strlen($displayName, 'UTF-8') > 190) {
            throw new DomainException('ชื่อที่แสดงต้องมี 1–190 ตัวอักษร');
        }
        $email = $email === null ? null : trim($email);
        $email = $email === '' ? null : $email;
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('กรุณาระบุอีเมลที่ถูกต้อง');
        }

        return [$displayName, $email];
    }

    private function validatePassword(#[\SensitiveParameter] string $password): void
    {
        if (strlen($password) < 12) {
            throw new DomainException('รหัสผ่านต้องมีอย่างน้อย 12 ตัวอักษร');
        }
    }

    private function normalizeRoleCodes(array $roleCodes): array
    {
        $codes = [];
        foreach ($roleCodes as $code) {
            if (!is_string($code)) {
                throw new DomainException('ข้อมูลบทบาทไม่ถูกต้อง');
            }
            $code = trim($code);
            if ($code !== '') {
                $codes[] = $code;
            }
        }
        $codes = array_values(array_unique($codes));
        sort($codes, SORT_STRING);
        if ($codes === []) {
            throw new DomainException('กรุณาเลือกบทบาทโรงเรียนอย่างน้อยหนึ่งบทบาท');
        }

        return $codes;
    }

    private function resolveRoles(array $codes): array
    {
        $available = array_column($this->roles->listActiveSchoolRoles(), null, 'code');
        $roles = [];
        foreach ($codes as $code) {
            if (!isset($available[$code])) {
                throw new DomainException('กรุณาเลือกเฉพาะบทบาทโรงเรียนที่ใช้งานได้');
            }
            $roles[$code] = $available[$code];
        }

        return $roles;
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
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException('ไม่สามารถบันทึกข้อมูลผู้ใช้โรงเรียนได้ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ');
        }
    }
}
