<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Approval\Approvals;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Workforce\Application\WorkforceApprovalSubjects;
use Src\Workforce\Domain\AmendmentStatus;
use Src\Workforce\Domain\Requisition;
use Src\Workforce\Domain\RequisitionRepository;
use Src\Workforce\Domain\RequisitionStatus;

/** Closes an approved or in-recruitment requisition by hand, with a reason; the hires not filled stop using the quota of the plan. */
final readonly class CloseRequisition
{
    public function __construct(
        private RequisitionAccess $access,
        private RequisitionRepository $requisitions,
        private Approvals $approvals,
        private RequisitionViews $views,
        private RequisitionChangeRecorder $recorder,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     * @throws StateConflict `requisition-not-open`
     */
    public function execute(Actor $actor, int $id, string $reason, ?string $ipAddress, ?string $userAgent): RequisitionView
    {
        $scope = $this->access->create($actor);

        $requisition = $this->transactions->run(function () use ($actor, $scope, $id, $reason): Requisition {
            $requisition = $this->requisitions->find($id, true);

            if ($requisition === null || ! $scope->covers($requisition->legalEntityId)) {
                throw EntityNotFound::of('Requisition', $id);
            }

            if (! $requisition->status->isOpen()) {
                throw new StateConflict('Only an approved requisition or one in recruitment can be closed.', 'requisition-not-open');
            }

            $amendment = $this->requisitions->pendingAmendment($id);

            if ($amendment !== null) {
                $this->approvals->cancel(WorkforceApprovalSubjects::REQUISITION_AMENDMENT, (int) $amendment->id, (int) $actor->id);
                $this->requisitions->saveAmendment($amendment->with(['status' => AmendmentStatus::Withdrawn, 'decidedAt' => $this->clock->now()]));
            }

            $closed = $requisition->with(['status' => RequisitionStatus::Closed, 'closedReason' => $reason, 'closedAt' => $this->clock->now()]);
            $this->requisitions->save($closed);

            return $closed;
        });

        $this->recorder->record($requisition, 'closed', $actor->id, $ipAddress, $userAgent, ['reason' => $reason]);

        return $this->views->detail($requisition, $scope);
    }
}
