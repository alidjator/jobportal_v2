<?php

namespace Src\LegacyImport\Application;

/**
 * Reads the free text of the legacy `jobs.vacancies` as a number of vacancies. A bare number or a number with words (`5`, `5 orang`) is that number; a range
 * (`2-3`, `2 sampai 3`) is its larger number; no number, several unrelated numbers or a value outside 1 to 65,535 is not read.
 */
final class LegacyVacancies
{
    public const int MAX = 65535;

    /** @return int|string the number, or the reason code why the text could not be read (`vacancies-empty`, `vacancies-unreadable`, `vacancies-ambiguous`, `vacancies-out-of-range`) */
    public static function parse(?string $text): int|string
    {
        $text = $text === null ? '' : trim($text);

        if ($text === '') {
            return 'vacancies-empty';
        }

        if (preg_match('/^(\d+)\s*(?:-|\x{2013}|sampai|s\/d|sd|hingga|to)\s*(\d+)(?:\s+\D.*)?$/iu', $text, $range) === 1) {
            $number = max((int) $range[1], (int) $range[2]);
        } else {
            preg_match_all('/\d+/', $text, $numbers);

            if ($numbers[0] === []) {
                return 'vacancies-unreadable';
            }

            if (count($numbers[0]) > 1) {
                return 'vacancies-ambiguous';
            }

            $number = (int) $numbers[0][0];
        }

        return $number >= 1 && $number <= self::MAX ? $number : 'vacancies-out-of-range';
    }
}
