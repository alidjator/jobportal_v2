<?php

namespace Src\Company\Application\Team;

use RuntimeException;
use Src\Company\Domain\Company;
use Src\Company\Domain\CompanyRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The checks every action on the own company starts with: the actor is a member of an active company and either a company admin (team
 * management, `company()`) or holds a permission (`companyWith()`).
 */
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

        return $this->activeCompany($actor->membership->companyId);
    }

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     */
    public function companyWith(Actor $actor, CompanyPermission $permission): Company
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null) {
            throw new AccessDenied('Only a company member can do this.', 'not-a-company-member');
        }

        $actor->membership->require($permission);

        return $this->activeCompany($actor->membership->companyId);
    }

    private function activeCompany(int $companyId): Company
    {
        $company = $this->companies->findById($companyId)
            ?? throw new RuntimeException("Company {$companyId} of a member does not exist.");

        if (! $company->isActive()) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        return $company;
    }
}
