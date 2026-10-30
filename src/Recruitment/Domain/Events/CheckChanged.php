<?php

namespace Src\Recruitment\Domain\Events;

use DateTimeImmutable;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationCheck;
use Src\Shared\Domain\DomainEvent;

/** Something happened to a pre-employment check. The job seeker is told what the team did (requested, scheduled, rescheduled, cancelled); the member who requested it is told of a refusal. */
final readonly class CheckChanged implements DomainEvent
{
    public function __construct(
        public CheckChange $change,
        public int $checkId,
        public int $checkTypeId,
        public int $applicationId,
        public int $jobId,
        public int $companyId,
        public int $applicantUserId,
        public int $requestedBy,
        public ?DateTimeImmutable $scheduledAt,
        private DateTimeImmutable $occurredAt,
    ) {}

    public static function of(CheckChange $change, Application $application, ApplicationCheck $check, DateTimeImmutable $at): self
    {
        return new self($change, (int) $check->id, $check->checkTypeId, $check->applicationId, $application->jobId, $application->companyId, $application->userId, $check->requestedBy, $check->scheduledAt, $at);
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
