<?php

namespace Src\Assessment\Domain\Events;

use DateTimeImmutable;
use Src\Assessment\Domain\Invitation;
use Src\Shared\Domain\DomainEvent;

/** Something happened to a test invitation. */
final readonly class InvitationChanged implements DomainEvent
{
    public function __construct(
        public InvitationChange $change,
        public int $invitationId,
        public int $applicationId,
        public int $jobId,
        public int $companyId,
        public int $applicantUserId,
        private DateTimeImmutable $occurredAt,
    ) {}

    public static function of(InvitationChange $change, Invitation $invitation, DateTimeImmutable $at): self
    {
        return new self($change, (int) $invitation->id, $invitation->applicationId, $invitation->jobId, $invitation->companyId, $invitation->applicantUserId, $at);
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
