<?php

namespace Src\LegacyImport\Application;

/** Small cleaners for the free text of the legacy database. */
final class LegacyText
{
    /** HTML from a rich-text editor as plain text with line breaks: tags are dropped (scripts and styles with their content), entities are decoded, blank runs are collapsed. */
    public static function plain(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);
        $text = (string) preg_replace('#<\s*(br|/p|/div|/li|/h[1-6])\b[^>]*>#i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = (string) preg_replace("/ *\n */", "\n", $text);
        $text = trim((string) preg_replace("/\n{3,}/", "\n\n", $text));

        return $text === '' ? null : $text;
    }

    /** The text cut to at most `$max` characters (not in the middle of a character). */
    public static function limit(?string $text, int $max): ?string
    {
        return $text === null ? null : mb_substr($text, 0, $max);
    }

    /** A trimmed value, or null when it is empty. */
    public static function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    /** Whether the value is a bcrypt hash (the only password format the new hasher can check). */
    public static function isBcrypt(?string $hash): bool
    {
        return $hash !== null && preg_match('/^\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}$/', $hash) === 1;
    }
}
