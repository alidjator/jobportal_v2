<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The admin settings of the Recruitment context, registered in the shared catalog by its service provider. */
final class RecruitmentSettings
{
    public const string MAX_STAGES = 'recruitment.max_stages';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::integer(
                self::MAX_STAGES, 10, 7, 20,
                'The most application stages a company may have, the seven defaults included.',
                overridable: true,
            ),
        ];
    }
}
