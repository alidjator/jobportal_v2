<?php

namespace Src\Company\Application\Profile;

use Src\Company\Application\Locations\CompanyLocationViews;
use Src\Company\Domain\CompanyLocationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The signed-in member's own company with its whole profile, as stored (ids for the classifications, Markdown as written), and its main location in place of the address. */
final readonly class GetCompanyProfile
{
    public function __construct(
        private CompanyMemberGuard $guard,
        private CompanyLocationRepository $locations,
        private CompanyLocationViews $views,
    ) {}

    /** @throws AccessDenied `not-a-company-member` */
    public function execute(Actor $actor): CompanyProfileView
    {
        $company = $this->guard->company($actor);
        $main = $this->locations->main((int) $company->id);

        return new CompanyProfileView($company, $main === null ? null : $this->views->one($main));
    }
}
