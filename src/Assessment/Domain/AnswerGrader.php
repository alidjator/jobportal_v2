<?php

namespace Src\Assessment\Domain;

/**
 * The score of an answer, worked out from the question alone. Choice, number and short-text questions of a skill test give their points when right and 0
 * when wrong; a Likert or forced-choice answer gives the score of the option chosen. A long-text answer, a drawing and a video answer need a person, so they give null.
 */
final class AnswerGrader
{
    private const float NUMBER_TOLERANCE = 0.000001;

    public static function grade(Question $question, ?Answer $answer): ?float
    {
        if (in_array($question->type, [QuestionType::LongText, QuestionType::Drawing, QuestionType::VideoAnswer], true)) {
            return null;
        }

        if (! $answer instanceof Answer) {
            return 0.0;
        }

        return match ($question->type) {
            QuestionType::SingleChoice, QuestionType::TrueFalse => self::pointsWhen($question, self::chosenAreCorrect($question, $answer, single: true)),
            QuestionType::MultipleResponse => self::pointsWhen($question, self::chosenAreCorrect($question, $answer, single: false)),
            QuestionType::Number => self::pointsWhen($question, self::numberIsAccepted($question, $answer)),
            QuestionType::ShortText => self::pointsWhen($question, self::textIsAccepted($question, $answer)),
            default => (float) (self::chosenOption($question, $answer)->scoreValue ?? 0),
        };
    }

    /** The scale a graded answer counts for: the question's, or for forced choice the chosen statement's. */
    public static function scaleOf(Question $question, ?Answer $answer): ?string
    {
        if ($question->type === QuestionType::ForcedChoice) {
            return $answer instanceof Answer ? self::chosenOption($question, $answer)?->scaleKey : null;
        }

        return $question->scaleKey;
    }

    private static function pointsWhen(Question $question, bool $right): float
    {
        return $right ? (float) $question->points : 0.0;
    }

    private static function chosenOption(Question $question, Answer $answer): ?QuestionOption
    {
        $position = $answer->optionPositions[0] ?? null;

        return $position === null ? null : ($question->options[$position - 1] ?? null);
    }

    private static function chosenAreCorrect(Question $question, Answer $answer, bool $single): bool
    {
        $correct = [];

        foreach ($question->options as $index => $option) {
            if ($option->isCorrect) {
                $correct[] = $index + 1;
            }
        }

        $chosen = array_values(array_unique($answer->optionPositions));
        sort($chosen);

        return $chosen !== [] && ($single ? count($chosen) === 1 : true) && $chosen === $correct;
    }

    private static function numberIsAccepted(Question $question, Answer $answer): bool
    {
        if ($answer->numberValue === null) {
            return false;
        }

        foreach ($question->options as $option) {
            if (is_numeric($option->label) && abs((float) $option->label - $answer->numberValue) < self::NUMBER_TOLERANCE) {
                return true;
            }
        }

        return false;
    }

    private static function textIsAccepted(Question $question, Answer $answer): bool
    {
        $given = self::normalised((string) $answer->textValue);

        if ($given === '') {
            return false;
        }

        foreach ($question->options as $option) {
            if (self::normalised($option->label) === $given) {
                return true;
            }
        }

        return false;
    }

    private static function normalised(string $text): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
    }
}
