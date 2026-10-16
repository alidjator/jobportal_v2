<?php

namespace Src\Approval\Domain;

/** One step of a flow: its approvers, how many of them must approve, and the conditions under which the step applies (all of them must hold; none means always). */
final readonly class FlowStep
{
    /**
     * @param  list<FlowApprover>  $approvers
     * @param  list<FlowCondition>  $conditions
     */
    public function __construct(public int $position, public StepMode $mode, public array $conditions, public array $approvers) {}
}
