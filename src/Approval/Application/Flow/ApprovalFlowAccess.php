<?php

namespace Src\Approval\Application\Flow;

use Src\Approval\Domain\FlowOwner;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Organization\GroupDirectory;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Application\Organization\GroupPermissions;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * Who may read or change approval flows. The flows of a group: any group member reads them, `group.approvals.manage` changes them. The flows of a tenant: any member of an
 * internal tenant reads them, `approvals.manage` changes them. A tenant that is not internal has no approvals (they belong to the ERP).
 */
final readonly class ApprovalFlowAccess
{
    public function __construct(private GroupPermissions $groups, private GroupDirectory $directory, private CompanyDirectory $companies) {}

    /** @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required` */
    public function group(Actor $actor, bool $manage): FlowOwner
    {
        return FlowOwner::group($this->groups->groupOf($actor, $manage ? GroupPermission::ApprovalsManage : null));
    }

    /**
     * @return array{FlowOwner, int} the tenant's owner and the id of its group
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `not-an-internal-tenant`
     */
    public function company(Actor $actor, bool $manage): array
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null || $actor->id === null) {
            throw new AccessDenied('Only a company member can do this.', 'not-a-company-member');
        }

        $companyId = $actor->membership->companyId;

        if (! $this->companies->isActive($companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        if ($manage) {
            $actor->membership->require(CompanyPermission::ApprovalsManage);
        }

        $groupId = $this->directory->groupOfCompany($companyId) ?? throw new AccessDenied('Approvals belong to the tenants of an ERP group.', 'not-an-internal-tenant');

        return [FlowOwner::company($companyId), $groupId];
    }
}
