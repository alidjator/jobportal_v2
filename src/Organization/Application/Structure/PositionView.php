<?php

namespace Src\Organization\Application\Structure;

use Src\Organization\Domain\Position;
use Src\Organization\Domain\PositionVersion;

/** A position with its version on the day that was read (`as_of`). */
final readonly class PositionView
{
    public function __construct(public Position $position, public PositionVersion $version) {}
}
