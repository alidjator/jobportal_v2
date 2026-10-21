<?php

namespace Src\Assessment\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\ErrorMessage;

/** The content limits of the descriptive part of a test. */
final class TestRules
{
    public const int TITLE_MIN = 3;

    public const int TITLE_MAX = 200;

    public const int TEXT_MAX = 5000;

    /**
     * @throws BusinessRuleViolation `invalid-test` with `errors` on `title`
     */
    public static function title(string $title): string
    {
        $title = trim($title);
        $length = mb_strlen($title);

        if ($length < self::TITLE_MIN || $length > self::TITLE_MAX) {
            throw self::invalid('title', new ErrorMessage('The title must have between :min and :max characters.', ['min' => self::TITLE_MIN, 'max' => self::TITLE_MAX]));
        }

        return $title;
    }

    /**
     * Trimmed text, or null when it is empty.
     *
     * @throws BusinessRuleViolation `invalid-test` with `errors` on the field
     */
    public static function text(string $field, ?string $text): ?string
    {
        $text = $text === null ? '' : trim($text);

        if (mb_strlen($text) > self::TEXT_MAX) {
            throw self::invalid($field, new ErrorMessage("The {$field} may have at most :max characters.", ['max' => self::TEXT_MAX]));
        }

        return $text === '' ? null : $text;
    }

    /**
     * The trimmed capabilities, at most 10, each 1 to 60 characters and unique ignoring letter case.
     *
     * @param  list<string>  $capabilities
     * @return list<string>
     *
     * @throws BusinessRuleViolation `invalid-test` with `errors` on `measured_capabilities`
     */
    public static function capabilities(array $capabilities): array
    {
        $clean = array_map(static fn (string $capability): string => trim($capability), $capabilities);

        if (count($clean) > Test::CAPABILITIES_MAX) {
            throw self::invalid('measured_capabilities', new ErrorMessage('A test may list at most :max measured capabilities.', ['max' => Test::CAPABILITIES_MAX]));
        }

        $seen = [];

        foreach ($clean as $capability) {
            $length = mb_strlen($capability);

            if ($length < 1 || $length > Test::CAPABILITY_MAX_LENGTH) {
                throw self::invalid('measured_capabilities', new ErrorMessage('Every capability must have between 1 and :max characters.', ['max' => Test::CAPABILITY_MAX_LENGTH]));
            }

            $key = mb_strtolower($capability);

            if (isset($seen[$key])) {
                throw self::invalid('measured_capabilities', 'The capabilities must be different from each other.');
            }

            $seen[$key] = true;
        }

        return $clean;
    }

    private static function invalid(string $field, string|ErrorMessage $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-test', extensions: ['errors' => [$field => [$message]]]);
    }
}
