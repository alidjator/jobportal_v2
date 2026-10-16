<?php

namespace Src\Organization\Application;

use Src\Shared\Application\Organization\GroupSettingKeys;
use Src\Shared\Application\Settings\SettingDefinition;

/** The settings of the Organization context: those a group sets for its internal tenants (the group scope of the settings). */
final class OrganizationSettings
{
    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::boolean(
                GroupSettingKeys::REQUIRE_TWO_FACTOR, true,
                'Whether every member of every internal tenant of the group must sign in with a second factor (a company admin always must). On by default; a group admin can switch it off.',
                groupEditable: true,
            ),
        ];
    }
}
