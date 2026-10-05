<?php

namespace Src\Company\Application;

use Src\Company\Domain\Company;
use Src\Company\Domain\Invitation;

final readonly class CreatedCompany
{
    public function __construct(
        public Company $company,
        public Invitation $invitation,
    ) {}
}
