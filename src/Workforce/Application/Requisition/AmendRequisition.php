<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Approval\Approvals;
use Src\Shared\Application\Approval\ApprovalSubmission;
use Src\Shared\Application\Job\RequisitionJobs;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Workforce\Application\WorkforceApprovalSubjects;
use Src\Workforce\Domain\AmendmentStatus;
use Src\Workforce\Domain\RequisitionAmendment;
use Src\Workforce\Domain\RequisitionRepository;

/**
 * Changes the hires or the salary range of an open requisition. Raising the hires or the highest salary is an amendment that goes through approval again (the new values apply when it
 * is approved, one at a time); anything else only lowers what was approved and applies at once, with a reason, returning the quota. The hires never drop below the vacancies of
 * the jobs already opened.
 */
final readonly class AmendRequisition
{
    public function __construct(
        private RequisitionAccess $access,
        private RequisitionRules $rules,
        private QuotaGuard $quota,
        private RequisitionRepository $requisitions,
        private RequisitionJobs $jobs,
        private Approvals $approvals,
        private RequisitionViews $views,
        private RequisitionChangeRecorder $recorder,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     * @throws StateConflict `requisition-not-open`, `amendment-pending`, `approval-flow-missing` or `approval-already-pending`
     * @throws BusinessRuleViolation `requisition-no-change`, `invalid-requisition-field`, `requisition-hires-below-jobs`, `requisition-no-plan-line` or `requisition-quota-exceeded`
     */
    public function execute(Actor $actor, int $id, AmendmentInput $input, ?string $ipAddress, ?string $userAgent): RequisitionView
    {
        $scope = $this->access->create($actor);

        [$requisition, $change, $detail] = $this->transactions->run(function () use ($actor, $scope, $id, $input): array {
            $requisition = $this->requisitions->find($id, true);

            if ($requisition === null || ! $scope->covers($requisition->legalEntityId)) {
                throw EntityNotFound::of('Requisition', $id);
            }

            if (! $requisition->status->isOpen()) {
                throw new StateConflict('Only an approved requisition or one in recruitment can be amended.', 'requisition-not-open');
            }

            if ($this->requisitions->pendingAmendment($id) !== null) {
                throw new StateConflict('An amendment of this requisition is waiting for a decision.', 'amendment-pending');
            }

            $hires = $input->hires ?? $requisition->hires;
            $min = $input->salaryMin ?? $requisition->salaryMin;
            $max = $input->salaryMax ?? $requisition->salaryMax;

            if ($hires === $requisition->hires && $min === $requisition->salaryMin && $max === $requisition->salaryMax) {
                throw new BusinessRuleViolation('Nothing changes: name other hires or another salary range.', 'requisition-no-change');
            }

            if ($min !== null && $max !== null && $min > $max) {
                throw new BusinessRuleViolation('The minimum salary is above the maximum.', 'invalid-requisition-field');
            }

            if ($hires < $this->jobs->openVacancies($id)) {
                throw new BusinessRuleViolation('The hires cannot drop below the vacancies of the jobs already opened.', 'requisition-hires-below-jobs');
            }

            $raises = $hires > $requisition->hires || ($max !== null && ($requisition->salaryMax === null || $max > $requisition->salaryMax));

            if (! $raises) {
                $reduced = $requisition->with(['hires' => $hires, 'salaryMin' => $min, 'salaryMax' => $max]);
                $this->requisitions->save($reduced);

                return [$reduced, 'reduced', ['reason' => $input->reason, 'hires' => [$requisition->hires, $hires]]];
            }

            $this->quota->assertAvailable($requisition, $hires);

            $amendment = $this->requisitions->createAmendment(new RequisitionAmendment(null, $id, $input->hires, $input->salaryMin, $input->salaryMax, $input->reason, AmendmentStatus::PendingApproval, (int) $actor->id));
            $approvalId = $this->approvals->submit(new ApprovalSubmission(
                WorkforceApprovalSubjects::REQUISITION_AMENDMENT, (int) $amendment->id, $requisition->companyId, (int) $actor->id,
                sprintf('Amendment of requisition #%d: %d hire(s)', $id, $hires), WorkforceApprovalSubjects::requisitionFacts($requisition, $this->rules->rankOf($requisition->requestedGradeId), $hires, $max),
                ['hiring_manager' => [$requisition->hiringManagerUserId]],
            ));
            $this->requisitions->saveAmendment($amendment->with(['approvalId' => $approvalId]));

            return [$requisition, 'amendment_requested', ['amendment_id' => $amendment->id, 'reason' => $input->reason]];
        });

        $this->recorder->record($requisition, $change, $actor->id, $ipAddress, $userAgent, $detail);

        return $this->views->detail($requisition, $scope);
    }
}
