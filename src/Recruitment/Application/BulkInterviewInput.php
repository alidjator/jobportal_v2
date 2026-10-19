<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\InterviewFormat;

/**
 * The base schedule of a bulk move to an interview stage: the first slot starts at `startsAt` and each following slot lasts `durationMinutes` after a gap,
 * inside the daily window `dayStart` to `dayEnd` (`H:i`, Jakarta time) on working days.
 */
final readonly class BulkInterviewInput
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public int $durationMinutes,
        public int $gapMinutes,
        public string $dayStart,
        public string $dayEnd,
        public InterviewFormat $format,
        public ?string $location,
        public ?string $meetingUrl,
        public int $interviewerId,
        public bool $recorded = false,
        public ?string $note = null,
        public ?int $locationId = null,
    ) {}
}
