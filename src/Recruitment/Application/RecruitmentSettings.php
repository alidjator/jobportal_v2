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

    public const string MAX_EXPORT_ROWS = 'recruitment.max_export_rows';

    public const string MAX_INTERVIEW_KITS = 'recruitment.max_interview_kits';

    public const string STALE_AFTER_DAYS = 'recruitment.stale_after_days';

    public const string OFFER_LABOR_RULES = 'recruitment.offer_labor_rules';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::integer(
                self::MAX_STAGES, 11, 8, 20,
                'The most application stages a company may have, the eight defaults included.',
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
            SettingDefinition::integer(
                self::MAX_EXPORT_ROWS, 50000, 1, 200000,
                'The most applications one applicant export (CSV) may hold; a larger selection is refused until the filters narrow it.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::MAX_INTERVIEW_KITS, 100, 1, 500,
                'The most interview kits a company may keep.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::STALE_AFTER_DAYS, 7, 1, 60,
                'After how many days in the applied or screening stage an application is marked stale. The company admin sets it on the company settings page.',
                companyEditable: true,
            ),
            SettingDefinition::boolean(
                self::OFFER_LABOR_RULES, true,
                'Whether an offer follows the labour-law limits (PP 35/2021): probation only for a permanent employee and at most 3 months, an internship at most 12 months. While off, only the field limits apply.',
                overridable: true,
            ),
        ];
    }
}
