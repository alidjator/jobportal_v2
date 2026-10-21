<?php

namespace Src\Workforce\Application\Plan;

use Src\Shared\Application\Clock;
use Src\Shared\Application\Organization\PositionDirectory;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Workforce\Domain\PlanLine;

/**
 * Checks the lines of a plan and turns them into plan lines. A line names an active position of the legal entity once, the year-end target is not below the opening headcount,
 * and the twelve monthly additions add up to the difference. A reader without `workforce.costs.view` cannot see or change the estimated costs: the costs they send are ignored
 * and the ones a line already has stay.
 */
final readonly class PlanLineRules
{
    public const int MAX_LINES = 500;

    public function __construct(private PositionDirectory $positions, private Clock $clock) {}

    /**
     * @param  list<PlanLineInput>  $inputs
     * @param  list<PlanLine>  $existing  the lines the plan has now
     * @return list<PlanLine>
     *
     * @throws BusinessRuleViolation `invalid-plan-line`
     */
    public function build(int $legalEntityId, array $inputs, bool $mayChangeCosts, array $existing): array
    {
        if (count($inputs) > self::MAX_LINES) {
            throw new BusinessRuleViolation('A plan has at most :max lines.', 'invalid-plan-line', parameters: ['max' => self::MAX_LINES]);
        }

        $positions = $this->positions->ofEntity($legalEntityId, $this->clock->now());
        $costs = [];

        foreach ($existing as $line) {
            $costs[$line->positionId] = $line->estimatedAnnualCost;
        }

        $seen = [];
        $lines = [];

        foreach ($inputs as $input) {
            $position = $positions[$input->positionId] ?? throw new BusinessRuleViolation('Position :position is not a position of this legal entity.', 'invalid-plan-line', parameters: ['position' => $input->positionId]);

            if (! $position->active) {
                throw new BusinessRuleViolation('Position :position is deactivated.', 'invalid-plan-line', parameters: ['position' => $position->code]);
            }

            if (isset($seen[$input->positionId])) {
                throw new BusinessRuleViolation('Position :position appears twice.', 'invalid-plan-line', parameters: ['position' => $position->code]);
            }

            $seen[$input->positionId] = true;
            $additions = $input->targetHeadcount - $input->openingHeadcount;

            if ($additions < 0) {
                throw new BusinessRuleViolation('The year-end target of position :position is below its opening headcount.', 'invalid-plan-line', parameters: ['position' => $position->code]);
            }

            if (count($input->months) !== PlanLine::MONTHS || min($input->months) < 0 || array_sum($input->months) !== $additions) {
                throw new BusinessRuleViolation(
                    'The monthly additions of position :position must be twelve whole numbers that add up to its additions (:additions).', 'invalid-plan-line',
                    parameters: ['position' => $position->code, 'additions' => $additions],
                );
            }

            $lines[] = new PlanLine(
                $input->positionId, $input->openingHeadcount, $input->targetHeadcount, $input->months, $mayChangeCosts ? $input->estimatedAnnualCost : ($costs[$input->positionId] ?? null),
            );
        }

        return $lines;
    }
}
