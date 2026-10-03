<?php

namespace Src\Recruitment\Domain\JobManagement;

use Closure;
use Illuminate\Support\Str;

/** Slug lowongan: slug judul; bila sudah dipakai lowongan lain ditambah `-{time}-{uniqid}`. */
final class JobSlug
{
    /** @param  Closure(string): bool  $exists  apakah slug dipakai lowongan lain */
    public static function make(string $title, Closure $exists): string
    {
        $slug = Str::slug($title);

        return $exists($slug) ? $slug.'-'.time().'-'.uniqid() : $slug;
    }
}
