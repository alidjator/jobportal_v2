<?php

namespace Src\Workforce\Application\Plan;

use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Workforce\Domain\Plan;
use Src\Workforce\Domain\PlanRepository;
use Src\Workforce\Domain\PlanStatus;

/**
 * Moves a plan on when its approval ends: approved (the version in force before it is superseded), rejected (editable, can be submitted again) or returned for revision
 * (back to a draft). An outcome for an approval the plan no longer waits for is ignored.
 */
final readonly class ApplyPlanOutcome
{
    public function __construct(private PlanRepository $plans, private PlanChangeRecorder $recorder, private Clock $clock, private TransactionManager $transactions) {}

    public function granted(int $planId, int $approvalId, ?int $decidedBy): void
    {
        $changed = $this->transactions->run(function () use ($planId, $approvalId): array {
            $plan = $this->waiting($planId, $approvalId);

            if ($plan === null) {
                return [];
            }

            $superseded = [];

            foreach ($this->plans->versionsOf($plan->legalEntityId, $plan->fiscalYear) as $version) {
                if ($version->status === PlanStatus::Approved) {
                    $this->plans->save($version->with(['status' => PlanStatus::Superseded]));
                    $superseded[] = $version;
                }
            }

            $approved = $plan->with(['status' => PlanStatus::Approved, 'decidedAt' => $this->clock->now()]);
            $this->plans->save($approved);

            return [$approved, $superseded];
        });

        if ($changed === []) {
            return;
        }

        [$approved, $superseded] = $changed;

        foreach ($superseded as $version) {
            $this->recorder->record($version, 'superseded', $decidedBy);
        }

        $this->recorder->record($approved, 'approved', $decidedBy);
    }

    public function rejected(int $planId, int $approvalId, string $reason, ?int $decidedBy): void
    {
        $this->close($planId, $approvalId, PlanStatus::Rejected, 'rejected', $reason, $decidedBy);
    }

    public function returned(int $planId, int $approvalId, string $reason, ?int $decidedBy): void
    {
        $this->close($planId, $approvalId, PlanStatus::Draft, 'returned', $reason, $decidedBy);
    }

    private function close(int $planId, int $approvalId, PlanStatus $status, string $change, string $reason, ?int $decidedBy): void
    {
        $plan = $this->transactions->run(function () use ($planId, $approvalId, $status, $reason): ?Plan {
            $plan = $this->waiting($planId, $approvalId);

            if ($plan === null) {
                return null;
            }

            $closed = $plan->with(['status' => $status, 'decidedAt' => $this->clock->now(), 'decisionReason' => $reason]);
            $this->plans->save($closed);

            return $closed;
        });

        if ($plan !== null) {
            $this->recorder->record($plan, $change, $decidedBy, reason: $reason);
        }
    }

    private function waiting(int $planId, int $approvalId): ?Plan
    {
        $plan = $this->plans->find($planId, true);

        return $plan !== null && $plan->status === PlanStatus::PendingApproval && $plan->approvalId === $approvalId ? $plan : null;
    }
}
