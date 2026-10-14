<?php

namespace Src\Company\Domain;

use DateTimeImmutable;
use Src\Shared\Application\Company\CompanyPermission;

/**
 * A member of a company: the account (`userId`), the role, the permissions checked for them (none for a company admin, who holds all), whether they are
 * the owner of the company (one company admin per company) and when they joined. A suspended member keeps everything, but cannot sign in.
 */
final readonly class CompanyMember
{
    /** @param  list<string>  $permissions */
    public function __construct(
        public int $userId,
        public CompanyRole $role,
        public array $permissions,
        public DateTimeImmutable $joinedAt,
        public bool $isOwner = false,
        public MemberStatus $status = MemberStatus::Active,
        public ?DateTimeImmutable $suspendedAt = null,
        public ?int $suspendedByUserId = null,
    ) {}

    public function isCompanyAdmin(): bool
    {
        return $this->role === CompanyRole::CompanyAdmin;
    }

    public function isSuspended(): bool
    {
        return $this->status === MemberStatus::Suspended;
    }

    /** @return list<string> */
    public function effectivePermissions(): array
    {
        return $this->role === CompanyRole::CompanyAdmin
            ? CompanyPermission::values()
            : array_values(array_filter(CompanyPermission::values(), fn (string $name): bool => in_array($name, $this->permissions, true)));
    }
}
