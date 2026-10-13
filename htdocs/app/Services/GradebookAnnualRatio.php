<?php
declare(strict_types=1);

namespace App\Services;

use DomainException;

/** Exact B1 policy arithmetic. No scoring/runtime integration. */
final readonly class GradebookAnnualRatio
{
    private function __construct(public int $coursework, public int $finalExam) {}

    public static function from(mixed $coursework, mixed $finalExam): self
    {
        $values = [];
        foreach ([$coursework, $finalExam] as $value) {
            if ((!is_int($value) && !is_string($value)) || !preg_match('/\A[1-9][0-9]?\z/', (string) $value)) {
                throw new DomainException('Ratio must contain integers from 1 to 99');
            }
            $values[] = (int) $value;
        }
        if (array_sum($values) !== 100) { throw new DomainException('Ratio must sum to 100'); }
        return new self(...$values);
    }

    public function targetsInHundredths(): array
    {
        return ['COURSEWORK' => 50 * $this->coursework, 'FINAL_EXAM' => 50 * $this->finalExam];
    }

    public static function maxInHundredths(mixed $value): int
    {
        if (!is_string($value) || !preg_match('/\A([0-9]+)(?:\.([0-9]{1,2}))?\z/', trim($value), $parts)) {
            throw new DomainException('Expected a decimal string with at most two decimal places');
        }
        $whole = ltrim($parts[1], '0');
        if (strlen($whole) > 5) { throw new DomainException('Maximum exceeds DECIMAL(7,2)'); }
        $result = (int) $whole * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
        if ($result < 1) { throw new DomainException('Maximum must be positive'); }
        return $result;
    }
}
