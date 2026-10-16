<?php

namespace Src\Organization\Application\Structure;

use DateTimeImmutable;
use Src\Organization\Domain\Timeline;
use Src\Organization\Domain\UnitRepository;
use Src\Organization\Domain\UnitVersion;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** One unit as it is on a day (today by default), or its whole history of versions. Any group member reads. */
final readonly class GetUnit
{
    public function __construct(private StructureAccess $access, private ChangeDating $dating, private UnitRepository $units) {}

    /**
     * @throws AccessDenied `not-a-group-member` or `group-suspended`
     * @throws EntityNotFound the unit is missing, belongs to another group or does not exist yet on the day
     */
    public function execute(Actor $actor, int $unitId, ?DateTimeImmutable $asOf): UnitView
    {
        $unit = $this->access->unit($this->access->read($actor), $unitId);
        $version = Timeline::at($this->units->versionsOf($unitId), $this->dating->asOf($asOf)) ?? throw EntityNotFound::of('Unit', $unitId);

        return new UnitView($unit, $version);
    }

    /**
     * @return list<UnitVersion> by start date
     *
     * @throws AccessDenied `not-a-group-member` or `group-suspended`
     * @throws EntityNotFound the unit is missing or belongs to another group
     */
    public function history(Actor $actor, int $unitId): array
    {
        $this->access->unit($this->access->read($actor), $unitId);

        return $this->units->versionsOf($unitId);
    }
}
