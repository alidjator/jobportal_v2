<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The admin settings of the Recruitment context, registered in the shared catalog by its service provider. */
final class RecruitmentSettings
{
    public const string MAX_STAGES = 'recruitment.max_stages';

    public const string SCREENING_ENABLED = 'recruitment.screening_enabled';

    public const string MAX_SCREENING_QUESTIONS = 'recruitment.max_screening_questions';

    public const string MAX_APPLICATIONS_PER_DAY = 'recruitment.max_applications_per_day';

    public const string MAX_BULK_APPLICATIONS = 'recruitment.max_bulk_applications';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::integer(
                self::MAX_STAGES, 10, 7, 20,
                'The most application stages a company may have, the seven defaults included.',
                overridable: true,
            ),
            SettingDefinition::boolean(
                self::SCREENING_ENABLED, true,
                'Whether a company can use screening questions. While off, its questions and their attachment to jobs cannot be changed and its jobs show none.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::MAX_SCREENING_QUESTIONS, 5, 1, 20,
                'The most screening questions a job may have.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::MAX_APPLICATIONS_PER_DAY, 20, 1, 100,
                'The most applications one job seeker may submit per calendar day (Jakarta time), withdrawn ones included.',
            ),
            SettingDefinition::integer(
                self::MAX_BULK_APPLICATIONS, 100, 1, 500,
                'The most applications one bulk move or bulk rejection may cover.',
                overridable: true,
            ),
        ];
    }
}
