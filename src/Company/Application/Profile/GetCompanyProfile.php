<?php

namespace Src\Company\Application\Profile;

use Src\Company\Domain\Company;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The signed-in member's own company with its whole profile, as stored (ids for the classifications and the location, Markdown as written). */
final readonly class GetCompanyProfile
{
    public function __construct(private CompanyMemberGuard $guard) {}

    /** @throws AccessDenied `not-a-company-member` */
    public function execute(Actor $actor): Company
    {
        return $this->guard->company($actor);
    }
}
