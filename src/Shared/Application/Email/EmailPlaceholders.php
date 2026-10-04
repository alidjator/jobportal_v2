<?php

namespace Src\Shared\Application\Email;

/**
 * `{{name}}` placeholders in subjects and bodies. Substitution is plain text replacement of known names: nothing is evaluated, so a
 * template can never run code. Values are made safe for where they land (Markdown characters escaped in a body, line breaks removed
 * from a subject); a placeholder with no value is left as it is.
 */
final class EmailPlaceholders
{
    private const string PATTERN = '/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/';

    /** @return list<string> the distinct placeholder names, in order of first use */
    public static function in(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    /** @param  array<string, string>  $values */
    public static function intoBody(string $markdown, array $values): string
    {
        return self::substitute($markdown, $values, fn (string $value): string => self::escapeMarkdown($value));
    }

    /** @param  array<string, string>  $values */
    public static function intoSubject(string $subject, array $values): string
    {
        return trim((string) preg_replace('/\s*[\r\n]+\s*/', ' ', self::substitute($subject, $values, fn (string $value): string => $value)));
    }

    /**
     * @param  array<string, string>  $values
     * @param  callable(string): string  $prepare
     */
    private static function substitute(string $text, array $values, callable $prepare): string
    {
        return (string) preg_replace_callback(
            self::PATTERN,
            fn (array $match): string => array_key_exists($match[1], $values) ? $prepare($values[$match[1]]) : $match[0],
            $text,
        );
    }

    private static function escapeMarkdown(string $value): string
    {
        return (string) preg_replace('/([\\\\`*_{}\[\]()#+\-.!|<>~&])/', '\\\\$1', $value);
    }
}
