<?php

namespace Src\Workforce\Application\Plan;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Workforce\Domain\PlanRepository;
use Src\Workforce\Domain\PlanStatus;

/** The plans the actor may read, newest fiscal year and version first; a group member may narrow the list to one legal entity. */
final readonly class ListPlans
{
    public function __construct(private PlanAccess $access, private PlanRepository $plans, private PlanViews $views) {}

    /**
     * @return list<PlanView>
     *
     * @throws AccessDenied
     */
    public function execute(Actor $actor, PlanSide $side, ?int $legalEntityId, ?int $fiscalYear, ?PlanStatus $status): array
    {
        $scope = $this->access->read($actor, $side);
        $entityIds = $legalEntityId === null ? $scope->legalEntityIds : array_values(array_filter($scope->legalEntityIds, static fn (int $id): bool => $id === $legalEntityId));

        return $this->views->of($this->plans->ofEntities($entityIds, $fiscalYear, $status), $scope);
    }
}
