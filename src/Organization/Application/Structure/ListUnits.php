<?php

namespace Src\Organization\Application\Structure;

use DateTimeImmutable;
use Src\Organization\Domain\Timeline;
use Src\Organization\Domain\UnitRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The units of a legal entity as they are on a day (today by default): a unit that does not exist yet on that day is left out. Any group member reads. */
final readonly class ListUnits
{
    public function __construct(private StructureAccess $access, private ChangeDating $dating, private UnitRepository $units) {}

    /**
     * @return list<UnitView> by code
     *
     * @throws AccessDenied `not-a-group-member` or `group-suspended`
     * @throws EntityNotFound the entity is missing or belongs to another group
     */
    public function execute(Actor $actor, int $legalEntityId, ?DateTimeImmutable $asOf): array
    {
        $entity = $this->access->entity($this->access->read($actor), $legalEntityId);
        $day = $this->dating->asOf($asOf);
        $timelines = $this->units->timelinesOfEntity((int) $entity->id);
        $views = [];

        foreach ($this->units->ofEntity((int) $entity->id) as $unit) {
            $version = Timeline::at($timelines[(int) $unit->id] ?? [], $day);

            if ($version !== null) {
                $views[] = new UnitView($unit, $version);
            }
        }

        return $views;
    }
}
