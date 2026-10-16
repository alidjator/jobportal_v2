<?php

namespace Src\Approval\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The settings of the Approval context. */
final class ApprovalSettings
{
    public const string REMINDER_DAYS = 'approvals.reminder_days';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::integer(
                self::REMINDER_DAYS, 2, 1, 14,
                'After how many days without a decision the approvers of the step being decided are reminded, and again every that many days until it is decided.',
                overridable: true,
            ),
        ];
    }
}
