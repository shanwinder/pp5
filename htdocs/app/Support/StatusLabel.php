<?php
declare(strict_types=1);

namespace App\Support;

/** Plain presentation text only; callers escape output, and persisted codes remain authoritative. */
final class StatusLabel
{
    public static function text(string $code, string $kind = 'entity'): string
    {
        $labels = $kind === 'academic-year'
            ? ['DRAFT'=>'ร่าง', 'ACTIVE'=>'กำลังใช้งาน', 'CLOSED'=>'ปิดปีแล้ว']
            : ['ACTIVE'=>'ใช้งาน', 'INACTIVE'=>'ปิดใช้งาน'];
        if (in_array($kind, ['school', 'membership', 'user'], true)) { $labels['SUSPENDED'] = 'ระงับ'; }
        return $labels[$code] ?? $code;
    }
}
