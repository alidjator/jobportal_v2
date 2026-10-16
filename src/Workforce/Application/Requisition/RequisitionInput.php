<?php

namespace Src\Workforce\Application\Requisition;

use DateTimeImmutable;
use Src\Workforce\Domain\Requisition;
use Src\Workforce\Domain\RequisitionType;

/** What a member writes in a requisition. */
final readonly class RequisitionInput
{
    public function __construct(
        public RequisitionType $type,
        public int $positionId,
        public ?int $requestedGradeId,
        public int $hires,
        public int $jobTypeId,
        public ?int $contractMonths,
        public DateTimeImmutable $targetJoinDate,
        public ?int $costCentreId,
        public ?int $salaryMin,
        public ?int $salaryMax,
        public string $justification,
        public ?string $replacementName,
        public ?DateTimeImmutable $replacementLeavingDate,
        public int $hiringManagerUserId,
    ) {}

    public static function of(Requisition $requisition): self
    {
        return new self(
            $requisition->type, $requisition->positionId, $requisition->requestedGradeId, $requisition->hires, $requisition->jobTypeId, $requisition->contractMonths,
            $requisition->targetJoinDate, $requisition->costCentreId, $requisition->salaryMin, $requisition->salaryMax, $requisition->justification, $requisition->replacementName,
            $requisition->replacementLeavingDate, $requisition->hiringManagerUserId,
        );
    }
}
