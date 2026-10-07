<?php

namespace Src\LegacyImport\Application;

/** A URL name from a free text: lower case ASCII letters and digits with single hyphens, at most `$max` characters, never starting or ending with a hyphen. */
final class SlugMaker
{
    public static function from(string $text, int $max): string
    {
        $text = mb_strtolower(trim($text));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $ascii === false ? $text : $ascii), '-');

        return rtrim(substr($slug, 0, $max), '-');
    }
}
