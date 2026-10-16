<?php

namespace Src\Shared\Application\Approval;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An approver rejected the subject, with a reason. The context that owns the subject listens and moves it on. */
final readonly class ApprovalRejected implements DomainEvent
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
