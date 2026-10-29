<?php

namespace Src\Organization\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The order of the units of a legal entity. A reorder names some units in the wanted order (the siblings that were dragged): they take each other's places, so the units that are not named
 * keep theirs.
 */
final class UnitOrder
{
    /**
     * The new `sort_order` of each named unit.
     *
     * @param  list<Unit>  $units  every unit of the entity
     * @param  list<int>  $wantedIds  the named units in the wanted order, each once
     * @return array<int, int> sort order by unit id
     *
     * @throws BusinessRuleViolation `invalid-unit-order` with `errors` on `unit_ids`
     */
    public static function arrange(array $units, array $wantedIds): array
    {
        $current = [];

        foreach ($units as $unit) {
            $current[(int) $unit->id] = $unit->sortOrder;
        }

        if ($wantedIds === [] || count($wantedIds) !== count(array_unique($wantedIds)) || array_diff($wantedIds, array_keys($current)) !== []) {
            $message = 'The list must name units of the legal entity, each once.';

            throw new BusinessRuleViolation($message, 'invalid-unit-order', extensions: ['errors' => ['unit_ids' => [$message]]]);
        }

        $places = array_map(static fn (int $id): int => $current[$id], $wantedIds);
        sort($places);

        return array_combine($wantedIds, $places);
    }
}
