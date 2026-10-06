<?php

namespace Src\Recruitment\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** The content limits of a library question: its text and, for a choice question, its choices. */
final class QuestionRules
{
    public const int TEXT_MIN = 5;

    public const int TEXT_MAX = 300;

    public const int OPTIONS_MIN = 2;

    public const int OPTIONS_MAX = 10;

    public const int OPTION_MAX_LENGTH = 100;

    public const int TEXT_ANSWER_MAX = 1000;

    public const int NUMBER_MAX = 999_999_999;

    /**
     * @throws BusinessRuleViolation `invalid-screening-question` with `errors` on `text`
     */
    public static function text(string $text): string
    {
        $text = trim($text);
        $length = mb_strlen($text);

        if ($length < self::TEXT_MIN || $length > self::TEXT_MAX) {
            throw self::invalid('text', 'The question must have between '.self::TEXT_MIN.' and '.self::TEXT_MAX.' characters.');
        }

        return $text;
    }

    /**
     * The trimmed choices of a question of this type: a list of 2 to 10 for a choice question (unique ignoring letter case), an empty list for the other types.
     *
     * @param  list<string>  $labels
     * @return list<string>
     *
     * @throws BusinessRuleViolation `invalid-screening-question` with `errors` on `options`
     */
    public static function options(QuestionType $type, array $labels): array
    {
        if (! $type->hasOptions()) {
            if ($labels !== []) {
                throw self::invalid('options', 'Only a choice question has options.');
            }

            return [];
        }

        $clean = array_map(static fn (string $label): string => trim($label), $labels);

        if (count($clean) < self::OPTIONS_MIN || count($clean) > self::OPTIONS_MAX) {
            throw self::invalid('options', 'A choice question needs between '.self::OPTIONS_MIN.' and '.self::OPTIONS_MAX.' options.');
        }

        $seen = [];

        foreach ($clean as $label) {
            $length = mb_strlen($label);

            if ($length < 1 || $length > self::OPTION_MAX_LENGTH) {
                throw self::invalid('options', 'Every option must have between 1 and '.self::OPTION_MAX_LENGTH.' characters.');
            }

            $key = mb_strtolower($label);

            if (isset($seen[$key])) {
                throw self::invalid('options', 'The options must be different from each other.');
            }

            $seen[$key] = true;
        }

        return $clean;
    }

    private static function invalid(string $field, string $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-screening-question', extensions: ['errors' => [$field => [$message]]]);
    }
}
