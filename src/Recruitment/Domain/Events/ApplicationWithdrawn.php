<?php

namespace Src\Recruitment\Domain\Events;

use DateTimeImmutable;
use Src\Recruitment\Domain\StageMoveActor;
use Src\Recruitment\Domain\WithdrawalReason;
use Src\Shared\Domain\DomainEvent;

/**
 * An application was withdrawn: by the job seeker (also by declining an offer) or marked by a team member. The job's team is told, the job seeker too when the
 * team marked it, and the interviewers when an interview was cancelled with it. `excludedUserIds` are the members the team notice leaves out (the one who marked
 * it, the sender of a declined offer). `actorId`, the address and the user agent are only set for a withdrawal marked by the team (the audit entry).
 */
final readonly class ApplicationWithdrawn implements DomainEvent
{
    /** @param  list<int>  $excludedUserIds */
    public function __construct(
        public int $applicationId,
        public int $jobId,
        public int $companyId,
        public int $applicantUserId,
        public StageMoveActor $by,
        public ?WithdrawalReason $reason,
        public ?int $actorId,
        public array $excludedUserIds,
        public ?int $cancelledInterviewId,
        public ?string $ipAddress,
        public ?string $userAgent,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function byTeam(): bool
    {
        return $this->by === StageMoveActor::Recruiter;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
