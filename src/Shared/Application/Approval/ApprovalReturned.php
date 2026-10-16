<?php

namespace Src\Shared\Application\Approval;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An approver returned the subject for revision, with a reason: it goes back to a draft. The context that owns the subject listens and moves it on. */
final readonly class ApprovalReturned implements DomainEvent
{
    public function __construct(
        public int $approvalId,
        public string $subjectType,
        public int $subjectId,
        public int $companyId,
        public int $requesterId,
        public string $reason,
        public ?int $decidedByUserId,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
