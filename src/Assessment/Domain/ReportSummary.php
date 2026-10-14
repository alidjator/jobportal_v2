<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

/** One line of the provider's review queue: the report, the test and whose attempt it is. */
final readonly class ReportSummary
{
    public function __construct(
        public int $reportId,
        public int $invitationId,
        public int $applicantUserId,
        public string $testTitle,
        public ReportStatus $status,
        public ?string $number,
        public DateTimeImmutable $submittedAt,
        public ?DateTimeImmutable $signedAt,
        public bool $autoIssued = false,
        public ?DateTimeImmutable $reviewedAt = null,
    ) {}
}
