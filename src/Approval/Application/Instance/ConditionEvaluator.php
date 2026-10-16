<?php

namespace Src\Approval\Application\Instance;

use InvalidArgumentException;
use Src\Approval\Domain\ConditionOperator;
use Src\Approval\Domain\FlowCondition;

/** Whether a step's conditions hold of a subject's facts. A fact the subject did not give is a programming error of the subject's context, not a condition that fails. */
final class ConditionEvaluator
{
    /**
     * @param  list<FlowCondition>  $conditions
     * @param  array<string, int|bool|string>  $facts
     */
    public function allHold(array $conditions, array $facts): bool
    {
        foreach ($conditions as $condition) {
            if (! $this->holds($condition, $facts)) {
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, int|bool|string>  $facts */
    private function holds(FlowCondition $condition, array $facts): bool
    {
        if (! array_key_exists($condition->fact, $facts)) {
            throw new InvalidArgumentException("The submission gives no value for the fact {$condition->fact}.");
        }

        $fact = $facts[$condition->fact];
        $value = $condition->value;

        return match ($condition->operator) {
            ConditionOperator::Gte => is_int($fact) && is_int($value) && $fact >= $value,
            ConditionOperator::Lte => is_int($fact) && is_int($value) && $fact <= $value,
            ConditionOperator::Eq => $fact === $value,
            ConditionOperator::In => is_array($value) && in_array($fact, $value, true),
        };
    }
}
