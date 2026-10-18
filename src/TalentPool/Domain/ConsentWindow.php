<?php

namespace Src\TalentPool\Domain;

use DateTimeImmutable;

/** How long a consenting application keeps an applicant available in the company's pool: 24 months from the day it was submitted (`docs/design/phase-8-talent-pool.md` section 1). */
final readonly class ConsentWindow
{
    public const int MONTHS = 24;

    /** The earliest submission time that still counts at `$now`. */
    public static function since(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify('-'.self::MONTHS.' months');
    }
}
