<?php

namespace Src\Workforce\Application\Plan;

/**
 * What an actor may reach among the plans: the legal entities (one for a tenant, every one of the group for a group member), whether they may change them and whether they
 * see the estimated costs. `$companyId` is the tenant of a company member.
 */
final readonly class PlanScope
{
    /** @param  list<int>  $legalEntityIds */
    public function __construct(
        public PlanSide $side,
        public int $groupId,
        public array $legalEntityIds,
        public bool $canManage,
        public bool $canSeeCosts,
        public ?int $companyId = null,
    ) {}

    public function covers(int $legalEntityId): bool
    {
        return in_array($legalEntityId, $this->legalEntityIds, true);
    }
}
