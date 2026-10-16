<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Workforce\Application\Plan\PlanSide;
use Src\Workforce\Domain\RequisitionRepository;
use Src\Workforce\Domain\RequisitionStatus;
use Src\Workforce\Domain\RequisitionType;

/** The requisitions the actor may read, newest first; a group member may narrow the list to one legal entity. */
final readonly class ListRequisitions
{
    public function __construct(private RequisitionAccess $access, private RequisitionRepository $requisitions, private RequisitionViews $views) {}

    /**
     * @return list<RequisitionView>
     *
     * @throws AccessDenied
     */
    public function execute(Actor $actor, PlanSide $side, ?int $legalEntityId, ?RequisitionStatus $status, ?RequisitionType $type, ?int $positionId): array
    {
        $scope = $this->access->read($actor, $side);
        $entityIds = $legalEntityId === null ? $scope->legalEntityIds : array_values(array_filter($scope->legalEntityIds, static fn (int $entity): bool => $entity === $legalEntityId));

        return $this->views->of($this->requisitions->search($scope->companyId, $entityIds, $status, $type, $positionId), $scope);
    }
}
