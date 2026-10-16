<?php

namespace Src\Organization\Application\Structure;

use Src\Organization\Application\Group\GroupContext;
use Src\Organization\Domain\CostCentre;
use Src\Organization\Domain\CostCentreRepository;
use Src\Organization\Domain\Events\StructureItemChanged;
use Src\Organization\Domain\ItemStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The cost centres of a legal entity (code, name): any group member reads them, `group.structure.manage` changes them. A cost centre a unit still uses is not deactivated. */
final readonly class CostCentreCatalog
{
    public function __construct(
        private StructureAccess $access,
        private CostCentreRepository $centres,
        private ChangeDating $dating,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @return list<CostCentre>
     *
     * @throws AccessDenied `not-a-group-member` or `group-suspended`
     * @throws EntityNotFound the entity is missing or belongs to another group
     */
    public function list(Actor $actor, int $legalEntityId): array
    {
        $entity = $this->access->entity($this->access->read($actor), $legalEntityId);

        return $this->centres->ofEntity((int) $entity->id);
    }

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the entity is missing or belongs to another group
     * @throws StateConflict `legal-entity-inactive` or `cost-centre-code-taken`
     */
    public function create(Actor $actor, int $legalEntityId, string $code, string $name, ?string $ipAddress, ?string $userAgent): CostCentre
    {
        $context = $this->access->manage($actor);
        $entity = $this->access->activeEntity($context, $legalEntityId);

        if ($this->centres->codeTaken((int) $entity->id, $code)) {
            throw new StateConflict('The legal entity already has a cost centre with this code.', 'cost-centre-code-taken');
        }

        $centre = $this->centres->create(new CostCentre(null, (int) $entity->id, $code, $name));
        $this->record((int) $context->group->id, (int) $centre->id, 'created', $actor, $ipAddress, $userAgent);

        return $centre;
    }

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the cost centre is missing or belongs to another group
     */
    public function update(Actor $actor, int $id, string $name, ?string $ipAddress, ?string $userAgent): CostCentre
    {
        $context = $this->access->manage($actor);

        return $this->store($this->own($context, $id)->rename($name), (int) $context->group->id, 'updated', $actor, $ipAddress, $userAgent);
    }

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the cost centre is missing or belongs to another group
     * @throws StateConflict `cost-centre-in-use`, `already-inactive` or `already-active`
     */
    public function changeStatus(Actor $actor, int $id, bool $activate, ?string $ipAddress, ?string $userAgent): CostCentre
    {
        $context = $this->access->manage($actor);
        $centre = $this->own($context, $id);

        if ($centre->isActive() === $activate) {
            throw new StateConflict($activate ? 'The cost centre is already active.' : 'The cost centre is already inactive.', $activate ? 'already-active' : 'already-inactive');
        }

        if (! $activate && $this->centres->inUse($id, $this->dating->today())) {
            throw new StateConflict('An active unit still uses this cost centre.', 'cost-centre-in-use');
        }

        return $this->store($centre->withStatus($activate ? ItemStatus::Active : ItemStatus::Inactive), (int) $context->group->id, $activate ? 'reactivated' : 'deactivated', $actor, $ipAddress, $userAgent);
    }

    private function own(GroupContext $context, int $id): CostCentre
    {
        $centre = $this->centres->find($id);

        if ($centre === null) {
            throw EntityNotFound::of('Cost centre', $id);
        }

        $this->access->entity($context, $centre->legalEntityId);

        return $centre;
    }

    private function store(CostCentre $centre, int $group, string $change, Actor $actor, ?string $ipAddress, ?string $userAgent): CostCentre
    {
        $this->centres->save($centre);
        $this->record($group, (int) $centre->id, $change, $actor, $ipAddress, $userAgent);

        return $centre;
    }

    private function record(int $group, int $id, string $change, Actor $actor, ?string $ipAddress, ?string $userAgent): void
    {
        $this->events->dispatch([new StructureItemChanged($group, 'cost_centre', $id, $change, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
