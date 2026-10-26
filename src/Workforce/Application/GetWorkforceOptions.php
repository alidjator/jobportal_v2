<?php

namespace Src\Workforce\Application;

use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Application\Organization\LegalEntityDirectory;
use Src\Shared\Application\Organization\PositionDirectory;
use Src\Shared\Application\Organization\PositionSummary;
use Src\Shared\Application\Organization\StructureDirectory;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The choices of the forms of manpower plans and requisitions for a member of an internal tenant (`docs/design/erp-requisitions.md` sections 2 and 3): the structure of the tenant's own
 * legal entity as it is today, read only. A company member holds no group role, so `organization/*` is closed to them; this gives them just what a form lists. Needs
 * `workforce.plans.manage` or `workforce.requisitions.create`; an external tenant gets 403 `not-an-internal-tenant`.
 */
final readonly class GetWorkforceOptions
{
    public function __construct(
        private TenantRegistry $tenants,
        private CompanyDirectory $companies,
        private LegalEntityDirectory $entities,
        private PositionDirectory $positions,
        private StructureDirectory $structure,
        private CompanyTeam $team,
        private CompanyMemberAccounts $accounts,
        private Clock $clock,
    ) {}

    /** @throws AccessDenied */
    public function execute(Actor $actor): WorkforceOptions
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null) {
            throw new AccessDenied('Only a company member can do this.', 'not-a-company-member');
        }

        $membership = $actor->membership;

        if (! $this->companies->isActive($membership->companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        if (! $membership->can(CompanyPermission::WorkforcePlansManage) && ! $membership->can(CompanyPermission::WorkforceRequisitionsCreate)) {
            $membership->require(CompanyPermission::WorkforceRequisitionsCreate);
        }

        $legalEntityId = $this->tenants->legalEntityOf($membership->companyId) ?? throw new AccessDenied('Manpower plans belong to the tenants of an ERP group.', 'not-an-internal-tenant');
        $groupId = $this->entities->groupOf($legalEntityId) ?? throw new AccessDenied('Manpower plans belong to the tenants of an ERP group.', 'not-an-internal-tenant');
        $today = $this->clock->now();

        $positions = array_values(array_filter($this->positions->ofEntity($legalEntityId, $today), static fn (PositionSummary $position): bool => $position->active));
        $summaries = $this->accounts->summaries(array_keys($this->team->roles($membership->companyId)));
        $managers = [];

        foreach ($summaries as $summary) {
            if ($summary->status === 'active') {
                $managers[] = ['id' => $summary->userId, 'name' => $summary->name];
            }
        }

        usort($managers, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return new WorkforceOptions(
            $legalEntityId,
            $positions,
            $this->structure->unitOptions($legalEntityId, $today),
            $this->structure->gradeOptions($groupId),
            $this->structure->costCentreOptions($legalEntityId),
            $managers,
        );
    }
}
