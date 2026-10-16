<?php

namespace Src\Workforce\Application\Requisition;

use Src\Workforce\Application\Plan\PlanSide;

/**
 * What an actor may reach among the requisitions: a company member the requisitions of their own tenant, a group member those of every legal entity of the group (read only).
 * `$companyId` is the tenant of a company member.
 */
final readonly class RequisitionScope
{
    /** @param  list<int>  $legalEntityIds */
    public function __construct(
        public PlanSide $side,
        public int $groupId,
        public array $legalEntityIds,
        public bool $canCreate,
        public bool $canSeeCosts,
        public ?int $companyId = null,
    ) {}

    public function covers(int $legalEntityId): bool
    {
        return in_array($legalEntityId, $this->legalEntityIds, true);
    }
}
