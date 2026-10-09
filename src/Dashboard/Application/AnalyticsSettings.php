<?php

namespace Src\Dashboard\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The admin settings of the analytics, registered in the shared catalog by the service provider of the Dashboard context. */
final class AnalyticsSettings
{
    public const string MIN_GROUP_SIZE = 'analytics.min_group_size';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::integer(
                self::MIN_GROUP_SIZE, 5, 2, 50,
                'The smallest group of applications or jobs an analysis may show figures for. A smaller group shows as "fewer than" this number, so no figure can be traced to a few people.',
            ),
        ];
    }
}
