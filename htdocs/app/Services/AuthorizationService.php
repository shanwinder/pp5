<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuthorizationRepository;
use Throwable;

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

    public function hasSubjectOfferingPermission(
        int $userId,
        string $contextType,
        ?int $schoolId,
        int $subjectOfferingId,
        string $permissionCode
    ): bool {
        if ($contextType !== 'SCHOOL' || $schoolId === null || $schoolId <= 0 || $userId <= 0 || $subjectOfferingId <= 0) {
            return false;
        }
        try {
            return $this->authorization->hasSubjectOfferingPermission($userId, $schoolId, $subjectOfferingId, $permissionCode);
        } catch (Throwable) {
            // An unavailable permission store must deny access without exposing database details.
            return false;
        }
    }

    public function hasSubjectOfferingPermissionForUpdate(
        int $userId,
        string $contextType,
        ?int $schoolId,
        int $subjectOfferingId,
        string $permissionCode
    ): bool {
        if ($contextType !== 'SCHOOL' || $schoolId === null || $schoolId <= 0 || $userId <= 0 || $subjectOfferingId <= 0) {
            return false;
        }
        try {
            return $this->authorization->hasSubjectOfferingPermissionForUpdate($userId, $schoolId, $subjectOfferingId, $permissionCode);
        } catch (Throwable) {
            return false;
        }
    }
}
