<?php

namespace Src\Company\Application\Images;

use Src\Shared\Application\Settings\SettingDefinition;

/** The admin settings of the Company context, registered in the shared catalog by its service provider. */
final class CompanySettings
{
    public const string LOGO_MAX_SIZE_KB = 'company.logo_max_size_kb';

    public const string BANNER_MAX_SIZE_KB = 'company.banner_max_size_kb';

    public const string DOCUMENT_MAX_SIZE_KB = 'company.document_max_size_kb';

    /** @return list<SettingDefinition> */
    public static function definitions(): array
    {
        return [
            SettingDefinition::integer(self::LOGO_MAX_SIZE_KB, 2048, 100, 10240, 'The largest company logo file a company admin may upload, in kilobytes.'),
            SettingDefinition::integer(self::BANNER_MAX_SIZE_KB, 5120, 100, 10240, 'The largest company banner file a company admin may upload, in kilobytes.'),
            SettingDefinition::integer(self::DOCUMENT_MAX_SIZE_KB, 5120, 100, 10240, 'The largest verification document file a company admin may upload, in kilobytes.', overridable: true),
        ];
    }
}
