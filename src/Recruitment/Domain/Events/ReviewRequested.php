<?php

namespace Src\Recruitment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A recruiter asked the hiring managers of a job to review applications: the reviewers are told. */
final readonly class ReviewRequested implements DomainEvent
{
    public function __construct(public int $requestId, private DateTimeImmutable $occurredAt) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
