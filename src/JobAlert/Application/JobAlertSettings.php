<?php

namespace Src\JobAlert\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The admin settings of the JobAlert context, registered in the shared catalog by its service provider. */
final class JobAlertSettings
{
    public const string MAX_ALERTS_PER_USER = 'jobalert.max_alerts_per_user';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::integer(
                self::MAX_ALERTS_PER_USER, 5, 1, 50,
                'The most job alerts one job seeker may have.',
            ),
        ];
    }
}
