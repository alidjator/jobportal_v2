<?php

namespace Src\Organization\Application\Structure;

/** The data of a new unit. */
final readonly class UnitInput
{
    public function __construct(public string $name, public ?int $parentUnitId, public int $unitTypeId, public ?int $costCentreId) {}
}
