<?php

namespace Src\Company\Application\Team;

use RuntimeException;
use Src\Company\Domain\Company;
use Src\Company\Domain\CompanyRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The checks every team action starts with: the actor is a company admin of an active company. */
final readonly class CompanyAdminGuard
{
    public function __construct(private CompanyRepository $companies) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     */
    public function company(Actor $actor): Company
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null) {
            throw new AccessDenied('Only a company member can manage a team.', 'not-a-company-member');
        }

        if (! $actor->isCompanyAdmin()) {
            throw new AccessDenied('Only a company admin can manage the team.', 'company-admin-required');
        }

        $company = $this->companies->findById($actor->membership->companyId)
            ?? throw new RuntimeException("Company {$actor->membership->companyId} of a member does not exist.");

        if (! $company->isActive()) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        return $company;
    }
}
