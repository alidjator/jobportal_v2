<?php

namespace Src\Recruitment\Domain\Events;

use DateTimeImmutable;
use Src\Recruitment\Domain\HireDetails;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Domain\DomainEvent;

/**
 * A recruiter moved an application to another stage or rejected it. The applicant is told when it entered `interview`, `offer`, `hired` or `rejected` (see the notification listener).
 * A move into `hired` carries the hire details the hired email shows. `noticeCovered` is true when the same move sent an offer or scheduled an interview: that
 * email and notice already tell the applicant, so the stage email and notice are skipped (one message per move).
 */
final readonly class ApplicationStageChanged implements DomainEvent
{
    public function __construct(
        public int $applicationId,
        public int $jobId,
        public int $companyId,
        public int $applicantUserId,
        public StageType $fromType,
        public StageType $toType,
        private DateTimeImmutable $occurredAt,
        public ?HireDetails $hire = null,
        public bool $noticeCovered = false,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
