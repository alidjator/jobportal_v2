<?php

namespace Src\Job\Application;

/** A job is filled when it states vacancies and its hires reach them. Derived, never stored (`docs/design/phase-8-hiring.md` section 2). */
final class FilledJob
{
    public static function is(?int $vacancies, int $hires): bool
    {
        return $vacancies !== null && $hires >= $vacancies;
    }
}
