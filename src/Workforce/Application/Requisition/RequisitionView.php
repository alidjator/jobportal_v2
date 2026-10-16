<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Application\Organization\PositionSummary;
use Src\Workforce\Domain\Requisition;
use Src\Workforce\Domain\RequisitionAmendment;

/**
 * A requisition with what a reader needs around it: its position as it is today, whether the salary budget is shown and, for the detail, its amendments, the jobs opened for it
 * and, for a `planned` one, the quota of its plan line.
 */
final readonly class RequisitionView
{
    /**
     * @param  list<RequisitionAmendment>  $amendments
     * @param  array{additions: int, used: int, remaining: int}|null  $quota
     */
    public function __construct(
        public Requisition $requisition,
        public ?PositionSummary $position,
        public bool $showCosts,
        public array $amendments = [],
        public ?int $jobCount = null,
        public ?int $openVacancies = null,
        public ?array $quota = null,
    ) {}
}
