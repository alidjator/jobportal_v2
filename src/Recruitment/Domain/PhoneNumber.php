<?php

namespace Src\Recruitment\Domain;

/**
 * Turns an Indonesian phone number as people type it into E.164 (`+62...`), the form the voice server dials. Only Indonesian numbers are accepted: `0812...`,
 * `62812...`, `+62 812-...` and `0062812...` are the same number; spaces, dots, dashes and brackets are dropped. A number without a leading `0`, `62` or `+`
 * is ambiguous and refused, and so is any other country code.
 */
final class PhoneNumber
{
    /** @return string|null the number as `+62` and 8 to 12 digits, or null when it is not an Indonesian number */
    public static function normalise(string $input): ?string
    {
        $text = (string) preg_replace('/[\s().-]/', '', $input);

        if ($text === '') {
            return null;
        }

        $international = false;

        if (str_starts_with($text, '+')) {
            $international = true;
            $text = substr($text, 1);
        } elseif (str_starts_with($text, '00')) {
            $international = true;
            $text = substr($text, 2);
        }

        if (preg_match('/^\d+$/', $text) !== 1) {
            return null;
        }

        $national = match (true) {
            str_starts_with($text, '62') => substr($text, 2),
            ! $international && str_starts_with($text, '0') => substr($text, 1),
            default => null,
        };

        return $national !== null && preg_match('/^[1-9]\d{7,11}$/', $national) === 1 ? '+62'.$national : null;
    }
}
