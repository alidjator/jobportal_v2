<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\StageType;

/**
 * One line of the recruiter's list: the applicant as the snapshot shows them, the must-have result and the stage. The applicant fields are null for an
 * applicant whose account was anonymised (`isDeleted`). Email and phone are left to the detail. `interviewState` is `not_scheduled`, `pending`, `confirmed`
 * or `completed` for an application in an interview stage, null outside one.
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
        public ?DateTimeImmutable $stageEnteredAt = null,
        public int $daysInStage = 0,
        public bool $stale = false,
        public int $otherApplicationsCount = 0,
        public ?string $interviewState = null,
        public ?array $interviewRsvp = null,
        public ?array $reviewSummary = null,
    ) {}

    /** The row with the result of its latest scored interview (what the viewer may see of it). */
    public function withInterviewResult(?InterviewResult $result): self
    {
        return $this->with(interviewResult: $result);
    }

    /**
     * The row with the answers of the panel of its active interview to the invitation: how many have not answered and how many cannot attend (null without an active interview).
     *
     * @param  ?array{pending: int, declined: int}  $counts
     */
    public function withInterviewRsvp(?array $counts): self
    {
        return $this->with(interviewRsvp: $counts);
    }

    /**
     * The row with what the shortlist reviews say about it: the decisions given and the answers still awaited (null when no open review request names it).
     *
     * @param  ?array{advance: int, decline: int, maybe: int, pending: int}  $summary
     */
    public function withReviewSummary(?array $summary): self
    {
        return $this->with(reviewSummary: $summary);
    }

    /** The row with the number of other active applications of the same person that the viewer may see. */
    public function withOtherApplications(int $count): self
    {
        return $this->with(otherApplicationsCount: $count);
    }

    /** A copy with some properties replaced (named arguments). */
    private function with(mixed ...$changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
