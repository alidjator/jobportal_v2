<?php

namespace Src\Assessment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A psychologist replaced the text and the explanation of a report that was issued automatically; `reportNumber` is the new one (with `-R1`, `-R2`). */
final readonly class ReportRevised implements DomainEvent
{
    public function __construct(
        public int $reportId,
        public string $reportNumber,
        public int $revision,
        public int $invitationId,
        public int $applicationId,
        public int $jobId,
        public int $companyId,
        public int $providerId,
        public int $psychologistUserId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
