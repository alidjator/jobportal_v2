<?php

namespace Src\Organization\Application\Structure;

use Src\Organization\Domain\CostCentreRepository;
use Src\Organization\Domain\LegalEntity;
use Src\Organization\Domain\Timeline;
use Src\Organization\Domain\UnitRepository;
use Src\Organization\Domain\UnitTypeRepository;
use Src\Organization\Domain\UnitVersion;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * What every new version of a unit must satisfy: a unit type of the group, a cost centre of the entity, and a parent unit of the same entity that is active, exists
 * from the day the version starts and does not lead back to this unit on any day of the version's period.
 */
final readonly class UnitVersionRules
{
    public function __construct(private UnitRepository $units, private UnitTypeRepository $types, private CostCentreRepository $centres) {}

    /**
     * Puts the version into the unit's timeline after checking it, and returns the timeline to store.
     *
     * @param  list<UnitVersion>  $current  the unit's versions now (empty for a new unit)
     * @return list<UnitVersion>
     *
     * @throws BusinessRuleViolation `invalid-unit-type`, `invalid-cost-centre`, `invalid-parent`, `parent-cycle` or `before-first-version`
     */
    public function timelineWith(LegalEntity $entity, int $unitId, array $current, UnitVersion $version): array
    {
        $type = $this->types->find($version->unitTypeId);

        if ($type === null || $type->groupId !== $entity->groupId || ! $type->isActive()) {
            throw new BusinessRuleViolation('The unit type is not an active unit type of the group.', 'invalid-unit-type');
        }

        if ($version->costCentreId !== null) {
            $centre = $this->centres->find($version->costCentreId);

            if ($centre === null || $centre->legalEntityId !== $entity->id || ! $centre->isActive()) {
                throw new BusinessRuleViolation('The cost centre is not an active cost centre of the legal entity.', 'invalid-cost-centre');
            }
        }

        $timeline = Timeline::with($current, $version);
        $placed = Timeline::at($timeline, $version->validFrom) ?? $version;

        if ($placed->parentUnitId !== null) {
            $timelines = $this->units->timelinesOfEntity((int) $entity->id);
            $timelines[$unitId] = $timeline;
            $parent = $this->units->find($placed->parentUnitId);

            if ($parent === null || $parent->legalEntityId !== $entity->id || $parent->id === $unitId || ! $parent->isActive()
                || ! Timeline::startedBy($timelines[$placed->parentUnitId] ?? [], $placed->validFrom)) {
                throw new BusinessRuleViolation('The parent is not an active unit of the legal entity that exists from the day of the change.', 'invalid-parent');
            }

            if (Timeline::closesLoop($timelines, $unitId, $placed, static fn (UnitVersion $other): ?int => $other->parentUnitId)) {
                throw new BusinessRuleViolation('The parent would make the unit its own ancestor.', 'parent-cycle');
            }
        }

        return $timeline;
    }
}
