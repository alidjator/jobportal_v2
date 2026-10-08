<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\InterviewFormat;

/** What a recruiter enters to schedule an interview. */
final readonly class InterviewInput
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public int $durationMinutes,
        public InterviewFormat $format,
        public ?string $location,
        public ?string $meetingUrl,
        public int $interviewerId,
    ) {}
}
