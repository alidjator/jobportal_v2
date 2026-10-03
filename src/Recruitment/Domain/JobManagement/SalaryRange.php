<?php

namespace Src\Recruitment\Domain\JobManagement;

/** Rentang gaji: 0 <= min <= max. */
final class SalaryRange
{
    private function __construct(public readonly float $min, public readonly float $max) {}

    public static function of(float|int|string $min, float|int|string $max): self
    {
        if (! is_numeric($min)) {
            throw new InvalidJobData('min_salary', 'The min salary field must be a number.');
        }
        if (! is_numeric($max)) {
            throw new InvalidJobData('max_salary', 'The max salary field must be a number.');
        }

        $min = (float) $min;
        $max = (float) $max;

        if ($min < 0) {
            throw new InvalidJobData('min_salary', 'The min salary field must be at least 0.');
        }
        if ($min > $max) {
            throw new InvalidJobData('max_salary', "The max salary field must be at least {$min}.");
        }

        return new self($min, $max);
    }

    public function minAsInt(): int
    {
        return (int) $this->min;
    }

    public function maxAsInt(): int
    {
        return (int) $this->max;
    }
}
