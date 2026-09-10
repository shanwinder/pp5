<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuthorizationRepository;
use App\Repositories\SchoolMembershipRepository;
use App\Repositories\UserRepository;
use App\Support\AccessContext;
use DomainException;

final class AuthenticationService
{
    public function __construct(
        private UserRepository $users,
        private SchoolMembershipRepository $memberships,
        private AuthorizationRepository $authorization
    ) {}

    public function attempt(string $username, string $password): array
    {
        $user = $this->users->findActiveByUsername(trim($username));

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            throw new DomainException('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
        }

        $userId = (int) $user['id'];
        $memberships = $this->memberships->findActiveRowsForUser($userId);
        $schoolId = null;
        $membershipId = null;

        if ($this->authorization->hasActiveSystemAdmin($userId)) {
            if (count($memberships) !== 0) {
                throw new DomainException('สิทธิ์บัญชีผู้ดูแลระบบไม่ถูกต้อง กรุณาติดต่อผู้ดูแลระบบ');
            }
            $contextType = AccessContext::SYSTEM;
        } else {
            if (count($memberships) !== 1) {
                throw new DomainException('บัญชีนี้ต้องมีโรงเรียนที่ใช้งานหนึ่งแห่ง กรุณาติดต่อผู้ดูแลระบบ');
            }

            $membership = $memberships[0];
            $schoolId = (int) $membership['school_id'];
            $membershipId = (int) $membership['id'];
            if (!$this->memberships->isActiveMembership($membershipId, $userId, $schoolId)
                || !$this->authorization->hasActiveSchoolRole($userId, $schoolId)) {
                throw new DomainException('บัญชีนี้ยังไม่ได้รับสิทธิ์เข้าใช้งานโรงเรียน กรุณาติดต่อผู้ดูแลระบบ');
            }
            $contextType = AccessContext::SCHOOL;
        }

        $this->users->recordLogin($userId);

        return [
            'context_type' => $contextType,
            'user_id' => $userId,
            'school_id' => $schoolId,
            'school_membership_id' => $membershipId,
            'display_name' => (string) $user['display_name'],
        ];
    }
}
