<?php

namespace Src\Shared\Application\Company;

use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The company a member belongs to, their role there (`company_admin` or `member`) and the permissions checked for them. A company admin holds
 * every permission without rows, so `permissions` of a company admin is empty and `can()` is the one place that answers.
 */
final readonly class CompanyMembership
{
    public const string ROLE_COMPANY_ADMIN = 'company_admin';

    public const string ROLE_MEMBER = 'member';

    /** @param  list<string>  $permissions  the names of the checked permissions of a member */
    public function __construct(
        public int $companyId,
        public string $role,
        public array $permissions = [],
    ) {}

    public function isCompanyAdmin(): bool
    {
        return $this->role === self::ROLE_COMPANY_ADMIN;
    }

    /** @return list<string> the permissions the member holds, in catalog order: every one for a company admin */
    public function effectivePermissions(): array
    {
        return $this->isCompanyAdmin()
            ? CompanyPermission::values()
            : array_values(array_filter(CompanyPermission::values(), fn (string $name): bool => in_array($name, $this->permissions, true)));
    }

    public function can(CompanyPermission $permission): bool
    {
        return $this->isCompanyAdmin() || in_array($permission->value, $this->permissions, true);
    }

    /** @throws AccessDenied `company-permission-required`, with the missing permission in the problem body */
    public function require(CompanyPermission $permission): void
    {
        if (! $this->can($permission)) {
            throw new AccessDenied(
                "This action needs the permission {$permission->value}.", 'company-permission-required', extensions: ['permission' => $permission->value],
            );
        }
    }
}
