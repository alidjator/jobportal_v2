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

    public const string REAPPLY_AFTER_DAYS = 'recruitment.reapply_after_days';

    public const string INTERVIEW_RESCHEDULE_CUTOFF_HOURS = 'recruitment.interview_reschedule_cutoff_hours';

    public const string REJECTION_UNDO_MINUTES = 'recruitment.rejection_undo_minutes';

    public const string PRE_EMPLOYMENT_CHECKS_ENABLED = 'recruitment.pre_employment_checks_enabled';

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
            SettingDefinition::integer(
                self::REAPPLY_AFTER_DAYS, 0, 0, 365,
                'The waiting period, in days after a rejection, that a new job starts with before the same person may apply to it again (30 to 365). 0 means never. The company admin sets it on the company settings page; each job keeps its own value.',
                companyEditable: true,
            ),
            SettingDefinition::boolean(
                self::OFFER_LABOR_RULES, true,
                'Whether an offer follows the labour-law limits (PP 35/2021): probation only for a permanent employee and at most 3 months, an internship at most 12 months. While off, only the field limits apply.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::INTERVIEW_RESCHEDULE_CUTOFF_HOURS, 24, 0, 168,
                'Until how many hours before a confirmed interview the job seeker may ask to move it, and how far ahead the time they propose must be. 0 allows it until the start.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::REJECTION_UNDO_MINUTES, 0, 0, 60,
                'How many minutes a rejection waits before it takes effect, during which the team can still undo it (`docs/design/phase-8-rejections.md` section 2). 0 rejects at once. The design default is 10; it ships as 0 until the screens that undo are checked.',
                overridable: true,
            ),
            SettingDefinition::boolean(
                self::PRE_EMPLOYMENT_CHECKS_ENABLED, false,
                'Whether a company may keep a checklist of pre-employment checks (medical check-up, background, references, police record) per application (`docs/design/phase-8-pre-employment-checks.md`). Off until the legal review (question 21) is answered.',
            ),
        ];
    }
}
