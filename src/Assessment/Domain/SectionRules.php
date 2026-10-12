<?php

namespace Src\Assessment\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** The rules of a section: its title, its format and what the format allows, and its time. */
final class SectionRules
{
    public const int DURATION_MIN_SECONDS = 60;

    /** Kraepelin-type columns: how many, how many digits each, and the seconds each one lasts. */
    public const array SPEED_COLUMN_LIMITS = [
        'columns' => [1, 100],
        'digits_per_column' => [10, 100],
        'seconds_per_column' => [5, 300],
    ];

    /**
     * @throws BusinessRuleViolation `invalid-section` with `errors` on `title`
     */
    public static function title(string $title): string
    {
        $title = trim($title);
        $length = mb_strlen($title);

        if ($length < 2 || $length > 200) {
            throw self::invalid('title', 'The title must have between 2 and 200 characters.');
        }

        return $title;
    }

    /**
     * @throws BusinessRuleViolation `invalid-section` or `video-not-allowed`, with `errors` on `format`
     */
    public static function assertFormatAllowed(TestKind $kind, SectionFormat $format): void
    {
        if ($kind === TestKind::Skill && $format->isPsychologicalOnly()) {
            throw self::invalid('format', 'Only a psychological battery can use this section format.');
        }

        if ($kind === TestKind::Psychological && $format->isSkillOnly()) {
            throw self::invalid('format', 'Only a skill test can have video sections.', 'video-not-allowed');
        }
    }

    /**
     * The numbers of a section format: the Kraepelin columns for `speed_columns`, none for the others.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, int>
     *
     * @throws BusinessRuleViolation `invalid-section` with `errors` on `settings`
     */
    public static function settings(SectionFormat $format, array $settings): array
    {
        if ($format !== SectionFormat::SpeedColumns) {
            if ($settings !== []) {
                throw self::invalid('settings', 'Only a speed columns section has settings.');
            }

            return [];
        }

        $clean = [];

        foreach (self::SPEED_COLUMN_LIMITS as $key => [$min, $max]) {
            $value = $settings[$key] ?? null;

            if (! is_int($value) || $value < $min || $value > $max) {
                throw self::invalid('settings', "The setting {$key} must be a whole number between {$min} and {$max}.");
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * The section time in seconds. A speed columns section lasts as long as its columns (`columns` × `seconds_per_column`); the others use the
     * given time, which must lie between one minute and `$maxSeconds`.
     *
     * @param  array<string, int>  $settings  the clean settings
     *
     * @throws BusinessRuleViolation `invalid-section` with `errors` on `duration_seconds`
     */
    public static function duration(SectionFormat $format, ?int $seconds, array $settings, int $maxSeconds): int
    {
        if ($format === SectionFormat::SpeedColumns) {
            $total = $settings['columns'] * $settings['seconds_per_column'];

            if ($total > $maxSeconds) {
                throw self::invalid('settings', 'The columns last longer than the longest section time allowed.');
            }

            return $total;
        }

        if ($seconds === null || $seconds < self::DURATION_MIN_SECONDS || $seconds > $maxSeconds) {
            throw self::invalid('duration_seconds', 'The section time must be between '.self::DURATION_MIN_SECONDS.' and '.$maxSeconds.' seconds.');
        }

        return $seconds;
    }

    /**
     * @throws BusinessRuleViolation `invalid-section` with `errors` on `draw_count`
     */
    public static function drawCount(SectionFormat $format, ?int $drawCount): ?int
    {
        if ($drawCount === null) {
            return null;
        }

        if ($format !== SectionFormat::Questions) {
            throw self::invalid('draw_count', 'Only a section of questions draws questions at random.');
        }

        if ($drawCount < 1) {
            throw self::invalid('draw_count', 'The number of questions drawn must be at least 1.');
        }

        return $drawCount;
    }

    /**
     * @throws BusinessRuleViolation `invalid-section`: a speed columns section has no questions
     */
    public static function assertTakesQuestions(SectionFormat $format): void
    {
        if ($format === SectionFormat::SpeedColumns) {
            throw self::invalid('format', 'A speed columns section has no questions.');
        }
    }

    private static function invalid(string $field, string $message, string $code = 'invalid-section'): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, $code, extensions: ['errors' => [$field => [$message]]]);
    }
}
