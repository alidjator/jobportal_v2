<?php

namespace Src\LegacyImport\Application;

use DateTimeImmutable;
use DateTimeZone;

/** Reads the dates the legacy database keeps as free text. The result is the first day of the month (or the day itself for `day`), midnight UTC; null when the text cannot be read. */
final class LegacyDates
{
    private const array MONTHS = [
        'januari' => 1, 'january' => 1, 'jan' => 1, 'februari' => 2, 'february' => 2, 'feb' => 2, 'maret' => 3, 'march' => 3, 'mar' => 3, 'april' => 4, 'apr' => 4,
        'mei' => 5, 'may' => 5, 'juni' => 6, 'june' => 6, 'jun' => 6, 'juli' => 7, 'july' => 7, 'jul' => 7, 'agustus' => 8, 'august' => 8, 'agu' => 8, 'aug' => 8,
        'september' => 9, 'sep' => 9, 'sept' => 9, 'oktober' => 10, 'october' => 10, 'okt' => 10, 'oct' => 10, 'november' => 11, 'nov' => 11, 'desember' => 12, 'december' => 12,
        'des' => 12, 'dec' => 12,
    ];

    /** A month from `2020-03`, `2020-03-15`, `03/2020`, `15/03/2020`, `March 2020` or `Maret 2020`. */
    public static function month(?string $text): ?DateTimeImmutable
    {
        $text = $text === null ? '' : trim(mb_strtolower($text));

        if (preg_match('/^(\d{4})-(\d{1,2})(?:-\d{1,2})?(?:[ t].*)?$/', $text, $m) === 1) {
            return self::make((int) $m[1], (int) $m[2]);
        }

        if (preg_match('/^(\d{1,2})[\/.-](\d{4})$/', $text, $m) === 1) {
            return self::make((int) $m[2], (int) $m[1]);
        }

        if (preg_match('/^\d{1,2}[\/.-](\d{1,2})[\/.-](\d{4})$/', $text, $m) === 1) {
            return self::make((int) $m[2], (int) $m[1]);
        }

        if (preg_match('/^([a-z]+)\.?,?\s+(\d{4})$/', $text, $m) === 1 && isset(self::MONTHS[$m[1]])) {
            return self::make((int) $m[2], self::MONTHS[$m[1]]);
        }

        return null;
    }

    /** A four-digit year from `2019`, `2019-06-01` or similar text. */
    public static function year(?string $text): ?int
    {
        return $text !== null && preg_match('/(?<!\d)(\d{4})(?!\d)/', $text, $m) === 1 ? (int) $m[1] : null;
    }

    /** A date from `2020-03-15` (the time part, if any, is ignored). */
    public static function day(?string $text): ?DateTimeImmutable
    {
        if ($text === null || preg_match('/^(\d{4})-(\d{2})-(\d{2})/', trim($text), $m) !== 1 || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return new DateTimeImmutable("{$m[1]}-{$m[2]}-{$m[3]}", new DateTimeZone('UTC'));
    }

    private static function make(int $year, int $month): ?DateTimeImmutable
    {
        return $year >= 1900 && $year <= 2200 && $month >= 1 && $month <= 12
            ? new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), new DateTimeZone('UTC'))
            : null;
    }
}
