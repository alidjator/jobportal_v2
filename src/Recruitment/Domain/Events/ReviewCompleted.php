<?php

namespace Src\Recruitment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** Every reviewer answered every active application of a review request: the requester is told. */
final readonly class ReviewCompleted implements DomainEvent
{
    public function __construct(public int $requestId, private DateTimeImmutable $occurredAt) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
