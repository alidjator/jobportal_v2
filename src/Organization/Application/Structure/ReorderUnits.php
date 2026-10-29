<?php

namespace Src\Organization\Application\Structure;

use Src\Organization\Domain\Events\UnitRestructured;
use Src\Organization\Domain\UnitOrder;
use Src\Organization\Domain\UnitRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A group admin puts some units of a legal entity in a new order (the org chart and the table follow it). The units named take each other's places; the others keep theirs. Not dated. */
final readonly class ReorderUnits
{
    public function __construct(private StructureAccess $access, private UnitRepository $units, private EventDispatcher $events, private Clock $clock) {}

    /**
     * @param  list<int>  $unitIds  the units in the wanted order
     *
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the entity is missing or belongs to another group
     * @throws StateConflict `legal-entity-inactive`
     * @throws BusinessRuleViolation `invalid-unit-order`
     */
    public function execute(Actor $actor, int $legalEntityId, array $unitIds, ?string $ipAddress, ?string $userAgent): void
    {
        $context = $this->access->manage($actor);
        $entity = $this->access->activeEntity($context, $legalEntityId);
        $units = $this->units->ofEntity((int) $entity->id);
        $orders = UnitOrder::arrange($units, $unitIds);
        $before = array_map(static fn ($unit): int => (int) $unit->id, array_values(array_filter($units, static fn ($unit): bool => isset($orders[(int) $unit->id]))));

        $this->units->saveOrders($orders);

        $this->events->dispatch([new UnitRestructured(
            (int) $context->group->id, (int) $entity->id, $unitIds[0], 'reordered', null, null, ['unit_ids' => $before], ['unit_ids' => $unitIds], (int) $actor->id, $ipAddress, $userAgent, $this->clock->now(),
        )]);
    }
}
