<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;

/** What a recruiter enters to send an offer. A missing position defaults to the job title. */
final readonly class OfferInput
{
    public function __construct(
        public ?string $position,
        public int $salary,
        public DateTimeImmutable $startDate,
        public ?string $note,
        public DateTimeImmutable $expiresAt,
    ) {}
}
