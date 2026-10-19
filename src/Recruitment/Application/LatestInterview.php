<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;

/**
 * The latest interview of an application that has a sent scorecard, with what one viewer may see of its result: `$result` is null while the viewer is on the
 * panel and has not sent their own scorecard (`$locked`).
 */
final readonly class LatestInterview
{
    public function __construct(
        public int $applicationId,
        public int $interviewId,
        public DateTimeImmutable $startsAt,
        public ?int $kitId,
        public ?string $kitName,
        public bool $locked,
        public ?InterviewResult $result,
    ) {}
}
