<?php

namespace Src\Workforce\Application;

use Src\Shared\Application\Settings\SettingDefinition;
use Src\Workforce\Domain\RequisitionNumberFormat;

/** The settings of the Workforce context. */
final class WorkforceSettings
{
    public const string FISCAL_YEAR_START_MONTH = 'workforce.fiscal_year_start_month';

    public const string REQUISITION_NUMBER_FORMAT = 'workforce.requisition_number_format';

    public const string REQUISITION_NUMBER_MODE = 'workforce.requisition_number_mode';

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
            SettingDefinition::text(
                self::REQUISITION_NUMBER_FORMAT, RequisitionNumberFormat::DEFAULT, RequisitionNumberFormat::MAX_LENGTH,
                'The format of the number a requisition gets when it is made: letters, digits and - _ / ., the day as {YYYY}, {YY}, {MM} and {DD}, and exactly one running number, {SEQ} or {SEQ:3} for three digits. The running number starts again when the rest of the number changes (a format with {DD} counts per day). Example: JR-{YYYY}{MM}{DD}-{SEQ:3} gives JR-20261101-001.',
                companyEditable: true, groupEditable: true, pattern: RequisitionNumberFormat::PATTERN,
            ),
            SettingDefinition::text(
                self::REQUISITION_NUMBER_MODE, 'auto', 6,
                'Where the number of a requisition comes from: auto (the format of workforce.requisition_number_format) or manual (the member who makes the requisition types it, one to 40 characters, never used twice in the company). A number is fixed once the requisition is submitted.',
                companyEditable: true, groupEditable: true, pattern: '/^(auto|manual)$/',
            ),
        ];
    }
}
