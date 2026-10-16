<?php

namespace Src\Organization\Application\Structure;

use DateTimeImmutable;
use Src\Organization\Domain\Events\UnitRestructured;
use Src\Organization\Domain\Unit;
use Src\Organization\Domain\UnitRepository;
use Src\Organization\Domain\UnitVersion;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A group admin adds a unit to a legal entity: its stable code and its first version, from today or a day set by the caller (a past day needs a reason). */
final readonly class CreateUnit
{
    public function __construct(
        private StructureAccess $access,
        private ChangeDating $dating,
        private UnitRepository $units,
        private UnitVersionRules $rules,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the entity is missing or belongs to another group
     * @throws StateConflict `legal-entity-inactive` or `unit-code-taken`
     * @throws BusinessRuleViolation `reason-required`, `invalid-unit-type`, `invalid-cost-centre` or `invalid-parent`
     */
    public function execute(Actor $actor, int $legalEntityId, string $code, UnitInput $input, ?DateTimeImmutable $validFrom, ?string $reason, ?string $ipAddress, ?string $userAgent): UnitView
    {
        $context = $this->access->manage($actor);
        $entity = $this->access->activeEntity($context, $legalEntityId);
        $dated = $this->dating->resolve($validFrom, $reason);

        if ($this->units->codeTaken((int) $entity->id, $code)) {
            throw new StateConflict('The legal entity already has a unit with this code.', 'unit-code-taken');
        }

        $unit = $this->units->create(new Unit(null, (int) $entity->id, $code));
        $version = new UnitVersion((int) $unit->id, $dated->from, null, $input->name, $input->parentUnitId, $input->unitTypeId, $input->costCentreId);
        $timeline = $this->rules->timelineWith($entity, (int) $unit->id, [], $version);
        $this->units->replaceVersions((int) $unit->id, $timeline);

        $this->events->dispatch([new UnitRestructured(
            (int) $context->group->id, (int) $entity->id, (int) $unit->id, 'created', $dated->from->format('Y-m-d'), $dated->reason, null, $timeline[0]->snapshot(),
            (int) $actor->id, $ipAddress, $userAgent, $this->clock->now(),
        )]);

        return new UnitView($unit, $timeline[0]);
    }
}
