<?php

namespace Src\Assessment\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** The content rules of a question: its text, points, scale and options, which depend on its type, on the kind of test and on the section format. */
final class QuestionRules
{
    public const int TEXT_MIN = 3;

    public const int TEXT_MAX = 2000;

    public const int POINTS_MAX = 100;

    public const int OPTION_LABEL_MAX = 500;

    public const int SCORE_MAX = 100;

    /** The limits of a video answer question, in seconds (the think time, when it is limited, and the answer time) and in retakes. */
    public const int VIDEO_SECONDS_MIN = 30;

    public const int VIDEO_SECONDS_MAX = 300;

    public const int VIDEO_RETAKES_MAX = 5;

    public const string SCALE_KEY_PATTERN = '/^[a-z][a-z0-9_]{0,59}$/';

    /** @var array<string, array{0: int, 1: int}> the number of options of each type that has them */
    private const array OPTION_COUNTS = [
        'single_choice' => [2, 10],
        'multiple_response' => [2, 10],
        'true_false' => [2, 2],
        'number' => [1, 1],
        'short_text' => [1, 10],
        'likert' => [2, 10],
        'forced_choice' => [2, 6],
    ];

    /**
     * Checks the question and returns what is stored: the points (zero for a question no one scores by points; for a drawing they are the highest score the psychologist can give), the scale key and the options
     * in their clean form. A video answer question also has its think time (null: not limited), answer time and retakes; every other type has none of them.
     *
     * @param  list<QuestionOption>  $options
     * @return array{text: string, points: int, scaleKey: ?string, options: list<QuestionOption>, thinkSeconds: ?int, answerSeconds: ?int, maxRetakes: ?int}
     *
     * @throws BusinessRuleViolation `invalid-question` or `video-not-allowed`, with `errors` on the field
     */
    public static function check(
        TestKind $kind,
        SectionFormat $format,
        QuestionType $type,
        string $text,
        int $points,
        ?string $scaleKey,
        array $options,
        ?int $thinkSeconds = null,
        ?int $answerSeconds = null,
        ?int $maxRetakes = null,
    ): array {
        self::assertTypeFits($kind, $format, $type);

        $text = trim($text);
        $length = mb_strlen($text);

        if ($length < self::TEXT_MIN || $length > self::TEXT_MAX) {
            throw self::invalid('text', 'The question must have between '.self::TEXT_MIN.' and '.self::TEXT_MAX.' characters.');
        }

        $scored = ! in_array($type, [QuestionType::Likert, QuestionType::ForcedChoice], true);

        if ($scored && ($points < 1 || $points > self::POINTS_MAX)) {
            throw self::invalid('points', 'The points must be between 1 and '.self::POINTS_MAX.'.');
        }

        return [
            'text' => $text,
            'points' => $scored ? $points : 0,
            'scaleKey' => self::scaleKey($kind, $type, $scaleKey),
            'options' => self::options($type, $options),
            ...self::video($type, $thinkSeconds, $answerSeconds, $maxRetakes),
        ];
    }

    /**
     * @return array{thinkSeconds: ?int, answerSeconds: ?int, maxRetakes: ?int}
     */
    private static function video(QuestionType $type, ?int $thinkSeconds, ?int $answerSeconds, ?int $maxRetakes): array
    {
        if ($type !== QuestionType::VideoAnswer) {
            foreach (['think_seconds' => $thinkSeconds, 'answer_seconds' => $answerSeconds, 'max_retakes' => $maxRetakes] as $field => $value) {
                if ($value !== null) {
                    throw self::invalid($field, 'Only a video answer question has this setting.');
                }
            }

            return ['thinkSeconds' => null, 'answerSeconds' => null, 'maxRetakes' => null];
        }

        $range = 'between '.self::VIDEO_SECONDS_MIN.' and '.self::VIDEO_SECONDS_MAX.' seconds';

        if ($thinkSeconds !== null && ($thinkSeconds < self::VIDEO_SECONDS_MIN || $thinkSeconds > self::VIDEO_SECONDS_MAX)) {
            throw self::invalid('think_seconds', "The think time must be {$range}, or empty when it is not limited.");
        }

        if ($answerSeconds === null || $answerSeconds < self::VIDEO_SECONDS_MIN || $answerSeconds > self::VIDEO_SECONDS_MAX) {
            throw self::invalid('answer_seconds', "The answer time must be {$range}.");
        }

        if ($maxRetakes === null || $maxRetakes < 0 || $maxRetakes > self::VIDEO_RETAKES_MAX) {
            throw self::invalid('max_retakes', 'The number of retakes must be between 0 and '.self::VIDEO_RETAKES_MAX.'.');
        }

        return ['thinkSeconds' => $thinkSeconds, 'answerSeconds' => $answerSeconds, 'maxRetakes' => $maxRetakes];
    }

