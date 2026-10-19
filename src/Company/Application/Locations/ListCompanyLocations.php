<?php

namespace Src\Company\Application\Locations;

use Src\Company\Application\Profile\CompanyMemberGuard;
use Src\Company\Domain\CompanyLocationRepository;
use Src\Company\Domain\LocationStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The work locations of the member's own company, active ones first. Any member reads them: the interview and requisition pickers use the list. */
final readonly class ListCompanyLocations
{
    public function __construct(
        private CompanyMemberGuard $guard,
        private CompanyLocationRepository $locations,
        private CompanyLocationViews $views,
    ) {}

    /**
     * @return list<CompanyLocationView>
     *
     * @throws AccessDenied `not-a-company-member`
     */
    public function execute(Actor $actor, ?LocationStatus $status = null): array
    {
        $company = $this->guard->company($actor);

        return $this->views->many($this->locations->forCompany((int) $company->id, $status));
    }
}
