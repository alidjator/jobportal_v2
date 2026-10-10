<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The check every talent pool action starts with: a member of a company that is not suspended. Renaming or deleting a category also needs a company admin. */
final readonly class TalentPoolAccess
{
    public function __construct(private CompanyDirectory $companies) {}

    /** @throws AccessDenied `not-a-company-member` or `company-suspended` */
    public function member(Actor $actor): PoolMember
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null || $actor->id === null) {
            throw new AccessDenied('Only a company member can do this.', 'not-a-company-member');
        }

        if (! $this->companies->isActive($actor->membership->companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        return new PoolMember($actor->id, $actor->membership->companyId, $actor->isCompanyAdmin());
    }

    /** @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-admin-required` */
    public function admin(Actor $actor): PoolMember
    {
        $member = $this->member($actor);

        if (! $member->isCompanyAdmin) {
            throw new AccessDenied('Only a company admin can do this.', 'company-admin-required');
        }

        return $member;
    }
}
