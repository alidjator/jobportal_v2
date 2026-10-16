<?php

namespace Src\Approval\Application\Flow;

use Src\Approval\Domain\FlowStep;

/** The steps of a flow as a request gives them, before the rules checked them. */
final readonly class FlowInput
{
    /** @param  list<FlowStep>  $steps */
    public function __construct(public array $steps) {}
}
