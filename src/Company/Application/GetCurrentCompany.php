<?php

namespace Src\Company\Application;

use RuntimeException;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\CompanyRole;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The company of the signed-in company member and their role there. It also works while the company is suspended, so the client can say why features are off. */
final readonly class GetCurrentCompany
{
    public function __construct(private CompanyRepository $companies) {}

    /** @throws AccessDenied `not-a-company-member` when the actor is not a member of a company */
    public function execute(Actor $actor): CurrentCompany
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null) {
            throw new AccessDenied('Only a company member can read their company.', 'not-a-company-member');
        }

        $company = $this->companies->findById($actor->membership->companyId)
            ?? throw new RuntimeException("Company {$actor->membership->companyId} of a member does not exist.");

        return new CurrentCompany($company, CompanyRole::from($actor->membership->role), $actor->membership->effectivePermissions());
    }
}
