<?php
declare(strict_types=1);

namespace App\Services;

use DomainException;

/** Validation only; neither scope nor a revision is an authorization grant. */
final class GradebookFoundationIdentity
{
    public static function uuid(string $value): string
    {
        if (!preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/', $value)) {
            throw new DomainException('Expected canonical UUID');
        }
        return $value;
    }

    public static function scope(array $row, int $schoolId, int $yearId, ?int $classroomId = null): void
    {
        if ($schoolId <= 0 || $yearId <= 0 || (int) ($row['school_id'] ?? 0) !== $schoolId
            || (int) ($row['academic_year_id'] ?? 0) !== $yearId
            || ($classroomId !== null && ($classroomId <= 0 || (int) ($row['classroom_id'] ?? 0) !== $classroomId))) {
            throw new DomainException('Resource scope mismatch');
        }
    }

    public static function nextRevision(mixed $actual, mixed $expected): int
    {
        foreach ([$actual, $expected] as $value) {
            if ((!is_int($value) && !is_string($value)) || !preg_match('/\A[1-9][0-9]*\z/', (string) $value)
                || strlen((string) $value) > strlen((string) PHP_INT_MAX)
                || (strlen((string) $value) === strlen((string) PHP_INT_MAX) && strcmp((string) $value, (string) PHP_INT_MAX) >= 0)) {
                throw new DomainException('Invalid or exhausted revision');
            }
        }
        if ((string) $actual !== (string) $expected) { throw new DomainException('Stale revision', 409); }
        return (int) $actual + 1;
    }

    public static function text(string $value, int $maximum): string
    {
        if (!mb_check_encoding($value, 'UTF-8') || preg_match('/\p{Cc}/u', $value)) {
            throw new DomainException('Invalid text');
        }
        $value = preg_replace('/\A\s+|\s+\z/u', '', $value);
        if ($value === '' || mb_strlen($value, 'UTF-8') > $maximum) { throw new DomainException('Invalid text length'); }
        return $value;
    }
}
