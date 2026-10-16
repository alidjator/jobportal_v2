<?php

namespace Src\Organization\Application\Structure;

use Src\Organization\Domain\Events\StructureItemChanged;
use Src\Organization\Domain\ItemStatus;
use Src\Organization\Domain\UnitType;
use Src\Organization\Domain\UnitTypeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The unit types of a group (code, name, level order): any group member reads them, `group.structure.manage` changes them. A unit type an active unit still uses is not deactivated. */
final readonly class UnitTypeCatalog
{
    public function __construct(
        private StructureAccess $access,
        private UnitTypeRepository $types,
        private ChangeDating $dating,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @return list<UnitType>
     *
     * @throws AccessDenied `not-a-group-member` or `group-suspended`
     */
    public function list(Actor $actor): array
    {
        return $this->types->ofGroup((int) $this->access->read($actor)->group->id);
    }

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws StateConflict `unit-type-code-taken`
     */
    public function create(Actor $actor, string $code, string $name, int $levelOrder, ?string $ipAddress, ?string $userAgent): UnitType
    {
        $group = (int) $this->access->manage($actor)->group->id;

        if ($this->types->codeTaken($group, $code)) {
            throw new StateConflict('The group already has a unit type with this code.', 'unit-type-code-taken');
        }

        $type = $this->types->create(new UnitType(null, $group, $code, $name, $levelOrder));
        $this->record($group, (int) $type->id, 'created', $actor, $ipAddress, $userAgent);

        return $type;
    }

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the unit type is missing or belongs to another group
     */
    public function update(Actor $actor, int $id, ?string $name, ?int $levelOrder, ?string $ipAddress, ?string $userAgent): UnitType
    {
        $group = (int) $this->access->manage($actor)->group->id;
        $current = $this->own($group, $id);
        $type = $current->rename($name ?? $current->name, $levelOrder ?? $current->levelOrder);

        return $this->store($type, $group, 'updated', $actor, $ipAddress, $userAgent);
    }

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the unit type is missing or belongs to another group
     * @throws StateConflict `unit-type-in-use`, `already-inactive` or `already-active`
     */
    public function changeStatus(Actor $actor, int $id, bool $activate, ?string $ipAddress, ?string $userAgent): UnitType
    {
        $group = (int) $this->access->manage($actor)->group->id;
        $type = $this->own($group, $id);

        if ($type->isActive() === $activate) {
            throw new StateConflict($activate ? 'The unit type is already active.' : 'The unit type is already inactive.', $activate ? 'already-active' : 'already-inactive');
        }

        if (! $activate && $this->types->inUse($id, $this->dating->today())) {
            throw new StateConflict('An active unit still uses this unit type.', 'unit-type-in-use');
        }

        return $this->store($type->withStatus($activate ? ItemStatus::Active : ItemStatus::Inactive), $group, $activate ? 'reactivated' : 'deactivated', $actor, $ipAddress, $userAgent);
    }

    private function own(int $group, int $id): UnitType
    {
        $type = $this->types->find($id);

        if ($type === null || $type->groupId !== $group) {
            throw EntityNotFound::of('Unit type', $id);
        }

        return $type;
    }

    private function store(UnitType $type, int $group, string $change, Actor $actor, ?string $ipAddress, ?string $userAgent): UnitType
    {
        $this->types->save($type);
        $this->record($group, (int) $type->id, $change, $actor, $ipAddress, $userAgent);

        return $type;
    }

    private function record(int $group, int $id, string $change, Actor $actor, ?string $ipAddress, ?string $userAgent): void
    {
        $this->events->dispatch([new StructureItemChanged($group, 'unit_type', $id, $change, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
