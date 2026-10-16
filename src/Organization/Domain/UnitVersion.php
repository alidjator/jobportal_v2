<?php

namespace Src\Organization\Domain;

use DateTimeImmutable;

/** The data of a unit over a period: name, parent unit (null for a top unit), unit type and default cost centre. */
final readonly class UnitVersion implements Versioned
{
    public function __construct(
        public int $unitId,
        public DateTimeImmutable $validFrom,
        public ?DateTimeImmutable $validTo,
        public string $name,
        public ?int $parentUnitId,
        public int $unitTypeId,
        public ?int $costCentreId,
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
            'valid_from' => $this->validFrom->format('Y-m-d'), 'valid_to' => $this->validTo?->format('Y-m-d'), 'name' => $this->name,
            'parent_unit_id' => $this->parentUnitId, 'unit_type_id' => $this->unitTypeId, 'cost_centre_id' => $this->costCentreId,
        ];
    }
}
