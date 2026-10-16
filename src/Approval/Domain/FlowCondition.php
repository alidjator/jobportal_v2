<?php

namespace Src\Approval\Domain;

/** A step applies only when this holds of the subject's facts: `fact` `operator` `value` (a list of values for `in`). */
final readonly class FlowCondition
{
    /** @param  int|bool|string|list<string>  $value */
    public function __construct(public string $fact, public ConditionOperator $operator, public int|bool|string|array $value) {}

    /** @return array{fact: string, operator: string, value: int|bool|string|list<string>} */
    public function toArray(): array
    {
        return ['fact' => $this->fact, 'operator' => $this->operator->value, 'value' => $this->value];
    }
}
