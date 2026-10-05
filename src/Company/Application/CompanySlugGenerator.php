<?php

namespace Src\Company\Application;

interface CompanySlugGenerator
{
    /** A lower-case, hyphen-separated, URL-safe form of the name (ASCII only), at most 60 characters and never empty (`$fallback` when nothing is left). */
    public function make(string $name, string $fallback = 'company'): string;
}
