<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\StageType;

/**
 * One line of the recruiter's list: the applicant as the snapshot shows them, the must-have result and the stage. The applicant fields are null for an
 * applicant whose account was anonymised (`isDeleted`). Email and phone are left to the detail.
 */
final readonly class RecruiterApplicationRow
{
    /**
     * @param  array{title: string, company: string}|null  $latestExperience
     * @param  array{amount: int, period: array<string, mixed>|null}|null  $expectedSalary
     */
    public function __construct(
        public int $id,
        public bool $isDeleted,
        public ?string $name,
        public ?string $headline,
        public ?array $latestExperience,
        public ?string $city,
        public ?array $expectedSalary,
        public ?int $age,
        public ?bool $meetsMustHave,
        public int $stageId,
        public StageType $stageType,
        public string $stageName,
        public DateTimeImmutable $appliedAt,
        public ?float $ratingAverage = null,
        public int $ratingCount = 0,
        public ?InterviewResult $interviewResult = null,
    ) {}

    /** The row with the result of its latest scored interview (what the viewer may see of it). */
    public function withInterviewResult(?InterviewResult $result): self
    {
        return new self(
            $this->id, $this->isDeleted, $this->name, $this->headline, $this->latestExperience, $this->city, $this->expectedSalary, $this->age,
            $this->meetsMustHave, $this->stageId, $this->stageType, $this->stageName, $this->appliedAt, $this->ratingAverage, $this->ratingCount, $result,
        );
    }
}
