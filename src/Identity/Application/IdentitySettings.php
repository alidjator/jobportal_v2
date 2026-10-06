<?php

namespace Src\Identity\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The admin settings of the Identity context, registered in the shared catalog by its service provider. They are global. */
final class IdentitySettings
{
    public const string AVATAR_MAX_SIZE_KB = 'identity.avatar_max_size_kb';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::integer(self::AVATAR_MAX_SIZE_KB, 2048, 100, 10240, 'The largest profile picture file an account may upload, in kilobytes.'),
        ];
    }
}
