<?php

namespace Src\Company\Application\Profile;

use RuntimeException;
use Src\Company\Domain\Company;
use Src\Company\Domain\CompanyRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The check for reading one's own company: the actor is a member, of any role. A suspended company can still be read, so the client can explain why features are off. */
final readonly class CompanyMemberGuard
{
    public function __construct(private CompanyRepository $companies) {}

    /** @throws AccessDenied `not-a-company-member` */
    public function company(Actor $actor): Company
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null) {
            throw new AccessDenied('Only a company member can read their company.', 'not-a-company-member');
        }

        return $this->companies->findById($actor->membership->companyId)
            ?? throw new RuntimeException("Company {$actor->membership->companyId} of a member does not exist.");
    }
}
