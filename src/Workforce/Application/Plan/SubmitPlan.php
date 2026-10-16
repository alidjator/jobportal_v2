<?php

namespace Src\Workforce\Application\Plan;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Approval\Approvals;
use Src\Shared\Application\Approval\ApprovalSubmission;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Workforce\Application\WorkforceApprovalSubjects;
use Src\Workforce\Domain\Plan;
use Src\Workforce\Domain\PlanRepository;
use Src\Workforce\Domain\PlanStatus;

/** Sends a draft or rejected plan through the approval flow of its tenant (or group). The outcome comes back through the approval events (`ApplyPlanOutcome`). */
final readonly class SubmitPlan
{
    public function __construct(
        private PlanAccess $access,
        private PlanRepository $plans,
        private PlanViews $views,
        private PlanChangeRecorder $recorder,
        private Approvals $approvals,
        private TenantRegistry $tenants,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     * @throws StateConflict `plan-not-editable`, `approval-flow-missing` or `approval-already-pending`
     * @throws BusinessRuleViolation `plan-empty`, `plan-entity-without-tenant`, `approval-not-internal-tenant` or `approval-requester-only-approver`
     */
    public function execute(Actor $actor, PlanSide $side, int $planId, ?string $ipAddress, ?string $userAgent): PlanView
    {
        $scope = $this->access->manage($actor, $side);

        $plan = $this->transactions->run(function () use ($actor, $scope, $planId): Plan {
            $plan = $this->plans->find($planId, true);

            if ($plan === null || ! $scope->covers($plan->legalEntityId)) {
                throw EntityNotFound::of('Manpower plan', $planId);
            }

            if (! $plan->status->isEditable()) {
                throw new StateConflict('Only a draft or a rejected plan can be submitted.', 'plan-not-editable');
            }

            if ($plan->lines === []) {
                throw new BusinessRuleViolation('A plan without lines cannot be submitted.', 'plan-empty');
            }

            $tenantId = $this->tenants->tenantsOf([$plan->legalEntityId])[$plan->legalEntityId]
                ?? throw new BusinessRuleViolation('The legal entity has no tenant that could take the plan through approval.', 'plan-entity-without-tenant');

            $approvalId = $this->approvals->submit(new ApprovalSubmission(
                WorkforceApprovalSubjects::PLAN, (int) $plan->id, $tenantId, (int) $actor->id,
                sprintf('Manpower plan %d, version %d', $plan->fiscalYear, $plan->version), WorkforceApprovalSubjects::planFacts($plan),
            ));

            $submitted = $plan->with(['status' => PlanStatus::PendingApproval, 'approvalId' => $approvalId, 'submittedAt' => $this->clock->now(), 'decidedAt' => null, 'decisionReason' => null]);
            $this->plans->save($submitted);

            return $submitted;
        });

        $this->recorder->record($plan, 'submitted', $actor->id, $ipAddress, $userAgent);

        return $this->views->of([$plan], $scope)[0];
    }
}
