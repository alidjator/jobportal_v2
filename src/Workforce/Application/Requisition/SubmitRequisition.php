<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Approval\Approvals;
use Src\Shared\Application\Approval\ApprovalSubmission;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Workforce\Application\WorkforceApprovalSubjects;
use Src\Workforce\Domain\Requisition;
use Src\Workforce\Domain\RequisitionRepository;
use Src\Workforce\Domain\RequisitionStatus;

/**
 * Sends a draft or rejected requisition through the approval flow of its tenant (or group). What is stored is checked again first (the position may have been deactivated, the target
 * date may have passed), a `planned` one must fit the quota of its plan line, and from now on it uses that quota. The hiring manager is the relative approver `hiring_manager`.
 */
final readonly class SubmitRequisition
{
    public function __construct(
        private RequisitionAccess $access,
        private RequisitionRules $rules,
        private QuotaGuard $quota,
        private RequisitionRepository $requisitions,
        private RequisitionViews $views,
        private RequisitionChangeRecorder $recorder,
        private Approvals $approvals,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     * @throws StateConflict `requisition-not-editable`, `approval-flow-missing` or `approval-already-pending`
     * @throws BusinessRuleViolation `invalid-requisition-field`, `requisition-no-plan-line`, `requisition-quota-exceeded`, `approval-not-internal-tenant` or `approval-requester-only-approver`
     */
    public function execute(Actor $actor, int $id, ?string $ipAddress, ?string $userAgent): RequisitionView
    {
        $scope = $this->access->create($actor);

        $requisition = $this->transactions->run(function () use ($actor, $scope, $id): Requisition {
            $requisition = $this->requisitions->find($id, true);

            if ($requisition === null || ! $scope->covers($requisition->legalEntityId)) {
                throw EntityNotFound::of('Requisition', $id);
            }

            if (! $requisition->status->isEditable()) {
                throw new StateConflict('Only a draft or a rejected requisition can be submitted.', 'requisition-not-editable');
            }

            $current = $requisition->with($this->rules->fields($requisition->companyId, $requisition->legalEntityId, RequisitionInput::of($requisition)));
            $this->quota->assertAvailable($current, $current->hires);

            $approvalId = $this->approvals->submit(new ApprovalSubmission(
                WorkforceApprovalSubjects::REQUISITION, $id, $current->companyId, (int) $actor->id, $this->title($current),
                WorkforceApprovalSubjects::requisitionFacts($current, $this->rules->rankOf($current->requestedGradeId)), ['hiring_manager' => [$current->hiringManagerUserId]],
            ));

            $submitted = $current->with([
                'status' => RequisitionStatus::PendingApproval, 'approvalId' => $approvalId, 'submittedAt' => $this->clock->now(), 'decidedAt' => null, 'decisionReason' => null,
            ]);
            $this->requisitions->save($submitted);

            return $submitted;
        });

        $this->recorder->record($requisition, 'submitted', $actor->id, $ipAddress, $userAgent);

        return $this->views->detail($requisition, $scope);
    }

    private function title(Requisition $requisition): string
    {
        return sprintf('Requisition #%d: %d hire(s), %s', $requisition->id, $requisition->hires, $requisition->type->value);
    }
}
