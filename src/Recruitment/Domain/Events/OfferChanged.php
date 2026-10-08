<?php

namespace Src\Recruitment\Domain\Events;

use DateTimeImmutable;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\Offer;
use Src\Shared\Domain\DomainEvent;

/** Something happened to an offer. The job seeker is told about what the team did and about the deadline, and the member who sent it about the answer. */
final readonly class OfferChanged implements DomainEvent
{
    public function __construct(
        public OfferChange $change,
        public int $offerId,
        public int $applicationId,
        public int $jobId,
        public int $companyId,
        public int $applicantUserId,
        public int $createdBy,
        private DateTimeImmutable $occurredAt,
    ) {}

    public static function of(OfferChange $change, Application $application, Offer $offer, DateTimeImmutable $at): self
    {
        return new self($change, (int) $offer->id, $offer->applicationId, $application->jobId, $application->companyId, $application->userId, $offer->createdBy, $at);
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
