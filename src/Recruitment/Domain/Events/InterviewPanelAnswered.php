<?php

namespace Src\Recruitment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A panel member answered "cannot attend" to the invitation to an interview: the member who scheduled it is told, the panel stays as it is. */
final readonly class InterviewPanelAnswered implements DomainEvent
{
    public function __construct(
        public int $interviewId,
        public int $applicationId,
        public int $jobId,
        public int $companyId,
        public int $memberId,
        public int $createdBy,
        public ?string $note,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
