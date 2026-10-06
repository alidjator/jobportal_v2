<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The checks every stage action starts with: the caller is a member of an active company, and for changes a company admin. */
final readonly class StageAccess
{
    public function __construct(private CompanyDirectory $companies) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     */
    public function member(Actor $actor): StageMember
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null || $actor->id === null) {
            throw new AccessDenied('Only a company member can see the stages.', 'not-a-company-member');
        }

        if (! $this->companies->isActive($actor->membership->companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        return new StageMember($actor->id, $actor->membership->companyId, $actor->isCompanyAdmin());
    }

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-admin-required`
     */
    public function companyAdmin(Actor $actor): StageMember
    {
        $member = $this->member($actor);

        if (! $member->isCompanyAdmin) {
            throw new AccessDenied('Only a company admin can change the stages.', 'company-admin-required');
        }

        return $member;
    }
}
