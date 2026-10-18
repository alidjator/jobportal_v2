<?php

namespace Src\Platform\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The admin settings of the Platform context, registered in the shared catalog by its service provider. */
final class PlatformSettings
{
    public const string OPERATIONS_EMAIL = 'platform.operations_email';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::text(
                self::OPERATIONS_EMAIL, '', 255,
                'The address that gets the scheduler alerts (a scheduled task is late or failing, and when it recovers). Empty means no email.',
            ),
        ];
    }
}
