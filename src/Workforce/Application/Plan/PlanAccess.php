<?php

namespace Src\Workforce\Application\Plan;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Application\Organization\GroupPermissions;
use Src\Shared\Application\Organization\LegalEntityDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * Who may read or change manpower plans (`docs/design/erp-requisitions.md` section 2). A company member reaches the plans of their own internal tenant: `workforce.plans.view`
 * or `workforce.plans.manage` reads, `workforce.plans.manage` changes, `workforce.costs.view` shows the estimated costs. A group member reaches the plans of every legal entity
 * of the group: `group.workforce.view` or `group.workforce.manage` reads, `group.workforce.manage` changes; they see the costs.
 */
final readonly class PlanAccess
{
    public function __construct(private TenantRegistry $tenants, private CompanyDirectory $companies, private GroupPermissions $groups, private LegalEntityDirectory $entities) {}

    /** @throws AccessDenied */
    public function read(Actor $actor, PlanSide $side): PlanScope
    {
        return $side === PlanSide::Company ? $this->company($actor, false) : $this->group($actor, false);
    }

    /** @throws AccessDenied */
    public function manage(Actor $actor, PlanSide $side): PlanScope
    {
        return $side === PlanSide::Company ? $this->company($actor, true) : $this->group($actor, true);
    }

    private function company(Actor $actor, bool $manage): PlanScope
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null || $actor->id === null) {
            throw new AccessDenied('Only a company member can do this.', 'not-a-company-member');
        }

        $membership = $actor->membership;

        if (! $this->companies->isActive($membership->companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        if ($manage) {
            $membership->require(CompanyPermission::WorkforcePlansManage);
        } elseif (! $membership->can(CompanyPermission::WorkforcePlansView) && ! $membership->can(CompanyPermission::WorkforcePlansManage)) {
            $membership->require(CompanyPermission::WorkforcePlansView);
        }

        $legalEntityId = $this->tenants->legalEntityOf($membership->companyId) ?? throw new AccessDenied('Manpower plans belong to the tenants of an ERP group.', 'not-an-internal-tenant');
        $groupId = $this->entities->groupOf($legalEntityId) ?? throw new AccessDenied('Manpower plans belong to the tenants of an ERP group.', 'not-an-internal-tenant');

        return new PlanScope(PlanSide::Company, $groupId, [$legalEntityId], $membership->can(CompanyPermission::WorkforcePlansManage), $membership->can(CompanyPermission::WorkforceCostsView), $membership->companyId);
    }

    private function group(Actor $actor, bool $manage): PlanScope
    {
        $groupId = $this->groups->groupOf($actor);
        $held = $this->groups->heldBy($actor)['permissions'] ?? [];
        $canManage = in_array(GroupPermission::WorkforceManage->value, $held, true);
        $needed = $manage ? GroupPermission::WorkforceManage : GroupPermission::WorkforceView;

        if (! $canManage && ($manage || ! in_array($needed->value, $held, true))) {
            throw new AccessDenied("This action needs the group permission {$needed->value}.", 'group-permission-required', extensions: ['permission' => $needed->value]);
        }

        return new PlanScope(PlanSide::Group, $groupId, array_keys($this->entities->namesOfGroup($groupId)), $canManage, true);
    }
}
