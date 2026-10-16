<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Approval\Approvals;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Job\RequisitionJobs;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Workforce\Application\WorkforceApprovalSubjects;
use Src\Workforce\Domain\AmendmentStatus;
use Src\Workforce\Domain\Requisition;
use Src\Workforce\Domain\RequisitionRepository;
use Src\Workforce\Domain\RequisitionStatus;

/** Cancels a requisition while no job exists for it; a running approval is ended and the quota it used comes back. */
final readonly class CancelRequisition
{
    public function __construct(
        private RequisitionAccess $access,
        private RequisitionRepository $requisitions,
        private RequisitionJobs $jobs,
        private Approvals $approvals,
        private RequisitionViews $views,
        private RequisitionChangeRecorder $recorder,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     * @throws StateConflict `requisition-not-cancellable` or `requisition-has-jobs`
     */
    public function execute(Actor $actor, int $id, ?string $ipAddress, ?string $userAgent): RequisitionView
    {
        $scope = $this->access->create($actor);

        $requisition = $this->transactions->run(function () use ($actor, $scope, $id): Requisition {
            $requisition = $this->requisitions->find($id, true);

            if ($requisition === null || ! $scope->covers($requisition->legalEntityId)) {
                throw EntityNotFound::of('Requisition', $id);
            }

            if (! in_array($requisition->status, [RequisitionStatus::Draft, RequisitionStatus::Rejected, RequisitionStatus::PendingApproval, RequisitionStatus::Approved, RequisitionStatus::InRecruitment], true)) {
                throw new StateConflict('This requisition can no longer be cancelled.', 'requisition-not-cancellable');
            }

            if ($this->jobs->count($id) > 0) {
                throw new StateConflict('A requisition with jobs is closed, not cancelled.', 'requisition-has-jobs');
            }

            if ($requisition->status === RequisitionStatus::PendingApproval) {
                $this->approvals->cancel(WorkforceApprovalSubjects::REQUISITION, $id, (int) $actor->id);
            }

            $this->withdrawPendingAmendment($requisition, (int) $actor->id);

            $cancelled = $requisition->with(['status' => RequisitionStatus::Cancelled, 'closedAt' => $this->clock->now()]);
            $this->requisitions->save($cancelled);

            return $cancelled;
        });

        $this->recorder->record($requisition, 'cancelled', $actor->id, $ipAddress, $userAgent);

        return $this->views->detail($requisition, $scope);
    }

    private function withdrawPendingAmendment(Requisition $requisition, int $actorId): void
    {
        $amendment = $this->requisitions->pendingAmendment((int) $requisition->id);

        if ($amendment !== null) {
            $this->approvals->cancel(WorkforceApprovalSubjects::REQUISITION_AMENDMENT, (int) $amendment->id, $actorId);
            $this->requisitions->saveAmendment($amendment->with(['status' => AmendmentStatus::Withdrawn, 'decidedAt' => $this->clock->now()]));
        }
    }
}
