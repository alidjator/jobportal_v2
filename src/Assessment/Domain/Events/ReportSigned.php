<?php

namespace Src\Assessment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** A psychologist signed the report of a psychological test. */
final readonly class ReportSigned implements DomainEvent
{
    public function __construct(
        public int $reportId,
        public string $reportNumber,
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
