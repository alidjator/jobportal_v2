<?php

namespace Src\ReferenceData\Infrastructure;

use Illuminate\Support\Str;
use Src\ReferenceData\Application\Masters\SlugGenerator;

final class LaravelSlugGenerator implements SlugGenerator
{
    private const int MAX_LENGTH = 150;

    public function make(string $text): string
    {
        $slug = Str::slug($text);

        return $slug === '' ? 'item' : mb_substr($slug, 0, self::MAX_LENGTH);
    }
}
