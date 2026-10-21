<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Workforce\Domain\Requisition;
use Src\Workforce\Domain\RequisitionType;

/** A `planned` requisition may not use more than its position's line in the plan in force has left. The other types use no quota. */
final readonly class QuotaGuard
{
    public function __construct(private PlanQuota $quota) {}

    /**
     * @param  int  $hires  the hires the requisition would have (its own number, or the new number of an amendment)
     *
     * @throws BusinessRuleViolation `requisition-no-plan-line` or `requisition-quota-exceeded`, with the remaining quota
     */
    public function assertAvailable(Requisition $requisition, int $hires): void
    {
        if ($requisition->type !== RequisitionType::Planned) {
            return;
        }

        $quota = $this->quota->of($requisition->legalEntityId, $requisition->positionId, $requisition->fiscalYear, $requisition->id)
            ?? throw new BusinessRuleViolation('The plan in force for this fiscal year has no line for the position.', 'requisition-no-plan-line');

        if ($hires > $quota['remaining']) {
            throw new BusinessRuleViolation(
                'The plan has :remaining hire(s) left for this position and fiscal year.', 'requisition-quota-exceeded', extensions: ['remaining' => $quota['remaining']], parameters: ['remaining' => $quota['remaining']],
            );
        }
    }
}
