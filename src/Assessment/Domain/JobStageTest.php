<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

/**
 * The default test of a job for one of its company's assessment stages (`docs/design/phase-8-assessment-automation.md` section 1): a move into the stage sends it when the
 * request names no test. It may also advance the application by itself when a skill test is scored at or above `advanceMinPercentage` (section 2); the two parts of that rule
 * come together or not at all.
 */
final readonly class JobStageTest
{
    /** @param  list<int>  $assessorIds */
    public function __construct(
        public int $jobId,
        public int $stageId,
        public int $testId,
        public int $dueDays,
        public array $assessorIds,
        public ?int $advanceMinPercentage,
        public ?int $advanceToStageId,
        public int $updatedBy,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}

    public function advances(): bool
    {
        return $this->advanceMinPercentage !== null && $this->advanceToStageId !== null;
    }
}
