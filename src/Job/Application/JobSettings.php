<?php

namespace Src\Job\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The admin settings of the Job context, registered in the shared catalog by its service provider. Both can be overridden per company. */
final class JobSettings
{
    public const string REQUIRE_APPROVAL = 'jobs.require_approval';

    public const string MAX_DEADLINE_DAYS = 'jobs.max_deadline_days';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::boolean(
                self::REQUIRE_APPROVAL, false,
                'Whether a job must be approved by an admin before it goes live. When off, publishing goes live at once.',
                overridable: true,
            ),
            SettingDefinition::integer(
                self::MAX_DEADLINE_DAYS, 90, 7, 365,
                'The farthest deadline a job may have, in days from today.',
                overridable: true,
            ),
        ];
    }
}
