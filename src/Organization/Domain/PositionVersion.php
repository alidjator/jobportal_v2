<?php

namespace Src\Organization\Domain;

use DateTimeImmutable;

/** The data of a position over a period: title, unit, grade range, the position it reports to and the optional job role of the reference data. */
final readonly class PositionVersion implements Versioned
{
    public function __construct(
        public int $positionId,
        public DateTimeImmutable $validFrom,
        public ?DateTimeImmutable $validTo,
        public string $title,
        public int $unitId,
        public ?int $minGradeId,
        public ?int $maxGradeId,
        public ?int $reportsToPositionId,
        public ?int $jobRoleId,
    ) {}

    public function startsOn(): DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function endsOn(): ?DateTimeImmutable
    {
        return $this->validTo;
    }

    public function withPeriod(DateTimeImmutable $from, ?DateTimeImmutable $to): static
    {
        return $this->with(['validFrom' => $from, 'validTo' => $to]);
    }

    /**
     * The version with these fields changed, keyed by property name.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): static
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }

    /** @return array<string, mixed> what an audit entry keeps of the version */
    public function snapshot(): array
    {
        return [
            'valid_from' => $this->validFrom->format('Y-m-d'), 'valid_to' => $this->validTo?->format('Y-m-d'), 'title' => $this->title, 'unit_id' => $this->unitId,
            'min_grade_id' => $this->minGradeId, 'max_grade_id' => $this->maxGradeId, 'reports_to_position_id' => $this->reportsToPositionId, 'job_role_id' => $this->jobRoleId,
        ];
    }
}
