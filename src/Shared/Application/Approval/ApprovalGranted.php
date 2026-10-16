<?php

namespace Src\Shared\Application\Approval;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** Every step of the approval was approved: the subject is approved. The context that owns the subject listens and moves it on. */
final readonly class ApprovalGranted implements DomainEvent
{
    public function __construct(
        public int $approvalId,
        public string $subjectType,
        public int $subjectId,
        public int $companyId,
        public int $requesterId,
        public ?int $decidedByUserId,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
