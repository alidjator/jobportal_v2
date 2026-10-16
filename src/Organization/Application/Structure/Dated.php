<?php

namespace Src\Organization\Application\Structure;

use DateTimeImmutable;

/** The day a change of a unit or position takes effect and, for a change dated in the past, the reason. */
final readonly class Dated
{
    public function __construct(public DateTimeImmutable $from, public ?string $reason) {}
}
