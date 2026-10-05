<?php

namespace Src\Company\Application;

use Src\Company\Domain\Company;
use Src\Company\Domain\CompanyRole;

final readonly class CurrentCompany
{
    public function __construct(
        public Company $company,
        public CompanyRole $role,
    ) {}
}
