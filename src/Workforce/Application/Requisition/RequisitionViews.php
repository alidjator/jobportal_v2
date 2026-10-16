<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Application\Clock;
use Src\Shared\Application\Job\RequisitionJobs;
use Src\Shared\Application\Organization\PositionDirectory;
use Src\Workforce\Domain\Requisition;
use Src\Workforce\Domain\RequisitionRepository;
use Src\Workforce\Domain\RequisitionType;

/** Builds the views of requisitions: positions are looked up once per legal entity; the detail adds amendments, jobs and quota. */
final readonly class RequisitionViews
{
    public function __construct(
        private PositionDirectory $positions,
        private RequisitionRepository $requisitions,
        private RequisitionJobs $jobs,
        private PlanQuota $quota,
        private Clock $clock,
    ) {}

    /**
     * @param  list<Requisition>  $requisitions
     * @return list<RequisitionView>
     */
    public function of(array $requisitions, RequisitionScope $scope): array
    {
        $positions = [];

        foreach (array_unique(array_map(static fn (Requisition $requisition): int => $requisition->legalEntityId, $requisitions)) as $entityId) {
            $positions[$entityId] = $this->positions->ofEntity($entityId, $this->clock->now());
        }

        return array_map(static fn (Requisition $requisition): RequisitionView => new RequisitionView(
            $requisition, $positions[$requisition->legalEntityId][$requisition->positionId] ?? null, $scope->canSeeCosts,
        ), $requisitions);
    }

    public function detail(Requisition $requisition, RequisitionScope $scope): RequisitionView
    {
        $id = (int) $requisition->id;
        $quota = $requisition->type === RequisitionType::Planned ? $this->quota->of($requisition->legalEntityId, $requisition->positionId, $requisition->fiscalYear, $id) : null;

        return new RequisitionView(
            $requisition, $this->positions->ofEntity($requisition->legalEntityId, $this->clock->now())[$requisition->positionId] ?? null, $scope->canSeeCosts,
            $this->requisitions->amendmentsOf($id), $this->jobs->count($id), $this->jobs->openVacancies($id), $quota,
        );
    }
}
