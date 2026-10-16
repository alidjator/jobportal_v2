<?php

namespace Src\Organization\Application\Structure;

use Src\Organization\Domain\Events\UnitRestructured;
use Src\Organization\Domain\ItemStatus;
use Src\Organization\Domain\Timeline;
use Src\Organization\Domain\UnitRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A group admin deactivates or reactivates a unit. A unit that an active unit has as parent, or an active position as its unit, is not deactivated. Nothing is deleted. */
final readonly class ChangeUnitStatus
{
    public function __construct(
        private StructureAccess $access,
        private ChangeDating $dating,
        private UnitRepository $units,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the unit is missing or belongs to another group
     * @throws StateConflict `unit-in-use`, `already-inactive` or `already-active`
     */
    public function execute(Actor $actor, int $unitId, bool $activate, ?string $ipAddress, ?string $userAgent): UnitView
    {
        $context = $this->access->manage($actor);
        $unit = $this->access->unit($context, $unitId);

        if ($unit->isActive() === $activate) {
            throw new StateConflict($activate ? 'The unit is already active.' : 'The unit is already inactive.', $activate ? 'already-active' : 'already-inactive');
        }

        if (! $activate && $this->units->inUse($unitId, $this->dating->today())) {
            throw new StateConflict('An active unit or position still depends on this unit.', 'unit-in-use');
        }

        $changed = $unit->withStatus($activate ? ItemStatus::Active : ItemStatus::Inactive);
        $this->units->save($changed);

        $this->events->dispatch([new UnitRestructured(
            (int) $context->group->id, $unit->legalEntityId, $unitId, $activate ? 'reactivated' : 'deactivated', null, null, null, null, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now(),
        )]);

        $versions = $this->units->versionsOf($unitId);

        return new UnitView($changed, Timeline::at($versions, $this->dating->today()) ?? $versions[0]);
    }
}
