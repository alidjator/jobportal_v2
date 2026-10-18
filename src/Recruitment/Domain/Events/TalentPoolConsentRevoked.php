<?php

namespace Src\Recruitment\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** An applicant took back the consent to be kept in a company's talent pool (`docs/design/phase-8-talent-pool.md` section 1). The talent pool drops the entry that rested on it. */
final readonly class TalentPoolConsentRevoked implements DomainEvent
{
    public function __construct(
        public int $applicationId,
        public int $companyId,
        public int $applicantUserId,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
