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

    /** @return list<GradeOption> the active grades of the group, lowest rank first */
    public function gradeOptions(int $groupId): array;

    /** @return list<CostCentreOption> the active cost centres of the legal entity, in code order */
    public function costCentreOptions(int $legalEntityId): array;

    /** @return list<UnitOption> the active units of the legal entity that exist on the day, in name order */
    public function unitOptions(int $legalEntityId, DateTimeImmutable $on): array;
}
