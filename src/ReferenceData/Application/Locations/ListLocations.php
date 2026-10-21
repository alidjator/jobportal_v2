<?php

namespace Src\ReferenceData\Application\Locations;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Lists the areas of one level. Every level but the provinces needs its parent, which keeps each list small. */
final readonly class ListLocations
{
    public function __construct(private LocationRepository $locations) {}

    /**
     * @return list<LocationItem>
     *
     * @throws BusinessRuleViolation `location-parent-required` when a level that needs a parent is asked without one
     */
    public function execute(LocationLevel $level, ?int $parentId): array
    {
        if ($level->parentColumn !== null && $parentId === null) {
            throw new BusinessRuleViolation('Filter by :parent to list the :level.', 'location-parent-required', parameters: ['parent' => $level->parentColumn, 'level' => $level->key]);
        }

        return $this->locations->list($level, $level->parentColumn === null ? null : $parentId);
    }
}
