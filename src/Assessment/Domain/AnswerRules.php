<?php

namespace Src\Assessment\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** What a candidate may send as the answer to a question of each type. Only the field of the type is accepted, and the options are named by position. */
final class AnswerRules
{
    public const int MAX_SHORT_TEXT = 500;

    public const int MAX_LONG_TEXT = 10_000;

    /**
     * @param  list<int>|null  $optionPositions
     *
     * @throws BusinessRuleViolation `invalid-answer` with `errors` on `option_positions`, `number` or `text`
     */
    public static function answer(Question $question, ?array $optionPositions, int|float|null $number, ?string $text): Answer
    {
        $id = (int) $question->id;

        return match ($question->type) {
            QuestionType::SingleChoice, QuestionType::TrueFalse, QuestionType::Likert, QuestionType::ForcedChoice => self::choice($question, $optionPositions, $number, $text, single: true),
            QuestionType::MultipleResponse => self::choice($question, $optionPositions, $number, $text, single: false),
            QuestionType::Number => self::number($id, $optionPositions, $number, $text),
            QuestionType::ShortText => new Answer($id, textValue: self::text($id, $optionPositions, $number, $text, self::MAX_SHORT_TEXT)),
            QuestionType::LongText => new Answer($id, textValue: self::text($id, $optionPositions, $number, $text, self::MAX_LONG_TEXT)),
            QuestionType::Drawing => throw self::invalid('text', 'A drawing is sent as a file, not as an answer.'),
        };
    }

    /** @param  list<int>|null  $positions */
    private static function choice(Question $question, ?array $positions, int|float|null $number, ?string $text, bool $single): Answer
    {
        if ($number !== null || $text !== null) {
            throw self::invalid('option_positions', 'This question is answered by choosing options.');
        }

        $positions = array_values(array_unique($positions ?? []));
        $count = count($question->options);

        if ($positions === [] || ($single && count($positions) !== 1)) {
            throw self::invalid('option_positions', $single ? 'Choose exactly one option.' : 'Choose at least one option.');
        }

        foreach ($positions as $position) {
            if ($position < 1 || $position > $count) {
                throw self::invalid('option_positions', 'Choose among the options of the question.');
            }
        }

        sort($positions);

        return new Answer((int) $question->id, $positions);
    }

    /** @param  list<int>|null  $positions */
    private static function number(int $id, ?array $positions, int|float|null $number, ?string $text): Answer
    {
        if ($positions !== null || $text !== null || $number === null || abs((float) $number) > 999_999_999) {
            throw self::invalid('number', 'This question is answered with a number.');
        }

        return new Answer($id, numberValue: (float) $number);
    }

    /** @param  list<int>|null  $positions */
    private static function text(int $id, ?array $positions, int|float|null $number, ?string $text, int $max): string
    {
        if ($positions !== null || $number !== null) {
            throw self::invalid('text', 'This question is answered with text.');
        }

        $text = trim((string) $text);

        if ($text === '' || mb_strlen($text) > $max) {
            throw self::invalid('text', "The answer must have between 1 and {$max} characters.");
        }

        return $text;
    }

    private static function invalid(string $field, string $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-answer', extensions: ['errors' => [$field => [$message]]]);
    }
}
