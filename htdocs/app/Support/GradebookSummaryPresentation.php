<?php
declare(strict_types=1);

namespace App\Support;

/** Presentation of server-confirmed decimal strings; no score arithmetic. */
final class GradebookSummaryPresentation
{
    public static function display(string $total, string $max): string
    {
        return self::trimDecimal($total) . '/' . self::trimDecimal($max);
    }

    private static function trimDecimal(string $value): string
    {
        if (!str_contains($value, '.')) { return $value; }
        return rtrim(rtrim($value, '0'), '.');
    }
}
