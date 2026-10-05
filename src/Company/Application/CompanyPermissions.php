<?php

namespace Src\Company\Application;

use Src\Shared\Application\Permissions\PermissionDefinition;

/** The admin permissions of the Company context, registered in the shared catalog by its service provider. */
final class CompanyPermissions
{
    public const string GROUP = 'company';

    public const string COMPANIES_MANAGE = 'companies.manage';

    /** @return list<PermissionDefinition> */
    public static function definitions(): array
    {
        return [
            new PermissionDefinition(self::COMPANIES_MANAGE, self::GROUP),
        ];
    }
}
