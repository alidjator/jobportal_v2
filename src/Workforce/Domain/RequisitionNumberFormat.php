<?php

namespace Src\Workforce\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The format of the number of a requisition, a text the tenant (or its group) chooses: literal letters, digits and `- _ / .`, the date tokens `{YYYY}`, `{YY}`, `{MM}` and `{DD}`
 * (the Jakarta day the requisition is made) and exactly one running number, `{SEQ}` or `{SEQ:n}` padded with zeros to `n` digits (1 to 9). The running number starts again at 1 whenever the rest of
 * the number changes, so a format with `{DD}` counts per day, one with only `{MM}` per month, one with only `{YYYY}` per year and one with no date never starts again.
 */
final class RequisitionNumberFormat
{
    public const string DEFAULT = 'JR-{YYYY}{MM}{DD}-{SEQ:3}';

    public const int MAX_LENGTH = 40;

    /** The pattern a stored format must match (the setting checks it): literal characters and tokens, exactly one running number. */
    public const string PATTERN = '/^(?!.*\{SEQ.*\{SEQ)(?=.*\{SEQ(?::[1-9])?\})(?:[A-Za-z0-9\-_\/.]|\{(?:YYYY|YY|MM|DD)\}|\{SEQ(?::[1-9])?\})+$/';

    private const string TIME_ZONE = 'Asia/Jakarta';

    public static function isValid(string $format): bool
    {
        return mb_strlen($format) <= self::MAX_LENGTH && preg_match(self::PATTERN, $format) === 1;
    }

    /** The number for the running number `$sequence` on the day of `$moment`. */
    public static function render(string $format, DateTimeImmutable $moment, int $sequence): string
    {
        return self::fill($format, $moment, static fn (int $width): string => str_pad((string) $sequence, $width, '0', STR_PAD_LEFT));
    }

    /** What the running number counts under: the number with its running part left out, `JR-20261101-` for the default format. */
    public static function series(string $format, DateTimeImmutable $moment): string
    {
        return self::fill($format, $moment, static fn (int $width): string => '');
    }

    /** @param  callable(int): string  $sequence  the text of the running number for its width */
    private static function fill(string $format, DateTimeImmutable $moment, callable $sequence): string
    {
        $day = $moment->setTimezone(new DateTimeZone(self::TIME_ZONE));

        return (string) preg_replace_callback(
            '/\{(YYYY|YY|MM|DD|SEQ)(?::([1-9]))?\}/',
            static fn (array $token): string => match ($token[1]) {
                'YYYY' => $day->format('Y'),
                'YY' => $day->format('y'),
                'MM' => $day->format('m'),
                'DD' => $day->format('d'),
                default => $sequence((int) ($token[2] ?? 1)),
            },
            $format,
        );
    }
}
