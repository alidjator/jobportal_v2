<?php

namespace Src\Workforce\Application\Plan;

use DateTimeImmutable;
use Src\Shared\Application\Organization\PositionSummary;
use Src\Workforce\Domain\Plan;

/** A plan with what a reader needs around it: the legal entity's name, the days of the fiscal year, the positions of its lines and whether the costs are shown. */
final readonly class PlanView
{
    /** @param  array<int, PositionSummary>  $positions  by position id; a position that does not exist today is missing */
    public function __construct(
        public Plan $plan,
        public string $legalEntityName,
        public DateTimeImmutable $startsOn,
        public DateTimeImmutable $endsOn,
        public array $positions,
        public bool $showCosts,
    ) {}
}
