<?php

namespace Src\Shared\Application\Recruitment;

/** One application of a job roster. `name` is null for an applicant whose account was anonymised (`isDeleted`). `interviewScore` is the average of the sent scorecards of the latest scored interview, null when there is none or the viewer may not see it yet. */
final readonly class RosterEntry
{
    public function __construct(
        public int $applicationId,
        public bool $isDeleted,
        public ?string $name,
        public int $stageId,
        public string $stageType,
        public string $stageName,
        public ?int $matchScore,
        public ?float $ratingAverage,
        public ?float $interviewScore,
    ) {}
}
