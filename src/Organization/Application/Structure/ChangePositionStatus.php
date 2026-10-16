<?php

namespace Src\Organization\Application\Structure;

use Src\Organization\Domain\Events\PositionChanged;
use Src\Organization\Domain\ItemStatus;
use Src\Organization\Domain\PositionRepository;
use Src\Organization\Domain\Timeline;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A group admin deactivates or reactivates a position. A position that an active position reports to is not deactivated. Nothing is deleted. */
final readonly class ChangePositionStatus
{
    public function __construct(
        private StructureAccess $access,
        private ChangeDating $dating,
        private PositionRepository $positions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the position is missing or belongs to another group
     * @throws StateConflict `position-in-use`, `already-inactive` or `already-active`
     */
    public function execute(Actor $actor, int $positionId, bool $activate, ?string $ipAddress, ?string $userAgent): PositionView
    {
        $context = $this->access->manage($actor);
        $position = $this->access->position($context, $positionId);

        if ($position->isActive() === $activate) {
            throw new StateConflict($activate ? 'The position is already active.' : 'The position is already inactive.', $activate ? 'already-active' : 'already-inactive');
        }

        if (! $activate && $this->positions->inUse($positionId, $this->dating->today())) {
            throw new StateConflict('An active position still reports to this position.', 'position-in-use');
        }

        $changed = $position->withStatus($activate ? ItemStatus::Active : ItemStatus::Inactive);
        $this->positions->save($changed);

        $this->events->dispatch([new PositionChanged(
            (int) $context->group->id, $position->legalEntityId, $positionId, $activate ? 'reactivated' : 'deactivated', null, null, null, null, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now(),
        )]);

        $versions = $this->positions->versionsOf($positionId);

        return new PositionView($changed, Timeline::at($versions, $this->dating->today()) ?? $versions[0]);
    }
}
