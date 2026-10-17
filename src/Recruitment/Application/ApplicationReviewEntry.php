<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\ReviewDecision;
use Src\Recruitment\Domain\ReviewRequestStatus;

/** One answer of a shortlist review about an application, as the viewer may see it. */
final readonly class ApplicationReviewEntry
{
    public function __construct(
        public int $requestId,
        public DateTimeImmutable $dueOn,
        public ReviewRequestStatus $requestStatus,
        public int $reviewerId,
        public ?string $reviewerName,
        public ReviewDecision $decision,
        public ?string $reason,
        public DateTimeImmutable $answeredAt,
    ) {}
}
