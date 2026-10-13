<?php

namespace Src\LegacyImport\Application;

/** Guesses whether a legacy skill is a soft skill from a word list; every other one is a hard skill. An admin corrects the kind afterwards. */
final class SkillKindGuesser
{
    private const array SOFT_WORDS = [
        'communication', 'komunikasi', 'leadership', 'kepemimpinan', 'teamwork', 'team work', 'kerjasama', 'kerja sama', 'problem solv', 'critical thinking',
        'analytical thinking', 'time management', 'manajemen waktu', 'public speaking', 'presentation', 'presentasi', 'negotiation', 'negosiasi', 'adaptab',
        'creativ', 'kreatif', 'collaboration', 'kolaborasi', 'interpersonal', 'attention to detail', 'decision making', 'self motivat', 'integrity',
        'integritas', 'discipline', 'disiplin', 'customer service', 'pelayanan',
    ];

    public static function guess(string $name): string
    {
        $name = mb_strtolower($name);

        foreach (self::SOFT_WORDS as $word) {
            if (str_contains($name, $word)) {
                return 'soft';
            }
        }

        return 'hard';
    }
}
