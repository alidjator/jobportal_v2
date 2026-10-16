<?php

namespace Src\Organization\Application\Structure;

use Src\Organization\Domain\Unit;
use Src\Organization\Domain\UnitVersion;

/** A unit with its version on the day that was read (`as_of`). */
final readonly class UnitView
{
    public function __construct(public Unit $unit, public UnitVersion $version) {}
}
