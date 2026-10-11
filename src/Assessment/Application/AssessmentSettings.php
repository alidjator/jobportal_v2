<?php

namespace Src\Assessment\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The admin settings of the Assessment context, registered in the shared catalog by its service provider. */
final class AssessmentSettings
{
    public const string PSYCHOLOGICAL_ENABLED = 'assessment.psychological_enabled';

    public const string MAX_COMPANY_TESTS = 'assessment.max_company_tests';

    public const string MAX_SECTIONS_PER_TEST = 'assessment.max_sections_per_test';

    public const string MAX_QUESTIONS_PER_SECTION = 'assessment.max_questions_per_section';

    public const string MAX_SECTION_MINUTES = 'assessment.max_section_minutes';

    public const string MIN_DUE_HOURS = 'assessment.min_due_hours';

    public const string MAX_DUE_DAYS = 'assessment.max_due_days';

    public const string MAX_DRAWING_MEGABYTES = 'assessment.max_drawing_megabytes';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::boolean(
                self::PSYCHOLOGICAL_ENABLED, false,
                'Whether a company can use psychological batteries. Even then it uses only the batteries an admin granted to it.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::MAX_COMPANY_TESTS, 50, 1, 500,
                'The most skill tests a company may keep (archived ones do not count).',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::MAX_SECTIONS_PER_TEST, 10, 1, 20,
                'The most sections a test may have.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::MAX_QUESTIONS_PER_SECTION, 200, 1, 1000,
                'The most questions a section may have.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::MAX_SECTION_MINUTES, 180, 1, 600,
                'The longest time of one section, in minutes (the shortest is one minute).',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::MIN_DUE_HOURS, 1, 1, 72,
                'The least time between sending a test and its due time, in hours.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::MAX_DUE_DAYS, 30, 1, 90,
                'The most time between sending a test and its due time, in days.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::MAX_DRAWING_MEGABYTES, 5, 1, 20,
                'The largest drawing a candidate may upload (JPG or PNG), in megabytes.',
            ),
        ];
    }
}
