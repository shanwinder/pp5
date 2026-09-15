<?php
declare(strict_types=1);

namespace App\Validation;

use DateTimeImmutable;
use DomainException;

final class StudentProfileRules
{
    public static function normalize(
        string $studentCode,
        ?string $nationalId,
        string $prefixTh,
        string $firstNameTh,
        string $lastNameTh,
        ?string $genderCode,
        ?string $birthDate
    ): array {
        $studentCode = self::required($studentCode, 50, 'รหัสนักเรียน');
        $prefixTh = self::required($prefixTh, 50, 'คำนำหน้า');
        $firstNameTh = self::required($firstNameTh, 100, 'ชื่อ');
        $lastNameTh = self::required($lastNameTh, 100, 'นามสกุล');
        $nationalId = self::text($nationalId);
        $genderCode = self::text($genderCode);
        $birthDate = self::text($birthDate);

        if ($nationalId !== null && !preg_match('/\A[0-9]{13}\z/', $nationalId)) {
            throw new DomainException('เลขประจำตัวประชาชนต้องเป็นตัวเลข ASCII 13 หลัก');
        }
        if ($genderCode !== null && !in_array($genderCode, ['MALE', 'FEMALE', 'OTHER'], true)) {
            throw new DomainException('รหัสเพศไม่ถูกต้อง');
        }
        if ($birthDate !== null) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
            if (!preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $birthDate)
                || $date === false || $date->format('Y-m-d') !== $birthDate
                || $date > new DateTimeImmutable('today')) {
                throw new DomainException('วันเกิดต้องเป็นวันที่ YYYY-MM-DD ที่ถูกต้องและไม่อยู่ในอนาคต');
            }
        }

        return [
            'student_code' => $studentCode,
            'national_id' => $nationalId,
            'prefix_th' => $prefixTh,
            'first_name_th' => $firstNameTh,
            'last_name_th' => $lastNameTh,
            'gender_code' => $genderCode,
            'birth_date' => $birthDate,
        ];
    }

    private static function required(string $value, int $max, string $label): string
    {
        $value = self::text($value);
        if ($value === null || mb_strlen($value, 'UTF-8') > $max) {
            throw new DomainException($label . 'ต้องมี 1–' . $max . ' ตัวอักษร');
        }

        return $value;
    }

    private static function text(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        // Check before trimming: whitespace controls are invalid, even for optional fields.
        if (!mb_check_encoding($value, 'UTF-8') || preg_match('/\p{Cc}/u', $value)) {
            throw new DomainException('ข้อมูลนักเรียนต้องเป็นข้อความ UTF-8 ที่ไม่มีอักขระควบคุม');
        }
        $value = preg_replace('/\A\s+|\s+\z/u', '', $value);

        return $value === '' ? null : $value;
    }
}
