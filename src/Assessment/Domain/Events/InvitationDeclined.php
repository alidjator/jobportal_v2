<?php

namespace Src\Assessment\Domain\Events;

use DateTimeImmutable;
use Src\Assessment\Domain\Invitation;
use Src\Shared\Domain\DomainEvent;

/** The candidate turned a test down, so the team can plan another way to assess them (a live interview, for instance). */
final readonly class InvitationDeclined implements DomainEvent
{
    public function __construct(
        public int $invitationId,
        public int $applicationId,
        public int $jobId,
        public int $companyId,
        public int $applicantUserId,
        private DateTimeImmutable $occurredAt,
    ) {}

    public static function of(Invitation $invitation, DateTimeImmutable $at): self
    {
        return new self((int) $invitation->id, $invitation->applicationId, $invitation->jobId, $invitation->companyId, $invitation->applicantUserId, $at);
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
