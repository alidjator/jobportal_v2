<?php

namespace Src\Recruitment\Domain\Events;

use DateTimeImmutable;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\Interview;
use Src\Shared\Domain\DomainEvent;

/** Something happened to an interview. The job seeker is told about what the team did, and the team (the interviewer and who scheduled it) about what the job seeker did. */
final readonly class InterviewChanged implements DomainEvent
{
    public function __construct(
        public InterviewChange $change,
        public int $interviewId,
        public int $applicationId,
        public int $jobId,
        public int $companyId,
        public int $applicantUserId,
        public int $interviewerId,
        public int $createdBy,
        private DateTimeImmutable $occurredAt,
        /** @var list<int> the members newly on the panel (a panel change) */
        public array $addedUserIds = [],
        /** @var list<int> the members who left the panel, with a reschedule or a panel change */
        public array $removedUserIds = [],
        /** The team's message to the job seeker with a refused proposal; sent, never stored. */
        public ?string $message = null,
    ) {}

    /**
     * @param  list<int>  $addedUserIds
     * @param  list<int>  $removedUserIds
     */
    public static function of(
        InterviewChange $change,
        Application $application,
        Interview $interview,
        DateTimeImmutable $at,
        array $addedUserIds = [],
        array $removedUserIds = [],
        ?string $message = null,
    ): self {
        return new self(
            $change,
            (int) $interview->id,
            $interview->applicationId,
            $application->jobId,
            $application->companyId,
            $application->userId,
            $interview->interviewerId,
            $interview->createdBy,
            $at,
            $addedUserIds,
            $removedUserIds,
            $message,
        );
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
