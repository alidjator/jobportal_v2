<?php

namespace Src\ReferenceData\Application\Queries;

final class GetLocalizationStrings
{
    /** @return array<string,string> kosong bila locale tidak punya file terjemahan */
    public function __invoke(string $locale): array
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $locale)) {
            return [];
        }

        $path = lang_path($locale.'.json');

        return is_file($path) ? (json_decode(file_get_contents($path), true) ?? []) : [];
    }
}
