<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Application\Organization\GroupPermissions;
use Src\Shared\Application\Organization\LegalEntityDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Workforce\Application\Plan\PlanSide;

/**
 * Who may read or change requisitions (`docs/design/erp-requisitions.md` section 3). A company member of an internal tenant: `workforce.requisitions.view` or `.create` reads,
 * `.create` changes (draft, submit, amend, close, cancel), `workforce.costs.view` shows the salary budget. A group member reads the requisitions of the group with
 * `group.workforce.view` or `.manage`, costs included, and changes none.
 */
final readonly class RequisitionAccess
{
    public function __construct(private TenantRegistry $tenants, private CompanyDirectory $companies, private GroupPermissions $groups, private LegalEntityDirectory $entities) {}

    /** @throws AccessDenied */
    public function read(Actor $actor, PlanSide $side): RequisitionScope
    {
        return $side === PlanSide::Company ? $this->company($actor, false) : $this->group($actor);
    }

    /** @throws AccessDenied */
    public function create(Actor $actor): RequisitionScope
    {
        return $this->company($actor, true);
    }

    private function company(Actor $actor, bool $write): RequisitionScope
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null || $actor->id === null) {
            throw new AccessDenied('Only a company member can do this.', 'not-a-company-member');
        }

        $membership = $actor->membership;

        if (! $this->companies->isActive($membership->companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        if ($write) {
            $membership->require(CompanyPermission::WorkforceRequisitionsCreate);
        } elseif (! $membership->can(CompanyPermission::WorkforceRequisitionsCreate)) {
            $membership->require(CompanyPermission::WorkforceRequisitionsView);
        }

        $entityId = $this->tenants->legalEntityOf($membership->companyId) ?? throw new AccessDenied('Requisitions belong to the tenants of an ERP group.', 'not-an-internal-tenant');
        $groupId = $this->entities->groupOf($entityId) ?? throw new AccessDenied('Requisitions belong to the tenants of an ERP group.', 'not-an-internal-tenant');

        return new RequisitionScope(PlanSide::Company, $groupId, [$entityId], $membership->can(CompanyPermission::WorkforceRequisitionsCreate), $membership->can(CompanyPermission::WorkforceCostsView), $membership->companyId);
    }

    private function group(Actor $actor): RequisitionScope
    {
        $groupId = $this->groups->groupOf($actor);
        $held = $this->groups->heldBy($actor)['permissions'] ?? [];

        if (! in_array(GroupPermission::WorkforceView->value, $held, true) && ! in_array(GroupPermission::WorkforceManage->value, $held, true)) {
            throw new AccessDenied(
                'This action needs the group permission :permission.', 'group-permission-required', extensions: ['permission' => GroupPermission::WorkforceView->value],
                parameters: ['permission' => GroupPermission::WorkforceView->value],
            );
        }

        return new RequisitionScope(PlanSide::Group, $groupId, array_keys($this->entities->namesOfGroup($groupId)), false, true);
    }
}
