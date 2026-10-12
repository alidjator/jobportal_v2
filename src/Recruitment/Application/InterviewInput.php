<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\InterviewFormat;

/**
 * What a recruiter enters to schedule an interview. `$kitGiven` tells whether the request named a kit (`$kitId` null then means none); when it did not,
 * a first interview gets the job's default kit for the stage and a new time keeps the kit, the questions and the panel of the interview it replaces.
 * `$participantIds` are the extra panel members, or null when the request did not name any. `$recorded` is null when the request did not say: a new time
 * keeps the flag of the video interview it replaces, and a first interview is not recorded.
 */
final readonly class InterviewInput
{
    /** @param  list<int>|null  $participantIds */
    public function __construct(
        public DateTimeImmutable $startsAt,
        public int $durationMinutes,
        public InterviewFormat $format,
        public ?string $location,
        public ?string $meetingUrl,
        public int $interviewerId,
        public ?int $kitId = null,
        public bool $kitGiven = false,
        public ?array $participantIds = null,
        public ?bool $recorded = null,
    ) {}
}
