<?php

namespace Src\Shared\Application\Organization;

use DateTimeImmutable;

/** The grades, cost centres and units of the structure for the contexts that refer to them (Workforce). Provided by Organization. */
interface StructureDirectory
{
    public function grade(int $gradeId): ?GradeSummary;

    /** @return list<int> the ids of the active cost centres of the legal entity */
    public function activeCostCentreIds(int $legalEntityId): array;

    /** The default cost centre of the unit on the day, or null when it has none or does not exist. */
    public function unitCostCentre(int $unitId, DateTimeImmutable $on): ?int;
}
