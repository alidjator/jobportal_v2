<?php

namespace Src\Workforce\Application;

use Src\Shared\Application\Settings\SettingDefinition;

/** The settings of the Workforce context. */
final class WorkforceSettings
{
    public const string FISCAL_YEAR_START_MONTH = 'workforce.fiscal_year_start_month';

    public const string REQUISITION_REQUIRED = 'recruitment.requisition_required';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::integer(
                self::FISCAL_YEAR_START_MONTH, 1, 1, 12,
                'The month the fiscal year of the group starts in (1 for January). A fiscal year is named by the calendar year it starts in.',
                groupEditable: true,
            ),
            SettingDefinition::boolean(
                self::REQUISITION_REQUIRED, true,
                'Whether a tenant of an ERP group opens its jobs only from an approved requisition. Ignored for a company that is not a tenant of a group.',
                overridable: true,
            ),
        ];
    }
}
