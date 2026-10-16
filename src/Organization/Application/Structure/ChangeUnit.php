<?php

namespace Src\Organization\Application\Structure;

use DateTimeImmutable;
use Src\Organization\Domain\Events\UnitRestructured;
use Src\Organization\Domain\Timeline;
use Src\Organization\Domain\UnitRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A group admin restructures a unit: the fields sent become a new version from the day of the change (today by default, a past day needs a reason), on top of the
 * version that was valid that day. The unit's code and the versions before the day stay as they are.
 */
final readonly class ChangeUnit
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
     * @param  array<string, mixed>  $changes  keyed by property name of the version: `name`, `parentUnitId`, `unitTypeId`, `costCentreId`
     *
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the unit is missing or belongs to another group
     * @throws StateConflict `unit-inactive`
     * @throws BusinessRuleViolation `no-changes`, `reason-required`, `before-first-version`, `invalid-unit-type`, `invalid-cost-centre`, `invalid-parent` or `parent-cycle`
     */
    public function execute(Actor $actor, int $unitId, array $changes, ?DateTimeImmutable $validFrom, ?string $reason, ?string $ipAddress, ?string $userAgent): UnitView
    {
        $context = $this->access->manage($actor);
        $unit = $this->access->unit($context, $unitId);
        $entity = $this->access->entity($context, $unit->legalEntityId);

        if ($changes === []) {
            throw new BusinessRuleViolation('Nothing to change: send at least one field.', 'no-changes');
        }

        if (! $unit->isActive()) {
            throw new StateConflict('The unit is inactive.', 'unit-inactive');
        }

        $dated = $this->dating->resolve($validFrom, $reason);
        $current = $this->units->versionsOf($unitId);
        $base = Timeline::at($current, $dated->from) ?? throw new BusinessRuleViolation('The change is dated before the first version exists.', 'before-first-version');

        $timeline = $this->rules->timelineWith($entity, $unitId, $current, $base->with($changes)->withPeriod($dated->from, null));
        $this->units->replaceVersions($unitId, $timeline);
        $after = Timeline::at($timeline, $dated->from) ?? $base;

        $this->events->dispatch([new UnitRestructured(
            (int) $context->group->id, (int) $entity->id, $unitId, 'restructured', $dated->from->format('Y-m-d'), $dated->reason, $base->snapshot(), $after->snapshot(),
            (int) $actor->id, $ipAddress, $userAgent, $this->clock->now(),
        )]);

        return new UnitView($unit, Timeline::at($timeline, $this->dating->today()) ?? $after);
    }
}
