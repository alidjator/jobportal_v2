<?php

namespace Src\Workforce\Domain;

use DateTimeImmutable;

/** The manpower plan of a legal entity for a fiscal year; a revision is a new version. The fiscal year is named by the calendar year it starts in. */
final readonly class Plan
{
    /** @param  list<PlanLine>  $lines */
    public function __construct(
        public ?int $id,
        public int $legalEntityId,
        public int $fiscalYear,
        public int $version,
        public PlanStatus $status,
        public int $createdById,
        public array $lines = [],
        public ?int $approvalId = null,
        public ?DateTimeImmutable $submittedAt = null,
        public ?DateTimeImmutable $decidedAt = null,
        public ?string $decisionReason = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    public function totalAdditions(): int
    {
        return array_sum(array_map(static fn (PlanLine $line): int => $line->additions(), $this->lines));
    }

    public function totalCost(): int
    {
        return array_sum(array_map(static fn (PlanLine $line): int => $line->estimatedAnnualCost ?? 0, $this->lines));
    }

    /**
     * The plan with these fields changed, keyed by property name.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
