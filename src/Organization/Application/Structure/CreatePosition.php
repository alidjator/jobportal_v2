<?php

namespace Src\Organization\Application\Structure;

use DateTimeImmutable;
use Src\Organization\Domain\Events\PositionChanged;
use Src\Organization\Domain\Position;
use Src\Organization\Domain\PositionRepository;
use Src\Organization\Domain\PositionVersion;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A group admin adds a position (a job title, not a seat) to a legal entity: its stable code and its first version, from today or a day set by the caller (a past day needs a reason). */
final readonly class CreatePosition
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
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the entity is missing or belongs to another group
     * @throws StateConflict `legal-entity-inactive` or `position-code-taken`
     * @throws BusinessRuleViolation `reason-required`, `invalid-unit`, `invalid-grade`, `invalid-grade-range`, `invalid-job-role` or `invalid-reports-to`
     */
    public function execute(Actor $actor, int $legalEntityId, string $code, PositionInput $input, ?DateTimeImmutable $validFrom, ?string $reason, ?string $ipAddress, ?string $userAgent): PositionView
    {
        $context = $this->access->manage($actor);
        $entity = $this->access->activeEntity($context, $legalEntityId);
        $dated = $this->dating->resolve($validFrom, $reason);

        if ($this->positions->codeTaken((int) $entity->id, $code)) {
            throw new StateConflict('The legal entity already has a position with this code.', 'position-code-taken');
        }

        $position = $this->positions->create(new Position(null, (int) $entity->id, $code));
        $version = new PositionVersion(
            (int) $position->id, $dated->from, null, $input->title, $input->unitId, $input->minGradeId, $input->maxGradeId, $input->reportsToPositionId, $input->jobRoleId,
        );
        $timeline = $this->rules->timelineWith($entity, (int) $position->id, [], $version);
        $this->positions->replaceVersions((int) $position->id, $timeline);

        $this->events->dispatch([new PositionChanged(
            (int) $context->group->id, (int) $entity->id, (int) $position->id, 'created', $dated->from->format('Y-m-d'), $dated->reason, null, $timeline[0]->snapshot(),
            (int) $actor->id, $ipAddress, $userAgent, $this->clock->now(),
        )]);

        return new PositionView($position, $timeline[0]);
    }
}
