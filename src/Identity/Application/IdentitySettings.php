<?php

namespace Src\Identity\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The admin settings of the Identity context, registered in the shared catalog by its service provider. They are global. */
final class IdentitySettings
{
    public const string AVATAR_MAX_SIZE_KB = 'identity.avatar_max_size_kb';

    public const string ACTIVATION_REMINDER_DAYS = 'identity.activation_reminder_days';

    public const string ACTIVATION_BOUNCE_LIMIT_PERCENT = 'identity.activation_bounce_limit_percent';

    public const string ACTIVATION_COMPLAINT_LIMIT_PERCENT = 'identity.activation_complaint_limit_percent';

    public const string ACTIVATION_JOB_SEEKERS_NOT_BEFORE = 'identity.activation_job_seekers_not_before';

    public const string IMPORT_SUMMARY_RETENTION_DAYS = 'identity.import_summary_retention_days';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::integer(self::AVATAR_MAX_SIZE_KB, 2048, 100, 10240, 'The largest profile picture file an account may upload, in kilobytes.'),
            SettingDefinition::integer(self::ACTIVATION_REMINDER_DAYS, 7, 1, 60, 'After how many days an imported account that has not signed in gets its single activation reminder.'),
            SettingDefinition::text(self::ACTIVATION_BOUNCE_LIMIT_PERCENT, '2', 10, 'The activation emails stop when more than this percentage of the previous batch bounced (a number, for example 2).'),
            SettingDefinition::text(self::ACTIVATION_COMPLAINT_LIMIT_PERCENT, '0.1', 10, 'The activation emails stop when more than this percentage of the previous batch complained (a number, for example 0.1).'),
            SettingDefinition::text(self::ACTIVATION_JOB_SEEKERS_NOT_BEFORE, '', 10, 'The first day (YYYY-MM-DD) imported job seekers may be emailed their activation. Empty: the send command refuses.'),
            SettingDefinition::integer(self::IMPORT_SUMMARY_RETENTION_DAYS, 90, 7, 730, 'How many days after the first sign-in an imported job seeker or company can still read the summary of what the karir.pkp import carried; then it is deleted.'),
        ];
    }
}
