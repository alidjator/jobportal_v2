<?php

namespace Src\JobSeeker\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The admin settings of the JobSeeker context, registered in the shared catalog by its service provider. They are global: there is no per-company override. */
final class JobSeekerSettings
{
    public const string MIN_AGE = 'job_seeker.min_age';

    public const string MAX_AGE = 'job_seeker.max_age';

    public const string AVAILABLE_FROM_MAX_DAYS = 'job_seeker.available_from_max_days';

    public const string RESUME_MAX_SIZE_KB = 'job_seeker.resume_max_size_kb';

    public const string CANDIDATE_VIEWS_PER_DAY = 'job_seeker.candidate_views_per_day';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        // The ranges keep the minimum age always below the maximum age.
        return [
            SettingDefinition::integer(self::MIN_AGE, 15, 13, 30, 'The youngest age, in years, that a job seeker may give with their birth date.'),
            SettingDefinition::integer(self::MAX_AGE, 60, 40, 100, 'The oldest age, in years, that a job seeker may give with their birth date. 100 effectively switches the upper limit off.'),
            SettingDefinition::integer(
                self::AVAILABLE_FROM_MAX_DAYS, 30, 1, 40,
                'How many days ahead a job seeker may set the date from which they are available.',
            ),
            SettingDefinition::integer(self::RESUME_MAX_SIZE_KB, 5120, 512, 10240, 'The largest resume file a job seeker may upload, in kilobytes.'),
            SettingDefinition::integer(
                self::CANDIDATE_VIEWS_PER_DAY, 200, 10, 5000,
                'How many different candidate profiles a company member may open per day (Jakarta time). Opening the same profile again the same day does not count.',
            ),
        ];
    }
}
