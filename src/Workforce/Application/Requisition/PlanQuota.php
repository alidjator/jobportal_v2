<?php

namespace Src\Workforce\Application\Requisition;

use Src\Workforce\Domain\PlanRepository;
use Src\Workforce\Domain\PlanStatus;
use Src\Workforce\Domain\RequisitionRepository;

/**
 * The quota of a position in a fiscal year: the additions of its line in the plan in force, less the hires the `planned` requisitions already use. The monthly split is a target,
 * never a limit.
 */
final readonly class PlanQuota
{
    public function __construct(private PlanRepository $plans, private RequisitionRepository $requisitions) {}

    /**
     * @return array{additions: int, used: int, remaining: int}|null null when the plan in force has no line for the position (or there is no plan in force)
     */
    public function of(int $legalEntityId, int $positionId, int $fiscalYear, ?int $exceptRequisitionId = null): ?array
    {
        foreach ($this->plans->versionsOf($legalEntityId, $fiscalYear) as $plan) {
            if ($plan->status !== PlanStatus::Approved) {
                continue;
            }

            foreach ($plan->lines as $line) {
                if ($line->positionId === $positionId) {
                    $used = $this->requisitions->usedHires($legalEntityId, $positionId, $fiscalYear, $exceptRequisitionId);

                    return ['additions' => $line->additions(), 'used' => $used, 'remaining' => max($line->additions() - $used, 0)];
                }
            }
        }

        return null;
    }
}
