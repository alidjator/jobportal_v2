<?php

namespace Src\Workforce\Domain;

/**
 * The plan of one position: the headcount it opens the year with and the one it must reach by the year end (the difference is the additions), the additions split per month
 * of the fiscal year (a target, not a limit) and an optional estimated annual cost in rupiah.
 */
final readonly class PlanLine
{
    public const int MONTHS = 12;

    /** @param  list<int>  $months  the additions of fiscal month 1 to 12, in order */
    public function __construct(
        public int $positionId,
        public int $openingHeadcount,
        public int $targetHeadcount,
        public array $months,
        public ?int $estimatedAnnualCost = null,
    ) {}

    public function additions(): int
    {
        return $this->targetHeadcount - $this->openingHeadcount;
    }
}
