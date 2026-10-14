<?php

namespace Src\Company\Domain;

use DateTimeImmutable;
use Src\Shared\Application\Company\CompanyPermission;

/** A member of a company: the account (`userId`), the role, the permissions checked for them (none for a company admin, who holds all) and when they joined. */
final readonly class CompanyMember
{
    /** @param  list<string>  $permissions */
    public function __construct(
        public int $userId,
        public CompanyRole $role,
        public array $permissions,
        public DateTimeImmutable $joinedAt,
    ) {}

    /** @return list<string> */
    public function effectivePermissions(): array
    {
        return $this->role === CompanyRole::CompanyAdmin
            ? CompanyPermission::values()
            : array_values(array_filter(CompanyPermission::values(), fn (string $name): bool => in_array($name, $this->permissions, true)));
    }
}
