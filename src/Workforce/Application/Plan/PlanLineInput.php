<?php

namespace Src\Workforce\Application\Plan;

/** One submitted line of a plan; `months` holds the additions of fiscal month 1 to 12. */
final readonly class PlanLineInput
{
    /** @param  list<int>  $months */
    public function __construct(public int $positionId, public int $openingHeadcount, public int $targetHeadcount, public array $months, public ?int $estimatedAnnualCost = null) {}
}
