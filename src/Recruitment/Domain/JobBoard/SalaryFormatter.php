<?php

namespace Src\Recruitment\Domain\JobBoard;

interface SalaryFormatter
{
    /**
     * Label gaji: mode `range` => "5K - 10K USD" (dikonversi ke mata uang aktif),
     * selain itu `custom_salary` apa adanya.
     */
    public function label(?string $mode, mixed $min, mixed $max, ?string $custom): ?string;
}
