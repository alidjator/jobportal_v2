<?php

namespace Src\Company\Infrastructure;

use Illuminate\Support\Str;
use Src\Company\Application\CompanySlugGenerator;

final class LaravelCompanySlugGenerator implements CompanySlugGenerator
{
    private const int MAX_LENGTH = 60;

    public function make(string $name, string $fallback = 'company'): string
    {
        $slug = rtrim(mb_substr(Str::slug($name), 0, self::MAX_LENGTH), '-');

        return $slug === '' ? $fallback : $slug;
    }
}
