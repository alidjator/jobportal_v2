<?php

namespace Src\Job\Infrastructure;

use Illuminate\Support\Str;
use Src\Job\Application\JobSlugGenerator;

final class LaravelJobSlugGenerator implements JobSlugGenerator
{
    private const int MAX_LENGTH = 100;

    public function make(string $title): string
    {
        $slug = rtrim(mb_substr(Str::slug($title), 0, self::MAX_LENGTH), '-');

        return $slug === '' ? 'job' : $slug;
    }
}
