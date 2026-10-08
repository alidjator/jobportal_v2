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
    ) {}

    public static function of(InterviewChange $change, Application $application, Interview $interview, DateTimeImmutable $at): self
    {
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
        );
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
