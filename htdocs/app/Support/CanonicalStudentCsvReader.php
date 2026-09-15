<?php
declare(strict_types=1);

namespace App\Support;

use DomainException;

final class CanonicalStudentCsvReader
{
    private const HEADER = ['student_code', 'national_id', 'prefix_th', 'first_name_th', 'last_name_th',
        'gender_code', 'birth_date', 'grade_level_code', 'classroom_code', 'entry_date'];

    public function read(string $path, int $sizeBytes): array
    {
        if ($sizeBytes < 1 || $sizeBytes > 2097152 || !is_file($path)) {
            throw new DomainException('กรุณาเลือกไฟล์ CSV ขนาดไม่เกิน 2 MiB');
        }
        $text = @file_get_contents($path, false, null, 0, 2097153);
        if ($text === false || $text === '' || strlen($text) > 2097152 || !mb_check_encoding($text, 'UTF-8')) {
            throw new DomainException('ไม่สามารถอ่านไฟล์ CSV แบบ UTF-8 ขนาดไม่เกิน 2 MiB');
        }
        if (str_starts_with($text, "\xEF\xBB\xBF")) { $text = substr($text, 3); }
        // Strict quoting: PHP's permissive CSV parser accepts unterminated enclosures.
        $records = []; $fields = []; $field = ''; $state = 'start'; $line = 1; $rowLine = 1;
        for ($i = 0, $length = strlen($text); $i < $length; ++$i) {
            $char = $text[$i];
            if ($state === 'quoted') {
                if ($char === '"') { $state = 'closed'; }
                else { $field .= $char; if ($char === "\n") { ++$line; } }
                continue;
            }
            if ($char === '"') {
                if ($state === 'closed') { $field .= '"'; $state = 'quoted'; }
                elseif ($state === 'start') { $state = 'quoted'; }
                else { throw new DomainException('รูปแบบเครื่องหมายคำพูดใน CSV ไม่ถูกต้อง'); }
            } elseif ($char === ',' || $char === "\n" || $char === "\r") {
                $fields[] = $field; $field = ''; $state = 'start';
                if ($char !== ',') {
                    $records[] = ['row_no' => $rowLine, 'fields' => $fields]; $fields = [];
                    if ($char === "\r" && ($text[$i + 1] ?? '') === "\n") { ++$i; }
                    $rowLine = ++$line;
                    if (count($records) > 1001) { throw new DomainException('CSV ต้องมีข้อมูลไม่เกิน 1,000 แถว'); }
                }
            } else {
                if ($state === 'closed') { throw new DomainException('รูปแบบ CSV ไม่ถูกต้อง'); }
                $state = 'bare'; $field .= $char;
            }
        }
        if ($state === 'quoted') { throw new DomainException('เครื่องหมายคำพูดใน CSV ไม่ครบ'); }
        if ($fields !== [] || $state !== 'start') { $fields[] = $field; $records[] = ['row_no' => $rowLine, 'fields' => $fields]; }
        if (count($records) < 2 || count($records) > 1001 || $records[0]['fields'] !== self::HEADER) {
            throw new DomainException('กรุณาใช้หัวตาราง canonical CSV และข้อมูล 1–1,000 แถว');
        }
        $rows = [];
        foreach (array_slice($records, 1) as $record) {
            if (count($record['fields']) !== count(self::HEADER)) { throw new DomainException('จำนวนคอลัมน์ CSV ไม่ถูกต้อง'); }
            $rows[] = ['row_no' => $record['row_no']] + array_combine(self::HEADER, $record['fields']);
        }
        return $rows;
    }
}
