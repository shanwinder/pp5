<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuthorizationRepository;

final class AuthorizationService
{
    public function __construct(private AuthorizationRepository $authorization) {}

    public function hasPermission(int $userId, string $contextType, ?int $schoolId, string $permissionCode): bool
    {
        return match ($contextType) {
            'SYSTEM' => $schoolId === null
                && $this->authorization->hasSystemPermission($userId, $permissionCode),
            'SCHOOL' => is_int($schoolId)
                && $this->authorization->hasSchoolPermission($userId, $schoolId, $permissionCode),
            default => false,
        };
    }
}
