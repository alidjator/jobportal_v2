<?php

namespace Src\Platform\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The admin settings of the Platform context, registered in the shared catalog by its service provider. */
final class PlatformSettings
{
    public const string OPERATIONS_EMAIL = 'platform.operations_email';

    public const string SUPPORT_EMAIL = 'platform.support_email';

    public const string SUPPORT_WHATSAPP = 'platform.support_whatsapp';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::text(
                self::OPERATIONS_EMAIL, '', 255,
                'The address that gets the scheduler alerts (a scheduled task is late or failing, and when it recovers). Empty means no email.',
            ),
            SettingDefinition::text(
                self::SUPPORT_EMAIL, '', 255,
                'The support address named in the activation emails of imported accounts. The activation send command refuses to run while it is empty.',
            ),
            SettingDefinition::text(
                self::SUPPORT_WHATSAPP, '', 30,
                'The support WhatsApp number named next to the support address in the company activation email. Optional.',
            ),
        ];
    }
}
