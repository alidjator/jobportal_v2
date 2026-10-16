<?php

namespace Src\Organization\Domain;

/** The identity of an organisation unit of a legal entity: a stable id and code. Its name, parent, type and cost centre live in the dated `UnitVersion`s. */
final readonly class Unit
{
    public function __construct(
        public ?int $id,
        public int $legalEntityId,
        public string $code,
        public ItemStatus $status = ItemStatus::Active,
    ) {}

    public function isActive(): bool
    {
        return $this->status === ItemStatus::Active;
    }

    public function withStatus(ItemStatus $status): self
    {
        return new self($this->id, $this->legalEntityId, $this->code, $status);
    }
}
