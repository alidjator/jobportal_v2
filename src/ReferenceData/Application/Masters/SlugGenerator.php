<?php

namespace Src\ReferenceData\Application\Masters;

interface SlugGenerator
{
    /** A lower-case, hyphen-separated, URL-safe form of the text (ASCII only), at most 150 characters. */
    public function make(string $text): string;
}
