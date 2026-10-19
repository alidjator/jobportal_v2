<?php

namespace Src\Company\Application\Admin;

use Src\Company\Application\Locations\CompanyLocationViews;
use Src\Company\Domain\CompanyLocationRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** One company with its counts and work locations, for admins holding `companies.manage`. */
final readonly class GetAdminCompany
{
    public function __construct(
        private CompanyRepository $companies,
        private AdminCompanyReader $reader,
        private CompanyLocationRepository $locations,
        private CompanyLocationViews $views,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the company does not exist
     */
    public function execute(Actor $actor, int $companyId): AdminCompanyDetail
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can read a company.');
        }

        $company = $this->companies->findById($companyId) ?? throw EntityNotFound::of('Company', $companyId);

        return new AdminCompanyDetail($company, $this->reader->counts($companyId, $this->clock->now()), $this->views->many($this->locations->forCompany($companyId)));
    }
}
