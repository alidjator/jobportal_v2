<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Workforce\Application\Plan\PlanSide;
use Src\Workforce\Domain\RequisitionRepository;

final readonly class GetRequisition
{
    public function __construct(private RequisitionAccess $access, private RequisitionRepository $requisitions, private RequisitionViews $views) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound the requisition does not exist or belongs to a legal entity the actor cannot reach
     */
    public function execute(Actor $actor, PlanSide $side, int $id): RequisitionView
    {
        $scope = $this->access->read($actor, $side);
        $requisition = $this->requisitions->find($id);

        if ($requisition === null || ! $scope->covers($requisition->legalEntityId)) {
            throw EntityNotFound::of('Requisition', $id);
        }

        return $this->views->detail($requisition, $scope);
    }
}
