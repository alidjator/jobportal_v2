<?php

namespace Src\Recruitment\Domain\Events;

use DateTimeImmutable;
use Src\Recruitment\Domain\HireDetails;
use Src\Shared\Domain\DomainEvent;

/** A team member changed the join date or the note of a hired application. The applicant is told; the change is audited with the old and the new details. */
final readonly class ApplicationHireUpdated implements DomainEvent
{
    public function __construct(
        public int $applicationId,
        public int $jobId,
        public int $companyId,
        public int $applicantUserId,
        public int $actorId,
        public ?HireDetails $before,
        public HireDetails $after,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
