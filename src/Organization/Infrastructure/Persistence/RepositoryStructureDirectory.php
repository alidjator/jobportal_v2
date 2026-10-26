<?php

namespace Src\Organization\Infrastructure\Persistence;

use DateTimeImmutable;
use Src\Organization\Domain\CostCentre;
use Src\Organization\Domain\CostCentreRepository;
use Src\Organization\Domain\GradeRepository;
use Src\Organization\Domain\Timeline;
use Src\Organization\Domain\UnitRepository;
use Src\Organization\Domain\UnitVersion;
use Src\Shared\Application\Organization\CostCentreOption;
use Src\Shared\Application\Organization\GradeOption;
use Src\Shared\Application\Organization\GradeSummary;
use Src\Shared\Application\Organization\StructureDirectory;
use Src\Shared\Application\Organization\UnitOption;

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

    public function gradeOptions(int $groupId): array
    {
        $options = [];

        foreach ($this->grades->ofGroup($groupId) as $grade) {
            if ($grade->isActive()) {
                $options[] = new GradeOption((int) $grade->id, $grade->code, $grade->name, $grade->rank);
            }
        }

        usort($options, static fn (GradeOption $a, GradeOption $b): int => [$a->rank, $a->code] <=> [$b->rank, $b->code]);

        return $options;
    }

    public function costCentreOptions(int $legalEntityId): array
    {
        $options = [];

        foreach ($this->costCentres->ofEntity($legalEntityId) as $centre) {
            if ($centre->isActive()) {
                $options[] = new CostCentreOption((int) $centre->id, $centre->code, $centre->name);
            }
        }

        usort($options, static fn (CostCentreOption $a, CostCentreOption $b): int => $a->code <=> $b->code);

        return $options;
    }

    public function unitOptions(int $legalEntityId, DateTimeImmutable $on): array
    {
        $timelines = $this->units->timelinesOfEntity($legalEntityId);
        $options = [];

        foreach ($this->units->ofEntity($legalEntityId) as $unit) {
            $version = Timeline::at($timelines[(int) $unit->id] ?? [], $on);

            if ($unit->isActive() && $version instanceof UnitVersion) {
                $options[] = new UnitOption((int) $unit->id, $unit->code, $version->name, $version->costCentreId);
            }
        }

        usort($options, static fn (UnitOption $a, UnitOption $b): int => [$a->name, $a->code] <=> [$b->name, $b->code]);

        return $options;
    }
}
