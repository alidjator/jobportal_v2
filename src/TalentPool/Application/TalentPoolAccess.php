<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The check every talent pool action starts with: a member of a company that is not suspended who holds `talent_pool.manage`, categories included. */
final readonly class TalentPoolAccess
{
    public function __construct(private CompanyDirectory $companies) {}

    /** @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended` */
    public function member(Actor $actor): PoolMember
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null || $actor->id === null) {
            throw new AccessDenied('Only a company member can do this.', 'not-a-company-member');
        }

        $actor->membership->require(CompanyPermission::TalentPoolManage);

        if (! $this->companies->isActive($actor->membership->companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        return new PoolMember($actor->id, $actor->membership->companyId);
    }
}
