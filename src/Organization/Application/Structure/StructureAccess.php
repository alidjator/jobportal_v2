<?php

namespace Src\Organization\Application\Structure;

use Src\Organization\Application\Group\GroupAccess;
use Src\Organization\Application\Group\GroupContext;
use Src\Organization\Domain\LegalEntity;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Organization\Domain\Position;
use Src\Organization\Domain\PositionRepository;
use Src\Organization\Domain\Unit;
use Src\Organization\Domain\UnitRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Who may read or change the structure of a group: any group member reads it, `group.structure.manage` changes it. A legal entity of another group does not exist for the
 * caller.
 */
final readonly class StructureAccess
{
    public function __construct(
        private GroupAccess $access,
        private LegalEntityRepository $entities,
        private UnitRepository $units,
        private PositionRepository $positions,
    ) {}

    /** @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required` */
    public function manage(Actor $actor): GroupContext
    {
        return $this->access->context($actor, GroupPermission::StructureManage);
    }

    /** @throws AccessDenied `not-a-group-member` or `group-suspended` */
    public function read(Actor $actor): GroupContext
    {
        return $this->access->context($actor);
    }

    /** @throws EntityNotFound the entity is missing or belongs to another group */
    public function entity(GroupContext $context, int $legalEntityId): LegalEntity
    {
        $entity = $this->entities->find($legalEntityId);

        if ($entity === null || $entity->groupId !== $context->group->id) {
            throw EntityNotFound::of('Legal entity', $legalEntityId);
        }

        return $entity;
    }

    /**
     * @throws EntityNotFound the entity is missing or belongs to another group
     * @throws StateConflict `legal-entity-inactive`
     */
    public function activeEntity(GroupContext $context, int $legalEntityId): LegalEntity
    {
        $entity = $this->entity($context, $legalEntityId);

        if (! $entity->isActive()) {
            throw new StateConflict('The legal entity is inactive.', 'legal-entity-inactive');
        }

        return $entity;
    }

    /** @throws EntityNotFound the unit is missing or belongs to another group */
    public function unit(GroupContext $context, int $unitId): Unit
    {
        $unit = $this->units->find($unitId);

        if ($unit === null) {
            throw EntityNotFound::of('Unit', $unitId);
        }

        $this->entity($context, $unit->legalEntityId);

        return $unit;
    }

    /** @throws EntityNotFound the position is missing or belongs to another group */
    public function position(GroupContext $context, int $positionId): Position
    {
        $position = $this->positions->find($positionId);

        if ($position === null) {
            throw EntityNotFound::of('Position', $positionId);
        }

        $this->entity($context, $position->legalEntityId);

        return $position;
    }
}
