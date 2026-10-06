<?php

namespace Src\Job\Application;

interface JobSlugGenerator
{
    /** A lower-case, hyphen-separated, URL-safe form of the title (ASCII only), at most 100 characters and never empty. */
    public function make(string $title): string;
}
