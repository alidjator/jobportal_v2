<?php

namespace Src\Organization\Domain;

/** A cost centre of a legal entity: the default of a unit, later used by requisitions and Finance. */
final readonly class CostCentre
{
    public function __construct(
        public ?int $id,
        public int $legalEntityId,
        public string $code,
        public string $name,
        public ItemStatus $status = ItemStatus::Active,
    ) {}

    public function isActive(): bool
    {
        return $this->status === ItemStatus::Active;
    }

    public function rename(string $name): self
    {
        return new self($this->id, $this->legalEntityId, $this->code, $name, $this->status);
    }

    public function withStatus(ItemStatus $status): self
    {
        return new self($this->id, $this->legalEntityId, $this->code, $this->name, $status);
    }
}
