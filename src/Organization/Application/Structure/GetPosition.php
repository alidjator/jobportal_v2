<?php

namespace Src\Organization\Application\Structure;

use DateTimeImmutable;
use Src\Organization\Domain\PositionRepository;
use Src\Organization\Domain\PositionVersion;
use Src\Organization\Domain\Timeline;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** One position as it is on a day (today by default), or its whole history of versions. Any group member reads. */
final readonly class GetPosition
{
    public function __construct(private StructureAccess $access, private ChangeDating $dating, private PositionRepository $positions) {}

    /**
     * @throws AccessDenied `not-a-group-member` or `group-suspended`
     * @throws EntityNotFound the position is missing, belongs to another group or does not exist yet on the day
     */
    public function execute(Actor $actor, int $positionId, ?DateTimeImmutable $asOf): PositionView
    {
        $position = $this->access->position($this->access->read($actor), $positionId);
        $version = Timeline::at($this->positions->versionsOf($positionId), $this->dating->asOf($asOf)) ?? throw EntityNotFound::of('Position', $positionId);

        return new PositionView($position, $version);
    }

    /**
     * @return list<PositionVersion> by start date
     *
     * @throws AccessDenied `not-a-group-member` or `group-suspended`
     * @throws EntityNotFound the position is missing or belongs to another group
     */
    public function history(Actor $actor, int $positionId): array
    {
        $this->access->position($this->access->read($actor), $positionId);

        return $this->positions->versionsOf($positionId);
    }
}
