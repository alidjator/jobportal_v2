<?php

namespace Src\Workforce\Domain;

use DateTimeImmutable;

/**
 * A request to hire for a position of a legal entity of a tenant. The unit and the position are those of the position version in force when it was written; the fiscal year is
 * the one of the target join date and decides which plan line a `planned` requisition uses.
 */
final readonly class Requisition
{
    public function __construct(
        public ?int $id,
        public int $companyId,
        public int $legalEntityId,
        public RequisitionType $type,
        public RequisitionStatus $status,
        public int $positionId,
        public int $unitId,
        public ?int $requestedGradeId,
        public int $hires,
        public int $jobTypeId,
        public ?int $contractMonths,
        public DateTimeImmutable $targetJoinDate,
        public int $fiscalYear,
        public ?int $costCentreId,
        public ?int $salaryMin,
        public ?int $salaryMax,
        public string $justification,
        public ?string $replacementName,
        public ?DateTimeImmutable $replacementLeavingDate,
        public int $hiringManagerUserId,
        public int $requesterId,
        public ?int $approvalId = null,
        public ?string $decisionReason = null,
        public ?string $closedReason = null,
        public ?DateTimeImmutable $submittedAt = null,
        public ?DateTimeImmutable $decidedAt = null,
        public ?DateTimeImmutable $closedAt = null,
        public ?DateTimeImmutable $createdAt = null,
        /** The work location the hires will join (`company_locations`); optional, and a job opened from the requisition starts with it. */
        public ?int $locationId = null,
    ) {}

    /**
     * The requisition with these fields changed, keyed by property name.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
