<?php

namespace Src\JobInvitation\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The admin settings of the JobInvitation context, registered in the shared catalog by its service provider. */
final class InvitationSettings
{
    public const string MAX_PER_DAY = 'invitations.max_per_day';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::integer(
                self::MAX_PER_DAY, 50, 1, 1000,
                'The most job invitations the members of one company may send per calendar day (Jakarta time).',
                overridable: true,
            ),
        ];
    }
}
