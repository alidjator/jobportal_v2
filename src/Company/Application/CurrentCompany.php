<?php

namespace Src\Company\Application;

use Src\Company\Domain\Company;
use Src\Company\Domain\CompanyRole;

final readonly class CurrentCompany
{
    /** @param  list<string>  $permissions  the effective permissions: every one for a company admin */
    public function __construct(
        public Company $company,
        public CompanyRole $role,
        public array $permissions,
        public bool $isOwner,
    ) {}
}
