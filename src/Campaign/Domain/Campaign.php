<?php

namespace Src\Campaign\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * A registration link of a company (or of the platform when `companyId` is null): a code, a name, a type and an optional active period. The code is lower case
 * and never changes, because it is printed in links and QR codes. Dates are Jakarta calendar days (`Y-m-d`); a missing date leaves that end of the period open.
 */
final readonly class Campaign
{
    public function __construct(
        public ?int $companyId,
        public string $code,
        public string $name,
        public CampaignType $type,
        public ?string $startsOn,
        public ?string $endsOn,
        public bool $active,
        public ?int $createdByUserId,
        public ?int $createdByAdminId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?int $id = null,
    ) {
        if ($startsOn !== null && $endsOn !== null && $startsOn > $endsOn) {
            throw new BusinessRuleViolation('The campaign cannot end before it starts.', 'campaign-period-invalid');
        }
    }

    public function changed(string $name, CampaignType $type, ?string $startsOn, ?string $endsOn, bool $active, DateTimeImmutable $now): self
    {
        return new self(
            $this->companyId, $this->code, $name, $type, $startsOn, $endsOn, $active, $this->createdByUserId, $this->createdByAdminId,
            $this->createdAt, $now, $this->id,
        );
    }

    /** @param  string  $today  the Jakarta calendar day, `Y-m-d` */
    public function statusOn(string $today): CampaignStatus
    {
        return match (true) {
            ! $this->active => CampaignStatus::Inactive,
            $this->startsOn !== null && $today < $this->startsOn => CampaignStatus::Scheduled,
            $this->endsOn !== null && $today > $this->endsOn => CampaignStatus::Ended,
            default => CampaignStatus::Active,
        };
    }
}