    private static function assertTypeFits(TestKind $kind, SectionFormat $format, QuestionType $type): void
    {
        if ($kind === TestKind::Skill && $type->isPsychologicalOnly()) {
            throw self::invalid('type', 'Only a psychological battery can use this question type.');
        }

        if ($kind === TestKind::Psychological && $type->isSkillOnly()) {
            throw self::invalid('type', 'Only a skill test can have video answer questions.', 'video-not-allowed');
        }

        if ($format === SectionFormat::SpeedColumns) {
            throw self::invalid('type', 'A speed columns section has no questions.');
        }

        if (($format === SectionFormat::Drawing) !== ($type === QuestionType::Drawing)) {
            throw self::invalid('type', $format === SectionFormat::Drawing ? 'A drawing section holds drawing questions only.' : 'A drawing question belongs to a drawing section.');
        }

        if (($format === SectionFormat::Video) !== ($type === QuestionType::VideoAnswer)) {
            throw self::invalid('type', $format === SectionFormat::Video ? 'A video section holds video answer questions only.' : 'A video answer question belongs to a video section.', 'video-not-allowed');
        }
    }

    /**
     * A psychological question that is scored belongs to a scale (the psychological dimension it adds to); a forced choice puts the scale on its
     * options, a drawing is scored by the psychologist. A skill question has no scale.
     */
    private static function scaleKey(TestKind $kind, QuestionType $type, ?string $scaleKey): ?string
    {
        $scaleKey = $scaleKey === null || trim($scaleKey) === '' ? null : trim($scaleKey);

        $needsScale = $kind === TestKind::Psychological && ! in_array($type, [QuestionType::ForcedChoice, QuestionType::Drawing, QuestionType::LongText], true);

        if (! $needsScale) {
            if ($scaleKey !== null) {
                throw self::invalid('scale_key', 'This question does not belong to a scale.');
            }

            return null;
        }

        if ($scaleKey === null) {
            throw self::invalid('scale_key', 'A psychological question needs a scale.');
        }

        self::assertScaleKey($scaleKey, 'scale_key');

        return $scaleKey;
    }

    /**
     * @param  list<QuestionOption>  $options
     * @return list<QuestionOption>
     */
    private static function options(QuestionType $type, array $options): array
    {
        $range = self::OPTION_COUNTS[$type->value] ?? null;

        if ($range === null) {
            if ($options !== []) {
                throw self::invalid('options', 'This question type has no options.');
            }

            return [];
        }

        if (count($options) < $range[0] || count($options) > $range[1]) {
            throw self::invalid('options', $range[0] === $range[1]
                ? "This question type needs exactly {$range[0]} option(s)."
                : "This question type needs between {$range[0]} and {$range[1]} options.");
        }

        $clean = [];
        $seen = [];

        foreach ($options as $option) {
            $label = trim($option->label);
            $length = mb_strlen($label);

            if ($length < 1 || $length > self::OPTION_LABEL_MAX) {
                throw self::invalid('options', 'Every option must have between 1 and '.self::OPTION_LABEL_MAX.' characters.');
            }

            $key = mb_strtolower($label);

            if (isset($seen[$key])) {
                throw self::invalid('options', 'The options must be different from each other.');
            }

            $seen[$key] = true;
            $clean[] = self::option($type, $label, $option);
        }

        $correct = count(array_filter($clean, static fn (QuestionOption $option): bool => $option->isCorrect));

        if (in_array($type, [QuestionType::SingleChoice, QuestionType::TrueFalse], true) && $correct !== 1) {
            throw self::invalid('options', 'Exactly one option must be correct.');
        }

        if ($type === QuestionType::MultipleResponse && $correct < 1) {
            throw self::invalid('options', 'At least one option must be correct.');
        }

        return $clean;
    }

    private static function option(QuestionType $type, string $label, QuestionOption $option): QuestionOption
    {
        if (in_array($type, [QuestionType::Likert, QuestionType::ForcedChoice], true)) {
            if ($option->isCorrect) {
                throw self::invalid('options', 'A scored statement has no correct answer.');
            }

            if ($option->scoreValue === null || abs($option->scoreValue) > self::SCORE_MAX) {
                throw self::invalid('options', 'Every option needs a score between -'.self::SCORE_MAX.' and '.self::SCORE_MAX.'.');
            }

            if ($type === QuestionType::ForcedChoice) {
                if ($option->scaleKey === null) {
                    throw self::invalid('options', 'Every option of a forced choice needs a scale.');
                }

                self::assertScaleKey($option->scaleKey, 'options');
            } elseif ($option->scaleKey !== null) {
                throw self::invalid('options', 'The scale of a Likert question is set on the question.');
            }

            return new QuestionOption($label, false, $option->scoreValue, $option->scaleKey);
        }

        if ($option->scoreValue !== null || $option->scaleKey !== null) {
            throw self::invalid('options', 'Only a Likert or forced choice option has a score or a scale.');
        }

        if ($type === QuestionType::Number) {
            if (! is_numeric($label) || abs((float) $label) > 999_999_999) {
                throw self::invalid('options', 'The accepted answer must be a number.');
            }

            return new QuestionOption($label, true);
        }

        return new QuestionOption($label, $type === QuestionType::ShortText ? true : $option->isCorrect);
    }

    private static function assertScaleKey(string $scaleKey, string $field): void
    {
        if (preg_match(self::SCALE_KEY_PATTERN, $scaleKey) !== 1) {
            throw self::invalid($field, 'A scale key starts with a lowercase letter and holds lowercase letters, digits and underscores (at most 60).');
        }
    }

    private static function invalid(string $field, string $message, string $code = 'invalid-question'): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, $code, extensions: ['errors' => [$field => [$message]]]);
    }
}
