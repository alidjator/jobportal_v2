<?php

namespace Src\Organization\Application\Structure;

use DateTimeImmutable;
use Src\Organization\Domain\PositionRepository;
use Src\Organization\Domain\Timeline;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The positions of a legal entity as they are on a day (today by default): a position that does not exist yet on that day is left out. Any group member reads. */
final readonly class ListPositions
{
    public function __construct(private StructureAccess $access, private ChangeDating $dating, private PositionRepository $positions) {}

    /**
     * @return list<PositionView> by code
     *
     * @throws AccessDenied `not-a-group-member` or `group-suspended`
     * @throws EntityNotFound the entity is missing or belongs to another group
     */
    public function execute(Actor $actor, int $legalEntityId, ?DateTimeImmutable $asOf): array
    {
        $entity = $this->access->entity($this->access->read($actor), $legalEntityId);
        $day = $this->dating->asOf($asOf);
        $timelines = $this->positions->timelinesOfEntity((int) $entity->id);
        $views = [];

        foreach ($this->positions->ofEntity((int) $entity->id) as $position) {
            $version = Timeline::at($timelines[(int) $position->id] ?? [], $day);

            if ($version !== null) {
                $views[] = new PositionView($position, $version);
            }
        }

        return $views;
    }
}
