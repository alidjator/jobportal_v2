<?php

namespace Src\Company\Application;

use Src\Company\Domain\Company;
use Src\Company\Domain\CompanyRole;

final readonly class CurrentCompany
{
    /**
     * @param  list<string>  $permissions  the effective permissions: every one for a company admin
     * @param  bool  $takesPartInApprovals  whether the member asked for an approval, holds or held a place in one, or has one waiting: the approval pages are theirs even without an approval permission
     * @param  array{id: int, name: string|null, permissions: list<string>}|null  $group  the group role of the member: the group, and the group permissions the role holds; null without a role or in a suspended group
     */
    public function __construct(
        public Company $company,
        public CompanyRole $role,
        public array $permissions,
        public bool $isOwner,
        public ?array $group = null,
        public bool $takesPartInApprovals = false,
    ) {}
}
