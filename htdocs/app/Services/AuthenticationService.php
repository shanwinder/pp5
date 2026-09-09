<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\SchoolMembershipRepository;
use App\Repositories\UserRepository;
use DomainException;

final class AuthenticationService
{
    public function __construct(
        private UserRepository $users,
        private SchoolMembershipRepository $memberships
    ) {}

    public function attempt(string $username, string $password): array
    {
        $user = $this->users->findActiveByUsername(trim($username));

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            throw new DomainException('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
        }

        $memberships = $this->memberships->findActiveForUser((int) $user['id']);

        if (count($memberships) === 0) {
            throw new DomainException(
                'บัญชีนี้ยังไม่ได้รับสิทธิ์เข้าใช้งานโรงเรียน กรุณาติดต่อผู้ดูแลระบบ'
            );
        }

        if (count($memberships) > 1) {
            throw new DomainException(
                'บัญชีนี้มีโรงเรียนที่ใช้งานมากกว่าหนึ่งแห่ง กรุณาติดต่อผู้ดูแลระบบ'
            );
        }

        $membership = $memberships[0];
        $this->users->recordLogin((int) $user['id']);

        return [
            'user_id' => (int) $user['id'],
            'school_id' => (int) $membership['school_id'],
            'school_membership_id' => (int) $membership['id'],
            'display_name' => (string) $user['display_name'],
        ];
    }
}
