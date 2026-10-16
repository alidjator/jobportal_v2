<?php

namespace Src\Organization\Infrastructure\Persistence;

use DateTimeImmutable;
use Src\Organization\Domain\CostCentre;
use Src\Organization\Domain\CostCentreRepository;
use Src\Organization\Domain\GradeRepository;
use Src\Organization\Domain\Timeline;
use Src\Organization\Domain\UnitRepository;
use Src\Organization\Domain\UnitVersion;
use Src\Shared\Application\Organization\GradeSummary;
use Src\Shared\Application\Organization\StructureDirectory;

final readonly class RepositoryStructureDirectory implements StructureDirectory
{
    public function __construct(private GradeRepository $grades, private CostCentreRepository $costCentres, private UnitRepository $units) {}

    public function grade(int $gradeId): ?GradeSummary
    {
        $grade = $this->grades->find($gradeId);

        return $grade === null ? null : new GradeSummary((int) $grade->id, $grade->groupId, $grade->rank, $grade->isActive());
    }

    public function activeCostCentreIds(int $legalEntityId): array
    {
        return array_values(array_map(
            static fn (CostCentre $centre): int => (int) $centre->id,
            array_filter($this->costCentres->ofEntity($legalEntityId), static fn (CostCentre $centre): bool => $centre->isActive()),
        ));
    }

    public function unitCostCentre(int $unitId, DateTimeImmutable $on): ?int
    {
        $version = Timeline::at($this->units->versionsOf($unitId), $on);

        return $version instanceof UnitVersion ? $version->costCentreId : null;
    }
}
