<?php

namespace Src\Assessment\Application\Results;

use Src\Assessment\Domain\IntegrityEventType;
use Src\Assessment\Domain\IntegrityTier;

/** The integrity signals of an attempt, oldest first, with the tier they add up to. Signals never reject a candidate: the team decides. */
final readonly class IntegrityLog
{
    /** @param  list<array{type: IntegrityEventType, occurred_at: \DateTimeImmutable}>  $events */
    public function __construct(public IntegrityTier $tier, public int $count, public array $events) {}
}
