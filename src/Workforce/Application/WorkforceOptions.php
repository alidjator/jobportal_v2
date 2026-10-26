<?php

namespace Src\Workforce\Application;

use Src\Shared\Application\Organization\CostCentreOption;
use Src\Shared\Application\Organization\GradeOption;
use Src\Shared\Application\Organization\PositionSummary;
use Src\Shared\Application\Organization\UnitOption;

/** What a member needs to fill a plan line or a requisition: the active positions, units, grades and cost centres of their own legal entity and the accounts that may be hiring manager. */
final readonly class WorkforceOptions
{
    /**
     * @param  list<PositionSummary>  $positions
     * @param  list<UnitOption>  $units
     * @param  list<GradeOption>  $grades
     * @param  list<CostCentreOption>  $costCentres
     * @param  list<array{id: int, name: string}>  $managers
     */
    public function __construct(
        public int $legalEntityId,
        public array $positions,
        public array $units,
        public array $grades,
        public array $costCentres,
        public array $managers,
    ) {}
}
