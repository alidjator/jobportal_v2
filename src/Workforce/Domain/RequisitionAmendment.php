<?php

namespace Src\Workforce\Domain;

use DateTimeImmutable;

/** A change of the hires or the salary budget of an open requisition that goes through approval; the new values apply once it is approved. */
final readonly class RequisitionAmendment
{
    public function __construct(
        public ?int $id,
        public int $requisitionId,
        public ?int $hires,
        public ?int $salaryMin,
        public ?int $salaryMax,
        public string $reason,
        public AmendmentStatus $status,
        public int $requestedById,
        public ?int $approvalId = null,
        public ?string $decisionReason = null,
        public ?DateTimeImmutable $decidedAt = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
