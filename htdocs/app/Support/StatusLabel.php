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
        $labels = match ($kind) {
            'enrollment' => ['ACTIVE'=>'กำลังเรียน', 'TRANSFERRED_OUT'=>'ย้ายออก', 'WITHDRAWN'=>'ลาออก'],
            'placement' => ['ACTIVE'=>'ห้องปัจจุบัน', 'ENDED'=>'สิ้นสุดแล้ว'],
            'import-batch' => ['PREVIEW'=>'รอตรวจสอบและยืนยัน', 'APPLIED'=>'นำเข้าแล้ว', 'CANCELLED'=>'ยกเลิกแล้ว', 'EXPIRED'=>'หมดอายุแล้ว'],
            'import-action' => ['CREATE'=>'สร้างใหม่', 'MATCH'=>'ตรงกับข้อมูลเดิม', 'NOOP'=>'ไม่ต้องเปลี่ยนแปลง',
                'NONE'=>'ไม่มีการดำเนินการ', 'CONFLICT'=>'ข้อมูลขัดแย้ง', 'ERROR'=>'ข้อมูลไม่ถูกต้อง'],
            default => $labels,
        };
        return $labels[$code] ?? $code;
    }
}
