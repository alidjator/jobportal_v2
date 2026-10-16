<?php

namespace Src\Workforce\Application\Plan;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Workforce\Domain\PlanLine;
use Src\Workforce\Domain\RequisitionRepository;

/** The additions of a line can never drop below the hires the requisitions of its position already use (`docs/design/erp-requisitions.md` section 2). */
final readonly class PlanFloor
{
    public function __construct(private RequisitionRepository $requisitions) {}

    /**
     * @param  list<PlanLine>  $lines  the lines the plan would have; a position without a line has no additions
     *
     * @throws BusinessRuleViolation `plan-below-requisitions`, with the position and the hires it uses
     */
    public function assertHolds(int $legalEntityId, int $fiscalYear, array $lines): void
    {
        $additions = [];

        foreach ($lines as $line) {
            $additions[$line->positionId] = $line->additions();
        }

        foreach ($this->requisitions->usedHiresByPosition($legalEntityId, $fiscalYear) as $positionId => $used) {
            if ($used > ($additions[$positionId] ?? 0)) {
                throw new BusinessRuleViolation(
                    "Requisitions already use {$used} hire(s) of position {$positionId}, so its additions cannot be lower.", 'plan-below-requisitions', extensions: ['position_id' => $positionId, 'used' => $used],
                );
            }
        }
    }
}
