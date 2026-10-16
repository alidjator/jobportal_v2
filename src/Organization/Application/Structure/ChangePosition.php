<?php

namespace Src\Organization\Application\Structure;

use DateTimeImmutable;
use Src\Organization\Domain\Events\PositionChanged;
use Src\Organization\Domain\PositionRepository;
use Src\Organization\Domain\Timeline;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A group admin changes a position: the fields sent become a new version from the day of the change (today by default, a past day needs a reason), on top of the
 * version that was valid that day. The code and the versions before the day stay as they are.
 */
final readonly class ChangePosition
{
    public function __construct(
        private StructureAccess $access,
        private ChangeDating $dating,
        private PositionRepository $positions,
        private PositionVersionRules $rules,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $changes  keyed by property name of the version: `title`, `unitId`, `minGradeId`, `maxGradeId`, `reportsToPositionId`, `jobRoleId`
     *
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the position is missing or belongs to another group
     * @throws StateConflict `position-inactive`
     * @throws BusinessRuleViolation `no-changes`, `reason-required`, `before-first-version`, `invalid-unit`, `invalid-grade`, `invalid-grade-range`, `invalid-job-role`, `invalid-reports-to` or `reports-to-cycle`
     */
    public function execute(Actor $actor, int $positionId, array $changes, ?DateTimeImmutable $validFrom, ?string $reason, ?string $ipAddress, ?string $userAgent): PositionView
    {
        $context = $this->access->manage($actor);
        $position = $this->access->position($context, $positionId);
        $entity = $this->access->entity($context, $position->legalEntityId);

        if ($changes === []) {
            throw new BusinessRuleViolation('Nothing to change: send at least one field.', 'no-changes');
        }

        if (! $position->isActive()) {
            throw new StateConflict('The position is inactive.', 'position-inactive');
        }

        $dated = $this->dating->resolve($validFrom, $reason);
        $current = $this->positions->versionsOf($positionId);
        $base = Timeline::at($current, $dated->from) ?? throw new BusinessRuleViolation('The change is dated before the first version exists.', 'before-first-version');

        $timeline = $this->rules->timelineWith($entity, $positionId, $current, $base->with($changes)->withPeriod($dated->from, null));
        $this->positions->replaceVersions($positionId, $timeline);
        $after = Timeline::at($timeline, $dated->from) ?? $base;

        $this->events->dispatch([new PositionChanged(
            (int) $context->group->id, (int) $entity->id, $positionId, 'changed', $dated->from->format('Y-m-d'), $dated->reason, $base->snapshot(), $after->snapshot(),
            (int) $actor->id, $ipAddress, $userAgent, $this->clock->now(),
        )]);

        return new PositionView($position, Timeline::at($timeline, $this->dating->today()) ?? $after);
    }
}
