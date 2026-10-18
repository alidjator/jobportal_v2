<?php

namespace Src\Shared\Application\Recruitment;

use DateTimeImmutable;

/** What the talent pool needs to know about one application before it saves its applicant: whose it is, whether it is hired and whether the applicant consented. */
final readonly class PoolApplicationFacts
{
    public function __construct(
        public int $applicationId,
        public int $companyId,
        public int $applicantUserId,
        public bool $hired,
        public bool $consentGiven,
        public DateTimeImmutable $appliedAt,
    ) {}
}
