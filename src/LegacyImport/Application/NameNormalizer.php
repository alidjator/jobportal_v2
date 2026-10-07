<?php

namespace Src\LegacyImport\Application;

/** Makes two spellings of the same name equal: letter case, accents, `&` as `and`, punctuation and spaces do not matter. */
final class NameNormalizer
{
    public static function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = str_replace('&', ' and ', $name);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $name = $ascii === false ? $name : $ascii;
        $name = (string) preg_replace('/[^a-z0-9]+/', ' ', $name);

        return trim($name);
    }
}
